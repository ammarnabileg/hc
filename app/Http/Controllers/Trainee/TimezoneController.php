<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Services\Learning\TimezoneDetector;
use App\Services\Learning\UserClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * توقيت المستخدم (الدستور 5): كشفٌ تلقائيّ + تعديلٌ يدويّ.
 *
 * الكشف يصل من المتصفّح كتلميح، لكنّ **القرار والتخزين على الخادم** — فالمتصفّح
 * لا يُصدّق على شيء، وقيمته تُتحقَّق كمنطقة IANA صالحة قبل أن تُكتَب.
 */
class TimezoneController extends Controller
{
    public function __construct(
        private readonly TimezoneDetector $detector,
        private readonly UserClock $clock,
    ) {}

    /** نداء صامت من الصفحة: «أنا في هذه المنطقة الآن» */
    public function detect(Request $request): JsonResponse
    {
        $changed = $this->detector->sync($request, $request->user());

        return response()->json([
            'changed' => $changed,
            'timezone' => $this->clock->timezoneFor($request->user()),
        ]);
    }

    /** الاختيار اليدويّ — و«تلقائيًّا» يعني مسح الاختيار لا تثبيت قيمة */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'timezone' => ['nullable', 'string', 'timezone'],
        ], [], ['timezone' => (string) setting('availability.timezone.update_msg', 'المنطقة الزمنيّة')]);

        $this->detector->setManual($request->user(), $data['timezone'] ?: null);

        // ردّ فوريّ لكلّ فعل (2.17-ب)
        return back()->with('status', setting('availability.timezone.saved_message'));
    }
}
