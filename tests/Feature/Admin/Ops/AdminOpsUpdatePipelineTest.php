<?php

namespace Tests\Feature\Admin\Ops;

use App\Services\Admin\Ops\BackupManager;
use App\Services\Admin\Ops\BatchMigrator;
use App\Services\Admin\Ops\SchemaLedger;
use App\Services\Admin\Ops\UpdateLock;
use App\Services\Admin\System\MaintenanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * خطّ التحديث الآمن (2.11) — كلّ اختبار هنا يقابل بندًا منصوصًا لا شاشةً تفتح:
 *
 *  ب) قفل يمنع تشغيلين · فحوص قبليّة توقف عند نقص المساحة · وضع صيانة.
 *  د) دفعات قابلة للاستئناف · قاعدة عدم الفقد (نقل ⟵ تحقّق ⟵ حذف).
 *  ح) الفشل في المنتصف: استعادة + خروج من الصيانة + تقرير.
 *  ط) الإصدار يرتفع **بعد النجاح وحده**.
 */
class AdminOpsUpdatePipelineTest extends OpsTestCase
{
    private const MANAGER = ['updates.view', 'updates.manage', 'version_history.view', 'version_history.list', 'backups.create', 'backups.restore'];

    private const OK_PATH = 'tests/Feature/Admin/Ops/fixtures/migrations';

    private const FAILING_PATH = 'tests/Feature/Admin/Ops/fixtures/failing';

    // ------------------------------------------------------------------ ح) الفشل والاستعادة

    /**
     * ⭐ الاختبار الأهمّ: هجرة تسقط في نصّ الترحيل بعد ما مسحت بيانات ⟵
     * البيانات ترجع · الصيانة تترفع · التقرير يتكتب · الإصدار ما يرتفعش.
     */
    public function test_failure_midway_restores_data_lifts_maintenance_and_writes_a_report(): void
    {
        $admin = $this->admin(self::MANAGER);
        $this->set('updates.migrations_path', self::FAILING_PATH);

        $seeded = DB::table('app_version_history')->count();
        $this->assertGreaterThan(0, $seeded, 'لازم يكون في بيانات نفقدها عشان الاختبار يعني حاجة.');

        $this->actingAs($admin)->post(route('admin.ops.updates.dry-run'));
        $response = $this->actingAs($admin)->post(route('admin.ops.updates.migrate'), [
            'confirm' => 'تنفيذ',
            'understood' => '1',
        ])->assertRedirect();

        // (1) الهجرة الناجحة قبلها اتنزّلت — مافيش قاعدة نصف مرحَّلة
        $this->assertFalse(Schema::hasTable('ops_step_one'), 'الهجرة اللي نجحت فضلت مطبَّقة رغم فشل اللي بعدها.');

        // (2) البيانات اللي مسحتها الهجرة الفاشلة رجعت من النسخة الاحتياطيّة
        $this->assertSame($seeded, DB::table('app_version_history')->where('event', '!=', 'failed')->count(),
            'البيانات الممسوحة ما رجعتش — الاستعادة مشتغلتش.');

        // (3) المنصّة مش مقفولة على المستخدمين
        $this->assertFalse(app(MaintenanceService::class)->isActive(), 'المنصّة فضلت في وضع صيانة بعد فشل التحديث.');

        // (4) القفل اتفكّ فالتحديث الجاي ممكن
        $this->assertFalse(app(UpdateLock::class)->isLocked());

        // (5) تقرير يقول ماذا حدث وماذا يفعل المالك (2.17)
        $run = DB::table('update_runs')->orderByDesc('id')->first();
        $this->assertSame('failed', $run->status);
        $this->assertSame('2030_02_02_000000_ops_failing_step_two', $run->failed_migration);
        $this->assertTrue((bool) $run->restored);

        $report = json_decode((string) $run->report, true);
        $this->assertStringContainsString('2030_02_02_000000_ops_failing_step_two', $report['what']);
        $this->assertNotEmpty($report['next_steps']);
        $this->assertNotEmpty($report['actions']);

        // (6) الأثر باقٍ رغم الاستعادة — سجلّ التدقيق لا يُمحى بما يُصلِح الخطأ
        $this->assertDatabaseHas('audit_logs', ['action' => 'ops.updates.failed', 'user_id' => $admin->id]);

        // (7) الإصدار ما اتغيّرش
        $this->assertSame('1.0.0', (string) setting('updates.current_version'));

        $response->assertSessionHas('ops.failure');
    }

