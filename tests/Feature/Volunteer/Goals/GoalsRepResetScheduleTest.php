<?php

namespace Tests\Feature\Volunteer\Goals;

use App\Models\Setting;
use App\Services\Volunteer\Goals\Integrations;
use App\Services\Volunteer\Goals\RepService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;

/**
 * لحظة التصفير الشهريّ **تُقرأ من الإعدادات فعلًا** (13.4-ن-ز · 2.13).
 *
 * كانت الجدولة `monthlyOn(1, '05:00')` — تعبير كرون يُقرأ مرّةً عند تحميل الملفّ،
 * فيبقى `0 5 1 * *` بعد أن يغيّر الأدمن الموعد؛ والأمر بلا `--force` يرفض خارج
 * الموعد المضبوط، فلا يلتقيان أبدًا ولا يحدث التصفير في أيّ لحظة — بلا خطأ ولا
 * سجلّ. فالجدولة صارت **مسحةً كلّ ساعة** والقرار في `isResetMoment()` وحدها.
 */
class GoalsRepResetScheduleTest extends GoalsTestCase
{
    private function rep(): RepService
    {
        return app(RepService::class);
    }

    private function putSetting(string $key, string $value, string $type = 'number'): void
    {
        Setting::updateOrCreate(['key' => $key], [
            'group' => 'volunteer_rep',
            'label_ar' => $key,
            'type' => $type,
            'default_value' => $value,
            'value' => $value,
        ]);

        Cache::forget('settings');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /** ⭐ تغيير الموعد من الإعدادات يغيّر **لحظة التنفيذ** لا الشاشة وحدها */
    public function test_changing_the_configured_moment_changes_when_the_reset_actually_runs(): void
    {
        $this->putSetting('rep.reset.day_of_month', '7');
        $this->putSetting('rep.reset.hour', '9');

        // الموعد القديم المحروق (يوم 1 الساعة 5) لم يعد لحظةَ تصفير
        $this->assertFalse($this->rep()->isResetMoment(CarbonImmutable::parse('2026-08-01 05:00', 'Africa/Cairo')));

        // والموعد الجديد هو اللحظة
        $this->assertTrue($this->rep()->isResetMoment(CarbonImmutable::parse('2026-08-07 09:00', 'Africa/Cairo')));

        $next = $this->rep()->nextResetAt();
        $this->assertSame(7, $next->day);
        $this->assertSame(9, $next->hour);

        // والأمر ينفّذ في اللحظة الجديدة بلا --force
        $user = $this->makeUser();
        Integrations::credit($user, RepService::CURRENCY, rep_rule('task.early'), 'task', null, 'تسليم قبل الموعد');
        $this->assertGreaterThan(0, $this->rep()->score($user->fresh()));

        $this->travelTo(CarbonImmutable::parse('2026-08-07 09:30', 'Africa/Cairo'));
        $this->artisan('rep:reset-monthly')->assertExitCode(0);
        $this->assertSame(0.0, $this->rep()->score($user->fresh()));
        $this->travelBack();
    }

    /** ⛔ وإيقافه من الإعدادات **يمنعه فعلًا** — حتى بـ--force */
    public function test_disabling_the_reset_prevents_it(): void
    {
        $user = $this->makeUser();
        Integrations::credit($user, RepService::CURRENCY, rep_rule('task.early'), 'task', null, 'تسليم قبل الموعد');
        $before = $this->rep()->score($user->fresh());
        $this->assertGreaterThan(0, $before);

        $this->putSetting('rep.reset.enabled', '0', 'bool');

        $this->assertFalse($this->rep()->resetEnabled());
        $this->assertFalse($this->rep()->isResetMoment(CarbonImmutable::parse('2026-08-01 05:00', 'Africa/Cairo')));

        $this->artisan('rep:reset-monthly', ['--force' => true])->assertExitCode(0);

        $this->assertSame($before, $this->rep()->score($user->fresh()));
    }

    /** ومنطقة التصفير مفتاحها الخاصّ — لا توقيت المنصّة العامّ */
    public function test_reset_timezone_setting_is_actually_read(): void
    {
        $this->putSetting('rep.reset.timezone', 'America/New_York', 'string');

        $this->assertSame('America/New_York', $this->rep()->resetTimezone());
        $this->assertSame('America/New_York', $this->rep()->nextResetAt()->timezone->getName());

        // الساعة 5 بتوقيت نيويورك هي اللحظة — لا الساعة 5 بتوقيت القاهرة
        $this->putSetting('rep.reset.day_of_month', '1');
        $this->putSetting('rep.reset.hour', '5');

        $this->assertTrue($this->rep()->isResetMoment(CarbonImmutable::parse('2026-09-01 05:00', 'America/New_York')));
        $this->assertFalse($this->rep()->isResetMoment(CarbonImmutable::parse('2026-09-01 05:00', 'Africa/Cairo')));
    }

    /** والجدولة نفسها مسحةٌ متكرّرة لا تعبيرَ كرون شهريًّا محروقًا */
    public function test_the_schedule_is_a_recurring_sweep_not_a_hardcoded_monthly_cron(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'rep:reset-monthly'));

        $this->assertNotNull($event, 'أمر التصفير الشهريّ غير مجدوَل أصلًا');
        $this->assertSame('0 * * * *', $event->expression, 'الجدولة لازم تكون مسحةً كلّ ساعة والقرار في isResetMoment()');
    }
}
