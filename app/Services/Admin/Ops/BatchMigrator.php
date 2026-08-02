<?php

namespace App\Services\Admin\Ops;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * الترحيل على دفعات القابل للاستئناف + **قاعدة عدم الفقد** (2.11-د).
 *
 * المشكلة التي يحلّها: جدولٌ بمليون صفّ لا يُنقَل في طلب واحد — تنتهي المهلة أو
 * تنفد الذاكرة، فيقف الترحيل في نصفه. ولو بدأ من أوّله في المحاولة التالية فقد
 * يُكرّر ما نُقِل. لذلك **مؤشّر محفوظ بعد كلّ دفعة**: الاستئناف يبدأ من آخر صفّ
 * نجح، لا من الصفر ولا من نقطة مجهولة.
 *
 * وقاعدة عدم الفقد تُطبَّق بثلاث خطوات لا تُختصَر:
 *   أنشئ الهدف ⟵ `copy()` انسخ على دفعات ⟵ `verify()` طابِق الأعداد والبصمة
 *   ⟵ وبعدها **فقط** `dropSource()`. وأيّ حذفٍ بلا هذا التسلسل يرفضه الكود نفسه.
 */
class BatchMigrator
{
    /** حجم الدفعة — إعداد حيّ لا رقم محروق (2.13) */
    public function size(): int
    {
        return max(1, (int) setting('updates.batch_rows', 1000));
    }

    public function progress(string $job): ?object
    {
        return DB::table('migration_batch_state')->where('job', $job)->first();
    }

    public function reset(string $job): void
    {
        DB::table('migration_batch_state')->where('job', $job)->delete();
    }

    /**
     * معالجة جدول على دفعات **قابلة للاستئناف**.
     *
     * المُعالِج يستقبل مجموعة صفوف؛ ولو رمى استثناءً يبقى المؤشّر عند آخر دفعة
     * نجحت — فإعادة التشغيل تكمل من هناك ولا تعيد ما تمّ.
     *
     * @param  callable(Collection):void  $handler
     * @return array{processed:int, resumed_from:int, batches:int, done:bool}
     */
    public function each(string $job, string $table, callable $handler, string $key = 'id'): array
    {
        if (! Schema::hasTable($table)) {
            throw new RuntimeException("الجدول «{$table}» مش موجود — مفيش حاجة تتعالج.");
        }

        $state = $this->state($job, $table, null);
        $resumedFrom = (int) $state->cursor;
        $size = $this->size();
        $processed = 0;
        $batches = 0;

        while (true) {
            $rows = DB::table($table)
                ->where($key, '>', $this->cursor($job))
                ->orderBy($key)
                ->limit($size)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $handler($rows);

            $batches++;
            $processed += $rows->count();

            // ⭐ المؤشّر يتقدّم **بعد** نجاح الدفعة لا قبلها — وإلّا ضاعت دفعة عند الفشل
            $this->advance($job, (int) $rows->last()->{$key}, $rows->count());
        }

        $this->finish($job, 'done');

        return ['processed' => $processed, 'resumed_from' => $resumedFrom, 'batches' => $batches, 'done' => true];
    }

    /**
     * نسخ جدول إلى آخر على دفعات — الخطوة الثانية من قاعدة عدم الفقد.
     *
     * @param  array<string, string>  $map  عمود الهدف ⟵ عمود المصدر
     * @param  null|callable(object):array  $transform  تحويل صفّ كامل عند اختلاف الشكل
     * @return array{copied:int, resumed_from:int}
     */
    public function copy(string $job, string $from, string $to, array $map = [], ?callable $transform = null, string $key = 'id'): array
    {
        if (! Schema::hasTable($to)) {
            throw new RuntimeException("الهدف «{$to}» لازم يتعمل قبل النقل — مفيش نقل لمكان مش موجود.");
        }

        $this->state($job, $from, $to);
        $copied = 0;

        $result = $this->each($job, $from, function (Collection $rows) use ($to, $map, $transform, &$copied) {
            $payload = $rows->map(function ($row) use ($map, $transform) {
                if ($transform) {
                    return $transform($row);
                }

                $source = (array) $row;

                if ($map === []) {
                    return $source;
                }

                $mapped = [];

                foreach ($map as $target => $origin) {
                    $mapped[$target] = $source[$origin] ?? null;
                }

                return $mapped;
            })->all();

            DB::table($to)->insert($payload);
            $copied += count($payload);
        }, $key);

        return ['copied' => $copied, 'resumed_from' => $result['resumed_from']];
    }

