<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\ApiRequestLog;
use Illuminate\Console\Command;

/**
 * `php artisan api:prune-request-logs` — **حدّ «آخر 100 لكلّ مفتاح» مفروضٌ
 * فعليًّا لا وصفًا** (12.15-أ ⭐).
 *
 * لكلّ مفتاح API على حدة: يحتفظ بأحدث `developers.api.log_retention_count`
 * سجلًّا (افتراضيًّا 100) ويحذف ما زاد — بعتبة `id` واحدة لكلّ مفتاح لا
 * بتحميل كلّ الصفوف، فالمسح آمنٌ على مفاتيح ذات آلاف الطلبات.
 */
class PruneApiRequestLogs extends Command
{
    protected $signature = 'api:prune-request-logs';

    protected $description = 'حذف ما زاد عن آخر N سجلّ استخدامٍ لكلّ مفتاح API (12.15-أ)';

    public function handle(): int
    {
        $retain = max(1, (int) setting('developers.api.log_retention_count', 100));
        $deleted = 0;

        ApiKey::query()->pluck('id')->each(function (int $apiKeyId) use ($retain, &$deleted) {
            $threshold = ApiRequestLog::query()
                ->where('api_key_id', $apiKeyId)
                ->orderByDesc('id')
                ->skip($retain - 1)
                ->take(1)
                ->value('id');

            if ($threshold === null) {
                return; // أقلّ من الحدّ — لا شيء يُحذَف
            }

            $deleted += ApiRequestLog::query()
                ->where('api_key_id', $apiKeyId)
                ->where('id', '<', $threshold)
                ->delete();
        });

        $this->info($deleted > 0
            ? "اتشال {$deleted} سجلّ استخدامٍ زائد عن حدّ الاحتفاظ ({$retain} لكلّ مفتاح) ✓"
            : 'مافيش سجلّات زائدة عن حدّ الاحتفاظ.');

        return self::SUCCESS;
    }
}
