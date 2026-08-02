<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\System\MaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * وضع الصيانة العامّ (12.7-و-1) — **ولا صيانة جزئيّة إطلاقًا**.
 *
 * ⭐ التفعيل يجمّد كلّ المهل والديدلاينات طوال المدّة، والرفع يعيد حسابها
 *    **دفعةً واحدة** مع سجلّ بعدد السجلّات المعدَّلة — استئنافٌ لا إلغاء.
 */
class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceService $maintenance) {}

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'hours' => ['required', 'integer', 'min:1', 'max:'.(int) setting('system.maintenance.max_hours', 168)],
        ]);

        $this->maintenance->start($request->user(), $data['message'], (int) $data['hours']);

        return back()->with('status', 'وضع الصيانة اشتغل — وكلّ المهل اتجمّدت من دلوقتي.');
    }

    /** أزرار سريعة: +1 · +3 · مخصّص — والقيم من الإعدادات لا محروقة */
    public function extend(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hours' => ['required', 'integer', 'min:1'],
        ]);

        $window = $this->maintenance->extend($request->user(), (int) $data['hours']);

        return back()->with(
            'status',
            $window ? 'اتمدّت المدّة ✓' : 'مافيش صيانة شغّالة دلوقتي.',
        );
    }

    public function lift(Request $request): RedirectResponse
    {
        $result = $this->maintenance->lift($request->user());

        return back()->with(
            'status',
            "الصيانة اترفعت — {$result['rows']} مهلة اتعاد حسابها بفارق ".round($result['seconds'] / 3600, 2).' ساعة.',
        );
    }

    /**
     * حالة حيّة لصفحة الصيانة: العدّاد ورسالة ما بعد الصفر ومدّة التحديث التلقائيّ.
     * ⭐ ولا عدّاد سالب أبدًا — الرسالة تتبدّل بنفس المساحة فلا تقفز الصفحة.
     */
    public function state(): JsonResponse
    {
        $state = $this->maintenance->publicState();

        if ($state['overrun']) {
            $state['message'] = $this->maintenance->overrunMessage();
        }

        return response()->json($state);
    }
}
