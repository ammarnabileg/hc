<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Admin\System\SettingsRegistry;
use App\Services\Wallet\ExchangeRates;
use App\Services\Wallet\ExchangeService;
use App\Services\Wallet\TransferService;
use App\Services\Wallet\WithdrawService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * 🔒 أسعار الصرف ورسوم العمليّات — **لمالك المنصّة وحده** (19.1 · 19.3).
 *
 * حارسان لا واحد: صلاحيّة `exchange_rates.*` (موسومة `is_owner_only` في المصفوفة)
 * **وفحصٌ صريح لدور مالك المنصّة**. فحتى لو أُسنِدت الصلاحيّة بالخطأ لأدمن عامّ
 * لا تُفتَح الصفحة — الحسّاس يُقفَل مرّتين لا مرّة.
 *
 * ولماذا شاشة مستقلّة؟ لأنّ هذه الأرقام هي **مصدر الحقيقة الوحيد** لكلّ حسابٍ
 * في المحفظة: خطأ في «1$ = كام كوين» يغيّر قيمة كلّ عمولة وكلّ تحويل في المنصّة.
 */
class WalletRatesController extends Controller
{
    public function __construct(private readonly SettingsRegistry $registry) {}

    /**
     * كتالوج الحقول: المفتاح ⟵ [العنوان · النوع · الافتراضيّ · المجموعة].
     * والسطر هنا هو **تعريف الإعداد**، فيُنشَأ عند أوّل فتحٍ للشاشة إن لم يكن موجودًا
     * — فلا تظهر شاشة فارغة لمجرّد أنّ السيدر لم يُشغَّل في هذه البيئة.
     */
    public function catalog(): array
    {
        return [
            'rates' => [
                'label' => 'أسعار الصرف (مبنيّة على الدولار)',
                'hint' => 'ثلاثة أسعار فقط، وكلّ سعرٍ آخر يُشتقّ منها — فلا تتناقض الأرقام.',
                'fields' => [
                    'finance.rates.usd_to_coins' => ['1$ = كام كوين', 'number', '50'],
                    'finance.rates.ticket_to_coins' => ['1 تذكرة = كام كوين', 'number', '10'],
                    'finance.rates.ticket_to_xp' => ['1 تذكرة = كام XP', 'number', '300'],
                ],
            ],
            'transfer' => [
                'label' => 'رسوم إرسال الحوالة',
                'hint' => 'رسوم الـXP مرتفعة عمدًا لتثبيط تبادلها حفاظًا على نزاهة الليدر بورد.',
                'fields' => [
                    'finance.transfer.coins_fee_percent' => ['رسوم حوالة الكوينز (%)', 'number', '15'],
                    'finance.transfer.xp_fee_percent' => ['رسوم حوالة الـXP (%)', 'number', '85'],
                    'finance.transfer.tickets_fee_percent' => ['رسوم حوالة التذاكر (%)', 'number', '0'],
                    'finance.transfer.min_amount' => ['أقلّ قيمة حوالة', 'number', '10'],
                    'finance.transfer.rounding' => ['تقريب الصافي (ceil/round)', 'string', 'ceil'],
                ],
            ],
            'exchange' => [
                'label' => 'رسوم تحويل العملة',
                'hint' => 'نسبة موحّدة لكلّ المسارات المسموحة — ولا استثناء لمسار.',
                'fields' => [
                    'finance.exchange.fee_percent' => ['رسوم تحويل العملة (%)', 'number', '5'],
                    'finance.exchange.min_amount_usd' => ['أقلّ قيمة تحويل (بالدولار)', 'number', '1'],
                ],
            ],
            'withdraw' => [
                'label' => 'رسوم وحدود سحب الأرباح',
                'hint' => 'الرسوم = النسبة أو الحدّ الأدنى، أيّهما أكبر.',
                'fields' => [
                    'finance.withdraw.fee_percent' => ['رسوم السحب (%)', 'number', '1'],
                    'finance.withdraw.min_fee_usd' => ['أدنى رسوم بالدولار', 'number', '0.5'],
                    'finance.withdraw.min_amount_usd' => ['أقلّ قيمة سحب بالدولار', 'number', '5'],
                ],
            ],
            'referral' => [
                'label' => 'عمولة الريفيرال',
                'hint' => 'تُصرَف بالدولار لحظة نجاح الشحن، وتدخل أرباح الداعي القابلة للسحب.',
                'fields' => [
                    'finance.referral.commission_percent' => ['عمولة الريفيرال (%)', 'number', '7'],
                ],
            ],
        ];
    }

    public function index(Request $request, ExchangeRates $rates, TransferService $transfers, ExchangeService $exchanges, WithdrawService $withdrawals): View
    {
        $this->assertOwner($request);

        return view('wallet.admin.rates', [
            'catalog' => $this->catalog(),
            'settings' => $this->settings(),
            'rates' => $rates->table(),
            // ⭐ معاينة حيّة بنفس خدمات التنفيذ — فما يراه المالك هو ما سيحدث فعلًا
            'previewAmount' => (float) setting('finance.preview.example_amount', 1000),
            'transferPreview' => $transfers->quote('coins', (float) setting('finance.preview.example_amount', 1000)),
            'exchangePreview' => $exchanges->quote('coins', 'tickets', (float) setting('finance.preview.example_amount', 1000)),
            'withdrawPreview' => $withdrawals->quote((float) setting('finance.withdraw.min_amount_usd', 5)),
        ]);
    }

    /** ⭐ أيّ تعديل ماليّ معه **سبب إلزاميّ** يدخل سجلّ التدقيق (2.13-د) */
    public function save(Request $request): RedirectResponse
    {
        $this->assertOwner($request);

        $keys = collect($this->catalog())->flatMap(fn ($group) => array_keys($group['fields']))->all();

        $data = $request->validate([
            'key' => ['required', 'string', 'in:'.implode(',', $keys)],
            'value' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        // سعر صرفٍ بصفر يعني قسمةً على صفر في كلّ مسار — نمنعه هنا لا بعد الانهيار
        if (str_starts_with($data['key'], 'finance.rates.') && (float) $data['value'] <= 0) {
            return back()->withErrors([
                'rates' => 'سعر الصرف لازم يكون أكبر من صفر — اكتب رقمًا موجبًا وجرّب تاني.',
            ]);
        }

        $setting = Setting::query()->where('key', $data['key'])->firstOrFail();
        $old = $setting->value;
        $result = $this->registry->save($setting, $data['value'], $request->user());

        if (! $result['saved']) {
            return back()->withErrors(['rates' => $result['message']]);
        }

        $this->registry->audit($setting, $old, $result['value'], $request->user(), 'exchange_rates.edit', $data['reason']);

        return back()->with('status', 'اتحفظ ✓ — السعر الجديد يسري على العمليّات الجديدة وحدها.');
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return Collection<string, Setting> */
    private function settings(): Collection
    {
        $rows = collect();

        foreach ($this->catalog() as $group) {
            foreach ($group['fields'] as $key => [$label, $type, $default]) {
                $rows[$key] = Setting::query()->firstOrCreate(['key' => $key], [
                    'group' => 'finance',
                    'label_ar' => $label,
                    'type' => $type,
                    'value' => $default,
                    'default_value' => $default,
                    'is_owner_only' => true,
                    'is_sensitive' => true,
                ]);
            }
        }

        return $rows;
    }

    private function assertOwner(Request $request): void
    {
        if (! $request->user()->isPlatformOwner()) {
            abort(403, 'أسعار الصرف لمالك المنصّة وحده — كلّم المالك لو محتاج تعديلًا.');
        }
    }
}
