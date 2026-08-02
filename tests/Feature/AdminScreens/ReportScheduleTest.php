<?php

namespace Tests\Feature\AdminScreens;

use App\Models\ReportSchedule;
use Illuminate\Support\Facades\Mail;

/**
 * التقارير المجدولة (24.3-خامسًا): ترندر · الصلاحيّة تحجب · الفلاتر تشتغل ·
 * 🔒 والتقرير الماليّ محجوزٌ لمالك المنصّة إنشاءً وتشغيلًا وقراءةً.
 */
class ReportScheduleTest extends ScreensTestCase
{
    public function test_screen_renders_for_its_owner(): void
    {
        $this->actingAs($this->admin(['report_schedules.list', 'report_schedules.view']))
            ->get(route('admin.report-schedules.index'))
            ->assertOk()
            ->assertSee('التقارير المجدولة')
            ->assertSee('تقرير المستخدمين الأسبوعيّ');
    }

    public function test_permission_blocks_the_screen_and_its_actions(): void
    {
        $schedule = ReportSchedule::query()->firstOrFail();

        $this->actingAs($this->admin(['users.list']))
            ->get(route('admin.report-schedules.index'))
            ->assertForbidden();

        $this->actingAs($this->admin(['report_schedules.list', 'report_schedules.view']))
            ->post(route('admin.report-schedules.run', $schedule))
            ->assertForbidden();
    }

    public function test_filters_narrow_the_schedules(): void
    {
        $admin = $this->admin(['report_schedules.list', 'report_schedules.view']);

        $this->actingAs($admin)
            ->get(route('admin.report-schedules.index', ['frequency' => 'monthly']))
            ->assertOk()
            ->assertSee('تقرير التدريبات الشهريّ')
            ->assertDontSee('تقرير المستخدمين الأسبوعيّ');

        $this->actingAs($admin)
            ->get(route('admin.report-schedules.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('تقرير المستخدمين الأسبوعيّ')
            ->assertDontSee('تقرير التدريبات الشهريّ');
    }

    /** 🔒 التقرير الماليّ لا يُنشئه غير مالك المنصّة ولا يظهر له أصلًا */
    public function test_financial_report_is_owner_only(): void
    {
        $admin = $this->admin([
            'report_schedules.list', 'report_schedules.view',
            'report_schedules.create', 'report_schedules.edit', 'report_schedules.manage',
        ]);

        $payload = [
            'name' => 'تقرير مبيعات',
            'report_tab' => 'sales',
            'format' => 'csv',
            'frequency' => 'daily',
            'hour' => 7,
            'timezone' => 'Africa/Cairo',
            'period_days' => 30,
            'emails' => 'boss@test.local',
        ];

        $this->actingAs($admin)->post(route('admin.report-schedules.store'), $payload)->assertForbidden();

        $this->actingAs($this->owner())->post(route('admin.report-schedules.store'), $payload)->assertRedirect();

        $financial = ReportSchedule::query()->where('report_tab', 'sales')->firstOrFail();
        $this->assertTrue((bool) $financial->is_financial);

        // وحتّى بعد وجودها لا تظهر في قائمة غير المالك ولا يُقرأ سجلّها
        $this->actingAs($admin)
            ->get(route('admin.report-schedules.index'))
            ->assertOk()
            ->assertDontSee('تقرير مبيعات');

        $this->actingAs($admin)
            ->get(route('admin.report-schedules.log', $financial))
            ->assertForbidden();
    }

    /** «شغّل الآن» يبعت فعلًا ويكتب سطرًا في السجلّ */
    public function test_manual_run_sends_and_records_a_log_row(): void
    {
        Mail::fake();

        $schedule = ReportSchedule::query()->where('name', 'تقرير المستخدمين الأسبوعيّ')->firstOrFail();
        $schedule->forceFill(['skip_when_empty' => false])->save();

        $this->actingAs($this->owner())
            ->post(route('admin.report-schedules.run', $schedule))
            ->assertRedirect();

        Mail::assertSentCount(1);

        $this->assertDatabaseHas('report_schedule_runs', [
            'report_schedule_id' => $schedule->id,
            'result' => 'sent',
            'was_manual' => true,
        ]);

        $this->assertSame('sent', $schedule->refresh()->last_result);
        $this->assertNotNull($schedule->next_run_at);
    }

    /** بلا مستقبِلين لا يُرسَل شيء — ورسالة الخطأ تقول ماذا حدث وماذا تفعل (2.17-ب) */
    public function test_missing_recipients_fail_with_an_actionable_message(): void
    {
        Mail::fake();

        $schedule = ReportSchedule::query()->firstOrFail();
        $schedule->forceFill(['recipient_emails' => [], 'skip_when_empty' => false])->save();

        $this->actingAs($this->owner())
            ->post(route('admin.report-schedules.run', $schedule))
            ->assertRedirect()
            ->assertSessionHas('problem');

        Mail::assertNothingSent();

        $this->assertDatabaseHas('report_schedule_runs', [
            'report_schedule_id' => $schedule->id,
            'result' => 'failed',
        ]);
    }

    /** المهمّة المجدولة تمرّ على المستحقّ وحده */
    public function test_the_scheduled_command_runs_only_due_schedules(): void
    {
        Mail::fake();

        ReportSchedule::query()->update(['status' => 'paused']);

        $due = ReportSchedule::query()->firstOrFail();
        $due->forceFill([
            'status' => 'active',
            'skip_when_empty' => false,
            'next_run_at' => now()->subHour(),
        ])->save();

        $this->artisan('reports:dispatch')->assertSuccessful();

        Mail::assertSentCount(1);
        $this->assertTrue($due->refresh()->next_run_at->isFuture());
    }

    public function test_settings_are_editable_and_resettable(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('admin.report-schedules.settings'), [
            'settings' => ['report_schedules.default_hour' => 9],
        ])->assertRedirect();

        $this->assertSame(9, (int) setting('report_schedules.default_hour'));

        $this->actingAs($owner)->post(route('admin.report-schedules.settings.reset'))->assertRedirect();

        $this->assertSame(7, (int) setting('report_schedules.default_hour'));
    }
}
