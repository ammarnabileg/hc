<?php

namespace App\Services\Learning;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonQuestion;
use App\Models\LessonQuizAttempt;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * تدفّق اختبار الدرس (الدستور 4.1) — بوّابة الانتقال، بلا تذاكر وبلا أثر على الحساب:
 *
 *   1) الأسئلة **بترتيب عشوائيّ**.
 *   2) **معاينة الإجابات** قبل الإرسال.
 *   3) بعد الإرسال النهائيّ يظهر **الصحّ والغلط**.
 *   4) غلطة واحدة ⟵ إعادة **بمحاولات غير محدودة بعد انتظار 20 ثانية**.
 *
 * ولماذا كلّ هذا في الخادم؟ لأنّ حاجز الانتظار في المتصفّح يُتجاوَز بإعادة تحميل الصفحة،
 * فـ`retry_available_at` مخزَّن في المحاولة والخادم هو مَن يرفض البدء قبله.
 */
class LessonQuizService
{
    public function __construct(private readonly LessonQuestionService $questions) {}

    /** الترتيب الطبيعيّ لأسئلة الدرس — قبل الخلط */
    public function questionsOf(Lesson $lesson): Collection
    {
        return $this->questions->forLesson($lesson);
    }

    public function runningAttempt(User $user, Lesson $lesson): ?LessonQuizAttempt
    {
        return LessonQuizAttempt::query()
            ->where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->where('status', 'in_progress')
            ->latest('id')
            ->first();
    }

    public function lastSubmittedAttempt(User $user, Lesson $lesson): ?LessonQuizAttempt
    {
        return LessonQuizAttempt::query()
            ->where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->where('status', 'submitted')
            ->latest('id')
            ->first();
    }

    /** ثواني الانتظار المتبقّية قبل السماح بمحاولة جديدة (4.1) */
    public function waitSecondsLeft(User $user, Lesson $lesson): int
    {
        $last = $this->lastSubmittedAttempt($user, $lesson);

        if (! $last || $last->passed) {
            return 0;
        }

        return $last->secondsUntilRetry();
    }

    /**
     * محاولة جاهزة للحلّ: القائمة إن وُجدت، وإلّا محاولة جديدة بترتيب عشوائيّ.
     * وتُعيد `null` إن كان حاجز الانتظار لم ينتهِ بعد — والقرار هنا لا في الواجهة.
     */
    public function startOrResume(User $user, Lesson $lesson): ?LessonQuizAttempt
    {
        $running = $this->runningAttempt($user, $lesson);

        if ($running) {
            return $running;
        }

        if ($this->waitSecondsLeft($user, $lesson) > 0) {
            return null;
        }

        $questions = $this->questionsOf($lesson);

        return LessonQuizAttempt::create([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'question_order' => $this->shuffledQuestionIds($questions),
            'option_order' => $this->shuffledOptions($questions),
            'answers' => [],
            'total_count' => $questions->count(),
            'status' => 'in_progress',
        ]);
    }

    /**
     * أسئلة المحاولة بترتيبها المخزَّن — ثابتٌ من شاشة الحلّ إلى المعاينة إلى النتيجة،
     * وإلّا صار المعروض في المعاينة غير الذي أجاب عنه.
     *
     * @return Collection<int, LessonQuestion>
     */
    public function orderedQuestions(LessonQuizAttempt $attempt, Lesson $lesson): Collection
    {
        $questions = $this->questionsOf($lesson)->keyBy('id');
        $order = collect($attempt->question_order ?? [])->map(fn ($id) => (int) $id);

        // سؤال أُضيف بعد بدء المحاولة يلحق في آخر الترتيب بدل أن يختفي
        $missing = $questions->keys()->map(fn ($id) => (int) $id)->diff($order);

        return $order->merge($missing)
            ->map(fn (int $id) => $questions->get($id))
            ->filter()
            ->values();
    }

    /** اختيارات سؤال بترتيب هذه المحاولة (بلا خلطٍ جديد عند كلّ عرض) */
    public function optionsOf(LessonQuizAttempt $attempt, LessonQuestion $question): array
    {
        $stored = ($attempt->option_order ?? [])[(string) $question->id] ?? null;
        $options = array_values((array) $question->options);

        if (! is_array($stored) || $stored === []) {
            return $options;
        }

        // نُبقي على المخزَّن ثمّ نلحق أيّ اختيارٍ استُجدّ بعد بدء المحاولة
        $known = array_values(array_intersect($stored, $options));

        return array_values(array_unique(array_merge($known, array_diff($options, $known))));
    }

