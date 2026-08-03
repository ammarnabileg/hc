<?php

namespace Tests\Feature\Exams;

use App\Models\Course;
use App\Models\Currency;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\User;
use App\Models\WalletBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ⭐ **«بدون مدة انتظار» و«كل دخول = تذكرة»** (الدستور 4.2).
 *
 * النصّ حرفيًّا: «**يكلّف تذكرة واحدة** تُخصَم **بمجرد الدخول** (سواء جاوب أو ما
 * جاوبش)، و**بدون مدة انتظار** (لا يوجد الـ 20 ثانية هنا). **كل دخول = تذكرة**».
 *
 * فلا سقفَ للمحاولات ولا انتظارَ بعد الرسوب في الافتراضيّ، والحاكم للدخول هو
 * **التذكرة وحدها**. وكان المخطّط يفتتح كلّ امتحانٍ بـ`attempts_allowed = 1`
 * و`retry_cooldown_hours = 24` فمن رسب مُنِع من الإعادة.
 *
 * والمفتاحان يبقيان قابلَين لضبط المالك (2.13) — الملغى الافتراضيّ لا الضبط.
 */
class ExamRetryTest extends ExamTestCase
{
    use RefreshDatabase;

    /** الافتراضيّ في المخطّط نفسه: بلا سقفٍ وبلا انتظار */
    public function test_a_new_exam_row_defaults_to_no_cap_and_no_cooldown(): void
    {
        $course = Course::create([
            'slug' => 'defaults-'.str()->random(6),
            'name_ar' => 'تدريب الافتراضيّات',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $exam = Exam::create([
            'examable_type' => $course->getMorphClass(),
            'examable_id' => $course->id,
            'title_ar' => 'امتحان بلا ضبطٍ من الأدمن',
        ]);

        $this->assertSame(0, (int) $exam->fresh()->attempts_allowed, '0 = بلا سقفٍ للمحاولات (4.2).');
        $this->assertSame(0, (int) $exam->fresh()->retry_cooldown_hours, '«بدون مدة انتظار» (4.2).');
    }

    /**
     * ⭐ الرحلة الحقيقيّة: ادخل · ارسب · أعد **فورًا** ⟵ تُقبَل بتذكرة،
     * لا تُمنَع أربعًا وعشرين ساعة.
     */
    public function test_a_failed_trainee_may_re_enter_immediately_with_another_ticket(): void
    {
        $user = $this->trainee();
        $exam = $this->defaultExam();
        $this->enroll($user, $exam);
        $this->give($user, 'tickets', 3);

        // المحاولة الأولى — رسوب
        $this->actingAs($user)->post(route('exams.begin', $exam));
        $this->actingAs($user)->post(route('exams.submit', $exam), ['answers' => $this->wrongAnswers($exam)]);

        $first = ExamAttempt::where('user_id', $user->id)->latest('id')->first();
        $this->assertFalse((bool) $first->passed);
        $this->assertSame(2.0, $this->balance($user, 'tickets'));

        // الإعادة **في نفس اللحظة** — بلا أيّ انتظار
        $this->actingAs($user)->post(route('exams.begin', $exam))
            ->assertRedirect(route('exams.take', ['exam' => $exam, 'q' => 1]));

        $this->assertSame(2, ExamAttempt::where('user_id', $user->id)->count(), 'كلّ دخول محاولةٌ جديدة.');
        $this->assertSame(1.0, $this->balance($user, 'tickets'), 'وكلّ دخول = تذكرة (4.2).');
    }

    /** والتذكرة وحدها هي الحاكم: بلا رصيدٍ لا دخول ولو لم يكن هناك سقفٌ ولا انتظار */
    public function test_the_ticket_alone_governs_entry(): void
    {
        $user = $this->trainee();
        $exam = $this->defaultExam();
        $this->enroll($user, $exam);
        $this->give($user, 'tickets', 1);

        $this->actingAs($user)->post(route('exams.begin', $exam));
        $this->actingAs($user)->post(route('exams.submit', $exam), ['answers' => $this->wrongAnswers($exam)]);

        $this->actingAs($user)->post(route('exams.begin', $exam));

        $this->assertSame(1, ExamAttempt::where('user_id', $user->id)->count(), 'بلا تذكرة لا محاولة.');
        $this->assertSame(0.0, $this->balance($user, 'tickets'));
    }

    /** والملغى الافتراضيّ لا الضبط: لو ضبط المالك سقفًا وانتظارًا عملا (2.13) */
    public function test_the_owner_may_still_configure_a_cap_and_a_cooldown(): void
    {
        $user = $this->trainee();
        $exam = $this->defaultExam();
        $this->enroll($user, $exam);
        $this->give($user, 'tickets', 5);

        $exam->update(['retry_cooldown_hours' => 24]);

        $this->actingAs($user)->post(route('exams.begin', $exam));
        $this->actingAs($user)->post(route('exams.submit', $exam), ['answers' => $this->wrongAnswers($exam)]);
        $this->actingAs($user)->post(route('exams.begin', $exam));

        $this->assertSame(1, ExamAttempt::where('user_id', $user->id)->count(), 'الانتظار المضبوط من المالك يعمل.');

        $exam->update(['retry_cooldown_hours' => 0, 'attempts_allowed' => 1]);
        $this->actingAs($user)->post(route('exams.begin', $exam));

        $this->assertSame(1, ExamAttempt::where('user_id', $user->id)->count(), 'والسقف المضبوط من المالك يعمل.');
    }

    // ------------------------------------------------------------ أدوات

    /** امتحان تدريبٍ بأعمدةٍ **افتراضيّة** — لا سقفَ ولا انتظارَ مكتوبين بيدنا */
    private function defaultExam(): Exam
    {
        $exam = $this->courseExam();

        $exam->forceFill([
            'attempts_allowed' => (int) $this->schemaDefault('attempts_allowed'),
            'retry_cooldown_hours' => (int) $this->schemaDefault('retry_cooldown_hours'),
        ])->save();

        return $exam->fresh();
    }

    /** الافتراضيّ كما يكتبه المخطّط على صفٍّ لم يمرّر العمود */
    private function schemaDefault(string $column): int
    {
        $course = Course::create([
            'slug' => 'probe-'.str()->random(6),
            'name_ar' => 'قياس الافتراضيّ',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $probe = Exam::create([
            'examable_type' => $course->getMorphClass(),
            'examable_id' => $course->id,
            'title_ar' => 'قياس',
        ]);

        return (int) $probe->fresh()->{$column};
    }

    /** @return array<int,string> */
    private function wrongAnswers(Exam $exam): array
    {
        return ExamQuestion::where('exam_id', $exam->id)
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => 'إجابة غلط'])
            ->all();
    }

    private function give(User $user, string $code, float $amount): void
    {
        $currency = Currency::query()->where('code', $code)->first();

        WalletBalance::updateOrCreate(
            ['user_id' => $user->id, 'currency_id' => $currency->id],
            ['balance' => $amount],
        );
    }

    private function balance(User $user, string $code): float
    {
        return (float) $user->fresh()->balance($code);
    }
}
