<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\System\FinanceSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * ⭐ سياسة الاسترجاع — شاشة مستقلّة بصلاحيّة المورد `refunds` وحده (12.2.2 · 12.2.3-أ-7)
 * لا بصلاحيّة الماليّة المعزولة `finance.*` (12.7 · owner-only).
 *
 * لماذا شاشة ثانية بدل توسيع 🔒 الماليّات؟ لأنّ المصفوفة تمنح **المسؤول الماليّ**
 * `refunds.view`/`refunds.edit` بنطاق ALL وشرط «دائمًا» صراحةً (12.2.3-أ-7)، بينما
 * كلّ مسار في 🔒 الماليّات محروسٌ بـ`finance.view`/`finance.edit` المعزولتين — فكانت
 * القدرة **ممنوحةً في القاعدة ولا شاشة تستخدمها** (database/data/_STATUS.md، فجوة
 * «قدرة في المصفوفة بانتظار شاشتها»، أُغلِقت هنا). والحلّ المعتمَد من المالك: شاشة
 * مستقلّة بحارس المورد نفسه — لا تصحيح جدول 12.2.3-أ-7.
 *
 * ⚠️ ونطاق المورد هنا **حرفيّ** كما في 12.2.2: عرض نصّ السياسة وتحريره ومعاينته
 * وToggles أماكن ظهوره — بلا فواتير ولا طلبات ولا سجلّ تدقيقٍ عامّ (ذاك يبقى خلف
 * `finance.view` وحدها، فلا يتّسع المورد عن نصّه).
 *
 * ⭐ ونفس مصدر الحقيقة: تقرأ وتكتب عبر `FinanceSettings` نفسها التي تخدم 🔒 الماليّات،
 * فلا يفترق نصّ السياسة بين الشاشتين (لا Drift)، والـAudit واحدٌ لا سجلّين.
 */
class RefundPolicyController extends Controller
{
    public function __construct(private readonly FinanceSettings $finance) {}

    public function index(): View
    {
        return view('admin.store.refund-policy.index', [
            'placements' => $this->finance->refundPlacements(),
            'policyAr' => $this->finance->refundPolicy('ar'),
            'policyEn' => $this->finance->refundPolicy('en'),
        ]);
    }

    /** معاينة النصّ قبل الحفظ — نفس منطق `FinanceController::previewRefundPolicy` بالحرف */
    public function preview(Request $request): JsonResponse
    {
        return response()->json([
            'html' => (string) $request->input('body', ''),
        ]);
    }

    /** حفظٌ بسبب تعديل إلزاميّ يدخل الـAudit — عبر `FinanceSettings::saveRefundPolicy` المشتركة */
    public function save(Request $request): RedirectResponse
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

        return back()->with('status', (string) setting('finance.admin.save_refund_policy_ok', 'نصّ سياسة الاسترجاع اتحفظ ✓'));
    }
}
