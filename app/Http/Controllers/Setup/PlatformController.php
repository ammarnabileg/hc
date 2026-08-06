<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\StepsThroughSetup;
use App\Services\Setup\Installer;
use App\Services\Setup\SetupSettings;
use App\Services\Setup\SetupState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * الخطوة 4 — بيانات المنصّة (2.2): الاسم واللوجو فقط (ثمانية حقول التنصيب
 * بالضبط) — أمّا الرابط والمنطقة الزمنيّة واللغة فتُشتَقّ تلقائيًّا (رابط الطلب
 * نفسه) أو تُؤخَذ ثابتة من إعدادات المنصّة بلا انتظار إدخال. كلّ قيمة هنا لها
 * إعداد بعدين في اللوحة (2.13) — فهذه بداية لا نهاية.
 */
class PlatformController extends Controller
{
    use StepsThroughSetup;

    public function __construct(private readonly Installer $installer) {}

    public function show(Request $request, SetupState $state): View|RedirectResponse
    {
        if ($redirect = $this->guardStep($state, 'platform')) {
            return $redirect;
        }

        return view('setup.platform', [
            'stepper' => $this->stepper($state, 'platform'),
            'draft' => [
                'app_name' => (string) $state->draft('app_name', SetupSettings::text('setup.platform.default_name', 'المنصّة')),
            ],
        ]);
    }

    public function store(Request $request, SetupState $state): RedirectResponse
    {
        if ($redirect = $this->guardStep($state, 'platform')) {
            return $redirect;
        }

        $maxLogoKb = (int) SetupSettings::number('setup.platform.logo_max_kb', 2048);

        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:120'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:'.$maxLogoKb],
        ], [
            'logo.mimes' => (string) setting('setup.platform.store_msg', 'الشعار يقبل صيغ PNG أو JPG أو WEBP أو SVG فقط.'),
            'logo.max' => strtr((string) setting('setup.platform.store_must_2', 'حجم الشعار أكبر من اللازم. صغّره لأقلّ من :a1 كيلوبايت وجرّب تاني.'), [':a1' => (string) ($maxLogoKb)]),
        ], [
            'app_name' => (string) setting('setup.platform.store_msg_2', 'اسم المنصّة'),
            'logo' => (string) setting('setup.platform.store_msg_6', 'الشعار'),
        ]);

        // الرابط/المنطقة الزمنيّة/اللغة ثابتة أو مُشتَقّة — لا إدخال مستخدم لها (2.2)
        $data['app_url'] = $request->getSchemeAndHttpHost();
        $data['timezone'] = (string) SetupSettings::text('setup.platform.default_timezone', 'Africa/Cairo');
        $data['locale'] = (string) SetupSettings::text('setup.platform.default_locale', 'ar');

        $state->remember(array_diff_key($data, ['logo' => null]));

        if (! $this->installer->writePlatformEnv($data)) {
            return back()->withErrors([
                'app_name' => (string) setting('setup.platform.store_msg_7', 'مقدرناش نحفظ بيانات المنصّة في ملفّ ‎.env‎. اضبط صلاحيّة الكتابة على مجلّد المشروع (775) وجرّب تاني.'),
            ]);
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('branding', 'public');

            if ($path) {
                $this->installer->storeLogo('storage/'.$path);
            }
        }

        $state->complete('platform');

        return redirect()->route('setup.owner');
    }
}
