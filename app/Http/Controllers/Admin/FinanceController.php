<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Admin\System\FinanceSettings;
use App\Services\Admin\System\SettingsRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * 🔒 الماليّات — مجموعة معزولة لمالك المنصّة وحده (24.3 · 2.13-و · 12.2.1).
 *
 * كلّ مسار هنا يمرّ بحارسين: صلاحيّة `finance.*` (owner-only في المصفوفة)
 * **وفحصٌ صريح لدور مالك المنصّة** — فحتى لو أُسنِدت الصلاحيّة بالخطأ لأدمن عامّ
 * لا تُفتَح الصفحة. الحسّاس يُقفَل مرّتين لا مرّة.
 */
class FinanceController extends Controller
{
    public function __construct(
        private readonly FinanceSettings $finance,
        private readonly SettingsRegistry $registry,
    ) {}

    public function index(Request $request): View
    {
        $this->finance->assertOwner($request->user());

        $groups = $this->finance->groups();
        $group = $request->string('group')->toString();

        if (! array_key_exists($group, $groups)) {
            $group = (string) array_key_first($groups);
        }

        return view('admin.store.finance.index', [
            'groups' => $groups,
            'group' => $group,
            'settings' => $this->finance->settingsOf($group),
            'registry' => $this->registry,
            'preview' => $this->finance->preview((float) setting('finance.preview.example_amount', 1000)),
            'placements' => $this->finance->refundPlacements(),
            'policyAr' => $this->finance->refundPolicy('ar'),
            'policyEn' => $this->finance->refundPolicy('en'),
        ]);
    }

    /**
     * أيّ تعديل ماليّ يمرّ بتأكيد ومعه **ملاحظة سبب إلزاميّة** تدخل الـAudit،
     * ولا يُطبَّق شيء جزئيًّا: إمّا الحقل كلّه أو لا شيء.
     */
    public function save(Request $request): JsonResponse
    {
        $this->finance->assertOwner($request->user());

        $data = $request->validate([
            'key' => ['required', 'string'],
            'value' => ['nullable'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $setting = Setting::query()->where('key', $data['key'])->firstOrFail();
        $old = $setting->value;
        $result = $this->registry->save($setting, $data['value'], $request->user());

        if ($result['saved']) {
            $this->registry->audit($setting, $old, $result['value'], $request->user(), 'finance.edit', $data['reason']);
        }

        return response()->json($result, $result['saved'] ? 200 : 422);
    }

    /**
     * ⭐ سياسة الاسترجاع: Textarea يقبل HTML أو نصًّا · نسختان (ع/إ) · معاينة · Audit (19.4).
     * والمورد `refunds` هنا **عرض وتحرير النصّ فقط** — بلا طلبات ولا اعتماد ولا رفض.
     */
    public function saveRefundPolicy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'in:ar,en'],
            'body' => ['required', 'string', 'max:20000'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $this->finance->saveRefundPolicy($request->user(), $data['locale'], $data['body'], $data['reason']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['body' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'نصّ سياسة الاسترجاع اتحفظ ✓');
    }

    /** معاينة النصّ قبل الحفظ — يقبل HTML كما هو، ولذلك يُعرَض في إطار معزول */
    public function previewRefundPolicy(Request $request): JsonResponse
    {
        $this->finance->assertOwner($request->user());

        return response()->json([
            'html' => (string) $request->input('body', ''),
        ]);
    }

    /** ⭐ سجلّ تدقيق الماليّات — ولا يظهر لغير مالك المنصّة إطلاقًا */
    public function audit(Request $request): View
    {
        $this->finance->assertOwner($request->user());

        return view('admin.store.finance.audit', [
            'logs' => AuditLog::query()
                ->with('user')
                ->where(fn ($q) => $q->where('action', 'like', 'finance.%')
                    ->orWhere('action', 'like', 'refunds.%')
                    ->orWhere('action', 'like', 'topup.%')
                    ->orWhere('action', 'like', 'invoices.%'))
                ->latest('id')
                ->paginate((int) setting('audit.per_page', 50)),
        ]);
    }
}
