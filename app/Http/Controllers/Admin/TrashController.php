<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\System\TrashRecovery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * سلّة المحذوفات الموحّدة (`soft_delete_recovery` — 12.2.2 سطر 2188-2191).
 *
 * سؤال الشاشة الواحد: **إيه اللي اتمسح، ولسّه فيه وقت أرجّعه؟**
 * والحصر على الخادم في الثلاثة الأفعال المتغيّرة، بصلاحيّةٍ من المصفوفة على
 * كلّ مسار (12.2.1) — لا مسار واحد بلا حارس.
 */
class TrashController extends Controller
{
    public function __construct(private readonly TrashRecovery $trash) {}

    public function index(Request $request): View
    {
        $type = $request->string('type')->toString() ?: null;

        if ($type !== null && ! array_key_exists($type, TrashRecovery::RESOURCES)) {
            $type = null;
        }

        $perPage = (int) setting('admin.trash.per_page', 20);
        $page = max(1, $request->integer('page', 1));

        return view('admin.trash.index', [
            'type' => $type,
            'resources' => $this->trash->resourceLabels(),
            'rows' => $this->trash->list($type, $perPage, $page),
            'retentionDays' => $this->trash->retentionDays(),
            'counts' => $this->trash->counts(),
        ]);
    }

    /**
     * بانل جانبيّ «عرض قبل الاسترجاع» (`.view`) — بيانات العنصر المحذوف كاملة
     * (2.15-أ-6: التفاصيل في بانل لا صفحة جديدة) + فعلا الاسترجاع والحذف
     * النهائيّ نفسهما، خلف صلاحيّتيهما داخل القالب لا هنا.
     */
    public function show(string $type, string $id): View
    {
        $data = $this->trash->find($type, $id);

        abort_if($data === null, 404);

        return view('admin.trash.partials.panel', [
            'row' => $data,
            'retentionDays' => $this->trash->retentionDays(),
        ]);
    }

    public function restore(Request $request, string $type, string $id): RedirectResponse
    {
        $result = $this->trash->restore($type, $id, $request->user());

        if ($result['ok']) {
            return back()->with('status', (string) setting('admin.trash.restore_ok', 'اترجّع العنصر ✓'));
        }

        $message = $result['reason'] === 'window_closed'
            ? (string) setting('admin.trash.restore_window_closed', 'انتهت مهلة الاسترجاع — العنصر متاح للحذف النهائيّ فقط.')
            : (string) setting('admin.trash.restore_not_found', 'العنصر ده مش موجود في السلّة أصلًا.');

        return back()->withErrors(['trash' => $message]);
    }

    /** ⛔ حذف نهائيّ لا رجعة فيه — التأكيد النصّيّ في الواجهة والتوثيق إلزاميّ في الخدمة */
    public function destroy(Request $request, string $type, string $id): RedirectResponse
    {
        $ok = $this->trash->forceDelete($type, $id, $request->user());

        return back()->with('status', $ok
            ? (string) setting('admin.trash.destroy_ok', 'اتحذف نهائيًّا — ولا يمكن التراجع.')
            : (string) setting('admin.trash.destroy_denied', 'العنصر ده مش موجود في السلّة أصلًا.'));
    }
}
