<?php

namespace Tests\Feature\Admin\Ops;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * التحديثات والترحيل (12.7-هـ): **نقرة آمنة + Dry-run + استرجاع**.
 * والقاعدة المُختبَرة هنا قبل كلّ شيء: **ممنوع أيّ تنفيذ بلا تأكيد**.
 */
class AdminOpsUpdatesTest extends OpsTestCase
{
    private const MANAGER = ['updates.view', 'updates.manage', 'version_history.view', 'version_history.list'];

    protected function setUp(): void
    {
        parent::setUp();

        // هجرة تجريبيّة معلّقة خارج مسار التنصيب — فـ«المعلّق» حالة حقيقيّة لا مفترضة
        $this->set('updates.migrations_path', 'tests/Feature/Admin/Ops/fixtures/migrations');
    }

    public function test_pending_migrations_are_listed_by_name(): void
    {
        $admin = $this->admin(self::MANAGER);

        $this->actingAs($admin)->get(route('admin.ops.updates'))
            ->assertOk()
            ->assertSee('2030_01_01_000000_create_ops_probe_table');
    }

    public function test_version_history_tab_loads_and_exports(): void
    {
        $admin = $this->admin(array_merge(self::MANAGER, ['version_history.export']));

        $this->actingAs($admin)->get(route('admin.ops.updates.history'))
            ->assertOk()
            ->assertSee('سجلّ الإصدارات')
            ->assertSee('1.0.0');

        $this->actingAs($admin)->get(route('admin.ops.updates.history.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    /** ⛔ الحاجز الأوّل: بلا كلمة تأكيد وبلا إقرار — لا يتحرّك حرف في قاعدة البيانات */
    public function test_migration_never_runs_without_confirmation(): void
    {
        $admin = $this->admin(self::MANAGER);

        $this->actingAs($admin)->post(route('admin.ops.updates.migrate'), [])
            ->assertSessionHasErrors(['confirm', 'understood']);

        $this->assertFalse(Schema::hasTable('ops_probe'), 'الهجرة اتنفّذت بلا تأكيد — ده كسر للقاعدة.');
        $this->assertDatabaseCount('app_version_history', 1); // صفّ السيدر وحده
    }

    /** وكلمة تأكيد غلط ليست تأكيدًا — والإقرار وحده لا يكفي */
    public function test_wrong_confirmation_phrase_is_rejected(): void
    {
        $admin = $this->admin(self::MANAGER);

        $this->actingAs($admin)->post(route('admin.ops.updates.migrate'), [
            'confirm' => 'يلا بينا',
            'understood' => '1',
        ])->assertSessionHasErrors('confirm');

        $this->assertFalse(Schema::hasTable('ops_probe'));
    }

    /** ⭐ Dry-run يعرض ما سيُنفَّذ **ولا يغيّر شيئًا** */
    public function test_dry_run_changes_nothing(): void
    {
        $admin = $this->admin(self::MANAGER);
        $migrationsBefore = DB::table('migrations')->count();

        $this->actingAs($admin)->post(route('admin.ops.updates.dry-run'))->assertRedirect();

        $this->assertFalse(Schema::hasTable('ops_probe'), 'الـDry-run نفّذ الهجرة فعلًا — وده عكس معناه.');
        $this->assertSame($migrationsBefore, DB::table('migrations')->count());

        // ومع ذلك يُسجَّل: مَن عاين ومتى
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ops.updates.dry_run',
            'user_id' => $admin->id,
        ]);
    }

    /** التنفيذ لا يمرّ إلّا بعد معاينة سارية — والإعداد هو مَن يقرّر ذلك */
    public function test_execution_is_blocked_until_a_dry_run_happened(): void
    {
        $admin = $this->admin(self::MANAGER);

        $this->actingAs($admin)->post(route('admin.ops.updates.migrate'), [
            'confirm' => 'تنفيذ',
            'understood' => '1',
        ])->assertSessionHasErrors('confirm');

        $this->assertFalse(Schema::hasTable('ops_probe'));
    }

    /** المسار الكامل: معاينة ⟵ نسخة احتياطيّة ⟵ تنفيذ ⟵ تسجيل */
    public function test_double_confirmed_migration_runs_and_is_recorded(): void
    {
        $admin = $this->admin(array_merge(self::MANAGER, ['backups.create']));

        $this->actingAs($admin)->post(route('admin.ops.updates.dry-run'));

        $this->actingAs($admin)->post(route('admin.ops.updates.migrate'), [
            'confirm' => 'تنفيذ',
            'understood' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Schema::hasTable('ops_probe'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ops.updates.migrated',
            'user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('app_version_history', [
            'event' => 'migrate',
            'performed_by' => $admin->id,
        ]);

        // نسخة احتياطيّة إلزاميّة قبل الترحيل (12.7-و)
        $this->assertDatabaseHas('backup_files', ['kind' => 'database', 'status' => 'done']);
    }

    /** الاسترجاع كذلك: بلا تأكيد لا يحدث شيء، وبتأكيد يشيل آخر دفعة */
    public function test_rollback_requires_confirmation_then_removes_last_batch(): void
    {
        $admin = $this->admin(array_merge(self::MANAGER, ['updates.restore', 'backups.create']));

        $this->actingAs($admin)->post(route('admin.ops.updates.dry-run'));
        $this->actingAs($admin)->post(route('admin.ops.updates.migrate'), [
            'confirm' => 'تنفيذ',
            'understood' => '1',
        ]);

        $this->assertTrue(Schema::hasTable('ops_probe'));

        $this->actingAs($admin)->post(route('admin.ops.updates.rollback'), [])
            ->assertSessionHasErrors('confirm');
        $this->assertTrue(Schema::hasTable('ops_probe'), 'الاسترجاع اشتغل بلا تأكيد.');

        $this->actingAs($admin)->post(route('admin.ops.updates.rollback'), [
            'confirm' => 'استرجاع',
            'understood' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse(Schema::hasTable('ops_probe'));
        $this->assertDatabaseHas('app_version_history', ['event' => 'rollback']);
    }

    /** التحذير بما سيُفقَد معروض بالاسم قبل أيّ زرّ استرجاع */
    public function test_rollback_screen_names_what_will_be_lost(): void
    {
        $admin = $this->admin(array_merge(self::MANAGER, ['updates.restore', 'backups.create']));

        $this->actingAs($admin)->post(route('admin.ops.updates.dry-run'));
        $this->actingAs($admin)->post(route('admin.ops.updates.migrate'), [
            'confirm' => 'تنفيذ',
            'understood' => '1',
        ]);

        $this->actingAs($admin)->get(route('admin.ops.updates'))
            ->assertOk()
            ->assertSee('2030_01_01_000000_create_ops_probe_table')
            ->assertSee('هتروح ولا ترجع', false);
    }

    /** الاتّجاه أمامًا فقط — لا رجوع لإصدار أقدم (2.11) */
    public function test_version_history_refuses_going_backwards(): void
    {
        $admin = $this->admin(self::MANAGER);

        $this->actingAs($admin)->post(route('admin.ops.updates.version'), ['version' => '0.9.0'])
            ->assertSessionHasErrors('version');

        $this->actingAs($admin)->post(route('admin.ops.updates.version'), [
            'version' => '1.1.0',
            'notes' => 'تحسينات صغيرة.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('app_version_history', ['version' => '1.1.0', 'event' => 'release']);
        $this->assertSame('1.1.0', (string) setting('updates.current_version'));
    }
}
