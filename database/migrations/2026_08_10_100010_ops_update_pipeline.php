<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * خطّ التحديث الآمن (2.11) — الجداول التي كان النظام يعمل بدونها.
 *
 * كان الترحيل سطرًا واحدًا: `migrate --force`. لا قفل يمنع تشغيلين، ولا فحص قبليّ،
 * ولا صيانة، ولا Checksum يعرف أنّ ملفّ هجرة تغيّر بعد تطبيقه، ولا أثر لما حدث
 * حين يفشل. وهذه الجداول الأربعة هي ذاكرة الخطّ الجديد:
 *
 *  1) `schema_migrations` (2.11-أ) — المعرّف + التوقيت + **Checksum** لكلّ هجرة مطبَّقة.
 *     جدول Laravel يحفظ الاسم والدفعة فقط، فلا يكشف ملفًّا عُدِّل بعد تطبيقه.
 *  2) `update_locks` (2.11-ب) — قفل يمنع تحديثين في آن واحد، بمهلة تُحرّره لو مات المشغّل.
 *  3) `update_runs` (2.11-ح · 2.17) — سجلّ كلّ تشغيل: المرحلة · الهجرة الفاشلة · السبب
 *     · هل استُعيدت النسخة · وماذا يفعل المالك.
 *  4) `migration_batch_state` (2.11-د) — مؤشّر الدفعات ليُستأنف الترحيل من حيث وقف
 *     بدل أن يبدأ من أوّله بعد انتهاء المهلة.
 *
 * وعمودان على `backup_files`: لقطة بيانات قابلة للاستعادة فعلًا + توقيت التحقّق —
 * لأنّ «نسخة احتياطيّة» بلا مسار يستعيدها ليست نسخةً بل ملفًّا على القرص (2.11-ج).
 */
