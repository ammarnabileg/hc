<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MaintenanceWindow;
use App\Models\Setting;
use App\Services\Admin\System\MaintenanceService;
use App\Services\Admin\System\SettingsRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ⭐ الإعدادات والنظام: **صفحة واحدة بتابات جانبيّة** (2.15-د) لا عناصر متفرّقة في السايد بار.
 *
 * وكلّ قواعد 2.13-و مطبَّقة هنا: نمط المفتاح `المجال.الميزة.المفتاح` · بحث موحّد
 * بمسار العنصر وتظليل مؤقّت · تصدير/استيراد JSON · مثال قيمة حيّ · حفظ تلقائيّ
 * بـ«تم الحفظ» جنب كلّ حقل · Placeholder بالافتراضيّ وزرّ Reset · Audit بالـHover.
 */
class SettingsAdminController extends Controller
{
    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly MaintenanceService $maintenance,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $tabs = $this->registry->tabsFor($user);
        $tab = $request->string('tab')->toString();

        if (! array_key_exists($tab, $tabs)) {
            $tab = (string) array_key_first($tabs);
        }

        $search = $request->string('q')->toString();

        return view('admin.settings.index', [
            'tabs' => $tabs,
            'tab' => $tab,
            'search' => $search,
            'settings' => $this->registry->forTab($tab, $user, $search),
            'registry' => $this->registry,
            'highlight' => $request->string('key')->toString(),
            'maintenance' => $this->maintenance,
            'window' => $this->maintenance->current(),
            'windows' => MaintenanceWindow::query()->latest('id')->limit(10)->get(),
            'logs' => $tab === 'audit'
                ? AuditLog::query()->with('user')->latest('id')->paginate((int) setting('audit.per_page', 50))
                : null,
        ]);
    }

    /** حفظ تلقائيّ لحقل واحد — والردّ يحمل رسالة «تم الحفظ ✓» لتظهر جنب الحقل */
    public function saveField(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'value' => ['nullable'],
        ]);

        $setting = Setting::query()->where('key', $data['key'])->firstOrFail();
        $result = $this->registry->save($setting, $data['value'], $request->user());

        return response()->json($result + [
            'example' => $this->registry->liveExample($setting->refresh()),
            'audit' => $this->registry->lastChange($setting),
        ], $result['saved'] ? 200 : 422);
    }

    public function resetField(Request $request): JsonResponse
    {
        $setting = Setting::query()->where('key', $request->string('key')->toString())->firstOrFail();
        $result = $this->registry->reset($setting, $request->user());

        return response()->json($result, $result['saved'] ? 200 : 422);
    }

    /** «تراجع عن آخر تغيير» — قيمة سابقة واحدة تكفي، بلا سجلّات متضخّمة */
    public function undoField(Request $request): JsonResponse
    {
        $setting = Setting::query()->where('key', $request->string('key')->toString())->firstOrFail();
        $result = $this->registry->undo($setting, $request->user());

        return response()->json($result, $result['saved'] ? 200 : 422);
    }

    /** Audit بالـHover: آخر تغيير فقط + رابط بروفايل المحرّر */
    public function audit(Request $request): JsonResponse
    {
        $setting = Setting::query()->where('key', $request->string('key')->toString())->firstOrFail();

        return response()->json($this->registry->lastChange($setting) ?? ['at' => 'مافيش تعديل مسجَّل']);
    }

    /** بحث موحّد داخل كلّ الإعدادات — والنتيجة بمسارها الكامل */
    public function search(Request $request): JsonResponse
    {
        return response()->json([
            'results' => $this->registry->search($request->string('q')->toString(), $request->user()),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $payload = $this->registry->export($request->user());

        return response()->streamDownload(
            fn () => print (json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            'settings-'.now()->format('Ymd-His').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:2048'],
        ]);

        $payload = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);

        if (! is_array($payload)) {
            return back()->withErrors(['file' => 'الملفّ مش JSON صالح — صدّر نسخة وقارن الشكل.']);
        }

        $result = $this->registry->import($payload, $request->user());

        return back()->with('status', "اتطبّق {$result['applied']} إعداد · اتخطّى {$result['skipped']}");
    }
}
