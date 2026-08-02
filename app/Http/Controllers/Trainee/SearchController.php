<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Services\Account\UserSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * صفحة البحث الكبيرة (الدستور 13.1 · 24.5).
 *
 * خصوصيّة حاكمة: **البحث بالبريد/الموبايل وسيلة وصول فقط** —
 * النتائج وزرّ [عرض] يفتحان **البروفايل العامّ** ولا تُعرَض بيانات حسّاسة إطلاقًا.
 */
class SearchController extends Controller
{
    public function __construct(private readonly UserSearch $search) {}

    public function index(Request $request): View
    {
        return view('account.search', $this->payload($request, 0));
    }

    /** شريحة إضافيّة للتمرير التدريجيّ — 6 في المرّة مع Skeleton (13.1) */
    public function more(Request $request): View
    {
        $offset = max(0, (int) $request->integer('offset'));

        return view('account.partials.search-results', $this->payload($request, $offset));
    }

    private function payload(Request $request, int $offset): array
    {
        // يراها كلّ مستخدم مفعَّل (24.5) — وغير المفعَّل لا يتصفّح الناس
        abort_unless($request->user()->isActive(), 403, 'الحساب لسّه تحت المراجعة.');

        $q = trim($request->string('q')->toString());

        // ⭐ «الكلّ» حصريّ: اختياره يلغي الباقي واختيار أيّ حقل يلغيه (13.1)
        $fields = UserSearch::normalizeFields(
            (array) $request->input('fields', []),
            $request->string('last')->toString() ?: null,
        );

        $size = UserSearch::pageSize();
        $results = $this->search->results($q, $fields, $offset, $size);
        $total = $q === '' ? 0 : $this->search->count($q, $fields);

        return [
            'q' => $q,
            'fields' => $fields,
            'fieldLabels' => UserSearch::fieldLabels(),
            'results' => $results,
            'total' => $total,
            'offset' => $offset,
            'nextOffset' => $offset + $size,
            'hasMore' => $offset + $results->count() < $total,
            'pageSize' => $size,
            'minLength' => UserSearch::minLength(),
        ];
    }
}
