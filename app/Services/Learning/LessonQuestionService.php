<?php

namespace App\Services\Learning;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonQuestion;
use App\Models\LessonQuestionAnswer;
use App\Models\User;
use App\Services\Gamification\EconomyLedger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * أسئلة الدرس والإدخال الرقميّ بنمط OTP (الدستور 4 · 4.1 · 24.5).
 *
 * لماذا التحقّق هنا وليس في المتصفّح؟ لأنّ الإجابة الصحيحة قيمةٌ تمنح XP،
 * وأيّ تحقّق في العميل يعني توزيع الجائزة على مَن يفتح أدوات المطوّر.
 * ولمنع تكرار الكسب: قيد فريد (user_id, lesson_question_id) في قاعدة البيانات
 * لا مجرّد شرط في الكود.
 */
class LessonQuestionService
{
    /** دلو المصدر في دفتر الأستاذ — «تعلّم» في شاشة المعاملات */
    private const LEDGER_SOURCE = 'academy';

    public function __construct(private readonly EconomyLedger $economy) {}

    /** @return Collection<int, LessonQuestion> */
    public function forLesson(Lesson $lesson): Collection
    {
        return LessonQuestion::query()
            ->where('lesson_id', $lesson->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** @return array<int, LessonQuestionAnswer> مفهرسة بمعرّف السؤال */
    public function answersOf(User $user, Lesson $lesson): array
    {
        return LessonQuestionAnswer::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_question_id', $this->forLesson($lesson)->pluck('id'))
            ->get()
            ->keyBy('lesson_question_id')
            ->all();
    }

    /** الدرس بلا أسئلة يُعتبَر مجتازًا — البوّابة تُقفل فقط حين توجد أسئلة (4.1) */
    public function allAnsweredCorrectly(User $user, Lesson $lesson): bool
    {
        $questions = $this->forLesson($lesson);

        if ($questions->isEmpty()) {
            return true;
        }

        $correct = LessonQuestionAnswer::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_question_id', $questions->pluck('id'))
            ->where('is_correct', true)
            ->count();

        return $correct === $questions->count();
    }

    /**
     * تصحيح إجابة — Server-side إلزاميّ.
     *
     * @return array{correct:bool,already:bool,xp:int,message:string}
     */
    public function answer(User $user, LessonQuestion $question, string $submitted, ?Enrollment $enrollment = null): array
    {
        $existing = LessonQuestionAnswer::query()
            ->where('user_id', $user->id)
            ->where('lesson_question_id', $question->id)
            ->first();

        // كُسِبت من قبل ⟵ لا XP ثانية مهما تكرّر الإرسال
        if ($existing && $existing->is_correct) {
            return [
                'correct' => true,
                'already' => true,
                'xp' => 0,
                'message' => setting('learning.questions.already_message'),
            ];
        }

        $isCorrect = $this->matches($question, $submitted);

        // إجابة خاطئة: تُسجَّل بلا عقوبة ولا استهلاك محاولة صحيحة (24.5)
        if (! $isCorrect) {
            LessonQuestionAnswer::query()->updateOrCreate(
                ['user_id' => $user->id, 'lesson_question_id' => $question->id],
                ['is_correct' => false, 'xp_awarded' => 0],
            );

            return [
                'correct' => false,
                'already' => false,
                'xp' => 0,
                'message' => setting('learning.questions.wrong_message'),
            ];
        }

        $xp = (int) $question->xp_reward;

        DB::transaction(function () use ($user, $question, $xp, $enrollment) {
            LessonQuestionAnswer::query()->updateOrCreate(
                ['user_id' => $user->id, 'lesson_question_id' => $question->id],
                ['is_correct' => true, 'xp_awarded' => $xp],
            );

            // ⭐ XP من النقطة الموحّدة: users.xp + المحفظة + تسجيل التدريب (7.3)
            $this->economy->awardXp(
                user: $user,
                amount: $xp,
                source: self::LEDGER_SOURCE,
                reference: $question,
                reason: setting('learning.questions.xp_reason', 'إجابة صحيحة على سؤال درس'),
                enrollment: $enrollment,
            );
        });

        return [
            'correct' => true,
            'already' => false,
            'xp' => $xp,
            'message' => setting('learning.questions.correct_message'),
        ];
    }

    /** هل هذه الإجابة صحيحة؟ — للعرض في شاشة النتيجة بلا إعادة تسجيل (4.1-3) */
    public function isCorrect(LessonQuestion $question, string $submitted): bool
    {
        return $this->matches($question, $submitted);
    }

    /** عدد خانات الإدخال الرقميّ = عدد أرقام الإجابة (4) */
    public function otpLength(LessonQuestion $question): int
    {
        $digits = mb_strlen($this->normalize((string) $question->correct_answer));
        $max = (int) setting('learning.otp.max_length', 8);

        return max(1, min($max, $digits));
    }

    // ------------------------------------------------------------ داخليّ

    private function matches(LessonQuestion $question, string $submitted): bool
    {
        $expected = $this->normalize((string) $question->correct_answer);
        $given = $this->normalize($submitted);

        if ($expected === '') {
            return false;
        }

        return $expected === $given;
    }

    /** توحيد الأرقام العربيّة-الهنديّة والمسافات حتى لا تُرفض إجابة صحيحة شكلًا */
    private function normalize(string $value): string
    {
        $value = str_replace(
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $value,
        );

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_strtolower(trim($value));
    }
}
