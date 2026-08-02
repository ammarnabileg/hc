<?php

namespace App\Services\Admin\Ops;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    /** أنواع النسخة — إعداد لا قائمة محروقة (2.13) */
    public function kinds(): array
    {
        $kinds = setting('backups.kinds', ['full' => 'كاملة', 'database' => 'قاعدة البيانات', 'files' => 'ملفّات التخزين']);

        return is_array($kinds) && $kinds !== [] ? $kinds : ['full' => 'كاملة'];
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

        return is_array($rows) && $rows !== [] ? $rows : ['daily' => 'يوميًّا'];
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
            return ['ok' => false, 'id' => null, 'message' => 'النسخ الاحتياطيّ متوقّف من الإعدادات — فعّله الأوّل.'];
        }

        $kind = array_key_exists($kind, $this->kinds()) ? $kind : 'full';
        $startedAt = microtime(true);
        $directory = $this->directory();

        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        if (! is_dir($directory) || ! is_writable($directory)) {
            return ['ok' => false, 'id' => null, 'message' => 'مجلّد النسخ مش قابل للكتابة — راجع صلاحيّات storage.'];
        }

        $stamp = now()->format('Ymd-His');
        $useZip = class_exists(ZipArchive::class) && $kind !== 'database';
        $filename = "backup-{$stamp}-{$kind}.".($useZip ? 'zip' : 'sql');
        $fullPath = $directory.DIRECTORY_SEPARATOR.$filename;

        try {
            $useZip
                ? $this->writeArchive($fullPath, $kind)
                : file_put_contents($fullPath, $this->databaseDump());
        } catch (Throwable $e) {
            $id = $this->record($filename, $kind, 'failed', 0, $startedAt, null, $actor, $scheduled, $e->getMessage());

            return ['ok' => false, 'id' => $id, 'message' => 'النسخة فشلت — '.$e->getMessage()];
        }

        $size = (int) (@filesize($fullPath) ?: 0);
        $id = $this->record($filename, $kind, 'done', $size, $startedAt, hash_file('sha256', $fullPath), $actor, $scheduled);

        $this->audit->record($actor, 'ops.backups.created', [
            'filename' => $filename,
            'kind' => $kind,
            'size' => $size,
            'scheduled' => $scheduled ? 'yes' : 'no',
        ], 'backup_files', $id);

        $this->prune();

        return ['ok' => true, 'id' => $id, 'message' => 'النسخة اتاخدت ✓ — '.$this->humanSize($size)];
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
            ->take(1000)
            ->get(['id', 'filename']);

        foreach ($extra as $row) {
            $path = $this->directory().DIRECTORY_SEPARATOR.$row->filename;

            if (is_file($path)) {
                @unlink($path);
            }

            DB::table('backup_files')->where('id', $row->id)->delete();
        }

        return $extra->count();
    }

    public function humanSize(int $bytes): string
    {
        $units = ['بايت', 'ك.ب', 'م.ب', 'ج.ب'];
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
    ): int {
        return (int) DB::table('backup_files')->insertGetId([
            'filename' => $filename,
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

    private function writeArchive(string $path, string $kind): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('مش قادر أفتح ملفّ الأرشيف للكتابة.');
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
            '-- نسخة احتياطيّة من لوحة الإدارة (12.7-و)',
            '-- التاريخ: '.now()->toDateTimeString(),
            '-- المحرّك: '.DB::getDriverName(),
            '',
        ];

        $limit = max(1, (int) setting('backups.rows_per_table_max', 20000));

        foreach ($this->tables() as $table) {
            $rows = DB::table($table)->limit($limit)->get();

            $lines[] = "-- جدول: {$table} ({$rows->count()} صفّ)";

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
