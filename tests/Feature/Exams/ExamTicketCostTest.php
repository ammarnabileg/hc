<?php

namespace Tests\Feature\Exams;

use App\Models\Currency;
use App\Models\ExamAttempt;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use App\Services\Admin\Volunteer\SettingsWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * 4.2 · 7.1: **الامتحان النهائيّ للتدريب يكلّف تذكرة تُخصَم بمجرّد الدخول**
 * (سواء جاوب أو ما جاوبش)، و**الكوينز لامتحان شهادة المسار وحده** (16).
 */
class ExamTicketCostTest extends ExamTestCase
{
    use RefreshDatabase;

    /** تذكرة واحدة تُخصَم بمجرّد الدخول — لا كوينز */
    public function test_course_exam_charges_one_ticket_on_entry(): void
    {
        $user = $this->trainee();
        $exam = $this->courseExam();
        $this->enroll($user, $exam);
        $this->give($user, 'tickets', 3);
        $this->give($user, 'coins', 500);

        $this->actingAs($user)
            ->post(route('exams.begin', $exam))
            ->assertRedirect();

        $this->assertSame(2.0, $this->balance($user, 'tickets'), 'تذكرة واحدة تُخصَم بمجرّد الدخول (4.2).');
        $this->assertSame(500.0, $this->balance($user, 'coins'), 'الكوينز لا تُمسّ في امتحان التدريب.');

        $ticketsId = Currency::where('code', 'tickets')->value('id');

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'currency_id' => $ticketsId,
            'amount' => -1,
            'reference_type' => $exam->getMorphClass(),
            'reference_id' => $exam->id,
        ]);
    }

    /** الخصم يقع مرّة واحدة: العودة لمحاولة جارية لا تخصم ثانيةً */
    public function test_entering_again_while_running_does_not_charge_twice(): void
    {
        $user = $this->trainee();
        $exam = $this->courseExam();
        $this->enroll($user, $exam);
        $this->give($user, 'tickets', 3);

        $this->actingAs($user)->post(route('exams.begin', $exam));
        $this->actingAs($user)->post(route('exams.begin', $exam));

        $this->assertSame(2.0, $this->balance($user, 'tickets'));
        $this->assertSame(1, ExamAttempt::query()->where('user_id', $user->id)->count());
    }

    /** بلا تذكرة لا محاولة ولا خصم — والرسالة تدلّ على طريق الكسب لا على الشحن (7.1) */
    public function test_without_a_ticket_there_is_no_attempt(): void
    {
        $user = $this->trainee();
        $exam = $this->courseExam();
        $this->enroll($user, $exam);
        $this->give($user, 'tickets', 0);

        $this->actingAs($user)
            ->get(route('exams.start', $exam))
            ->assertOk()
            ->assertSee(setting('exams.messages.insufficient_tickets'), false);

        $this->actingAs($user)->post(route('exams.begin', $exam));

        $this->assertSame(0, ExamAttempt::query()->where('user_id', $user->id)->count());
        $this->assertSame(0.0, $this->balance($user, 'tickets'));
        $this->assertSame(0, Transaction::query()->where('user_id', $user->id)->count());
    }

    /** بوب-أب ما قبل البدء يعرض التكلفة بعملتها الصحيحة: «تذاكر» لا «كوينز» */
    public function test_pre_start_dialog_shows_the_ticket_currency(): void
    {
        $user = $this->trainee();
        $exam = $this->courseExam();
        $this->enroll($user, $exam);
        $this->give($user, 'tickets', 4);

        $this->actingAs($user)
            ->get(route('exams.start', $exam))
            ->assertOk()
            ->assertSee(Currency::where('code', 'tickets')->value('name_ar'), false)
            ->assertSee(setting('exams.labels.ready'), false);
    }

    /**
     * ⭐ جدول «أوجه الصرف» في لوحة الإدارة له **مستهلك حقيقيّ** (2.13):
     * تغيير تكلفة `course.exam` من الشاشة يغيّر الخصم فعلًا — لا إعداد بلا أثر.
     */
    public function test_the_admin_spend_rule_drives_the_exam_cost(): void
    {
        $user = $this->trainee();
        $exam = $this->courseExam();
        $this->enroll($user, $exam);
        $this->give($user, 'tickets', 10);

        $this->setSpendCost('course.exam', 3);

        $this->actingAs($user)->post(route('exams.begin', $exam));

        $this->assertSame(7.0, $this->balance($user, 'tickets'), 'التكلفة تأتي من جدول الصرف لا من رقمٍ محروق.');
    }

    /** إيقاف صفّ الصرف من الشاشة يجعل الدخول مجّانيًّا — والقرار للأدمن */
    public function test_disabling_the_spend_rule_makes_entry_free(): void
    {
        $user = $this->trainee();
        $exam = $this->courseExam();
        $this->enroll($user, $exam);
        $this->give($user, 'tickets', 0);

        $this->setSpendCost('course.exam', 1, enabled: false);

        $this->actingAs($user)->post(route('exams.begin', $exam))->assertRedirect();

        $this->assertSame(1, ExamAttempt::query()->where('user_id', $user->id)->count());
    }

    /** درجة النجاح الافتراضيّة **70** لا 60 (4.2 · 8) */
    public function test_default_pass_score_is_seventy(): void
    {
        $this->assertSame(70, (int) setting('exams.pass_score.default'));
    }

    // ------------------------------------------------------------ أدوات

    /** يكتب صفّ الصرف كما تكتبه شاشة «XP والتذاكر» بالضبط */
    private function setSpendCost(string $key, float $cost, bool $enabled = true): void
    {
        SettingsWriter::put('xp_rules.spend', [
            ['key' => $key, 'label' => 'الامتحان النهائيّ', 'currency' => 'tickets', 'cost' => $cost, 'moment' => 'on_enter', 'enabled' => $enabled],
        ]);
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
