<?php

namespace App\Services\Admin\Ops;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * التحديثات والترحيل (12.7-هـ · 2.11): **نقرة آمنة + Dry-run + استرجاع**.
 *
 * القواعد التي يفرضها هذا الصنف بالكود لا بالنصيحة:
 *  1) **لا تنفيذ بلا تأكيد** — والتأكيد مزدوج: عبارة مكتوبة + إقرار صريح.
 *  2) **Dry-run إلزاميّ قبل التنفيذ** ما دام الإعداد مفعَّلًا، وصلاحيّته دقائق معدودة
 *     ومربوطة بنفس قائمة الهجرات المعلّقة — فلو تغيّرت القائمة سقط التصريح.
 *  3) **كلّ عمليّة تُسجَّل** بمن نفّذها ومتى، وتُقيَّد في سجلّ الإصدارات.
 *  4) **الاسترجاع يعرض ما سيُفقَد بالاسم** قبل أن يلمس شيئًا.
 */
class UpdateManager
{
    public function __construct(private readonly OpsAudit $audit) {}

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
        $migrator = app('migrator');
        $files = $migrator->getMigrationFiles(array_map(fn ($p) => base_path($p), $this->paths()));
        $ran = $migrator->getRepository()->repositoryExists() ? $migrator->getRepository()->getRan() : [];

        return array_values(array_diff(array_keys($files), $ran));
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
            Artisan::call('migrate', [
                '--pretend' => true,
                '--force' => true,
                '--path' => $this->paths(),
            ]);

            $output = trim(Artisan::output());
        }

        $this->audit->record($actor, 'ops.updates.dry_run', [
            'pending' => count($pending),
            'signature' => $signature,
            'migrations' => $pending,
        ], 'ops.updates');

        return [
            'pending' => $pending,
            'output' => $output !== '' ? $output : 'مافيش هجرات معلّقة — مفيش جملة واحدة هتتنفّذ.',
            'signature' => $signature,
        ];
    }

    /**
     * تنفيذ الترحيل. لا يُستدعى إلّا بعد تأكيد مزدوج في الكنترولر،
     * ويُقيَّد في سجلّ الإصدارات ليُعرَف من أيّ إصدار جئنا (2.11).
     *
     * @return array{ran:array<int,string>, output:string, history_id:int}
     */
    public function migrate(?User $actor, ?int $backupId = null): array
    {
        $pending = $this->pending();
        $before = $this->currentVersion();

        Artisan::call('migrate', [
            '--force' => true,
            '--path' => $this->paths(),
        ]);

        $output = trim(Artisan::output());

        $historyId = (int) DB::table('app_version_history')->insertGetId([
            'version' => $before,
            'previous_version' => $before,
            'event' => 'migrate',
            'notes' => 'تنفيذ '.count($pending).' هجرة معلّقة.',
            'migrations_count' => count($pending),
            'migrations' => json_encode($pending, JSON_UNESCAPED_UNICODE),
            'backup_file_id' => $backupId,
            'performed_by' => $actor?->id,
            'performed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit->record($actor, 'ops.updates.migrated', [
            'count' => count($pending),
            'migrations' => $pending,
            'backup_file_id' => $backupId,
        ], 'ops.updates', $historyId);

        return ['ran' => $pending, 'output' => $output, 'history_id' => $historyId];
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

        $historyId = (int) DB::table('app_version_history')->insertGetId([
            'version' => $this->currentVersion(),
            'previous_version' => $this->currentVersion(),
            'event' => 'rollback',
            'notes' => 'استرجاع آخر دفعة ('.count($batch).' هجرة).',
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
            return ['saved' => false, 'message' => "الاتّجاه أمامًا فقط — لازم يكون أحدث من {$current}."];
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

        return ['saved' => true, 'message' => "الإصدار بقى {$version} ✓"];
    }

    /** مقارنة SemVer بسيطة — تكفي لمنع الرجوع لإصدار أقدم */
    public function compare(string $a, string $b): int
    {
        return version_compare(ltrim($a, 'v'), ltrim($b, 'v'));
    }
}
