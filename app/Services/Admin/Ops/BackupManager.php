<?php

namespace App\Services\Admin\Ops;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
use ZipArchive;

/**
 * النسخ الاحتياطيّ (12.7-و): نسخ يدويّ بضغطة ونسخ مجدول بإعداداته.
 *
 * لماذا بلا أيّ مكتبة خارجيّة؟ لأنّ النسخة الاحتياطيّة آخر خطّ دفاع، ولا يصحّ أن
 * تتوقّف على أداة قد لا تكون منصَّبة على الخادم. فالتفريغ يُبنى بـPHP من قارئ
 * المخطّط نفسه — يعمل على أيّ محرّك تدعمه المنصّة، وبلا أوامر نظام.
 */
class BackupManager
{
    public function __construct(private readonly OpsAudit $audit) {}

    // ------------------------------------------------------------------ قراءة

    public function directory(): string
    {
        return storage_path(trim((string) setting('backups.path', 'backups'), '/'));
    }

    public function list(int $perPage, ?string $kind = null, ?string $status = null, string $pageName = 'page'): LengthAwarePaginator
    {
        return DB::table('backup_files')
            ->leftJoin('users', 'users.id', '=', 'backup_files.created_by')
            ->when($kind, fn ($q) => $q->where('backup_files.kind', $kind))
            ->when($status, fn ($q) => $q->where('backup_files.status', $status))
            ->orderByDesc('backup_files.id')
            ->select(['backup_files.*', 'users.name as author_name', 'users.code as author_code'])
            ->paginate(max(1, $perPage), ['*'], $pageName);
    }

    public function find(int $id): ?object
    {
        return DB::table('backup_files')->where('id', $id)->first();
    }

    public function latest(): ?object
    {
        return DB::table('backup_files')->where('status', 'done')->orderByDesc('id')->first();
    }