    /** حفظ إجابات المحاولة (للمعاينة والعودة للتعديل) */
    public function saveAnswers(LessonQuizAttempt $attempt, Lesson $lesson, array $input): LessonQuizAttempt
    {
        $answers = (array) ($attempt->answers ?? []);

        foreach ($this->orderedQuestions($attempt, $lesson) as $question) {
            $value = $this->readAnswer($question, $input);

            if ($value !== null) {
                $answers[(string) $question->id] = $value;
            }
        }

        $attempt->update(['answers' => $answers]);

        return $attempt->refresh();
    }

    /** قراءة إجابة سؤال من الطلب — والإدخال الرقميّ يصل خاناتٍ منفصلة بنمط OTP (4) */
    public function readAnswer(LessonQuestion $question, array $input): ?string
    {
        $digits = $input['digits'][$question->id] ?? null;

        if (is_array($digits)) {
            return implode('', array_map(fn ($d) => trim((string) $d), $digits));
        }

        $answer = $input['answers'][$question->id] ?? null;

        return is_scalar($answer) ? (string) $answer : null;
    }

    /**
     * التسليم النهائيّ: التصحيح في الخادم، ثمّ حاجز الانتظار عند وجود غلطة واحدة (4.1).
     *
     * @return array{attempt:LessonQuizAttempt,results:array<int,bool>,passed:bool}
     */
    public function submit(User $user, Lesson $lesson, LessonQuizAttempt $attempt, ?Enrollment $enrollment): array
    {
        $questions = $this->orderedQuestions($attempt, $lesson);
        $answers = (array) ($attempt->answers ?? []);
        $results = [];
        $correct = 0;

        foreach ($questions as $question) {
            // التصحيح ومنح الـXP يبقيان في خدمتهما الأصليّة — لا اقتصاد جديدًا هنا
            $outcome = $this->questions->answer(
                $user,
                $question,
                (string) ($answers[(string) $question->id] ?? ''),
                $enrollment,
            );

            $results[$question->id] = (bool) $outcome['correct'];
            $correct += $outcome['correct'] ? 1 : 0;
        }

        $passed = $questions->isEmpty() || $correct === $questions->count();
        $wait = (int) setting('learning.quiz.retry_wait_seconds', 20);

        $attempt->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'correct_count' => $correct,
            'total_count' => $questions->count(),
            'passed' => $passed,
            // غلطة واحدة تكفي لإلزام الانتظار — والمحاولات نفسها غير محدودة (4.1)
            'retry_available_at' => $passed ? null : now()->addSeconds($wait),
        ]);

        return ['attempt' => $attempt->refresh(), 'results' => $results, 'passed' => $passed];
    }

    /** صحّة كلّ سؤال في محاولةٍ مسلَّمة — لعرض الصحّ والغلط (4.1-3) */
    public function resultsOf(LessonQuizAttempt $attempt, Lesson $lesson): array
    {
        $answers = (array) ($attempt->answers ?? []);
        $results = [];

        foreach ($this->orderedQuestions($attempt, $lesson) as $question) {
            $results[$question->id] = $this->questions->isCorrect(
                $question,
                (string) ($answers[(string) $question->id] ?? ''),
            );
        }

        return $results;
    }

    // ------------------------------------------------------------ داخليّ

    /** @param  Collection<int, LessonQuestion>  $questions */
    private function shuffledQuestionIds(Collection $questions): array
    {
        $ids = $questions->pluck('id')->map(fn ($id) => (int) $id)->all();

        // «تُعرض الأسئلة بترتيب عشوائيّ» (4.1-1) — والمفتاح إعداد لا رقم محروق (2.13)
        if (setting('learning.quiz.shuffle_questions', true)) {
            shuffle($ids);
        }

        return $ids;
    }

    /** @param  Collection<int, LessonQuestion>  $questions */
    private function shuffledOptions(Collection $questions): array
    {
        $map = [];
        $shuffle = (bool) setting('learning.quiz.shuffle_options', true);

        foreach ($questions as $question) {
            $options = array_values((array) $question->options);

            if ($options === []) {
                continue;
            }

            if ($shuffle) {
                shuffle($options);
            }

            $map[(string) $question->id] = $options;
        }

        return $map;
    }
}
