<?php

namespace Tests\Feature\Admin\Ops;

use App\Services\Admin\Ops\SystemHealth;
use Illuminate\Support\Facades\DB;

/**
 * النسخ الاحتياطيّ وصحّة النظام (12.7-و):
 * نسخة تُكتَب فعلًا ويُسجَّل صفّها · مؤشّرات بلون ورمز · تنبيهات استباقيّة تصل للأدمن.
 */
class AdminOpsSystemTest extends OpsTestCase
{
    private const OPERATOR = [
        'system_health.view', 'system_health.export',
        'backups.view', 'backups.create', 'backups.delete', 'backups.export',
        'scheduled_jobs.view', 'scheduled_jobs.manage',
    ];

    public function test_screen_opens_with_every_indicator_carrying_a_symbol(): void
    {
        $admin = $this->admin(self::OPERATOR);

        $this->actingAs($admin)->get(route('admin.ops.system'))
            ->assertOk()
            ->assertSee('مساحة القرص')
            ->assertSee('قاعدة البيانات')
            ->assertSee('الكاش')
            ->assertSee('الطوابير')
            ->assertSee('الجدولة')
            ->assertSee('الامتدادات المطلوبة')
            ->assertSee('صلاحيّات الكتابة');
    }

    public function test_backups_and_schedule_tabs_load_on_demand(): void
    {
        $admin = $this->admin(self::OPERATOR);

        $this->actingAs($admin)->get(route('admin.ops.system', ['tab' => 'backups']))
            ->assertOk()
            ->assertSee('مافيش نسخ لسه');

        $this->actingAs($admin)->get(route('admin.ops.system', ['tab' => 'schedule']))
            ->assertOk()
            ->assertSee('تشغيل النسخ المجدول')
            ->assertSee('عدد النسخ المحفوظة');
    }

    /** ⭐ النسخة تُكتَب على القرص فعلًا، ويُسجَّل صفّها بالحجم ومَن أخذها */
    public function test_manual_backup_writes_a_file_and_records_its_row(): void
    {
        $admin = $this->admin(self::OPERATOR);

        $this->actingAs($admin)->post(route('admin.ops.system.backups.store'), ['kind' => 'database'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $row = DB::table('backup_files')->latest('id')->first();

        $this->assertNotNull($row);
        $this->assertSame('done', $row->status);
        $this->assertSame($admin->id, $row->created_by);
        $this->assertGreaterThan(0, $row->size_bytes);
        $this->assertNotNull($row->checksum, 'النسخة بلا بصمة سلامة لا يجوز الاعتماد عليها.');
        $this->assertFileExists($this->backupPath().DIRECTORY_SEPARATOR.$row->filename);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ops.backups.created',
            'user_id' => $admin->id,
        ]);
    }

    public function test_backup_can_be_downloaded_then_deleted_with_its_file(): void
    {
        $admin = $this->admin(self::OPERATOR);

        $this->actingAs($admin)->post(route('admin.ops.system.backups.store'), ['kind' => 'database']);
        $row = DB::table('backup_files')->latest('id')->first();
        $path = $this->backupPath().DIRECTORY_SEPARATOR.$row->filename;

        $this->actingAs($admin)->get(route('admin.ops.system.backups.download', $row->id))->assertOk();

        $this->actingAs($admin)->delete(route('admin.ops.system.backups.destroy', $row->id))
            ->assertRedirect();

        $this->assertFileDoesNotExist($path);
        $this->assertDatabaseMissing('backup_files', ['id' => $row->id]);
    }

    /** الاحتفاظ بآخر N نسخة — والإعداد يسري لحظيًّا لا في الدورة القادمة */
    public function test_only_the_configured_number_of_backups_is_kept(): void
    {
        $admin = $this->admin(self::OPERATOR);
        $this->set('backups.keep_count', 2);

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($admin)->post(route('admin.ops.system.backups.store'), ['kind' => 'database']);
        }

        $this->assertSame(2, DB::table('backup_files')->count());
    }

    /** ⭐ صحّة النظام تكشف امتدادًا ناقصًا بالاسم — لا برسالة عامّة */
    public function test_health_report_detects_a_missing_extension(): void
    {
        $admin = $this->admin(self::OPERATOR);
        $this->set('system.health.required_extensions', ['gd', 'ext_mesh_mawgoud']);

        $row = collect(app(SystemHealth::class)->report())->firstWhere('key', 'extensions');

        $this->assertSame('danger', $row['state']);
        $this->assertContains('ext_mesh_mawgoud', $row['missing']);

        $this->actingAs($admin)->get(route('admin.ops.system'))
            ->assertOk()
            ->assertSee('ext_mesh_mawgoud');
    }

    /** التنبيه الاستباقيّ يصل للأدمن كإشعار — لا ينتظر شكوى مستخدم */
    public function test_threshold_breach_notifies_admins_once_within_cooldown(): void
    {
        $admin = $this->admin(self::OPERATOR);

        // عتبة صفريّة ⟵ القرص «ممتلئ» حتمًا، فنختبر المسار لا الحظّ
        $this->set('backups.disk_alert_percent', 1);
        $this->set('system.health.alert_cooldown_minutes', 180);

        $this->actingAs($admin)->post(route('admin.ops.system.health'))->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'ops.system.health_checked']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ops.system.alert']);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $admin->id, 'category' => 'system']);

        $before = DB::table('app_notifications')->count();

        // نفس التنبيه مرّة تانية داخل التبريد لا يتكرّر — التنبيه المتكرّر يُهمَل
        $this->actingAs($admin)->post(route('admin.ops.system.health'));

        $this->assertSame($before, DB::table('app_notifications')->count());
    }

    public function test_schedule_settings_are_saved_and_audited(): void
    {
        $admin = $this->admin(self::OPERATOR);

        $this->actingAs($admin)->post(route('admin.ops.system.schedule'), [
            'enabled' => '1',
            'frequency' => 'weekly',
            'time' => '04:30',
            'kind' => 'full',
            'keep' => 5,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('weekly', (string) setting('backups.schedule.frequency'));
        $this->assertSame('04:30', (string) setting('backups.daily_time'));
        $this->assertSame(5, (int) setting('backups.keep_count'));

        $this->assertDatabaseHas('audit_logs', ['action' => 'ops.backups.schedule_updated']);
    }

    public function test_health_report_can_be_exported(): void
    {
        $admin = $this->admin(self::OPERATOR);

        $this->actingAs($admin)->get(route('admin.ops.system.health.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
