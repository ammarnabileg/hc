<?php

namespace App\Services\Admin\Ops;

use App\Models\User;
use App\Services\Admin\System\MaintenanceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * التحديثات والترحيل (12.7-هـ · 2.11): **نقرة آمنة + Dry-run + استرجاع**.
 *
 * القواعد التي يفرضها هذا الصنف بالكود لا بالنصيحة:
 *  1) **لا تنفيذ بلا تأكيد** — والتأكيد مزدوج: عبارة مكتوبة + إقرار صريح.
 *  2) **Dry-run إلزاميّ قبل التنفيذ** ما دام الإعداد مفعَّلًا، وصلاحيّته دقائق معدودة
 *     ومربوطة بنفس قائمة الهجرات المعلّقة — فلو تغيّرت القائمة سقط التصريح.
 *  3) **كلّ عمليّة تُسجَّل** بمن نفّذها ومتى، وتُقيَّد في سجلّ الإصدارات.
 *  4) **الاسترجاع يعرض ما سيُفقَد بالاسم** قبل أن يلمس شيئًا.
 *
 * ⭐ وخطّ التنفيذ نفسه (`run`) يمشي بالترتيب الذي فرضه 2.11 ولا يقفز خطوة:
 *    قفل ⟵ فحوص قبليّة ⟵ صيانة ⟵ نسخة احتياطيّة متحقَّق منها ⟵ هجرة هجرة مع
 *    تحقّق بعد كلّ خطوة ⟵ تحقّق نهائيّ ⟵ بذور افتراضيّة ⟵ رفع الإصدار وتفريغ
 *    الكاش ⟵ خروج من الصيانة. وأيّ تعثّر في أيّ نقطة: **إيقاف فوريّ + استعادة
 *    + خروج من الصيانة + تقرير يقول ماذا حدث وماذا يفعل المالك** (2.11-ح · 2.17).
 */
class UpdateManager
{
    public function __construct(
        private readonly OpsAudit $audit,
        private readonly BackupManager $backups,
        private readonly MaintenanceService $maintenance,
        private readonly UpdateLock $lock,
        private readonly UpdatePreflight $preflight,
        private readonly SchemaLedger $ledger,
        private readonly BatchMigrator $batches,
    ) {}

    // ------------------------------------------------------------------ قراءة

    public function currentVersion(): string
    {
        return (string) setting('updates.current_version', '1.0.0');
    }

    /**
     * مسارات ملفّات الهجرات — إعداد لا مسار محروق، ليعمل الترحيل على تنصيب
     * فيه حزم إضافيّة بمساراتها الخاصّة (2.13).
     *
     * @return array<int, string>
     */
    public function paths(): array
    {
        $extra = trim((string) setting('updates.migrations_path', ''));

        return array_values(array_unique(array_filter(['database/migrations', $extra])));
    }

    /**
     * الهجرات المعلّقة **بالاسم** — لأنّ رقمًا مجرّدًا لا يُبنى عليه قرار.
     *
     * @return array<int, string>
     */
    public function pending(): array
    {
        return array_values(array_keys($this->pendingFiles()));
    }

    /**
     * الهجرات المعلّقة **باسمها ومسار ملفّها** — لأنّ التنفيذ صار هجرةً هجرة،
     * ولكلّ ملفّ بصمة تُسجَّل بعد تطبيقه (2.11-أ).
     *
     * @return array<string, string>
     */
    public function pendingFiles(): array
    {
        $migrator = app('migrator');
        $files = $migrator->getMigrationFiles(array_map(fn ($p) => base_path($p), $this->paths()));
        $ran = $migrator->getRepository()->repositoryExists() ? $migrator->getRepository()->getRan() : [];

        return array_diff_key($files, array_flip($ran));
    }

    /** مسارات الملفّات المطلقة — يقرؤها فحص البصمات ليقارن ملفًّا بملفّ */
    public function absolutePaths(): array
    {
        return array_map(fn ($p) => base_path($p), $this->paths());
    }

    /**
     * آخر دفعة مطبَّقة — وهي بالضبط ما سيختفي عند الاسترجاع.
     *
     * @return array<int, string>
     */
    public function lastBatch(): array
    {
        $repository = app('migrator')->getRepository();

        if (! $repository->repositoryExists()) {
            return [];
        }

        return array_map(
            fn ($row) => is_object($row) ? (string) $row->migration : (string) $row,
            $repository->getLast(),
        );
    }

    public function appliedCount(): int
    {
        $repository = app('migrator')->getRepository();

        return $repository->repositoryExists() ? count($repository->getRan()) : 0;
    }

    /** الفحوص القبليّة كما تُعرَض في الشاشة — قراءة بلا أثر (2.11-ب) */
    public function preflightChecks(): array
    {
        return $this->preflight->checks($this->absolutePaths());
    }

