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
 * الخطوة 4 — بيانات المنصّة (2.2): الاسم واللوجو والرابط والمنطقة الزمنيّة واللغة.
 * كلّ قيمة هنا لها إعداد بعدين في اللوحة (2.13) — فهذه بداية لا نهاية.
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
                'app_url' => (string) $state->draft('app_url', $request->getSchemeAndHttpHost()),
                'timezone' => (string) $state->draft('timezone', SetupSettings::text('setup.platform.default_timezone', 'Africa/Cairo')),
                'locale' => (string) $state->draft('locale', SetupSettings::text('setup.platform.default_locale', 'ar')),
            ],
            'timezones' => $this->timezones(),
            'locales' => $this->locales(),
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
            'app_url' => ['required', 'url', 'max:190'],
            'timezone' => ['required', 'string', 'timezone'],
            'locale' => ['required', 'string', 'in:'.implode(',', array_keys($this->locales()))],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:'.$maxLogoKb],
        ], [
            'app_url.url' => (string) setting('setup.platform.store_must', 'الرابط لازم يبدأ بـ https:// أو http:// — انسخه من شريط المتصفّح كما هو.'),
            'logo.mimes' => (string) setting('setup.platform.store_msg', 'الشعار يقبل صيغ PNG أو JPG أو WEBP أو SVG فقط.'),
            'logo.max' => strtr((string) setting('setup.platform.store_must_2', 'حجم الشعار أكبر من اللازم. صغّره لأقلّ من :a1 كيلوبايت وجرّب تاني.'), [':a1' => (string) ($maxLogoKb)]),
        ], [
            'app_name' => (string) setting('setup.platform.store_msg_2', 'اسم المنصّة'),
            'app_url' => (string) setting('setup.platform.store_msg_3', 'رابط المنصّة'),
            'timezone' => (string) setting('setup.platform.store_msg_4', 'المنطقة الزمنيّة'),
            'locale' => (string) setting('setup.platform.store_msg_5', 'اللغة'),
            'logo' => (string) setting('setup.platform.store_msg_6', 'الشعار'),
        ]);

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

    /** @return array<string, string> */
    private function locales(): array
    {
        $locales = SetupSettings::get('setup.platform.locales', null);

        return is_array($locales) && $locales !== [] ? $locales : ['ar' => (string) setting('setup.platform.locales_msg', 'العربيّة'), 'en' => 'English'];
    }

    /** @return list<string> */
    private function timezones(): array
    {
        $preferred = SetupSettings::list('setup.platform.preferred_timezones', [
            'Africa/Cairo', 'Asia/Riyadh', 'Asia/Dubai', 'Africa/Khartoum', 'Asia/Amman', 'Europe/London', 'UTC',
        ]);

        return array_values(array_unique([...$preferred, ...timezone_identifiers_list()]));
    }
}
