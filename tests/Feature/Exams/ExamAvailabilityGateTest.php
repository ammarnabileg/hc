<?php

namespace Tests\Feature\Exams;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseAvailabilityPeriod;
use App\Models\Currency;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\User;
use App\Models\WalletBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ⭐ **الإتاحة تحكم الامتحان كما تحكم الدروس** (الدستور 5 · 4.2 · 8).
 *
 * القسم 5: «**عدة فترات إتاحة للتدريب الواحد** … المتدرب يوصل للتدريب فقط أثناء
 * إحدى هذه الفترات» · «**أوقات تشغيل يومية لكل تدريب** … خارج الساعات دي التدريب
 * **مقفول** حتى لو فترة الإتاحة سارية» — **بالتوقيت المحلّيّ للمستخدم**.
 *
 * وكان الحاجز على الدروس وحدها، ومسارات الامتحان الخمسة تردّ 200 والتدريب مقفول
 * — والامتحان **يُصدر الشهادة** (8)، فالثغرة تنتهي بشهادةٍ من بابٍ مغلق.
 */
class ExamAvailabilityGateTest extends ExamTestCase
{
    use RefreshDatabase;

    /**
     * كلّ مسارات الامتحان الخمسة تُردّ والتدريب مقفول بالنافذة اليوميّة —
     * ولا محاولة ولا خصم تذكرة ولا شهادة.
     */
    public function test_every_exam_route_is_closed_while_the_course_is_outside_its_daily_window(): void
    {
        [$user, $exam] = $this->readyTrainee();
        $this->closeDailyWindow($exam);

        $this->actingAs($user)->get(route('exams.start', $exam))->assertRedirect();
        $this->actingAs($user)->post(route('exams.begin', $exam))->assertRedirect();
        $this->actingAs($user)->get(route('exams.take', $exam))->assertRedirect();
        $this->actingAs($user)->postJson(route('exams.answer', $exam), [
            'question_id' => ExamQuestion::where('exam_id', $exam->id)->value('id'),
            'value' => 'القاهرة',
        ])->assertStatus(403);
        $this->actingAs($user)->post(route('exams.submit', $exam))->assertRedirect();

        $this->assertSame(0, ExamAttempt::where('user_id', $user->id)->count(), 'لا محاولة تُفتَح والتدريب مقفول.');
        $this->assertSame(3.0, $this->balance($user, 'tickets'), 'ولا تذكرة تُخصَم على بابٍ مغلق.');
        $this->assertSame(0, Certificate::where('user_id', $user->id)->count());
    }