    /** تقرير الفشل يظهر في الشاشة نفسها بزرّ الاستعادة — لا في لوج على الخادم */
    public function test_failure_report_is_shown_on_screen(): void
    {
        $admin = $this->admin(self::MANAGER);
        $this->set('updates.migrations_path', self::FAILING_PATH);

        $this->actingAs($admin)->post(route('admin.ops.updates.dry-run'));
        $this->actingAs($admin)->post(route('admin.ops.updates.migrate'), ['confirm' => 'تنفيذ', 'understood' => '1']);

        $this->actingAs($admin)->get(route('admin.ops.updates'))
            ->assertOk()
            ->assertSee('التحديث وقف')
            ->assertSee('ماذا تفعل الآن؟');
    }

    // ------------------------------------------------------------------ ب) القفل

    /** قفل التحديث: تشغيلان في آنٍ واحد = قاعدة نصف مرحَّلة — فالثاني يُرفَض */
    public function test_lock_prevents_two_updates_at_once(): void
    {
        $admin = $this->admin(self::MANAGER);
        $this->set('updates.migrations_path', self::OK_PATH);

        // تشغيل آخر ماسك القفل بالفعل
        $token = app(UpdateLock::class)->acquire($this->makeUser('أدمن تاني'));
        $this->assertNotNull($token);

        $this->actingAs($admin)->post(route('admin.ops.updates.dry-run'));
        $this->actingAs($admin)->post(route('admin.ops.updates.migrate'), ['confirm' => 'تنفيذ', 'understood' => '1'])
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'تحديث شغّال'));

        $this->assertFalse(Schema::hasTable('ops_probe'), 'التحديث الثاني اشتغل رغم القفل.');
        $this->assertDatabaseCount('backup_files', 0);

        // وبعد فكّ القفل يشتغل عاديّ
        app(UpdateLock::class)->release($token);

        $this->actingAs($admin)->post(route('admin.ops.updates.migrate'), ['confirm' => 'تنفيذ', 'understood' => '1']);
        $this->assertTrue(Schema::hasTable('ops_probe'));
    }

    /** والقفل المنتهية مهلته ليس قفلًا — وإلّا بقيت المنصّة ممنوعة من التحديث للأبد */
    public function test_expired_lock_does_not_block_forever(): void
    {
        $lock = app(UpdateLock::class);
        $lock->acquire($this->makeUser('أدمن قديم'));

        DB::table('update_locks')->update(['expires_at' => now()->subMinute()]);

        $this->assertFalse($lock->isLocked());
        $this->assertNotNull($lock->acquire($this->makeUser('أدمن جديد')));
    }

    // ------------------------------------------------------------------ ب) الفحوص القبليّة

    /** نقص المساحة يوقف التحديث **قبل** أن يلمس نسخةً أو جدولًا */
    public function test_preflight_stops_the_update_when_disk_space_is_short(): void
    {
        $admin = $this->admin(self::MANAGER);
        $this->set('updates.migrations_path', self::OK_PATH);
        $this->set('updates.preflight.min_free_mb', 999999999);

        $this->actingAs($admin)->post(route('admin.ops.updates.dry-run'));
        $this->actingAs($admin)->post(route('admin.ops.updates.migrate'), ['confirm' => 'تنفيذ', 'understood' => '1'])
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'الفحوص القبليّة'));

        $this->assertFalse(Schema::hasTable('ops_probe'));
        $this->assertDatabaseCount('backup_files', 0);
        $this->assertFalse(app(MaintenanceService::class)->isActive());

        $run = DB::table('update_runs')->orderByDesc('id')->first();
        $this->assertSame('failed', $run->status);
        $this->assertSame('preflight', $run->stage);

        $this->actingAs($admin)->get(route('admin.ops.updates'))->assertOk()->assertSee('مساحة القرص');
    }

    /** والفحوص تُعرَض في الشاشة بعلامات ✓/✗ لا كرسالة عامّة */
    public function test_preflight_checks_are_listed_on_screen(): void
    {
        $admin = $this->admin(self::MANAGER);

        $this->actingAs($admin)->get(route('admin.ops.updates'))
            ->assertOk()
            ->assertSee('الفحوص القبليّة')
            ->assertSee('إصدار PHP')
            ->assertSee('مساحة القرص')
            ->assertSee('سلامة الإصدار الحاليّ')
            ->assertSee('قفل التحديث');

        $this->actingAs($admin)->post(route('admin.ops.updates.preflight'))
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'الفحوص القبليّة'));
    }

    // ------------------------------------------------------------------ ط) الإصدار والبصمة

    /** الإصدار يرتفع **بعد النجاح وحده** — وكان الكود يكتب الإصدار القديم مكانه */
    public function test_version_rises_only_after_a_successful_update(): void
    {
        $admin = $this->admin(self::MANAGER);
        $this->set('updates.migrations_path', self::OK_PATH);

        $this->assertSame('1.0.0', (string) setting('updates.current_version'));

        $this->actingAs($admin)->post(route('admin.ops.updates.dry-run'));
        $this->actingAs($admin)->post(route('admin.ops.updates.migrate'), ['confirm' => 'تنفيذ', 'understood' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame('1.0.1', (string) setting('updates.current_version'), 'الإصدار ما ارتفعش بعد ترحيل ناجح.');

        $this->assertDatabaseHas('app_version_history', [
            'event' => 'migrate',
            'version' => '1.0.1',
            'previous_version' => '1.0.0',
        ]);

        $run = DB::table('update_runs')->orderByDesc('id')->first();
        $this->assertSame('success', $run->status);
        $this->assertSame('1.0.1', $run->to_version);
    }

    /** وإصدار وجهة مضبوط في الإعدادات يُحترَم بدل الترقيم التلقائيّ */
    public function test_target_version_setting_wins_over_auto_bump(): void
    {
        $admin = $this->admin(self::MANAGER);
        $this->set('updates.migrations_path', self::OK_PATH);
        $this->set('updates.target_version', '2.0.0');

        $this->actingAs($admin)->post(route('admin.ops.updates.dry-run'));
        $this->actingAs($admin)->post(route('admin.ops.updates.migrate'), ['confirm' => 'تنفيذ', 'understood' => '1']);

        $this->assertSame('2.0.0', (string) setting('updates.current_version'));
    }

    /** كلّ هجرة تُسجَّل ببصمتها (2.11-أ) — والشاشة تعرض الجدول */
    public function test_applied_migrations_are_recorded_with_a_checksum(): void
    {
        $admin = $this->admin(self::MANAGER);
        $this->set('updates.migrations_path', self::OK_PATH);

        $this->actingAs($admin)->post(route('admin.ops.updates.dry-run'));
        $this->actingAs($admin)->post(route('admin.ops.updates.migrate'), ['confirm' => 'تنفيذ', 'understood' => '1']);

        $row = DB::table('schema_migrations')->where('migration', '2030_01_01_000000_create_ops_probe_table')->first();

        $this->assertNotNull($row, 'الهجرة ما اتسجّلتش في schema_migrations.');
        $this->assertSame('applied', $row->status);
        $this->assertSame(64, strlen((string) $row->checksum));

        $this->actingAs($admin)->get(route('admin.ops.updates'))->assertOk()->assertSee('سجلّ الهجرات ببصمتها');
    }

    /** وضع الصيانة يُفعَّل أثناء الترحيل ويُرفَع بعده — بالحارس الجاهز لا بحارس جديد */
    public function test_maintenance_is_used_and_lifted_after_a_successful_update(): void
    {
        $admin = $this->admin(self::MANAGER);
        $this->set('updates.migrations_path', self::OK_PATH);

        $this->actingAs($admin)->post(route('admin.ops.updates.dry-run'));
        $this->actingAs($admin)->post(route('admin.ops.updates.migrate'), ['confirm' => 'تنفيذ', 'understood' => '1']);

        // فترة صيانة واحدة اتفتحت واتقفلت — لا فترة مفتوحة ولا منصّة مقفولة
        $this->assertDatabaseCount('maintenance_windows', 1);
        $this->assertNotNull(DB::table('maintenance_windows')->latest('id')->first()->ended_at);
        $this->assertFalse(app(MaintenanceService::class)->isActive());
    }

    // ------------------------------------------------------------------ هـ) التحقّق بعد كلّ خطوة

    /**
     * ⭐ هجرة **تنجح** لكنّها تفقد صفوفًا ⟵ التحقّق يمسكها ويوقف كلّ شيء ويستعيد.
     * وهذه أخطر من هجرة تنفجر، لأنّها كانت تمرّ بصمت قبل 2.11-هـ.
     */
    public function test_silent_row_loss_is_caught_by_the_after_step_verification(): void
    {
        $admin = $this->admin(self::MANAGER);
        $this->set('updates.migrations_path', 'tests/Feature/Admin/Ops/fixtures/lossy');

        $seeded = DB::table('app_version_history')->count();

        $this->actingAs($admin)->post(route('admin.ops.updates.dry-run'));
        $this->actingAs($admin)->post(route('admin.ops.updates.migrate'), ['confirm' => 'تنفيذ', 'understood' => '1']);

        $run = DB::table('update_runs')->orderByDesc('id')->first();
        $this->assertSame('failed', $run->status);
        $this->assertSame('verify', $run->stage);
        $this->assertStringContainsString('app_version_history', (string) $run->error);

        // البيانات رجعت والإصدار ما ارتفعش والمنصّة مفتوحة
        $this->assertSame($seeded, DB::table('app_version_history')->where('event', '!=', 'failed')->count());
        $this->assertSame('1.0.0', (string) setting('updates.current_version'));
        $this->assertFalse(app(MaintenanceService::class)->isActive());
    }

    /** والتحقّق النهائيّ يفحص العلاقات فعلًا — لا يرجع «تمام» بلا ما يبصّ */
    public function test_final_verification_detects_broken_relations(): void
    {
        $ledger = app(SchemaLedger::class);

        $this->assertSame([], $ledger->verifyRelations());

        // صفّ يتيم: يشير لمستخدم غير موجود
        DB::statement('PRAGMA defer_foreign_keys = ON');
        DB::table('audit_logs')->insert([
            'user_id' => 999999,
            'action' => 'probe.orphan',
            'auditable_type' => 'probe',
            'auditable_id' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $problems = $ledger->verifyRelations();

        $this->assertNotEmpty($problems, 'التحقّق النهائيّ ما شافش صفًّا يتيمًا.');
        $this->assertStringContainsString('audit_logs.user_id', implode(' ', $problems));
    }

    // ------------------------------------------------------------------ د) الدفعات القابلة للاستئناف

    /** ⭐ الدفعات تُستأنف من حيث وقفت — والإعداد `updates.batch_rows` حيّ لا عَلَم ميّت */
    public function test_batched_migration_resumes_where_it_stopped(): void
    {
        $this->makeRowsTable('ops_batch_source', 7);
        $this->set('updates.batch_rows', 2);

        $batches = app(BatchMigrator::class);
        $this->assertSame(2, $batches->size());

        $seen = [];

        // الدفعة الرابعة تنفجر: عالجنا 4 صفوف ووقفنا
        try {
            $batches->each('probe.batch', 'ops_batch_source', function ($rows) use (&$seen) {
                if (count($seen) >= 4) {
                    throw new RuntimeException('انتهت المهلة في نصّ المعالجة.');
                }

                foreach ($rows as $row) {
                    $seen[] = (int) $row->id;
                }
            });
            $this->fail('المفروض المعالجة تقف.');
        } catch (RuntimeException) {
            // متوقَّع
        }

        $state = $batches->progress('probe.batch');
        $this->assertSame(4, (int) $state->cursor, 'المؤشّر مش عند آخر دفعة نجحت.');
        $this->assertSame(4, (int) $state->processed);

        // إعادة التشغيل: تبدأ من الصفّ الخامس لا من الأوّل
        $resumed = [];
        $result = $batches->each('probe.batch', 'ops_batch_source', function ($rows) use (&$resumed) {
            foreach ($rows as $row) {
                $resumed[] = (int) $row->id;
            }
        });

        $this->assertSame([5, 6, 7], $resumed, 'الاستئناف أعاد صفوفًا كانت اتعالجت خلاص.');
        $this->assertSame(4, $result['resumed_from']);
        $this->assertSame(7, (int) $batches->progress('probe.batch')->processed);
    }

    /** ⛔ قاعدة عدم الفقد: لا حذف قبل نقلٍ **وتحقّق** — والكود هو مَن يرفض */
    public function test_source_table_cannot_be_dropped_before_a_verified_move(): void
    {
        $this->makeRowsTable('ops_move_source', 5);
        $this->makeRowsTable('ops_move_target', 0);
        $this->set('updates.batch_rows', 2);

        $batches = app(BatchMigrator::class);

        // محاولة حذف قبل أيّ نقل — مرفوضة
        $refused = $batches->dropSource('probe.move', 'ops_move_source');
        $this->assertFalse($refused['dropped']);
        $this->assertTrue(Schema::hasTable('ops_move_source'));

        // نقل على دفعات ثمّ تحقّق ثمّ حذف
        $copied = $batches->copy('probe.move', 'ops_move_source', 'ops_move_target');
        $this->assertSame(5, $copied['copied']);

        $verified = $batches->verify('probe.move', 'ops_move_source', 'ops_move_target', ['id', 'note']);
        $this->assertTrue($verified['ok']);

        $dropped = $batches->dropSource('probe.move', 'ops_move_source');
        $this->assertTrue($dropped['dropped']);
        $this->assertFalse(Schema::hasTable('ops_move_source'));
        $this->assertSame(5, DB::table('ops_move_target')->count());
    }

    /** ونقلٌ ناقص لا يُعتبَر نقلًا: التحقّق يرفض والحذف يُمنَع */
    public function test_incomplete_move_is_refused_by_verification(): void
    {
        $this->makeRowsTable('ops_partial_source', 4);
        $this->makeRowsTable('ops_partial_target', 0);

        $batches = app(BatchMigrator::class);
        DB::table('ops_partial_target')->insert(['id' => 1, 'note' => 'صفّ واحد بس']);

        $verified = $batches->verify('probe.partial', 'ops_partial_source', 'ops_partial_target');

        $this->assertFalse($verified['ok']);
        $this->assertFalse($batches->dropSource('probe.partial', 'ops_partial_source')['dropped']);
        $this->assertTrue(Schema::hasTable('ops_partial_source'));
    }

    // ------------------------------------------------------------------ ج) النسخة والاستعادة

    /** نسخة بلا لقطة بيانات ليست نسخةً يُستعاد منها — والتحقّق يقولها بصراحة */
    public function test_backup_verification_refuses_a_backup_without_a_snapshot(): void
    {
        $admin = $this->admin(self::MANAGER);

        $this->actingAs($admin)->post(route('admin.ops.system.backups.store'), ['kind' => 'database']);

        $backup = DB::table('backup_files')->orderByDesc('id')->first();
        $manager = app(BackupManager::class);

        $this->assertTrue($manager->verify((int) $backup->id)['ok']);

        @unlink($manager->directory().DIRECTORY_SEPARATOR.$backup->data_file);
        $this->assertFalse($manager->verify((int) $backup->id)['ok']);
    }

    // ------------------------------------------------------------------ أدوات

    /** جدول صفوف للتجربة — الدفعات تحتاج بيانات حقيقيّة لا افتراضًا */
    private function makeRowsTable(string $name, int $rows): void
    {
        Schema::create($name, function (Blueprint $table) {
            $table->id();
            $table->string('note')->nullable();
        });

        foreach (range(1, max(0, $rows)) as $index) {
            if ($rows === 0) {
                break;
            }

            DB::table($name)->insert(['id' => $index, 'note' => 'صفّ رقم '.$index]);
        }
    }
}