    public function pathOf(object $backup): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.$backup->filename;
    }

    /** مسار لقطة الاستعادة المرافقة — وهي ما يُستعاد منه فعلًا (2.11-ج) */
    public function snapshotPathOf(object $backup): ?string
    {
        $name = (string) ($backup->data_file ?? '');

        return $name !== '' ? $this->directory().DIRECTORY_SEPARATOR.$name : null;
    }

    /** أنواع النسخة — إعداد لا قائمة محروقة (2.13) */
    public function kinds(): array
    {
        $kinds = setting('backups.kinds', ['full' => 'كاملة', 'database' => 'قاعدة البيانات', 'files' => 'ملفّات التخزين']);

        return is_array($kinds) && $kinds !== [] ? $kinds : ['full' => setting('backups.backup_manager.kinds_1', 'كاملة')];
    }

    /** إعدادات الجدولة كما تُعرَض في الفورم — كلّها من `setting()` */
    public function schedule(): array
    {
        return [
            'enabled' => (bool) setting('backups.schedule.enabled', true),
            'frequency' => (string) setting('backups.schedule.frequency', 'daily'),
            'time' => (string) setting('backups.daily_time', '03:00'),
            'kind' => (string) setting('backups.default_kind', 'full'),
            'keep' => (int) setting('backups.keep_count', 7),
            'last_run' => (string) setting('backups.schedule.last_run_at', ''),
        ];
    }

    public function frequencies(): array
    {
        $rows = setting('backups.schedule.frequencies', ['daily' => 'يوميًّا', 'weekly' => 'أسبوعيًّا']);

        return is_array($rows) && $rows !== [] ? $rows : ['daily' => setting('backups.backup_manager.frequencies_1', 'يوميًّا')];
    }

    /** هل حان موعد النسخة المجدولة؟ — يقرؤها مشغّل الجدولة قبل أن ينسخ */
    public function isScheduleDue(): bool
    {
        $schedule = $this->schedule();

        if (! $schedule['enabled']) {
            return false;
        }

        $lastRun = $schedule['last_run'] !== '' ? strtotime($schedule['last_run']) : 0;
        $hours = $schedule['frequency'] === 'weekly' ? 168 : 24;

        return (time() - $lastRun) >= $hours * 3600;
    }

    /** عُمر آخر نسخة بالساعات — أساس التنبيه الاستباقيّ «مافيش نسخة من زمان» */
    public function hoursSinceLastBackup(): ?float
    {
        $latest = $this->latest();

        if (! $latest) {
            return null;
        }

        return round((time() - strtotime((string) $latest->created_at)) / 3600, 1);
    }

    // ------------------------------------------------------------------ تنفيذ

    /**
     * نسخة احتياطيّة الآن: قاعدة البيانات + ملفّات التخزين في ملفّ واحد
     * داخل `storage/backups`، ومعها صفٌّ في السجلّ (الحجم · التاريخ · مَن · البصمة).
     *
     * @return array{ok:bool, id:?int, message:string}
     */
    public function create(string $kind, ?User $actor, bool $scheduled = false): array
    {
        if (! setting('backups.enabled', true)) {
            return ['ok' => false, 'id' => null, 'message' => setting('backups.backup_manager.create_1', 'النسخ الاحتياطيّ متوقّف من الإعدادات — فعّله الأوّل.')];
        }

        $kind = array_key_exists($kind, $this->kinds()) ? $kind : 'full';
        $startedAt = microtime(true);
        $directory = $this->directory();

        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        if (! is_dir($directory) || ! is_writable($directory)) {
            return ['ok' => false, 'id' => null, 'message' => setting('backups.backup_manager.create_2', 'مجلّد النسخ مش قابل للكتابة — راجع صلاحيّات storage.')];
        }

        $stamp = now()->format('Ymd-His');
        $useZip = class_exists(ZipArchive::class) && $kind !== 'database';
        $filename = "backup-{$stamp}-{$kind}.".($useZip ? 'zip' : 'sql');
        $fullPath = $directory.DIRECTORY_SEPARATOR.$filename;
        $dataFile = null;

        try {
            $useZip
                ? $this->writeArchive($fullPath, $kind)
                : file_put_contents($fullPath, $this->databaseDump());

            // ⭐ لقطة الاستعادة (2.11-ج): ملفّ SQL للقراءة البشريّة لا يكفي — الاستعادة
            //    تحتاج بيانات مُهيكَلة تُقرأ سطرًا سطرًا بلا تحليل جُمَل هشّ.
            if ($kind !== 'files' && setting('backups.restore_snapshot', true)) {
                $dataFile = str_replace(['.zip', '.sql'], '', $filename).'.data.jsonl';
                $this->writeSnapshot($directory.DIRECTORY_SEPARATOR.$dataFile);
            }
        } catch (Throwable $e) {
            $id = $this->record($filename, $kind, 'failed', 0, $startedAt, null, $actor, $scheduled, $e->getMessage());

            return ['ok' => false, 'id' => $id, 'message' => strtr(setting('backups.backup_manager.body_1', 'النسخة فشلت — :p1'), [':p1' => (string) ($e->getMessage())])];
        }

        $size = (int) (@filesize($fullPath) ?: 0);
        $id = $this->record($filename, $kind, 'done', $size, $startedAt, hash_file('sha256', $fullPath), $actor, $scheduled, null, $dataFile);

        $this->audit->record($actor, 'ops.backups.created', [
            'filename' => $filename,
            'kind' => $kind,
            'size' => $size,
            'scheduled' => $scheduled ? 'yes' : 'no',
        ], 'backup_files', $id);

        $this->prune();

        return ['ok' => true, 'id' => $id, 'message' => strtr(setting('backups.backup_manager.body_2', 'النسخة اتاخدت ✓ — :p1'), [':p1' => (string) ($this->humanSize($size))])];
    }

    public function delete(int $id, ?User $actor): bool
    {
        $backup = $this->find($id);

        if (! $backup) {
            return false;
        }

        $path = $this->pathOf($backup);

        if (is_file($path)) {
            @unlink($path);
        }

        if (($snapshot = $this->snapshotPathOf($backup)) && is_file($snapshot)) {
            @unlink($snapshot);
        }

        DB::table('backup_files')->where('id', $id)->delete();

        $this->audit->record($actor, 'ops.backups.deleted', [
            'filename' => $backup->filename,
        ], 'backup_files', $id);

        return true;
    }

    public function markDownloaded(object $backup, ?User $actor): void
    {
        $this->audit->record($actor, 'ops.backups.downloaded', [
            'filename' => $backup->filename,
        ], 'backup_files', (int) $backup->id);
    }

    /** الاحتفاظ بآخر N نسخة فقط — والباقي يُحذَف ملفًّا وسجلًّا معًا */
    public function prune(): int
    {
        $keep = max(1, (int) setting('backups.keep_count', 7));

        $extra = DB::table('backup_files')
            ->orderByDesc('id')
            ->skip($keep)
            ->take((int) setting('backups.prune_batch', 1000))
            ->get(['id', 'filename', 'data_file']);

        foreach ($extra as $row) {
            foreach ([$row->filename, $row->data_file] as $name) {
                $path = $name ? $this->directory().DIRECTORY_SEPARATOR.$name : null;

                if ($path && is_file($path)) {
                    @unlink($path);
                }
            }

            DB::table('backup_files')->where('id', $row->id)->delete();
        }

        return $extra->count();
    }

    /**
     * ⭐ **التحقّق من سلامة النسخة قبل المتابعة** (2.11-ج): لا تبدأ الهجرةُ إلّا بنسخةٍ صالحة.
     *
     * ثلاثة أسئلة لا رابع لها: الملفّ موجود بحجم؟ بصمته زيّ ما اتسجّلت؟ ولقطة
     * الاستعادة مقروءة وفيها جداول؟ — الفشل في أيّ منها يعني أنّنا بلا شبكة أمان.
     *
     * @return array{ok:bool, message:string, tables:int, rows:int}
     */
    public function verify(int|object|null $backup): array
    {
        $backup = is_int($backup) ? $this->find($backup) : $backup;

        if (! $backup) {
            return ['ok' => false, 'message' => setting('backups.backup_manager.verify_1', 'النسخة مش موجودة في السجلّ.'), 'tables' => 0, 'rows' => 0];
        }

        if ((string) $backup->status !== 'done') {
            return ['ok' => false, 'message' => setting('backups.backup_manager.verify_2', 'النسخة دي مش مكتملة.'), 'tables' => 0, 'rows' => 0];
        }

        $path = $this->pathOf($backup);

        if (! is_file($path) || (int) @filesize($path) === 0) {
            return ['ok' => false, 'message' => setting('backups.backup_manager.verify_3', 'ملفّ النسخة مش موجود على القرص أو فاضي.'), 'tables' => 0, 'rows' => 0];
        }

        if ($backup->checksum && hash_file('sha256', $path) !== (string) $backup->checksum) {
            return ['ok' => false, 'message' => setting('backups.backup_manager.verify_4', 'بصمة الملفّ مختلفة عن المسجَّلة — النسخة اتغيّرت أو اتلفت.'), 'tables' => 0, 'rows' => 0];
        }

        $snapshot = $this->snapshotPathOf($backup);

        if (! $snapshot || ! is_file($snapshot)) {
            return ['ok' => false, 'message' => setting('backups.backup_manager.verify_5', 'مافيش لقطة بيانات مع النسخة دي — يعني مفيش استعادة تلقائيّة منها.'), 'tables' => 0, 'rows' => 0];
        }

        $header = $this->snapshotHeader($snapshot);

        if (! $header || ! is_array($header['tables'] ?? null) || $header['tables'] === []) {
            return ['ok' => false, 'message' => setting('backups.backup_manager.verify_6', 'لقطة البيانات مش مقروءة.'), 'tables' => 0, 'rows' => 0];
        }

        $this->markVerified($backup, strtr(setting('backups.backup_manager.verify_7', 'تحقّق سليم: :p1 جدول.'), [':p1' => (string) (count($header['tables']))]));

        return [
            'ok' => true,
            'message' => strtr(setting('backups.backup_manager.verify_8', 'النسخة سليمة ✓ — :p1 جدول.'), [':p1' => (string) (count($header['tables']))]),
            'tables' => count($header['tables']),
            'rows' => (int) array_sum($header['tables']),
        ];
    }

    /**
     * ⭐ **الاستعادة** (2.11-ح): ترجيع البيانات لحالتها لحظةَ أخذ النسخة.
     *
     * لماذا لا نعيد تشغيل ملفّ الـSQL؟ لأنّ تحليل جُمَل مكتوبة للقراءة البشريّة
     * هشّ (نصوص فيها فواصل وأسطر وعلامات اقتباس)، و«هشّ» كلمة لا تجوز في آخر خطّ
     * دفاع. اللقطة سطر لكلّ صفّ، فالاستعادة قراءةٌ لا تخمين.
     *
     * وجداول الأثر **لا تُمسّ** (`backups.restore_skip_tables`): سجلّ التدقيق
     * وتقرير الفشل وسجلّ النسخ لازم تنجو من الاستعادة، وإلّا محونا الدليل على
     * ما حدث بينما نصلح ما حدث.
     *
     * @return array{ok:bool, message:string, tables:int, rows:int, skipped:array<int,string>}
     */
    public function restore(int|object|null $backup, ?User $actor, ?string $reason = null): array
    {
        $backup = is_int($backup) ? $this->find($backup) : $backup;
        $check = $this->verify($backup);

        if (! $check['ok']) {
            return ['ok' => false, 'message' => strtr(setting('backups.backup_manager.restore_1', 'مقدرناش نستعيد — :p1'), [':p1' => (string) ($check['message'])]), 'tables' => 0, 'rows' => 0, 'skipped' => []];
        }

        $snapshot = (string) $this->snapshotPathOf($backup);
        $header = (array) $this->snapshotHeader($snapshot);
        $skip = setting('backups.restore_skip_tables', []);
        $skip = is_array($skip) ? array_map('strval', $skip) : [];

        $targets = [];
        $skipped = [];

        foreach (array_keys((array) $header['tables']) as $table) {
            $table = (string) $table;

            if (in_array($table, $skip, true)) {
                $skipped[] = $table;

                continue;
            }

            if (Schema::hasTable($table)) {
                $targets[] = $table;
            }
        }

        $rows = 0;
        $chunk = max(1, (int) setting('updates.batch_rows', 1000));

        // المفاتيح الأجنبيّة تُرخى أثناء الاستعادة: ترتيب الجداول في اللقطة أبجديّ
        // لا شجريّ، ولو بقيت القيود مشدودة لسقط أوّل حذف على أوّل علاقة.
        $this->relaxForeignKeys();

        try {
            foreach ($targets as $table) {
                DB::table($table)->delete();
            }

            $handle = fopen($snapshot, 'r');
            $buffer = [];
            $columns = [];

            fgets($handle); // سطر الترويسة قُرِئ سلفًا

            while (($line = fgets($handle)) !== false) {
                $entry = json_decode(trim($line), true);

                if (! is_array($entry) || ! isset($entry['t'], $entry['r'])) {
                    continue;
                }

                $table = (string) $entry['t'];

                if (! in_array($table, $targets, true)) {
                    continue;
                }

                $columns[$table] ??= Schema::getColumnListing($table);
                $row = array_intersect_key((array) $entry['r'], array_flip($columns[$table]));

                $buffer[$table][] = $row;

                if (count($buffer[$table]) >= $chunk) {
                    DB::table($table)->insert($buffer[$table]);
                    $rows += count($buffer[$table]);
                    $buffer[$table] = [];
                }
            }

            fclose($handle);

            foreach ($buffer as $table => $pending) {
                if ($pending !== []) {
                    DB::table($table)->insert($pending);
                    $rows += count($pending);
                }
            }
        } finally {
            $this->restoreForeignKeys();
        }

        Cache::forget('settings');

        $this->audit->record($actor, 'ops.backups.restored', [
            'filename' => $backup->filename,
            'tables' => count($targets),
            'rows' => $rows,
            'reason' => $reason ?? setting('backups.backup_manager.restore_2', 'استعادة يدويّة'),
        ], 'backup_files', (int) $backup->id);

        return [
            'ok' => true,
            'message' => strtr(setting('backups.backup_manager.restore_3', 'الاستعادة تمّت ✓ — :p1 جدول و:p2 صفّ رجعوا لحالتهم قبل التحديث.'), [':p1' => (string) (count($targets)), ':p2' => (string) ($rows)]),
            'tables' => count($targets),
            'rows' => $rows,
            'skipped' => $skipped,
        ];
    }

    public function humanSize(int $bytes): string
    {
        $units = [setting('backups.backup_manager.human_size_1', 'بايت'), setting('backups.backup_manager.human_size_2', 'ك.ب'), setting('backups.backup_manager.human_size_3', 'م.ب'), setting('backups.backup_manager.human_size_4', 'ج.ب')];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return rtrim(rtrim(number_format($bytes, 1), '0'), '.').' '.$units[$index];
    }

    // ------------------------------------------------------------------ داخليّ

    private function record(
        string $filename,
        string $kind,
        string $status,
        int $size,
        float $startedAt,
        ?string $checksum,
        ?User $actor,
        bool $scheduled,
        ?string $error = null,
        ?string $dataFile = null,
    ): int {
        return (int) DB::table('backup_files')->insertGetId([
            'filename' => $filename,
            'data_file' => $dataFile,
            'kind' => $kind,
            'status' => $status,
            'size_bytes' => $size,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'checksum' => $checksum,
            'is_scheduled' => $scheduled,
            'error' => $error,
            'created_by' => $actor?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * لقطة الاستعادة: سطر ترويسة ثمّ **سطر لكلّ صفّ** — تُكتب وتُقرأ بالتدفّق،
     * فلا تُحمَّل قاعدة بيانات كاملة في الذاكرة لا عند النسخ ولا عند الاستعادة.
     *
     * @return array<string, int> الجدول ⟵ عدد صفوفه
     */
    private function writeSnapshot(string $path): array
    {
        $limit = max(1, (int) setting('backups.rows_per_table_max', 20000));
        $tables = [];

        foreach ($this->tables() as $table) {
            $tables[$table] = min($limit, (int) DB::table($table)->count());
        }

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new \RuntimeException(setting('backups.backup_manager.write_snapshot_1', 'مش قادر أكتب لقطة الاستعادة — راجع صلاحيّات مجلّد النسخ.'));
        }

        $flags = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

        fwrite($handle, json_encode([
            'format' => 'hc-restore-1',
            'taken_at' => now()->toDateTimeString(),
            'driver' => DB::getDriverName(),
            'app_version' => (string) setting('updates.current_version', '1.0.0'),
            'tables' => $tables,
        ], $flags)."\n");

        $chunk = max(1, (int) setting('updates.batch_rows', 1000));

        foreach (array_keys($tables) as $table) {
            $written = 0;

            DB::table($table)->orderBy($this->orderColumn($table))->chunk($chunk, function ($rows) use ($handle, $table, $flags, $limit, &$written) {
                foreach ($rows as $row) {
                    if ($written >= $limit) {
                        return false;
                    }

                    fwrite($handle, json_encode(['t' => $table, 'r' => (array) $row], $flags)."\n");
                    $written++;
                }

                return true;
            });
        }

        fclose($handle);

        return $tables;
    }

    /**
     * إرخاء المفاتيح الأجنبيّة بحسب المحرّك.
     *
     * ولماذا أمران في SQLite لا واحد؟ لأنّ `foreign_keys` لا يُطفأ داخل معاملة
     * جارية (وهو حال أيّ استعادة تجري داخل معاملة اختبار أو معاملة أوسع)،
     * بينما `defer_foreign_keys` مصنوع لهذا بالضبط: يؤجّل الفحص للالتزام.
     */
    private function relaxForeignKeys(): void
    {
        $statements = match (DB::getDriverName()) {
            'sqlite' => ['PRAGMA foreign_keys = OFF', 'PRAGMA defer_foreign_keys = ON'],
            'pgsql' => ['SET CONSTRAINTS ALL DEFERRED'],
            default => ['SET FOREIGN_KEY_CHECKS=0'],
        };

        foreach ($statements as $statement) {
            try {
                DB::statement($statement);
            } catch (Throwable) {
                continue;
            }
        }
    }

    private function restoreForeignKeys(): void
    {
        $statements = match (DB::getDriverName()) {
            'sqlite' => ['PRAGMA foreign_keys = ON'],
            'pgsql' => [],
            default => ['SET FOREIGN_KEY_CHECKS=1'],
        };

        foreach ($statements as $statement) {
            try {
                DB::statement($statement);
            } catch (Throwable) {
                continue;
            }
        }
    }

    /** ترويسة اللقطة — أوّل سطر وحده، بلا تحميل الملفّ كلّه */
    private function snapshotHeader(string $path): ?array
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            return null;
        }

        $line = fgets($handle);
        fclose($handle);

        $header = json_decode((string) $line, true);

        return is_array($header) ? $header : null;
    }

    private function markVerified(object $backup, string $note): void
    {
        DB::table('backup_files')->where('id', $backup->id)->update([
            'verified_at' => now(),
            'verify_note' => mb_substr($note, 0, 255),
            'updated_at' => now(),
        ]);
    }

    /** عمود ترتيب مضمون للتقطيع — `id` إن وُجد وإلّا أوّل عمود */
    private function orderColumn(string $table): string
    {
        $columns = Schema::getColumnListing($table);

        return in_array('id', $columns, true) ? 'id' : ($columns[0] ?? 'rowid');
    }

    private function writeArchive(string $path, string $kind): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException(setting('backups.backup_manager.write_archive_1', 'مش قادر أفتح ملفّ الأرشيف للكتابة.'));
        }

        if ($kind !== 'files') {
            $zip->addFromString('database.sql', $this->databaseDump());
        }

        if ($kind !== 'database' && setting('backups.include_storage', true)) {
            $this->addStorageFiles($zip);
        }

        $zip->addFromString('manifest.json', json_encode([
            'kind' => $kind,
            'taken_at' => now()->toDateTimeString(),
            'app_version' => (string) setting('updates.current_version', '1.0.0'),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $zip->close();
    }

    /** ملفّات التخزين العامّة — بسقف حجم من الإعدادات حتى لا تلتهم النسخةُ القرصَ */
    private function addStorageFiles(ZipArchive $zip): void
    {
        $root = storage_path('app'.DIRECTORY_SEPARATOR.trim((string) setting('backups.files.folder', 'public'), '/'));

        if (! is_dir($root)) {
            return;
        }

        $budget = max(1, (int) setting('backups.files.max_mb', 200)) * 1024 * 1024;
        $used = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $used += $file->getSize();

            if ($used > $budget) {
                break;
            }

            $relative = 'storage/'.ltrim(str_replace($root, '', $file->getPathname()), DIRECTORY_SEPARATOR.'/');
            $zip->addFile($file->getPathname(), $relative);
        }
    }

    /**
     * تفريغ قاعدة البيانات جملًا نصّيّة. سقف الصفوف لكلّ جدول إعدادٌ أيضًا،
     * فالتنصيبات الصغيرة تأخذ كلّ شيء والكبيرة لا تعلّق الخادم.
     */
    private function databaseDump(): string
    {
        $lines = [
            setting('backups.backup_manager.database_dump_1', '-- نسخة احتياطيّة من لوحة الإدارة (12.7-و)'),
            strtr(setting('backups.backup_manager.database_dump_2', '-- التاريخ: :p1'), [':p1' => (string) (now()->toDateTimeString())]),
            strtr(setting('backups.backup_manager.database_dump_3', '-- المحرّك: :p1'), [':p1' => (string) (DB::getDriverName())]),
            '',
        ];

        $limit = max(1, (int) setting('backups.rows_per_table_max', 20000));

        foreach ($this->tables() as $table) {
            $rows = DB::table($table)->limit($limit)->get();

            $lines[] = strtr(setting('backups.backup_manager.database_dump_4', '-- جدول: :p1 (:p2 صفّ)'), [':p1' => (string) ($table), ':p2' => (string) ($rows->count())]);

            foreach ($rows as $row) {
                $data = (array) $row;
                $columns = implode(', ', array_map(fn ($c) => '"'.$c.'"', array_keys($data)));
                $values = implode(', ', array_map(fn ($v) => $this->quote($v), array_values($data)));
                $lines[] = "INSERT INTO \"{$table}\" ({$columns}) VALUES ({$values});";
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /** @return array<int, string> */
    private function tables(): array
    {
        $names = [];

        foreach (Schema::getTableListing() as $table) {
            // بعض المحرّكات ترجع الاسم مسبوقًا بالمخطّط (main.users) — نأخذ الاسم وحده
            $position = strrpos($table, '.');
            $name = $position === false ? $table : substr($table, $position + 1);

            // جداول المحرّك الداخليّة ليست بياناتنا ولا تُستعاد
            if (str_starts_with($name, 'sqlite_')) {
                continue;
            }

            $names[] = $name;
        }

        sort($names);

        return $names;
    }

    private function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'".str_replace("'", "''", (string) $value)."'";
    }
}
