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
 *
 * والصلاحيّة إلزاميّة (12.2.1): `user_search.view` تفتح الصفحة و`user_search.list`
 * تنفّذ البحث — وهما صفّان منصوصان في المصفوفة (12.2.2)، وكان المسار بلا أيّهما.
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
        $viewer = $request->user();

        // يراها كلّ مستخدم مفعَّل (24.5) — وغير المفعَّل لا يتصفّح الناس
        abort_unless($viewer->isActive(), 403, (string) setting('account.search_screen.payload_msg', 'الحساب لسّه تحت المراجعة.'));

        // ولا يفتحها إلّا مَن يملك مفتاحها المنصوص في المصفوفة (12.2.2)
        abort_unless($viewer->allows(UserSearch::PAGE_PERMISSION), 403);

        $q = trim($request->string('q')->toString());

        // ⭐ «الكلّ» حصريّ: اختياره يلغي الباقي واختيار أيّ حقل يلغيه (13.1)
        $fields = UserSearch::normalizeFields(
            (array) $request->input('fields', []),
            $request->string('last')->toString() ?: null,
        );

        $size = UserSearch::pageSize();

        // كلّ استعلامٍ محصورٌ بنطاق الباحث نفسه (12.2.1-ب) — لا قائمةٌ واحدة للجميع
        $results = $this->search->results($viewer, $q, $fields, $offset, $size);
        $total = $q === '' ? 0 : $this->search->count($viewer, $q, $fields);

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