    /** والطبقة الثانية من القسم 5: خارج فترات الإتاحة ولو بلا نافذة يوميّة */
    public function test_the_gate_also_closes_outside_the_availability_periods(): void
    {
        [$user, $exam] = $this->readyTrainee();

        CourseAvailabilityPeriod::create([
            'course_id' => (int) $exam->examable_id,
            'starts_on' => now()->subMonths(6)->toDateString(),
            'ends_on' => now()->subMonths(6)->addDays(7)->toDateString(),
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('exams.start', $exam))->assertRedirect();
        $this->actingAs($user)->post(route('exams.begin', $exam))->assertRedirect();

        $this->assertSame(0, ExamAttempt::where('user_id', $user->id)->count());
    }

    /** والتدريب غير المنشور لا امتحان له أصلًا */
    public function test_an_unpublished_course_closes_its_exam(): void
    {
        [$user, $exam] = $this->readyTrainee();
        Course::where('id', $exam->examable_id)->update(['status' => 'draft']);

        $this->actingAs($user)->post(route('exams.begin', $exam))->assertRedirect();
        $this->assertSame(0, ExamAttempt::where('user_id', $user->id)->count());
    }

    /**
     * ⭐ والباب لا يُقفَل في وجه من يستحقّه: داخل النافذة يمرّ كلّ شيء
     * — الدخول والحلّ والتسليم والشهادة.
     */
    public function test_inside_the_window_the_whole_journey_passes(): void
    {
        [$user, $exam] = $this->readyTrainee();
        $this->openDailyWindow($exam);

        $this->actingAs($user)->get(route('exams.start', $exam))->assertOk();
        $this->actingAs($user)->post(route('exams.begin', $exam))->assertRedirect(route('exams.take', ['exam' => $exam, 'q' => 1]));
        $this->actingAs($user)->get(route('exams.take', $exam))->assertOk();

        $this->actingAs($user)->post(route('exams.submit', $exam), ['answers' => $this->correctAnswers($exam)]);

        $attempt = ExamAttempt::where('user_id', $user->id)->latest('id')->first();
        $this->assertTrue((bool) $attempt->passed, 'من دخل داخل نافذته ونجح — ينجح.');
        $this->assertSame(2.0, $this->balance($user, 'tickets'));
    }

    /**
     * ⭐ الاستثناء المقصود: محاولةٌ بدأت داخل النافذة ودُفِعت تذكرتها لا تُسحَب
     * من صاحبها لأنّ الساعة دقّت — يكمل ويسلّم ويرى نتيجته.
     */
    public function test_a_running_attempt_survives_the_window_closing(): void
    {
        [$user, $exam] = $this->readyTrainee();
        $this->openDailyWindow($exam);

        $this->actingAs($user)->post(route('exams.begin', $exam))->assertRedirect();
        $this->closeDailyWindow($exam);

        $this->actingAs($user)->get(route('exams.take', $exam))->assertOk();
        $this->actingAs($user)->postJson(route('exams.answer', $exam), [
            'question_id' => ExamQuestion::where('exam_id', $exam->id)->value('id'),
            'value' => 'القاهرة',
        ])->assertOk();
        $this->actingAs($user)->post(route('exams.submit', $exam), ['answers' => $this->correctAnswers($exam)]);

        $attempt = ExamAttempt::where('user_id', $user->id)->latest('id')->first();
        $this->assertSame('submitted', $attempt->status);
        $this->assertTrue((bool) $attempt->passed);
    }

    /** وشاشة النتيجة سجلٌّ للقراءة — تبقى مفتوحة والتدريب مقفول */
    public function test_the_result_screen_stays_readable_while_the_course_is_closed(): void
    {
        [$user, $exam] = $this->readyTrainee();
        $this->openDailyWindow($exam);

        $this->actingAs($user)->post(route('exams.begin', $exam));
        $this->actingAs($user)->post(route('exams.submit', $exam), ['answers' => $this->correctAnswers($exam)]);

        $attempt = ExamAttempt::where('user_id', $user->id)->latest('id')->first();
        $this->closeDailyWindow($exam);

        $this->actingAs($user)->get(route('exams.result', $attempt))->assertOk();
    }

    /**
     * وامتحان **شهادة المسار** خارج هذا الحاجز: `examable` مسارٌ لا تدريب،
     * ولا نافذةَ إتاحةٍ للمسار في المخطّط — فلا يُقفَل بما لا وجود له.
     */
    public function test_a_path_certificate_exam_is_not_touched_by_the_course_gate(): void
    {
        $user = $this->trainee();
        $exam = $this->pathExam(price: 100);
        $this->give($user, 'coins', 500);

        $this->actingAs($user)->get(route('exams.start', $exam))->assertOk();
    }

    // ------------------------------------------------------------ أدوات

    /** @return array{0:User,1:Exam} */
    private function readyTrainee(): array
    {
        $user = $this->trainee();
        $exam = $this->courseExam();
        $this->enroll($user, $exam);
        $this->give($user, 'tickets', 3);

        return [$user, $exam];
    }

    /** نافذة يوميّة لا تضمّ اللحظة الحاليّة بساعة المستخدم */
    private function closeDailyWindow(Exam $exam): void
    {
        $now = now();

        Course::where('id', $exam->examable_id)->update([
            'daily_open_at' => $now->copy()->addHours(3)->format('H:i:00'),
            'daily_close_at' => $now->copy()->addHours(4)->format('H:i:00'),
        ]);
    }

    /** نافذة يوميّة تضمّ اللحظة الحاليّة */
    private function openDailyWindow(Exam $exam): void
    {
        $now = now();

        Course::where('id', $exam->examable_id)->update([
            'daily_open_at' => $now->copy()->subHour()->format('H:i:00'),
            'daily_close_at' => $now->copy()->addHour()->format('H:i:00'),
        ]);
    }

    /** @return array<int,string> */
    private function correctAnswers(Exam $exam): array
    {
        return ExamQuestion::where('exam_id', $exam->id)
            ->pluck('correct_answer', 'id')
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
