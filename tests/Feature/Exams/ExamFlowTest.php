<?php

namespace Tests\Feature\Exams;

use App\Models\Certificate;
use App\Models\Currency;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Transaction;
use App\Models\WalletBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * اختبارات الامتحان (4.2 · 24.5) — كلّ اختبار يقابل قاعدةً منصوصةً في الدستور.
 */
class ExamFlowTest extends ExamTestCase
{
    use RefreshDatabase;

    /** التصحيح والدرجة في الخادم حصرًا — ولا يُقبَل أيّ حساب قادم من المتصفّح */
    public function test_grading_happens_on_the_server_only(): void
    {
        $user = $this->trainee();
        $exam = $this->courseExam(passScore: 70);
        $this->enroll($user, $exam);
        $questions = $this->questionsOf($exam);
        $attempt = $this->startedAttempt($exam, $user);

        $this->actingAs($user)
            ->post(route('exams.submit', $exam), [
                'answers' => [
                    $questions[0]->id => 'القاهرة',   // صحيحة
                    $questions[1]->id => 'إجابة غلط',  // خاطئة
                    $questions[2]->id => '7',          // صحيحة
                ],
                // محاولة تزوير الدرجة من المتصفّح — يجب أن تُتجاهَل تمامًا
                'score' => 100,
                'passed' => 1,
            ])
            ->assertRedirect(route('exams.result', $attempt->fresh()));

        $attempt->refresh();

        $this->assertSame('submitted', $attempt->status);
        $this->assertEqualsWithDelta(66.67, (float) $attempt->score, 0.01);
        $this->assertFalse($attempt->passed, 'الدرجة أقلّ من درجة النجاح فلا يمرّ.');
    }

    /** انتهاء الوقت ⟵ تسليم تلقائيّ برسالة واضحة (24.5) */
    public function test_time_out_submits_automatically(): void
    {
        $user = $this->trainee();
        $exam = $this->courseExam();
        $this->enroll($user, $exam);
        $questions = $this->questionsOf($exam);

        $attempt = $this->startedAttempt($exam, $user, [
            'started_at' => now()->subMinutes($exam->duration_minutes + 1),
            'answers' => [(string) $questions[0]->id => 'القاهرة'],
        ]);

        $this->actingAs($user)
            ->get(route('exams.take', $exam))
            ->assertRedirect(route('exams.result', $attempt));

        $attempt->refresh();

        $this->assertSame('expired', $attempt->status, 'الوقت انتهى فالمحاولة تُسلَّم تلقائيًّا.');
        $this->assertNotNull($attempt->submitted_at);
        $this->assertNotNull($attempt->score, 'التسليم التلقائيّ يُصحَّح كأيّ تسليم.');

        $this->actingAs($user)
            ->get(route('exams.result', $attempt))
            ->assertOk()
            ->assertSee(setting('exams.messages.time_up'), false);
    }

    /** الحفظ التدريجيّ: انقطاع الشبكة لا يضيّع إجابةً ويُستأنف من مكانه (2.17-ب) */
    public function test_answers_are_saved_incrementally(): void
    {
        $user = $this->trainee();
        $exam = $this->courseExam();
        $this->enroll($user, $exam);
        $questions = $this->questionsOf($exam);
        $attempt = $this->startedAttempt($exam, $user);

        $this->actingAs($user)
            ->postJson(route('exams.answer', $exam), [
                'question_id' => $questions[0]->id,
                'value' => 'القاهرة',
            ])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $this->assertSame('القاهرة', $attempt->fresh()->answers[(string) $questions[0]->id]);

        // العودة للشاشة تجد الإجابة كما هي
        $this->actingAs($user)
            ->get(route('exams.take', ['exam' => $exam, 'q' => 1]))
            ->assertOk()
            ->assertSee('القاهرة', false);
    }

    /** الرسوب برسالة محايدة تشجّع ولا تعاتب (2.17-ج) */
    public function test_failing_shows_a_neutral_encouraging_message(): void
    {
        $user = $this->trainee();
        $exam = $this->courseExam();
        $this->enroll($user, $exam);
        $attempt = $this->startedAttempt($exam, $user);

        $this->actingAs($user)->post(route('exams.submit', $exam), ['answers' => []]);

        $this->actingAs($user)
            ->get(route('exams.result', $attempt->fresh()))
            ->assertOk()
            ->assertSee(setting('exams.messages.failed'), false)
            ->assertDontSee('فشلت', false);
    }

