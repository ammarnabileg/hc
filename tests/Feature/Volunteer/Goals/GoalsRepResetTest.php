<?php

namespace Tests\Feature\Volunteer\Goals;

use App\Models\Currency;
use App\Models\Transaction;
use App\Models\WalletBalance;
use App\Services\Volunteer\Goals\Integrations;
use App\Services\Volunteer\Goals\RepService;
use Carbon\CarbonImmutable;

/**
 * التصفير الشهريّ لدرجة الالتزام (الدستور 13.4-ن-ز).
 * ⭐ الرقم الظاهر يتصفّر — والسجلّ والمكتسَب التراكميّ يبقيان.
 */
class GoalsRepResetTest extends GoalsTestCase
{
    private function rep(): RepService
    {
        return app(RepService::class);
    }

    /** ⭐ القاعدة الحاسمة: الرقم الظاهر صفر، والسجلّ والمكتسَب كما هما */
    public function test_monthly_reset_zeroes_score_but_keeps_ledger_and_lifetime(): void
    {
        $user = $this->makeUser();

        Integrations::credit($user, RepService::CURRENCY, rep_rule('meeting.within_3h'), 'meeting', null, 'حضور اجتماع');
        Integrations::credit($user, RepService::CURRENCY, rep_rule('task.early'), 'task', null, 'تسليم قبل الموعد');

        $currencyId = Currency::query()->where('code', RepService::CURRENCY)->value('id');
        $before = WalletBalance::query()->where('user_id', $user->id)->where('currency_id', $currencyId)->first();

        $this->assertGreaterThan(0, (float) $before->balance);
        $transactionsBefore = Transaction::query()->where('user_id', $user->id)->count();
        $lifetimeBefore = (float) $before->lifetime_earned;

        $this->rep()->resetMonthly();

        $after = WalletBalance::query()->where('user_id', $user->id)->where('currency_id', $currencyId)->first();

        $this->assertSame(0.0, (float) $after->balance);
        $this->assertSame($lifetimeBefore, (float) $after->lifetime_earned);
        $this->assertSame($transactionsBefore, Transaction::query()->where('user_id', $user->id)->count());
        $this->assertNotNull($after->last_reset_at);
    }

    /** الأمر لا يعمل خارج موعده إلّا بـ--force — والموعد كلّه إعدادات */
    public function test_command_respects_configured_moment(): void
    {
        $user = $this->makeUser();
        Integrations::credit($user, RepService::CURRENCY, rep_rule('task.early'), 'task', null, 'تسليم قبل الموعد');

        // خارج الموعد: لا شيء يتغيّر
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-15 13:00', 'Africa/Cairo'));
        $this->travelTo(now()->setDate(2026, 8, 15)->setTime(13, 0));

        $this->artisan('rep:reset-monthly')->assertExitCode(0);
        $this->assertGreaterThan(0, $this->rep()->score($user->fresh()));

        // بـ--force ينفّذ فورًا
        $this->artisan('rep:reset-monthly', ['--force' => true])->assertExitCode(0);
        $this->assertSame(0.0, $this->rep()->score($user->fresh()));

        CarbonImmutable::setTestNow();
        $this->travelBack();
    }

    /** لحظة التصفير تُقرَأ من الإعدادات لا من رقم في الكود */
    public function test_next_reset_uses_settings_day_and_hour(): void
    {
        $next = $this->rep()->nextResetAt();

        $this->assertSame((int) setting('rep.reset.day_of_month', 1), $next->day);
        $this->assertSame((int) setting('rep.reset.hour', 5), $next->hour);
        $this->assertTrue($next->isFuture());
    }
}
