<?php

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Admin\System\SettingsRegistry;
use App\Services\Ads\AdEvents;
use App\Services\Growth\ContentKit;
use App\Services\Growth\OgCardRenderer;
use App\Services\Growth\ProfileCompletion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * شاشة إدارة حلقات النموّ (21.1-هـ · 21.2-ي · 21.3-و) — **لكلّ إعدادٍ جديد شاشة** (2.13).
 *
 * صفحة واحدة بتابات: الحلقات · الفهرسة والمقالات · الأحداث والتتبّع.
 * والحفظ تلقائيّ بـ«اتحفظ ✓» جنب الحقل، والقيمة الافتراضيّة Placeholder وزرّ Reset —
 * كلّه عبر `SettingsRegistry` نفسه فلا يوجد مسار حفظٍ ثانٍ للإعدادات.
 */
class GrowthAdminController extends Controller
{
    /** التابات: المفتاح ⟵ [العنوان · بادئات المفاتيح · سطر تعريفيّ] */
    public const TABS = [
        'loops' => [
            'label' => 'حلقات النموّ',
            'prefixes' => ['growth.profile_completion.', 'growth.invite_board.', 'growth.preview.', 'growth.linkedin.', 'referral.'],
            'hint' => 'بار «أكمل ملفك» ومكافأته · دروس المعاينة · لوحة متصدّري الدعوات.',
        ],
        'reach' => [
            'label' => 'الفهرسة والمحتوى',
            'prefixes' => ['growth.seo.', 'growth.sitemap.', 'growth.robots.', 'growth.articles.', 'growth.og.', 'growth.utm.', 'growth.acquisition.', 'growth.weekly_card.', 'growth.volunteer_kit.'],
            'hint' => 'الخريطة والفهرسة وقوالب الـOG وUTM ونسبة الاكتساب والكارت الأسبوعيّ وحزمة المتطوّعين.',
        ],
        'ads' => [
            'label' => 'التتبّع والأحداث',
            'prefixes' => ['ads.tracking.', 'ads.events.', 'ads.consent.', 'ads.pixel.', 'ads.capi.', 'ads.audience.', 'ads.best_user.'],
            'hint' => 'مفتاح إيقاف التتبّع كلّه · الأحداث الثمانية · نصوص بانر الموافقة.',
        ],
    ];

    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly ProfileCompletion $completion,
        private readonly ContentKit $kit,
        private readonly OgCardRenderer $og,
    ) {}

    public function index(Request $request): View
    {
        $tab = $request->string('tab')->toString();
        $tab = array_key_exists($tab, self::TABS) ? $tab : (string) array_key_first(self::TABS);

        return view('growth.admin.index', [
            'tabs' => self::TABS,
            'tab' => $tab,
            'settings' => $this->settingsFor($tab, $request),
            'registry' => $this->registry,
            'events' => AdEvents::catalog(),
            'ogTypes' => OgCardRenderer::TYPES,
            'ogRenderer' => $this->og,
            'completionFields' => $this->completion->fields(),
            'tips' => $this->kit->tips(),
        ]);
    }

    /** حفظ تلقائيّ لحقل واحد — نفس محرّك الإعدادات، فلا ازدواج في مسار الحفظ */
    public function saveSetting(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'value' => ['nullable'],
        ]);

        abort_unless($this->owned($data['key']), 422, 'المفتاح ده مش من إعدادات النموّ.');

        $setting = Setting::query()->where('key', $data['key'])->firstOrFail();
        $result = $this->registry->save($setting, $data['value'], $request->user());

        return response()->json($result, $result['saved'] ? 200 : 422);
    }

    /** الإعدادات الظاهرة في تابٍ بعينه — والحسّاس لا يُعرَض لغير مالك المنصّة (12.2.1) */
    private function settingsFor(string $tab, Request $request)
    {
        $prefixes = self::TABS[$tab]['prefixes'];

        return Setting::query()
            ->where(function ($q) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    $q->orWhere('key', 'like', $prefix.'%');
                }
            })
            ->when(! $request->user()->isPlatformOwner(), fn ($q) => $q->where('is_owner_only', false))
            ->orderBy('key')
            ->get();
    }

    private function owned(string $key): bool
    {
        foreach (self::TABS as $tab) {
            foreach ($tab['prefixes'] as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
