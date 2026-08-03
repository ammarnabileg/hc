<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Admin\System\SettingsRegistry;
use App\Services\Features\FeatureCatalog;
use App\Services\Features\FeatureRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 🖥️ **مفاتيح المزايا** (24.3) — «إطفاء/تشغيل أيّ ميزة في المنصّة بلا نشر كود
 * — **البديل الوحيد للصيانة الجزئيّة الملغاة** (12.7-و)».
 *
 * وكلّ ما هنا **يكتب في الخادم**: التبديل والنطاق والرؤية أثناء الإيقاف
 * والسبب. أمّا الواجهة فتعرض ما كتبه، ولا تحرس شيئًا بمفردها.
 */
class FeatureFlagController extends Controller
{
    public function __construct(private readonly FeatureRegistry $registry) {}

    /** التبديل — ومعه بوب-أب الإيقاف كاملًا (نصّ بديل ع/إ · إشعار · سبب) */
    public function toggle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'enabled' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'message_ar' => ['nullable', 'string', 'max:1000'],
            'message_en' => ['nullable', 'string', 'max:1000'],
            'notify_affected' => ['nullable', 'boolean'],
            'behavior' => ['nullable', 'string', 'in:,hide,message'],
            'visibility' => ['nullable', 'string', 'in:none,admins,roles'],
            'visible_roles' => ['nullable', 'array'],
            'visible_roles.*' => ['integer'],
        ]);

        $result = $this->registry->toggle(
            $data['key'],
            (bool) $data['enabled'],
            $request->user(),
            $data,
            $request,
        );

        return response()->json($result, $result['saved'] ? 200 : 422);
    }

    /** **النطاق**: عامّ ⇄ Override لدور/شريحة — والقيمة `null` ترفع الـOverride */
    public function scope(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'scope_type' => ['required', 'string', 'in:'.implode(',', FeatureCatalog::SCOPE_TYPES)],
            'scope_id' => ['required', 'integer', 'min:1'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        $result = $this->registry->setOverride(
            $data['key'],
            $data['scope_type'],
            (int) $data['scope_id'],
            $request->input('enabled') === null || $request->input('enabled') === 'null'
                ? null
                : (bool) $data['enabled'],
            $request->user(),
            $request,
        );

        return response()->json($result, $result['saved'] ? 200 : 422);
    }

    /**
     * زرّ `حفظ` في الهيدر — **بلوك الإعدادات الخمسة** دفعةً واحدة.
     *
     * ويمرّ من `SettingsRegistry::save()` نفسه الذي تمرّ منه بقيّة الإعدادات:
     * نفس التحقّق ونفس الـAudit ونفس إبطال الكاش — فلا مسارُ حفظٍ ثانٍ يفترق
     * سلوكُه عن الأوّل.
     */
    public function saveSettings(Request $request, SettingsRegistry $settings): RedirectResponse
    {
        $keys = [
            'features.show_beta_badge',
            'features.disabled_behavior',
            'features.disabled_message',
            'features.disabled_message_en',
            'features.alert_long_outage',
            'features.alert_after_hours',
        ];

        $resetting = $request->input('action') === 'reset';

        foreach ($keys as $key) {
            $setting = Setting::query()->where('key', $key)->first();

            if (! $setting) {
                continue;
            }

            if ($resetting) {
                $settings->reset($setting, $request->user());

                continue;
            }

            // التوجّل الغائب من الفورم = مطفأ — وإلّا لما أمكن إطفاؤه أبدًا
            $value = $setting->type === 'bool'
                ? ($request->boolean($this->fieldName($key)) ? 1 : 0)
                : $request->input($this->fieldName($key));

            $settings->save($setting, $value, $request->user());
        }

        return back()->with('status', (string) setting('features.msg.saved', 'اتحفظ ✓'));
    }

    private function fieldName(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate(['key' => ['required', 'string']]);
        $result = $this->registry->reset($data['key'], $request->user(), $request);

        return response()->json($result, $result['saved'] ? 200 : 422);
    }

    public function resetAll(Request $request): RedirectResponse
    {
        $count = $this->registry->resetAll($request->user(), $request);

        return back()->with('status', str_replace(
            ':count',
            (string) $count,
            (string) setting('features.msg.reset_all', 'رجّعنا :count ميزة لوضعها الافتراضيّ.'),
        ));
    }

    /** سجلّ التدقيق لميزةٍ بعينها: مَن · متى · **لماذا** */
    public function audit(Request $request): JsonResponse
    {
        $key = trim($request->string('key')->toString());

        if ($key === '') {
            return response()->json([
                'message' => (string) setting('features.msg.audit_missing_key', 'مافيش مفتاح ميزة في الطلب — افتح السجلّ من جنب الميزة نفسها.'),
            ], 422);
        }

        return response()->json([
            'rows' => $this->registry->auditFor($key)->map(fn ($log) => [
                'at' => $log->created_at?->toDateTimeString(),
                'by' => $log->user?->name,
                'action' => $log->action,
                'reason' => $log->new_values['reason'] ?? ($log->new_values['disabled_reason'] ?? ''),
                'old' => $log->old_values,
                'new' => $log->new_values,
            ])->values(),
        ]);
    }

    public function export(): StreamedResponse
    {
        $payload = $this->registry->export();

        return response()->streamDownload(
            fn () => print (json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            'feature-flags-'.now()->format('Ymd-His').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:1024'],
        ]);

        $payload = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);

        if (! is_array($payload)) {
            return back()->withErrors([
                'file' => (string) setting('features.msg.bad_json', 'الملفّ مش JSON صالح — صدّر نسخة وقارن الشكل.'),
            ]);
        }

        $result = $this->registry->import($payload, $request->user(), $request);

        return back()->with('status', str_replace(
            [':applied', ':skipped'],
            [$result['applied'], $result['skipped']],
            (string) setting('features.msg.imported', 'اتطبّق :applied مفتاح · اتخطّى :skipped.'),
        ));
    }
}