    public function preflightPasses(array $checks): bool
    {
        return $this->preflight->passes($checks);
    }

    public function lockState(): ?object
    {
        return $this->lock->current();
    }

    /** جدول `schema_migrations` بالبصمة — كما يطلبه 12.7-هـ حرفيًّا */
    public function ledgerRows(int $perPage): LengthAwarePaginator
    {
        return $this->ledger->rows($perPage);
    }

    /**
     * استعادة يدويّة من نسخة (زرّ «استرجاع من النسخة الاحتياطيّة» بعد الفشل).
     * الاستعادة نفسها في `BackupManager` — وهنا نضيف أثرها في سجلّ الإصدارات.
     */
    public function restoreFromBackup(int $backupId, ?User $actor): array
    {
        $result = $this->backups->restore($backupId, $actor, setting('updates.update_manager.restore_from_backup_1', 'استعادة يدويّة من شاشة التحديثات'));

        if (! $result['ok']) {
            return $result;
        }

        $historyId = (int) DB::table('app_version_history')->insertGetId([
            'version' => $this->currentVersion(),
            'previous_version' => $this->currentVersion(),
            'event' => 'restore',
            'notes' => strtr(setting('updates.update_manager.restore_from_backup_2', 'استعادة من نسخة احتياطيّة — :p1 جدول و:p2 صفّ.'), [':p1' => (string) ($result['tables']), ':p2' => (string) ($result['rows'])]),
            'migrations_count' => 0,
            'migrations' => null,
            'backup_file_id' => $backupId,
            'performed_by' => $actor?->id,
            'performed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit->record($actor, 'ops.updates.restored', [
            'backup_file_id' => $backupId,
            'tables' => $result['tables'],
            'rows' => $result['rows'],
        ], 'ops.updates', $historyId);

        return $result;
    }

    /** بصمة قائمة الهجرات المعلّقة — بها نربط تصريحَ الـDry-run بما فُحِص فعلًا */
    public function pendingSignature(?array $pending = null): string
    {
        return substr(hash('sha256', implode('|', $pending ?? $this->pending())), 0, 32);
    }

    public function history(int $perPage, string $pageName = 'page'): LengthAwarePaginator
    {
        return DB::table('app_version_history')
            ->leftJoin('users', 'users.id', '=', 'app_version_history.performed_by')
            ->orderByDesc('app_version_history.id')
            ->select([
                'app_version_history.*',
                'users.name as performer_name',
                'users.code as performer_code',
            ])
            ->paginate(max(1, $perPage), ['*'], $pageName);
    }

    /** كلّ سجلّ الإصدارات بلا ترقيم — للتصدير وحده */
    public function allHistory(): array
    {
        return DB::table('app_version_history')
            ->leftJoin('users', 'users.id', '=', 'app_version_history.performed_by')
            ->orderByDesc('app_version_history.id')
            ->select(['app_version_history.*', 'users.name as performer_name'])
            ->get()
            ->all();
    }

    /**
     * هل يملك هذا الأدمن تصريح Dry-run ساريًا لنفس القائمة المعلّقة؟
     * ولو الإعداد مطفأ فالتصريح غير مطلوب أصلًا — القرار للمالك لا للكود (2.13).
     */
    public function hasFreshDryRun(?User $actor, array $pending): bool
    {
        if (! setting('updates.dry_run_required', true)) {
            return true;
        }

        if (! $actor) {
            return false;
        }

        $minutes = (int) setting('updates.dry_run_valid_minutes', 30);
        $signature = $this->pendingSignature($pending);

        return DB::table('audit_logs')
            ->where('action', 'ops.updates.dry_run')
            ->where('user_id', $actor->id)
            ->where('created_at', '>=', now()->subMinutes(max(1, $minutes)))
            ->get(['new_values'])
            ->contains(function ($row) use ($signature) {
                $data = json_decode((string) $row->new_values, true);

                return is_array($data) && ($data['signature'] ?? null) === $signature;
            });
    }

    // ------------------------------------------------------------------ تنفيذ