    /**
     * التحقّق من تطابق النقل: **الأعداد + بصمة المحتوى** (2.11-د).
     * ولا شيء يُحذَف قبل أن ترجع هذه الدالّة `ok`.
     *
     * @param  array<int, string>  $columns  أعمدة المقارنة في الطرفين بالترتيب نفسه
     * @return array{ok:bool, source:int, target:int, message:string}
     */
    public function verify(string $job, string $from, string $to, array $columns = []): array
    {
        $source = (int) DB::table($from)->count();
        $target = (int) DB::table($to)->count();

        if ($source !== $target) {
            $this->finish($job, 'failed', null, "المصدر {$source} صفّ والهدف {$target} — النقل ناقص.");

            return ['ok' => false, 'source' => $source, 'target' => $target, 'message' => "النقل ناقص: {$source} ⟵ {$target}."];
        }

        if ($columns !== []) {
            $sourceHash = $this->fingerprint($from, $columns);
            $targetHash = $this->fingerprint($to, $columns);

            if ($sourceHash !== $targetHash) {
                $this->finish($job, 'failed', null, 'بصمة المحتوى مختلفة بين المصدر والهدف.');

                return ['ok' => false, 'source' => $source, 'target' => $target, 'message' => 'الأعداد متطابقة لكنّ المحتوى مختلف — مفيش حذف.'];
            }

            $this->finish($job, 'verified', $sourceHash, 'الأعداد والبصمة متطابقة.');

            return ['ok' => true, 'source' => $source, 'target' => $target, 'message' => 'النقل متطابق ✓'];
        }

        $this->finish($job, 'verified', null, 'الأعداد متطابقة.');

        return ['ok' => true, 'source' => $source, 'target' => $target, 'message' => 'النقل متطابق ✓'];
    }

    /**
     * الحذف **لا يقع إلّا بعد نقلٍ وتحقّق** — والكود هو مَن يرفض، لا التعليمات.
     *
     * @return array{dropped:bool, message:string}
     */
    public function dropSource(string $job, string $from): array
    {
        $state = $this->progress($job);

        if (! $state || $state->status !== 'verified') {
            return ['dropped' => false, 'message' => "ممنوع حذف «{$from}» قبل نقل متحقَّق منه (2.11-د)."];
        }

        Schema::dropIfExists($from);

        DB::table('migration_batch_state')->where('job', $job)->update([
            'note' => 'المصدر اتحذف بعد تحقّق.',
            'updated_at' => now(),
        ]);

        return ['dropped' => true, 'message' => "«{$from}» اتحذف بعد ما اتأكّدنا إنّ كلّ صفّ وصل ✓"];
    }

    /**
     * جداول نُقِلت بياناتها وتحقّقنا منها — يقرؤها التحقّق ليعرف أنّ اختفاءها
     * ليس فقدًا (2.11-هـ).
     *
     * @return array<int, string>
     */
    public function verifiedSources(): array
    {
        return DB::table('migration_batch_state')
            ->where('status', 'verified')
            ->whereNotNull('source_table')
            ->pluck('source_table')
            ->unique()
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------ داخليّ

    private function state(string $job, ?string $from, ?string $to): object
    {
        $existing = $this->progress($job);

        if ($existing) {
            return $existing;
        }

        DB::table('migration_batch_state')->insert([
            'job' => $job,
            'source_table' => $from,
            'target_table' => $to,
            'cursor' => 0,
            'processed' => 0,
            'expected' => $from && Schema::hasTable($from) ? (int) DB::table($from)->count() : 0,
            'status' => 'running',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->progress($job);
    }

    private function cursor(string $job): int
    {
        return (int) (DB::table('migration_batch_state')->where('job', $job)->value('cursor') ?? 0);
    }

    private function advance(string $job, int $cursor, int $count): void
    {
        DB::table('migration_batch_state')->where('job', $job)->update([
            'cursor' => $cursor,
            'processed' => DB::raw('processed + '.$count),
            'status' => 'running',
            'updated_at' => now(),
        ]);
    }

    private function finish(string $job, string $status, ?string $checksum = null, ?string $note = null): void
    {
        $row = $this->progress($job);

        if (! $row) {
            return;
        }

        // «done» لا تُنزِل حالةً أقوى وصلناها قبلها (verified)
        if ($status === 'done' && in_array($row->status, ['verified', 'failed'], true)) {
            return;
        }

        DB::table('migration_batch_state')->where('job', $job)->update(array_filter([
            'status' => $status,
            'checksum' => $checksum,
            'note' => $note,
            'updated_at' => now(),
        ], fn ($value) => $value !== null));
    }

    /** بصمة محتوى جدول على الأعمدة المعنيّة — مرتّبة فلا يغيّرها ترتيب الصفوف */
    private function fingerprint(string $table, array $columns): string
    {
        $lines = DB::table($table)
            ->orderBy($columns[0])
            ->get($columns)
            ->map(fn ($row) => implode('|', array_map(fn ($v) => (string) $v, (array) $row)))
            ->sort()
            ->values()
            ->all();

        return hash('sha256', implode("\n", $lines));
    }
}
