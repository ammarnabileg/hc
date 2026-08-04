<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\System\MaintenanceService;
use Carbon\CarbonImmutable;
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

    /**
     * ⭐ التفعيل الآن **أو الجدولة** (12.7-ج: «مجدول — يبدأ/ينتهي تلقائيًّا»).
     * كان الفورم رسالةً وساعاتٍ فقط بلا وقت بدء، فالجدولة وعدٌ بلا مدخل.
     */
    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'hours' => ['required', 'integer', 'min:1', 'max:'.(int) setting('system.maintenance.max_hours', 168)],
            'starts_at' => ['nullable', 'date'],
        ]);

        $startsAt = ($data['starts_at'] ?? null) ? CarbonImmutable::parse($data['starts_at']) : null;

        if ($startsAt && $startsAt->isFuture()) {
            $this->maintenance->schedule($request->user(), $data['message'], (int) $data['hours'], $startsAt);

            return back()->with('status', strtr((string) setting('maintenance.admin.start_ok', 'اتجدولت الصيانة — هتبدأ لوحدها :a1 ✓'), [':a1' => (string) ($startsAt->format('Y/m/d H:i'))]));
        }

        $this->maintenance->start($request->user(), $data['message'], (int) $data['hours']);

        return back()->with('status', (string) setting('maintenance.admin.start_msg', 'وضع الصيانة اشتغل — وكلّ المهل اتجمّدت من دلوقتي.'));
    }

    /** إلغاء الصيانة المجدولة قبل موعدها. */
    public function unschedule(Request $request): RedirectResponse
    {
        $this->maintenance->cancelSchedule($request->user());

        return back()->with('status', (string) setting('maintenance.admin.unschedule_ok', 'اتلغت الجدولة ✓'));
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
            $window ? (string) setting('maintenance.admin.extend_ok', 'اتمدّت المدّة ✓') : (string) setting('maintenance.admin.extend_empty', 'مافيش صيانة شغّالة دلوقتي.'),
        );
    }

    public function lift(Request $request): RedirectResponse
    {
        $result = $this->maintenance->lift($request->user());

        return back()->with(
            'status',
            strtr((string) setting('maintenance.admin.lift_ok', 'الصيانة اترفعت — :rows مهلة اتعاد حسابها بفارق :hours ساعة.'), [
                ':rows' => (string) $result['rows'],
                ':hours' => (string) round($result['seconds'] / 3600, 2),
            ]),
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