    /**
     * **Dry-run**: يعرض ما سيُنفَّذ بالضبط **بلا تنفيذ** — `--pretend` تطبع الجمل
     * ولا تلمس قاعدة البيانات، والتسجيل هنا هو ما يفتح بوّابة التنفيذ لاحقًا.
     *
     * @return array{pending:array<int,string>, output:string, signature:string}
     */
    public function dryRun(?User $actor): array
    {
        $pending = $this->pending();
        $signature = $this->pendingSignature($pending);
        $output = '';

        if ($pending !== []) {
            try {
                Artisan::call('migrate', [
                    '--pretend' => true,
                    '--force' => true,
                    '--path' => $this->paths(),
                ]);

                $output = trim(Artisan::output());
            } catch (Throwable $e) {
                // معاينةٌ تنفجر خبرٌ مفيد لا شاشةَ خطأ: الهجرة دي هتقع في التنفيذ
                // كمان — فنعرضها للمالك بلغته بدل 500 (2.17).
                $output = setting('updates.update_manager.dry_run_1', 'المعاينة وقفت على خطأ في الكود مش في قاعدة البيانات:\\n').$e->getMessage()
                    .setting('updates.update_manager.dry_run_2', '\\nصلّح الهجرة الأوّل — التنفيذ من غير كده هيقف في نصّه.');
            }
        }

        $this->audit->record($actor, 'ops.updates.dry_run', [
            'pending' => count($pending),
            'signature' => $signature,
            'migrations' => $pending,
        ], 'ops.updates');

        return [
            'pending' => $pending,
            'output' => $output !== '' ? $output : setting('updates.update_manager.dry_run_3', 'مافيش هجرات معلّقة — مفيش جملة واحدة هتتنفّذ.'),
            'signature' => $signature,
        ];
    }

