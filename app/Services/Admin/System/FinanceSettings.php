<?php

namespace App\Services\Admin\System;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * 🔒 الماليّات — مجموعة معزولة لمالك المنصّة وحده (24.3 · 2.13-و).
 *
 * لماذا صفحة مستقلّة لا تاب عاديّ؟ لأنّ هذه الأرقام **مصدر الحقيقة الوحيد**
 * لكلّ عمليّة ماليّة في المنصّة، وخطأٌ في رقم واحد يكسر الاقتصاد كلّه.
 * ولذلك: **القسم كلّه غير موجود في السايد بار لغير مالك المنصّة** — لا معطَّلًا.
 */
class FinanceSettings
{
    public function __construct(private readonly SettingsRegistry $registry) {}

    /** @return array<string, array{label:string, prefix:string, hint:string}> */
    public function groups(): array
    {
        return [
            'rates' => ['label' => 'أسعار الصرف', 'prefix' => 'finance.rates.', 'hint' => 'مبنيّة على الدولار — وأيّ تعديل يسري على العمليّات الجديدة.'],
            'transfer_fees' => ['label' => 'رسوم إرسال الحوالة', 'prefix' => 'finance.transfer.', 'hint' => 'رسوم مرتفعة عمدًا على XP حفاظًا على نزاهة الليدر بورد.'],
            'exchange_fees' => ['label' => 'رسوم تحويل العملة', 'prefix' => 'finance.exchange.', 'hint' => 'نسبة موحّدة لكلّ المسارات المسموحة.'],
            'topup' => ['label' => 'الشحن', 'prefix' => 'finance.topup.', 'hint' => 'حدود العمليّة والمهل وإعادة محاولة الويب هوك.'],
            'withdraw' => ['label' => 'السحب', 'prefix' => 'finance.withdraw.', 'hint' => 'الرسوم والحدود وطرق التحويل وSLA المعالجة.'],
            'referral' => ['label' => 'عمولة الريفيرال', 'prefix' => 'finance.referral.', 'hint' => 'تُصرف عند نجاح الشحن إلى أرباح الداعي.'],
            'pricing' => ['label' => 'التسعير العامّ', 'prefix' => 'finance.pricing.', 'hint' => 'العملة الافتراضيّة وسياسة التقريب ونصّ الـPaywall.'],
            'refund' => ['label' => 'سياسة الاسترجاع', 'prefix' => 'finance.refund.', 'hint' => 'نصّ السياسة ونسختاه وأماكن ظهوره — بلا طلبات استرجاع.'],
            'invoice' => ['label' => 'قالب الفاتورة', 'prefix' => 'finance.invoice.', 'hint' => 'الترقيم والحقول والتذييل وإشارة سياسة الاسترجاع.'],
        ];
    }

    public function assertOwner(User $user): void
    {
        if (! $user->isPlatformOwner()) {
            abort(403, 'المجموعة الماليّة لمالك المنصّة وحده.');
        }
    }

    /** @return Collection<int, Setting> */
    public function settingsOf(string $group): Collection
    {
        $prefix = $this->groups()[$group]['prefix'] ?? null;

        if ($prefix === null) {
            return collect();
        }

        return Setting::query()->where('key', 'like', $prefix.'%')->orderBy('key')->get();
    }

    /**
     * ⭐ صندوق معاينة لحظيّة: مثال حيّ يحسب المطلوب والرسوم والصافي —
     * فيرى الأدمن أثر الرقم قبل أن يحفظه.
     *
     * @return array{amount:float, fee:float, net:float, note:string}
     */
    public function preview(float $amount): array
    {
        $percent = (float) setting('finance.transfer.coins_fee_percent', 15);
        $fee = $this->roundUp($amount * $percent / 100);

        return [
            'amount' => $amount,
            'fee' => $fee,
            'net' => max(0, $amount - $fee),
            'note' => "على {$amount} كوين ⟵ رسوم {$percent}% = {$fee}، والصافي ".max(0, $amount - $fee),
        ];
    }

    /**
     * ⭐ نصّ سياسة الاسترجاع: **Textarea يقبل HTML أو نصًّا عاديًّا**،
     * **نسختان (عربيّة/إنجليزيّة)**، **معاينة قبل الحفظ**، و**Audit لكلّ تعديل** (19.4).
     *
     * والمورد `refunds` هنا = **عرض وتحرير النصّ فقط** — بلا طلبات ولا اعتماد ولا رفض.
     */
    public function saveRefundPolicy(User $actor, string $locale, string $body, string $reason): Setting
    {
        $this->assertOwner($actor);

        if (! in_array($locale, ['ar', 'en'], true)) {
            throw new RuntimeException('اللغة لازم تكون «ar» أو «en».');
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new RuntimeException('اكتب سبب التعديل — إلزاميّ في كلّ تغيير ماليّ.');
        }

        $setting = Setting::query()->firstOrCreate(
            ['key' => 'finance.refund.policy_'.$locale],
            [
                'group' => 'finance',
                'label_ar' => 'نصّ سياسة الاسترجاع ('.$locale.')',
                'type' => 'text',
                'value' => '',
                'default_value' => '',
                'is_owner_only' => true,
            ],
        );

        $old = $setting->value;
        $setting->update(['value' => $body]);
        $this->registry->audit($setting, $old, $body, $actor, 'refunds.edit', $reason);

        return $setting->refresh();
    }

    public function refundPolicy(string $locale): string
    {
        return (string) (Setting::query()->where('key', 'finance.refund.policy_'.$locale)->value('value') ?? '');
    }

    /** أماكن ظهور السياسة — Toggles لا نصوص محروقة (19.4) */
    public function refundPlacements(): array
    {
        return [
            'finance.refund.show_standalone_page' => 'صفحة مستقلّة دائمة',
            'finance.refund.show_before_payment' => 'إقرار/رابط قبل إتمام الدفع',
            'finance.refund.show_on_invoice' => 'إشارة في الفاتورة',
        ];
    }

    /** سياسة التقريب: للأعلى دائمًا (Ceil) — والقيمة إعداد لا رقم محروق */
    private function roundUp(float $value): float
    {
        return setting('finance.transfer.rounding', 'ceil') === 'ceil'
            ? (float) ceil($value)
            : round($value, 2);
    }
}
