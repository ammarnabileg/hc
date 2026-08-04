<?php

namespace App\Services\Admin\Ops;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * سجلّ الهجرات بالبصمة والتحقّق بعد كلّ خطوة (2.11-أ · 2.11-هـ).
 *
 * جدول Laravel يقول «هذه الهجرة اتنفّذت في الدفعة 7» وخلاص. وهذا لا يكفي لسؤالين
 * يقرّران سلامة الترقية:
 *   1) هل ملفّ الهجرة **هو نفسه** الذي طُبِّق؟ (Checksum — فملفّ عُدِّل بعد تطبيقه
 *      يعني أنّ قاعدتك ليست ما يظنّه الكود.)
 *   2) هل الخطوة الأخيرة **فقدت بيانات**؟ (أعداد الصفوف قبل/بعد + سلامة العلاقات.)
 *
 * ⚠️ قاعدة عدم الفقد (2.11-د) تُطبَّق هنا بالكود: نقص صفوف جدول أو اختفاؤه **يوقف
 *    التحديث** إلّا لو كان نقلًا موثَّقًا ومتحقَّقًا منه في `migration_batch_state`.
 */
class SchemaLedger
{
    /**
     * مزامنة سجلّنا مع جدول Laravel — أيّ هجرة مطبَّقة بلا صفّ عندنا تُسجَّل ببصمتها.
     * لماذا لازمة؟ لأنّ الهجرة التي أنشأت هذا الجدول لم تكن قد سُجِّلت في `migrations`
     * وقت تشغيلها، ولأنّ أيّ ترحيل يجري خارج شاشتنا لا يمرّ علينا.
     */
    public function sync(array $paths = []): int
    {
        if (! Schema::hasTable('schema_migrations') || ! Schema::hasTable('migrations')) {
            return 0;
        }

        $known = DB::table('schema_migrations')->pluck('migration')->all();
        $missing = DB::table('migrations')->whereNotIn('migration', $known ?: ['-'])->get(['migration', 'batch']);

        if ($missing->isEmpty()) {
            return 0;
        }

        $files = $this->files($paths);
        $now = now();

        foreach ($missing as $row) {
            $path = $files[(string) $row->migration] ?? null;

            DB::table('schema_migrations')->insertOrIgnore([
                'migration' => (string) $row->migration,
                'batch' => (int) $row->batch,
                'checksum' => $path ? $this->checksumOf($path) : null,
                'status' => 'applied',
                'duration_ms' => 0,
                'applied_at' => $now,
                'note' => setting('updates.schema_ledger.sync_1', 'مزامنة من جدول الهجرات.'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $missing->count();
    }

    public function checksumOf(string $path): ?string
    {
        return is_file($path) ? hash_file('sha256', $path) : null;
    }

    /** تسجيل هجرة طُبِّقت للتوّ — بالبصمة والزمن الذي استغرقته */
    public function record(string $migration, int $batch, ?string $path, int $durationMs, string $status = 'applied', ?string $note = null): void
    {
        DB::table('schema_migrations')->updateOrInsert(
            ['migration' => $migration],
            [
                'batch' => $batch,
                'checksum' => $path ? $this->checksumOf($path) : null,
                'status' => $status,
                'duration_ms' => $durationMs,
                'applied_at' => now(),
                'note' => $note,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function markRolledBack(array $migrations): void
    {
        if ($migrations === []) {
            return;
        }

        DB::table('schema_migrations')->whereIn('migration', $migrations)->update([
            'status' => 'rolled_back',
            'updated_at' => now(),
        ]);
    }

    public function setBatch(array $migrations, int $batch): void
    {
        if ($migrations === []) {
            return;
        }

        DB::table('schema_migrations')->whereIn('migration', $migrations)->update([
            'batch' => $batch,
            'updated_at' => now(),
        ]);
    }

    /**
     * هجرات مطبَّقة تغيّر ملفّها بعد تطبيقها — «سلامة الإصدار الحاليّ» في الفحص القبليّ.
     *
     * @return array<int, string>
     */
    public function mismatches(array $paths = []): array
    {
        $this->sync($paths);

        $files = $this->files($paths);
        $bad = [];

        foreach (DB::table('schema_migrations')->where('status', 'applied')->whereNotNull('checksum')->get(['migration', 'checksum']) as $row) {
            $path = $files[(string) $row->migration] ?? null;

            // ملفّ غير موجود ليس عطبًا: قد تكون حزمةٌ أُزيلت وهجرتها مطبَّقة
            if ($path === null) {
                continue;
            }

            if ($this->checksumOf($path) !== (string) $row->checksum) {
                $bad[] = (string) $row->migration;
            }
        }

        return $bad;
    }

    public function rows(int $perPage, string $pageName = 'ledger'): LengthAwarePaginator
    {
        $this->sync();

        return DB::table('schema_migrations')->orderByDesc('id')->paginate(max(1, $perPage), ['*'], $pageName);
    }

    public function appliedCount(): int
    {
        return (int) DB::table('schema_migrations')->where('status', 'applied')->count();
    }

    // ------------------------------------------------------------------ التحقّق

    /**
     * لقطة أعداد الصفوف لكلّ جدول — أساس «هل فقدنا بيانات؟».
     *
     * @return array<string, int>
     */
    public function snapshotCounts(): array
    {
        $counts = [];

        foreach ($this->tables() as $table) {
            try {
                $counts[$table] = (int) DB::table($table)->count();
            } catch (Throwable) {
                // جدول غير قابل للعدّ (عرض أو جدول نظام) لا يدخل المقارنة أصلًا
                continue;
            }
        }

        return $counts;
    }

    /**
     * التحقّق بعد خطوة واحدة (2.11-هـ): الجداول موجودة، ولا جدول فقد صفوفًا،
     * ولا جدول اختفى — إلّا ما كان نقلًا **متحقَّقًا منه** (2.11-د).
     *
     * @return array<int, string> المشاكل — والقائمة الفارغة تعني خطوةً سليمة
     */
    public function verifyStep(array $before, array $after, array $verifiedSources = []): array
    {
        if (! setting('updates.verify.forbid_row_loss', true)) {
            return [];
        }

        $problems = [];

        foreach ($before as $table => $count) {
            if (in_array($table, $verifiedSources, true)) {
                continue; // نُقِلت بياناته وتحقّقنا من التطابق قبل حذفه
            }

            if (! array_key_exists($table, $after)) {
                $problems[] = strtr(setting('updates.schema_ledger.verify_step_1', 'الجدول «:p1» اختفى ومعاه :p2 صفّ بلا نقل متحقَّق منه.'), [':p1' => (string) ($table), ':p2' => (string) ($count)]);

                continue;
            }

            if ($after[$table] < $count) {
                $lost = $count - $after[$table];
                $problems[] = strtr(setting('updates.schema_ledger.body_1', 'الجدول «:p1» نقص :p2 صفّ (:p3 ⟵ :p4).'), [':p1' => (string) ($table), ':p2' => (string) ($lost), ':p3' => (string) ($count), ':p4' => (string) ($after[$table])]);
            }
        }

        return $problems;
    }

    /**
     * التحقّق النهائيّ: سلامة العلاقات — صفوفٌ تشير لمفتاح أجنبيّ لم يعد موجودًا.
     *
     * @return array<int, string>
     */
    public function verifyRelations(): array
    {
        if (! setting('updates.verify.check_relations', true)) {
            return [];
        }

        $max = max(1, (int) setting('updates.verify.relations_max_tables', 200));
        $problems = [];

        foreach (array_slice($this->tables(), 0, $max) as $table) {
            try {
                $keys = Schema::getForeignKeys($table);
            } catch (Throwable) {
                continue; // محرّك لا يكشف المفاتيح الأجنبيّة — نتخطّاه بلا ادّعاء
            }

            foreach ($keys as $key) {
                $column = $key['columns'][0] ?? null;
                $foreignTable = $key['foreign_table'] ?? null;
                $foreignColumn = $key['foreign_columns'][0] ?? null;

                if (! $column || ! $foreignTable || ! $foreignColumn || ! Schema::hasTable($foreignTable)) {
                    continue;
                }

                try {
                    $orphans = (int) DB::table($table)
                        ->whereNotNull($column)
                        ->whereNotIn($column, DB::table($foreignTable)->select($foreignColumn))
                        ->count();
                } catch (Throwable) {
                    continue;
                }

                if ($orphans > 0) {
                    $problems[] = strtr(setting('updates.schema_ledger.text_1', '«:p1.:p2» فيه :p3 صفّ بيشير لـ«:p4» مش موجود.'), [':p1' => (string) ($table), ':p2' => (string) ($column), ':p3' => (string) ($orphans), ':p4' => (string) ($foreignTable)]);
                }
            }
        }

        return $problems;
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return array<int, string> */
    public function tables(): array
    {
        $names = [];

        foreach (Schema::getTableListing() as $table) {
            $position = strrpos($table, '.');
            $name = $position === false ? $table : substr($table, $position + 1);

            if (str_starts_with($name, 'sqlite_')) {
                continue;
            }

            $names[] = $name;
        }

        sort($names);

        return $names;
    }

    /** @return array<string, string> اسم الهجرة ⟵ مسار ملفّها */
    private function files(array $paths = []): array
    {
        $paths = $paths !== [] ? $paths : [database_path('migrations')];
        $files = [];

        foreach ($paths as $path) {
            foreach ((array) glob(rtrim((string) $path, '/\\').DIRECTORY_SEPARATOR.'*.php') as $file) {
                $files[basename((string) $file, '.php')] = (string) $file;
            }
        }

        return $files;
    }
}