    /**
     * ⭐ **خطّ التحديث الكامل** (2.11). لا يُستدعى إلّا بعد تأكيد مزدوج في الكنترولر.
     *
     * لماذا هجرة هجرة لا `migrate` واحدة؟ لأنّ «الفشل في منتصف الترحيل» هو الحالة
     * التي وُضِع 2.11 كلّه لأجلها: نحتاج أن نعرف **أيّ هجرة** سقطت، وأن نتحقّق
     * **بعد كلّ خطوة** لا بعد الكلّ، وأن نسجّل بصمة كلّ ملفّ طُبِّق.
     *
     * @return array{ok:bool, stage:string, ran:array<int,string>, from:string, to:string, message:string, report:array, run_id:int, output:string}
     */
    public function run(?User $actor): array
    {
        $before = $this->currentVersion();
        $pendingFiles = $this->pendingFiles();

        if ($pendingFiles === []) {
            return $this->result(true, 'idle', [], $before, $before, setting('updates.update_manager.run_1', 'مافيش هجرات معلّقة — مالناش شغل هنا.'), [], 0, '');
        }

        // 1) القفل قبل أيّ شيء — ولو كان محجوزًا فلا صيانة ولا نسخة ولا لمسة (2.11-ب)
        $token = $this->lock->acquire($actor);

        if ($token === null) {
            $holder = $this->lock->current();

            return $this->result(false, 'lock', [], $before, $before,
                setting('updates.update_manager.run_2', 'في تحديث شغّال دلوقتي').($holder?->holder_name ? strtr(setting('updates.update_manager.run_3', ' بدأه :p1'), [':p1' => (string) ($holder->holder_name)]) : '').setting('updates.update_manager.run_4', ' — استنّاه يخلص.'),
                $this->report('lock', null, 'تحديث تاني ماسك القفل.', [], null, false, false), 0, '');
        }

        $runId = (int) DB::table('update_runs')->insertGetId([
            'from_version' => $before,
            'status' => 'running',
            'stage' => 'preflight',
            'migrations' => json_encode(array_keys($pendingFiles), JSON_UNESCAPED_UNICODE),
            'performed_by' => $actor?->id,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ran = [];
        $output = [];
        $backupId = null;
        $startedMaintenance = false;
        $stage = 'preflight';
        $current = null;

        try {
            // 2) الفحوص القبليّة — فحصٌ فاشل يوقف كلّ شيء (2.11-ب)
            $checks = $this->preflight->checks($this->absolutePaths(), $token);

            if (! $this->preflight->passes($checks)) {
                throw new UpdateHalt('preflight', strtr(setting('updates.update_manager.run_5', 'الفحوص القبليّة وقفت التحديث: :p1'), [':p1' => (string) (implode(' · ', $this->preflight->failures($checks)))]));
            }

            // 3) وضع الصيانة — عشان محدّش يكتب في جدول بيتحرّك تحت رجليه (2.11-ب)
            $stage = 'maintenance';
            $this->stage($runId, $stage);

            if (setting('updates.maintenance_enabled', true) && $actor && ! $this->maintenance->isActive()) {
                $this->maintenance->start(
                    $actor,
                    (string) setting('updates.maintenance_message', 'بنحدّث المنصّة دلوقتي — دقايق ونرجع.'),
                    max(1, (int) setting('updates.maintenance_hours', 1)),
                );
                $startedMaintenance = true;
            }

            // 4) نسخة احتياطيّة إلزاميّة **متحقَّق منها** (2.11-ج)
            $stage = 'backup';
            $this->stage($runId, $stage);

            if (setting('updates.backup_before_migrate', true)) {
                $backup = $this->backups->create('database', $actor);

                if (! $backup['ok']) {
                    throw new UpdateHalt('backup', strtr(setting('updates.update_manager.run_6', 'مقدرناش ناخد نسخة احتياطيّة قبل الترحيل — :p1'), [':p1' => (string) ($backup['message'])]));
                }

                $backupId = $backup['id'];
                DB::table('update_runs')->where('id', $runId)->update(['backup_file_id' => $backupId, 'updated_at' => now()]);

                if (setting('backups.verify_before_migrate', true)) {
                    $verified = $this->backups->verify($backupId);

                    if (! $verified['ok']) {
                        throw new UpdateHalt('backup', strtr(setting('updates.update_manager.run_7', 'النسخة الاحتياطيّة مش صالحة — :p1'), [':p1' => (string) ($verified['message'])]));
                    }
                }
            }

            // 5) الهجرات: واحدة واحدة، ببصمة وتحقّق بعد كلّ خطوة (2.11-د · هـ)
            $stage = 'migrate';
            $this->stage($runId, $stage);

            $repository = app('migrator')->getRepository();
            $batch = $repository->repositoryExists() ? (int) $repository->getNextBatchNumber() : 1;

            foreach ($pendingFiles as $name => $file) {
                $current = $name; // ⭐ الهجرة الجارية بالاسم — عشان التقرير يقول أين وقعنا بالضبط
                $countsBefore = setting('updates.verify.after_each_step', true) ? $this->ledger->snapshotCounts() : [];
                $startedAt = microtime(true);

                Artisan::call('migrate', [
                    '--force' => true,
                    '--path' => [$file],
                    '--realpath' => true,
                ]);

                $output[] = trim(Artisan::output());
                $ran[] = $name;

                $this->ledger->record($name, $batch, $file, (int) round((microtime(true) - $startedAt) * 1000));

                if ($countsBefore !== []) {
                    $problems = $this->ledger->verifyStep($countsBefore, $this->ledger->snapshotCounts(), $this->batches->verifiedSources());

                    if ($problems !== []) {
                        throw new UpdateHalt('verify', strtr(setting('updates.update_manager.run_8', 'التحقّق بعد «:p1» لقى فقد بيانات: :p2'), [':p1' => (string) ($name), ':p2' => (string) (implode(' · ', $problems))]), $name);
                    }
                }
            }

            // دفعة واحدة لكلّ التحديث — فالاسترجاع يرجّع التحديث كلّه لا آخر هجرة فيه
            DB::table('migrations')->whereIn('migration', $ran)->update(['batch' => $batch]);
            $this->ledger->setBatch($ran, $batch);

            // 6) التحقّق النهائيّ الشامل (2.11-هـ)
            $stage = 'verify';
            $this->stage($runId, $stage);
            $relations = $this->ledger->verifyRelations();

            if ($relations !== []) {
                throw new UpdateHalt('verify', strtr(setting('updates.update_manager.run_9', 'التحقّق النهائيّ لقى علاقات مكسورة: :p1'), [':p1' => (string) (implode(' · ', array_slice($relations, 0, 5)))]));
            }

            // 7) بذور القيم الافتراضيّة الجديدة + خريطة إعادة التسمية (2.11-و · ز)
            $stage = 'seed';
            $this->stage($runId, $stage);
            $seeded = $this->seedDefaults();

            // 8) رفع الإصدار وتفريغ الكاش (2.11-ط) — **بعد النجاح وحده**
            $stage = 'finish';
            $this->stage($runId, $stage);
            $after = $this->bumpVersion($before);
            $this->clearCaches();
        } catch (Throwable $e) {
            return $this->fail($e, $actor, $runId, $token, $ran, $before, $backupId, $startedMaintenance, $stage, $output, $current);
        }

        // 9) الخروج من الصيانة وفكّ القفل
        if ($startedMaintenance && $actor) {
            $this->maintenance->lift($actor);
        }

        $this->lock->release($token);

        $historyId = (int) DB::table('app_version_history')->insertGetId([
            'version' => $after,
            'previous_version' => $before,
            'event' => 'migrate',
            'notes' => strtr(setting('updates.update_manager.run_10', 'تنفيذ :p1 هجرة معلّقة بنجاح.'), [':p1' => (string) (count($ran))]),
            'migrations_count' => count($ran),
            'migrations' => json_encode($ran, JSON_UNESCAPED_UNICODE),
            'backup_file_id' => $backupId,
            'performed_by' => $actor?->id,
            'performed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $summary = [
            'from' => $before,
            'to' => $after,
            'migrations' => $ran,
            'backup' => $backupId,
            'seeded' => $seeded,
        ];

        DB::table('update_runs')->where('id', $runId)->update([
            'status' => 'success',
            'stage' => 'finish',
            'to_version' => $after,
            'migrations' => json_encode($ran, JSON_UNESCAPED_UNICODE),
            'maintenance_lifted' => $startedMaintenance,
            'report' => json_encode($summary, JSON_UNESCAPED_UNICODE),
            'finished_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit->record($actor, 'ops.updates.migrated', [
            'count' => count($ran),
            'migrations' => $ran,
            'backup_file_id' => $backupId,
            'from' => $before,
            'to' => $after,
        ], 'ops.updates', $historyId);

        $this->notify($actor, strtr(setting('updates.update_manager.run_11', 'التحديث تمّ ✓ — من :p1 لـ:p2 بـ'), [':p1' => (string) ($before), ':p2' => (string) ($after)]).count($ran).setting('updates.update_manager.body_1', ' هجرة.'));

        return $this->result(true, 'finish', $ran, $before, $after,
            setting('updates.update_manager.body_2', 'اتنفّذت ').count($ran).strtr(setting('updates.update_manager.body_3', ' هجرة ✓ — الإصدار بقى :p1، والنسخة الاحتياطيّة محفوظة قبلها.'), [':p1' => (string) ($after)]),
            $summary, $runId, implode("\n", $output));
    }

    /**
     * الفشل (2.11-ح): **إيقاف فوريّ + تسجيل دقيق + استعادة + خروج من الصيانة + تقرير**.
     *
     * والترتيب مقصود: نُنزِل ما طُبِّق (`down`) أوّلًا لترجع الصورة الهيكليّة، ثمّ
     * نستعيد البيانات من النسخة. ولا نعتمد على الـRollback وحده لأنّ أوامر DDL
     * في MySQL خارج المعاملات أصلًا (2.11-د).
     */
    private function fail(
        Throwable $e,
        ?User $actor,
        int $runId,
        string $token,
        array $ran,
        string $before,
        ?int $backupId,
        bool $startedMaintenance,
        string $stage,
        array $output,
        ?string $current = null,
    ): array {
        $stage = $e instanceof UpdateHalt ? $e->stage : $stage;
        $failedMigration = ($e instanceof UpdateHalt ? $e->migration : null) ?? $current;

        $actions = [];

        // (أ) إنزال ما طُبِّق في هذا التشغيل — بترتيب معكوس وبلا أن يمنع فشلٌ منه الاستعادة
        if ($ran !== []) {
            $downed = $this->rollbackApplied($ran);
            $actions[] = $downed === []
                ? setting('updates.update_manager.fail_1', 'مقدرناش نرجّع الهجرات المطبَّقة — الاعتماد على الاستعادة.')
                : strtr(setting('updates.update_manager.fail_2', 'رجّعنا :p1 هجرة كانت اتطبّقت في التشغيل ده.'), [':p1' => (string) (count($downed))]);
        }

        // (ب) الاستعادة من النسخة — وهي شبكة الأمان الحقيقيّة
        $restored = false;

        if ($backupId && setting('updates.restore_on_failure', true)) {
            try {
                $result = $this->backups->restore($backupId, $actor, setting('updates.update_manager.fail_3', 'استعادة تلقائيّة بعد فشل تحديث'));
                $restored = (bool) $result['ok'];
                $actions[] = $result['message'];
            } catch (Throwable $restoreError) {
                $actions[] = strtr(setting('updates.update_manager.fail_4', 'الاستعادة نفسها فشلت — :p1'), [':p1' => (string) ($restoreError->getMessage())]);
            }
        } elseif (! $backupId) {
            $actions[] = setting('updates.update_manager.fail_5', 'مفيش نسخة احتياطيّة في التشغيل ده — التوقّف حصل قبل ما تتاخد.');
        }

        // (ج) الخروج من الصيانة — المنصّة لا تُترَك مقفولة بسبب تحديث فشل
        $lifted = false;

        if ($startedMaintenance && $actor) {
            try {
                $this->maintenance->lift($actor);
                $lifted = true;
                $actions[] = setting('updates.update_manager.fail_6', 'رفعنا وضع الصيانة والمنصّة رجعت شغّالة.');
            } catch (Throwable $liftError) {
                $actions[] = strtr(setting('updates.update_manager.fail_7', 'تعذّر رفع الصيانة تلقائيًّا — ارفعها يدويًّا من شاشة الصيانة (:p1).'), [':p1' => (string) ($liftError->getMessage())]);
            }
        }

        // (د) فكّ القفل — وإلّا بقيت المنصّة ممنوعة من التحديث حتى تنتهي المهلة
        $this->lock->release($token);
        $actions[] = setting('updates.update_manager.fail_8', 'فكّينا قفل التحديث.');

        $this->ledger->markRolledBack($ran);

        $report = $this->report($stage, $failedMigration, $e->getMessage(), $actions, $backupId, $restored, $lifted);

        DB::table('update_runs')->where('id', $runId)->update([
            'status' => 'failed',
            'stage' => $stage,
            'to_version' => $before,
            'migrations' => json_encode($ran, JSON_UNESCAPED_UNICODE),
            'failed_migration' => $failedMigration,
            'error' => mb_substr($e->getMessage(), 0, 2000),
            'restored' => $restored,
            'maintenance_lifted' => $lifted,
            'report' => json_encode($report, JSON_UNESCAPED_UNICODE),
            'finished_at' => now(),
            'updated_at' => now(),
        ]);

        $historyId = (int) DB::table('app_version_history')->insertGetId([
            'version' => $before,
            'previous_version' => $before,
            'event' => 'failed',
            'notes' => mb_substr(strtr(setting('updates.update_manager.fail_9', 'فشل التحديث عند: :p1'), [':p1' => (string) ($report['what'])]), 0, 1000),
            'migrations_count' => count($ran),
            'migrations' => json_encode($ran, JSON_UNESCAPED_UNICODE),
            'backup_file_id' => $backupId,
            'performed_by' => $actor?->id,
            'performed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit->record($actor, 'ops.updates.failed', [
            'stage' => $stage,
            'migration' => $failedMigration,
            'error' => mb_substr($e->getMessage(), 0, 500),
            'restored' => $restored ? 'yes' : 'no',
        ], 'ops.updates', $historyId);

        $this->notify($actor, strtr(setting('updates.update_manager.fail_10', 'التحديث وقف: :p1'), [':p1' => (string) ($report['what'])]));

        return $this->result(false, $stage, $ran, $before, $before, $report['what'], $report, $runId, implode("\n", $output));
    }

    /**
     * إنزال هجرات هذا التشغيل بترتيب معكوس. نستدعي `down()` مباشرةً بدل
     * `migrate:rollback` لأنّ الأخير يعمل بالدفعة، ونحن هنا في تشغيل لم تكتمل
     * دفعته أصلًا — فالدقّة أهمّ من الاختصار.
     *
     * @return array<int, string>
     */
    private function rollbackApplied(array $ran): array
    {
        $repository = app('migrator')->getRepository();
        $downed = [];

        foreach (array_reverse($ran) as $name) {
            // ⛔ حارس: لا نُنزِل دفعةً ليست لنا. لو آخر دفعة فيها هجرة من خارج
            //    هذا التشغيل نتوقّف فورًا ونترك الأمر للاستعادة من النسخة.
            $last = array_map(
                fn ($row) => is_object($row) ? (string) $row->migration : (string) $row,
                $repository->getLast(),
            );

            if ($last === [] || array_diff($last, $ran) !== []) {
                break;
            }

            try {
                Artisan::call('migrate:rollback', [
                    '--force' => true,
                    '--step' => 1,
                    '--path' => $this->absolutePaths(),
                    '--realpath' => true,
                ]);
            } catch (Throwable) {
                // هجرة لا تنزل ليست نهاية الطريق — الاستعادة من النسخة هي الضمان
                break;
            }

            if (! in_array($name, $repository->getRan(), true)) {
                $downed[] = $name;
            }
        }

        return $downed;
    }

    /**
     * تقرير 2.17: **ماذا حدث** + **ماذا تفعل** — بلا لغة نظام ولا رقم خطأ عارٍ.
     */
    private function report(string $stage, ?string $migration, string $error, array $actions, ?int $backupId, bool $restored, bool $lifted): array
    {
        $stages = [
            'lock' => setting('updates.update_manager.report_1', 'قفل التحديث'),
            'preflight' => setting('updates.update_manager.report_2', 'الفحوص القبليّة'),
            'maintenance' => setting('updates.update_manager.report_3', 'تفعيل وضع الصيانة'),
            'backup' => setting('updates.update_manager.report_4', 'النسخة الاحتياطيّة'),
            'migrate' => setting('updates.update_manager.report_5', 'تنفيذ الهجرات'),
            'verify' => setting('updates.update_manager.report_6', 'التحقّق من البيانات'),
            'seed' => setting('updates.update_manager.report_7', 'زرع القيم الافتراضيّة'),
            'finish' => setting('updates.update_manager.report_8', 'الإنهاء'),
        ];

        $backup = $backupId ? $this->backups->find($backupId) : null;

        return [
            'stage' => $stage,
            'stage_label' => $stages[$stage] ?? $stage,
            'what' => setting('updates.update_manager.report_9', 'وقفنا عند «').($stages[$stage] ?? $stage).'»'.($migration ? strtr(setting('updates.update_manager.report_10', ' في الهجرة :p1'), [':p1' => (string) ($migration)]) : '').' — '.$error,
            'migration' => $migration,
            'error' => $error,
            'actions' => $actions,
            'restored' => $restored,
            'maintenance_lifted' => $lifted,
            'backup_file' => $backup?->filename,
            'backup_file_id' => $backupId,
            'next_steps' => (string) setting('updates.failure_next_steps', 'راجع سبب الفشل وابعته لمطوّر المنصّة مع اسم الهجرة.'),
        ];
    }

    /**
     * بذور القيم الافتراضيّة عند التحديث (2.11-و · ز).
     *
     * ننادي **مسار الإنتاج** كما هو ولا نكتب سيدرًا موازيًا. لكنّنا نحمي قرار
     * المالك: كلّ قيمة عدّلها بيده تُحفَظ قبل التشغيل وتعود بعده — «مفاتيحُ
     * الإعداد الجديدة تُضاف بقيمٍ افتراضيّة **دون استبدال القديمة**».
     *
     * @return array{classes:array<int,string>, kept:int, renamed:int}
     */
    private function seedDefaults(): array
    {
        if (! setting('updates.seed_after_migrate', true)) {
            return ['classes' => [], 'kept' => 0, 'renamed' => 0];
        }

        $renamed = $this->applyRenameMap();

        // مسار الإنتاج لتعريفات الإعدادات — ننادي سيدر الإنتاج كما هو ولا نكتب سيدرًا موازيًا
        $classes = setting('updates.seed_classes', ['Database\\Seeders\\SettingDefinitionsSeeder']);
        $classes = is_array($classes) ? $classes : [];
        $ran = [];

        // قيم عدّلها المالك = كلّ ما يختلف عن افتراضيّه
        $custom = DB::table('settings')->whereColumn('value', '!=', 'default_value')->pluck('value', 'key');

        foreach ($classes as $class) {
            $class = (string) $class;

            if (! class_exists($class)) {
                continue;
            }

            Artisan::call('db:seed', ['--class' => $class, '--force' => true]);
            $ran[] = $class;
        }

        foreach ($custom as $key => $value) {
            DB::table('settings')->where('key', $key)->update(['value' => $value, 'updated_at' => now()]);
        }

        Cache::forget('settings');

        return ['classes' => $ran, 'kept' => $custom->count(), 'renamed' => $renamed];
    }

    /**
     * خريطة إعادة تسمية مفاتيح الإعدادات Old→New (2.11-و) — وهي **نقل قبل حذف**:
     * القيمة تنتقل للمفتاح الجديد، ولا يُحذَف القديم إلّا بعد التأكّد من وصولها.
     */
    private function applyRenameMap(): int
    {
        $map = setting('updates.settings_rename_map', []);

        if (! is_array($map) || $map === []) {
            return 0;
        }

        $count = 0;

        foreach ($map as $old => $new) {
            $old = (string) $old;
            $new = (string) $new;

            $source = DB::table('settings')->where('key', $old)->first();

            if (! $source || $old === $new || $new === '') {
                continue;
            }

            $target = DB::table('settings')->where('key', $new)->first();

            if (! $target) {
                DB::table('settings')->where('key', $old)->update(['key' => $new, 'updated_at' => now()]);
                $count++;

                continue;
            }

            // الهدف موجود: ننقل قيمة المالك إليه ما دام لسه على افتراضيّه
            if ($target->value === $target->default_value && $source->value !== $source->default_value) {
                DB::table('settings')->where('key', $new)->update(['value' => $source->value, 'updated_at' => now()]);
            }

            $landed = DB::table('settings')->where('key', $new)->value('value');

            // ⛔ الحذف بعد التحقّق وحده (2.11-د)
            if ($landed !== null) {
                DB::table('settings')->where('key', $old)->delete();
                $count++;
            }
        }

        Cache::forget('settings');

        return $count;
    }

    /**
     * رفع `app_version` — **بعد النجاح وحده** (2.11-ط). كان الكود يكتب الإصدار
     * القديم مكانه فيبقى كما هو للأبد مهما تعاقبت الترقيات.
     */
    private function bumpVersion(string $current): string
    {
        $target = trim((string) setting('updates.target_version', ''));

        if ($target !== '' && $this->compare($target, $current) > 0) {
            $next = ltrim($target, 'v');
        } else {
            [$major, $minor, $patch] = array_pad(array_map('intval', explode('.', ltrim($current, 'v'))), 3, 0);

            $next = match ((string) setting('updates.auto_bump_segment', 'patch')) {
                'major' => ($major + 1).'.0.0',
                'minor' => $major.'.'.($minor + 1).'.0',
                default => $major.'.'.$minor.'.'.($patch + 1),
            };
        }

        DB::table('settings')->where('key', 'updates.current_version')->update([
            'value' => $next,
            'updated_at' => now(),
        ]);

        Cache::forget('settings');

        return $next;
    }

    private function clearCaches(): void
    {
        Cache::forget('settings');
        Cache::forget('rep_rules');

        if (! setting('updates.clear_cache_after', true)) {
            return;
        }

        foreach (['cache:clear', 'view:clear'] as $command) {
            try {
                Artisan::call($command);
            } catch (Throwable) {
                // تفريغ الكاش تحسينٌ لا شرطُ نجاح — لا يُسقِط تحديثًا نجح
                continue;
            }
        }
    }

    /** إشعار الأدمن بالنتيجة (2.11-ط) — عبر بوّابة الإشعارات إن وُجدت */
    private function notify(?User $actor, string $message): void
    {
        if (! setting('updates.notify_admin_result', true) || ! $actor) {
            return;
        }

        $notifier = 'App\Services\Notifications\Notifier';

        if (! class_exists($notifier)) {
            return;
        }

        try {
            $notifier::send($actor, 'system', setting('updates.update_manager.notify_1', 'التحديثات والترحيل'), $message, route('admin.ops.updates'));
        } catch (Throwable) {
            // إشعارٌ لم يصل لا يغيّر نتيجة التحديث ولا يُبلَّع في تقريره
        }
    }

    private function stage(int $runId, string $stage): void
    {
        DB::table('update_runs')->where('id', $runId)->update(['stage' => $stage, 'updated_at' => now()]);
    }

    private function result(bool $ok, string $stage, array $ran, string $from, string $to, string $message, array $report, int $runId, string $output): array
    {
        return compact('ok', 'stage', 'ran', 'from', 'to', 'message', 'report', 'output') + ['run_id' => $runId];
    }

    // ------------------------------------------------------------------ قراءة التشغيلات

    /** آخر تشغيلات التحديث — الشاشة تعرض تقرير آخر فشل بلا بحث في السجلّات */
    public function runs(int $limit): array
    {
        return DB::table('update_runs')
            ->leftJoin('users', 'users.id', '=', 'update_runs.performed_by')
            ->orderByDesc('update_runs.id')
            ->limit(max(1, $limit))
            ->select(['update_runs.*', 'users.name as performer_name'])
            ->get()
            ->all();
    }

    public function lastFailure(): ?object
    {
        return DB::table('update_runs')->where('status', 'failed')->orderByDesc('id')->first();
    }

    /**
     * **استرجاع آخر دفعة** — والتحذير بما سيُفقَد يُعرَض في الواجهة قبل الوصول لهنا،
     * فالاسترجاع يمسح ما بنته تلك الهجرات ولا يعيد بياناتها.
     *
     * @return array{rolled:array<int,string>, output:string}
     */
    public function rollback(?User $actor): array
    {
        $batch = $this->lastBatch();

        Artisan::call('migrate:rollback', [
            '--force' => true,
            '--step' => 1,
            '--path' => $this->paths(),
        ]);

        $output = trim(Artisan::output());
        $this->ledger->markRolledBack($batch);

        $historyId = (int) DB::table('app_version_history')->insertGetId([
            'version' => $this->currentVersion(),
            'previous_version' => $this->currentVersion(),
            'event' => 'rollback',
            'notes' => strtr(setting('updates.update_manager.rollback_1', 'استرجاع آخر دفعة (:p1 هجرة).'), [':p1' => (string) (count($batch))]),
            'migrations_count' => count($batch),
            'migrations' => json_encode($batch, JSON_UNESCAPED_UNICODE),
            'performed_by' => $actor?->id,
            'performed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit->record($actor, 'ops.updates.rolled_back', [
            'count' => count($batch),
            'migrations' => $batch,
        ], 'ops.updates', $historyId);

        return ['rolled' => $batch, 'output' => $output];
    }

    /** تسجيل إصدار جديد يدويًّا مع ملاحظاته — والاتجاه أمامًا فقط إن كان الإعداد مفعَّلًا */
    public function recordVersion(string $version, ?string $notes, ?User $actor): array
    {
        $current = $this->currentVersion();

        if (setting('updates.forward_only', true) && $this->compare($version, $current) <= 0) {
            return ['saved' => false, 'message' => strtr(setting('updates.update_manager.record_version_1', 'الاتّجاه أمامًا فقط — لازم يكون أحدث من :p1.'), [':p1' => (string) ($current)])];
        }

        DB::table('settings')->where('key', 'updates.current_version')->update([
            'value' => $version,
            'updated_at' => now(),
        ]);

        Cache::forget('settings');

        $historyId = (int) DB::table('app_version_history')->insertGetId([
            'version' => $version,
            'previous_version' => $current,
            'event' => 'release',
            'notes' => $notes,
            'migrations_count' => 0,
            'migrations' => null,
            'performed_by' => $actor?->id,
            'performed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit->record($actor, 'ops.updates.version_recorded', [
            'from' => $current,
            'to' => $version,
        ], 'app_version_history', $historyId);

        return ['saved' => true, 'message' => strtr(setting('updates.update_manager.body_4', 'الإصدار بقى :p1 ✓'), [':p1' => (string) ($version)])];
    }

    /** مقارنة SemVer بسيطة — تكفي لمنع الرجوع لإصدار أقدم */
    public function compare(string $a, string $b): int
    {
        return version_compare(ltrim($a, 'v'), ltrim($b, 'v'));
    }
}