    /** النجاح يُصدر الشهادة تلقائيًّا (8) ويظهر احتفال الذروة (2.14-3) */
    public function test_passing_issues_a_certificate_automatically(): void
    {
        $user = $this->trainee();
        $exam = $this->courseExam();
        $this->enroll($user, $exam);
        $questions = $this->questionsOf($exam);
        $attempt = $this->startedAttempt($exam, $user);

        $this->actingAs($user)->post(route('exams.submit', $exam), [
            'answers' => [
                $questions[0]->id => 'القاهرة',
                $questions[1]->id => 'صحّ',
                $questions[2]->id => '7',
            ],
        ]);

        $attempt->refresh();
        $this->assertTrue($attempt->passed);

        $certificate = Certificate::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($certificate, 'الشهادة تُصدَر تلقائيًّا عند استيفاء الشرط.');
        $this->assertSame('valid', $certificate->status);
        $this->assertNotEmpty($certificate->hash, 'لكلّ شهادة توقيع رقميّ.');
        $this->assertNotNull($certificate->template_snapshot, 'نسخة القالب تُجمَّد لحظة الإصدار (12.5-ج).');
        $this->assertSame($user->name, $certificate->data_snapshot['holder_name']);

        $this->actingAs($user)
            ->get(route('exams.result', $attempt))
            ->assertOk()
            ->assertSee($certificate->code, false);
    }

    /** امتحان المسار مدفوع بالكوينز: الخصم يقع مرّةً واحدة ويُسجَّل معاملةً (19 · 24.5) */
    public function test_path_exam_charges_coins_once(): void
    {
        $user = $this->trainee();
        $exam = $this->pathExam(price: 150);
        $this->giveCoins($user, 200);

        $this->actingAs($user)
            ->post(route('exams.begin', $exam))
            ->assertRedirect();

        $this->assertSame(50.0, (float) $user->fresh()->balance('coins'));
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => -150,
            'reference_type' => $exam->getMorphClass(),
            'reference_id' => $exam->id,
        ]);
        $this->assertSame(1, Transaction::query()->where('user_id', $user->id)->count());
    }

    /** بوب-أب ما قبل البدء: المدّة والمحاولات والسعر والرصيد قبل/بعد + تأكيد (24.5) */
    public function test_pre_start_dialog_shows_duration_attempts_price_and_balance(): void
    {
        $user = $this->trainee();
        $exam = $this->pathExam(price: 150);
        $this->giveCoins($user, 400);

        $this->actingAs($user)
            ->get(route('exams.start', $exam))
            ->assertOk()
            ->assertSee(setting('exams.labels.confirm_title'), false)
            ->assertSee((string) $exam->duration_minutes, false)
            ->assertSee('150', false)   // السعر بالكوينز
            ->assertSee('400', false)   // الرصيد قبل
            ->assertSee('250', false)   // الرصيد بعد
            ->assertSee(setting('exams.labels.ready'), false);
    }

    /** الرصيد غير الكافي: لا محاولة ولا خصم، ويظهر [اشحن المحفظة] داخل البوب-أب */
    public function test_insufficient_balance_blocks_the_attempt(): void
    {
        $user = $this->trainee();
        $exam = $this->pathExam(price: 150);
        $this->giveCoins($user, 20);

        $this->actingAs($user)
            ->get(route('exams.start', $exam))
            ->assertOk()
            ->assertSee(setting('exams.messages.insufficient_balance'), false);

        $this->actingAs($user)->post(route('exams.begin', $exam));

        $this->assertSame(0, ExamAttempt::query()->where('user_id', $user->id)->count());
        $this->assertSame(20.0, (float) $user->fresh()->balance('coins'));
    }

    /** شاشة الحلّ: سؤال واحد في المرّة + شاشة مراجعة قبل التسليم (24.5) */
    public function test_take_screen_shows_one_question_at_a_time_and_a_review_screen(): void
    {
        $user = $this->trainee();
        $exam = $this->courseExam();
        $this->enroll($user, $exam);
        $questions = $this->questionsOf($exam);
        $this->startedAttempt($exam, $user);

        $this->actingAs($user)
            ->get(route('exams.take', ['exam' => $exam, 'q' => 1]))
            ->assertOk()
            ->assertSee($questions[0]->prompt, false)
            ->assertDontSee($questions[1]->prompt, false);

        $this->actingAs($user)
            ->get(route('exams.take', ['exam' => $exam, 'q' => 'review']))
            ->assertOk()
            ->assertSee($questions[0]->prompt, false)
            ->assertSee($questions[1]->prompt, false)
            ->assertSee(setting('exams.labels.submit'), false);
    }

    // ------------------------------------------------------------ أدوات

    private function questionsOf(Exam $exam)
    {
        return ExamQuestion::query()->where('exam_id', $exam->id)->orderBy('sort_order')->get();
    }

    private function startedAttempt(Exam $exam, $user, array $overrides = []): ExamAttempt
    {
        return ExamAttempt::create(array_merge([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'exam_version' => $exam->version ?? 1,
            'started_at' => now(),
            'answers' => [],
            'status' => 'in_progress',
        ], $overrides));
    }

    private function giveCoins($user, float $amount): void
    {
        $currency = Currency::query()->where('code', 'coins')->first();

        WalletBalance::updateOrCreate(
            ['user_id' => $user->id, 'currency_id' => $currency->id],
            ['balance' => $amount],
        );
    }
}
