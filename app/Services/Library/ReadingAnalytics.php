<?php

namespace App\Services\Library;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * تحليلات المكتبة **المجمّعة** (20.5): الأكثر قراءةً + متوسّط نسبة الإكمال — لتوجيه الإنتاج.
 *
 * ⛔ **بدون سجلّ فتح فرديّ لكلّ ملفّ — مرفوض صراحةً في الدستور.**
 *    ولذلك لا تُخرج هذه الخدمة اسم قارئٍ ولا معرّفه ولا وقت فتحه أبدًا:
 *    مصدرها `reading_progress` (سطرٌ واحد لكلّ مستخدم/منتج) ونُخرج منه **أعدادًا ومتوسّطات فقط**،
 *    ونُخفي أيّ منتجٍ لم يبلغ حدّ التجميع الأدنى حتى لا يدلّ الرقم على شخصٍ بعينه.
 */
class ReadingAnalytics
{
    public function __construct(private readonly PdfPageRenderer $renderer) {}

    /**
     * @return array{
     *     readers:int, products:int, average_completion:int,
     *     top:Collection<int, array{title:string,readers:int,completion:int}>
     * }
     */
    public function summary(int $limit = 5): array
    {
        $rows = DB::table('reading_progress')
            ->select('product_id', DB::raw('count(*) as readers'), DB::raw('sum(last_page) as pages'))
            ->groupBy('product_id')
            ->get();

        if ($rows->isEmpty()) {
            return ['readers' => 0, 'products' => 0, 'average_completion' => 0, 'top' => collect()];
        }

        $products = Product::query()
            ->whereIn('id', $rows->pluck('product_id')->all())
            ->get(['id', 'name_ar', 'file_path', 'type'])
            ->keyBy('id');

        $minReaders = max((int) setting('library.analytics.min_readers', 1), 1);
        $cards = collect();
        $completionSum = 0;
        $completionCount = 0;

        foreach ($rows as $row) {
            $product = $products->get($row->product_id);

            if (! $product) {
                continue;
            }

            $pages = max($this->renderer->pageCount($product), 1);
            $readers = (int) $row->readers;
            // متوسّط نسبة الإكمال = متوسّط (آخر صفحة ÷ عدد الصفحات) لكلّ القرّاء
            $completion = (int) round(min((float) $row->pages / ($pages * max($readers, 1)), 1) * 100);

            $completionSum += $completion;
            $completionCount++;

            if ($readers < $minReaders) {
                continue;
            }

            $cards->push([
                'title' => (string) $product->name_ar,
                'readers' => $readers,
                'completion' => $completion,
            ]);
        }

        return [
            'readers' => (int) $rows->sum('readers'),
            'products' => $rows->count(),
            'average_completion' => $completionCount > 0 ? (int) round($completionSum / $completionCount) : 0,
            'top' => $cards->sortByDesc('readers')->take(max($limit, 1))->values(),
        ];
    }
}