return new class extends Migration
{
    /** [key, group, label_ar, type, default] — تُزرَع بقيمها الافتراضيّة بلا استبدال قيمة عدّلها المالك (2.11-و) */
    private const SETTINGS = [
        // ---------------- القفل والصيانة (2.11-ب)
        ['updates.lock_ttl_minutes', 'updates', 'مهلة قفل التحديث (دقائق)', 'number', '30'],
        ['updates.maintenance_enabled', 'updates', 'تفعيل وضع الصيانة أثناء الترحيل', 'bool', '1'],
        ['updates.maintenance_hours', 'updates', 'ساعات صيانة التحديث المتوقّعة', 'number', '1'],
        ['updates.maintenance_message', 'updates', 'رسالة صيانة التحديث', 'text', 'بنحدّث المنصّة دلوقتي — دقايق ونرجع.'],

        // ---------------- الفحوص القبليّة (2.11-ب)
        ['updates.preflight.min_php', 'updates', 'أقلّ إصدار PHP مقبول', 'string', '8.2'],
        ['updates.preflight.min_free_mb', 'updates', 'أقلّ مساحة قرص فاضية للتحديث (م.ب)', 'number', '512'],
        ['updates.preflight.backup_size_factor', 'updates', 'مضاعف حجم القاعدة المطلوب فاضيًا', 'number', '3'],
        ['updates.preflight.required_extensions', 'updates', 'امتدادات PHP المطلوبة للتحديث', 'json', '["pdo","json","zip"]'],
        ['updates.preflight.writable_paths', 'updates', 'مجلّدات لازم تكون قابلة للكتابة', 'json', '["storage/app","storage/logs","storage/framework","bootstrap/cache"]'],
        ['updates.preflight.verify_checksums', 'updates', 'فحص سلامة الهجرات المطبَّقة (Checksum)', 'bool', '1'],

        // ---------------- التحقّق بعد كلّ خطوة (2.11-هـ)
        ['updates.verify.after_each_step', 'updates', 'تحقّق بعد كلّ هجرة', 'bool', '1'],
        ['updates.verify.forbid_row_loss', 'updates', 'منع نقص صفوف أيّ جدول أثناء الترحيل', 'bool', '1'],
        ['updates.verify.check_relations', 'updates', 'فحص سلامة العلاقات بعد الترحيل', 'bool', '1'],
        ['updates.verify.relations_max_tables', 'updates', 'أقصى جداول يفحص علاقاتها التحقّق النهائيّ', 'number', '200'],

        // ---------------- الفشل والاستعادة (2.11-ح · 2.17)
        ['updates.restore_on_failure', 'updates', 'استعادة تلقائيّة من النسخة عند الفشل', 'bool', '1'],
        ['updates.restore_confirm_phrase', 'updates', 'عبارة تأكيد الاستعادة من نسخة', 'string', 'استعادة'],
        ['updates.failure_next_steps', 'updates', 'ماذا يفعل المالك بعد فشل التحديث', 'text', 'راجع سبب الفشل تحت، وابعته لمطوّر المنصّة مع اسم الهجرة. لو الاستعادة اشتغلت فالبيانات رجعت لحالتها قبل التحديث والمنصّة شغّالة عاديّ — متكرّرش التحديث قبل ما السبب يتصلّح.'],

        // ---------------- الإنهاء (2.11-ط)
        ['updates.seed_after_migrate', 'updates', 'زرع القيم الافتراضيّة الجديدة بعد الترحيل', 'bool', '1'],
        ['updates.seed_classes', 'updates', 'سيدرات مسار الإنتاج التي تُشغَّل بعد الترحيل', 'json', '["Database\\\\Seeders\\\\SettingSeeder"]'],
        ['updates.settings_rename_map', 'updates', 'خريطة إعادة تسمية مفاتيح الإعدادات (قديم ⟵ جديد)', 'json', '{}'],
        ['updates.auto_bump_segment', 'updates', 'الجزء الذي يرتفع تلقائيًّا بعد الترحيل', 'string', 'patch'],
        ['updates.target_version', 'updates', 'إصدار الوجهة (فاضي = ترقيم تلقائيّ)', 'string', ''],
        ['updates.clear_cache_after', 'updates', 'تفريغ الكاش بعد التحديث', 'bool', '1'],
        ['updates.notify_admin_result', 'updates', 'إشعار الأدمن بنتيجة التحديث', 'bool', '1'],
        ['updates.runs_per_page', 'updates', 'عدد تشغيلات التحديث المعروضة', 'number', '5'],
        ['updates.ledger_per_page', 'updates', 'عدد صفوف جدول schema_migrations', 'number', '15'],

        // ---------------- النسخة الاحتياطيّة (2.11-ج)
        ['backups.restore_snapshot', 'backups', 'حفظ لقطة بيانات قابلة للاستعادة مع كلّ نسخة', 'bool', '1'],
        ['backups.restore_skip_tables', 'backups', 'جداول لا تُمسّ عند الاستعادة (الأثر والسجلّ)', 'json', '["audit_logs","update_runs","update_locks","backup_files","migration_batch_state","schema_migrations","sessions","cache","cache_locks","jobs","failed_jobs","job_batches"]'],
        ['backups.verify_before_migrate', 'backups', 'التحقّق من سلامة النسخة قبل بدء الترحيل', 'bool', '1'],
    ];

    public function up(): void
    {
        // (2.11-أ) سجلّ الهجرات بالبصمة — بجانب جدول Laravel لا بدلًا منه،
        // فالمُهاجر يقرأ جدوله ونحن نقرأ بصماتنا.
        if (! Schema::hasTable('schema_migrations')) {
            Schema::create('schema_migrations', function (Blueprint $table) {
                $table->id();
                $table->string('migration')->unique();
                $table->unsignedInteger('batch')->default(0);
                $table->string('checksum', 64)->nullable();      // sha256 لمحتوى الملفّ
                $table->string('status', 16)->default('applied'); // applied · rolled_back · failed
                $table->unsignedInteger('duration_ms')->default(0);
                $table->timestamp('applied_at')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['status', 'applied_at']);
            });
        }

        // (2.11-ب) القفل: صفٌّ واحد باسم فريد — والمهلة تحرّره لو انقطع التيّار وسط التحديث
        if (! Schema::hasTable('update_locks')) {
            Schema::create('update_locks', function (Blueprint $table) {
                $table->id();
                $table->string('name', 64)->unique();
                $table->string('token', 64);
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('locked_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        // (2.11-ح · 2.17) تقرير التشغيل: ماذا حدث بالضبط وماذا يفعل المالك
        if (! Schema::hasTable('update_runs')) {
            Schema::create('update_runs', function (Blueprint $table) {
                $table->id();
                $table->string('from_version', 32)->nullable();
                $table->string('to_version', 32)->nullable();
                $table->string('status', 16)->default('running');  // running · success · failed
                $table->string('stage', 24)->default('preflight');  // preflight · backup · migrate · verify · seed · finish
                $table->json('migrations')->nullable();
                $table->string('failed_migration')->nullable();
                $table->text('error')->nullable();
                $table->boolean('restored')->default(false);
                $table->boolean('maintenance_lifted')->default(false);
                $table->json('report')->nullable();
                $table->foreignId('backup_file_id')->nullable()->constrained('backup_files')->nullOnDelete();
                $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'started_at']);
            });
        }

        // (2.11-د) مؤشّر الدفعات — بلا هذا الصفّ كلّ انقطاع يعني إعادة من الصفر
        if (! Schema::hasTable('migration_batch_state')) {
            Schema::create('migration_batch_state', function (Blueprint $table) {
                $table->id();
                $table->string('job')->unique();             // اسم المهمّة (هجرة/نقل بعينه)
                $table->string('source_table')->nullable();
                $table->string('target_table')->nullable();
                $table->unsignedBigInteger('cursor')->default(0);   // آخر مفتاح عولج بنجاح
                $table->unsignedBigInteger('processed')->default(0);
                $table->unsignedBigInteger('expected')->default(0);
                $table->string('status', 16)->default('running');   // running · done · verified · failed
                $table->string('checksum', 64)->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        // (2.11-ج) النسخة لا تُستعاد بلا لقطة بيانات ولا تُعتمَد بلا تحقّق
        if (Schema::hasTable('backup_files') && ! Schema::hasColumn('backup_files', 'data_file')) {
            Schema::table('backup_files', function (Blueprint $table) {
                $table->string('data_file')->nullable()->after('filename');
                $table->timestamp('verified_at')->nullable();
                $table->string('verify_note')->nullable();
            });
        }

        $this->seedSettings();
        $this->backfillLedger();
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_batch_state');
        Schema::dropIfExists('update_runs');
        Schema::dropIfExists('update_locks');
        Schema::dropIfExists('schema_migrations');

        if (Schema::hasTable('backup_files') && Schema::hasColumn('backup_files', 'data_file')) {
            Schema::table('backup_files', function (Blueprint $table) {
                $table->dropColumn(['data_file', 'verified_at', 'verify_note']);
            });
        }

        DB::table('settings')->whereIn('key', array_column(self::SETTINGS, 0))->delete();
    }

    /** زرع المفاتيح الجديدة بقيم افتراضيّة **دون استبدال القديمة** (2.11-و) */
    private function seedSettings(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $now = now();

        foreach (self::SETTINGS as [$key, $group, $label, $type, $default]) {
            DB::table('settings')->insertOrIgnore([
                'key' => $key,
                'group' => $group,
                'label_ar' => $label,
                'type' => $type,
                'value' => $default,
                'default_value' => $default,
                'is_sensitive' => false,
                'is_owner_only' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * التنصيبات القائمة طبّقت هجراتها قبل وجود هذا الجدول — فنملؤه من جدول Laravel
     * ونحسب بصمة كلّ ملفّ موجود. وما لا نجد ملفّه يُسجَّل بلا بصمة: لا نخترع بصمة
     * ولا نعتبر غيابها عطبًا (2.11-أ).
     */
    private function backfillLedger(): void
    {
        if (! Schema::hasTable('migrations')) {
            return;
        }

        $files = [];

        foreach ((array) glob(database_path('migrations').DIRECTORY_SEPARATOR.'*.php') as $path) {
            $files[basename((string) $path, '.php')] = (string) $path;
        }

        $now = now();

        foreach (DB::table('migrations')->orderBy('id')->get(['migration', 'batch']) as $row) {
            $name = (string) $row->migration;
            $path = $files[$name] ?? null;

            DB::table('schema_migrations')->insertOrIgnore([
                'migration' => $name,
                'batch' => (int) $row->batch,
                'checksum' => $path && is_file($path) ? hash_file('sha256', $path) : null,
                'status' => 'applied',
                'duration_ms' => 0,
                'applied_at' => $now,
                'note' => 'مسجَّلة أثناء ترقية سجلّ الهجرات.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
