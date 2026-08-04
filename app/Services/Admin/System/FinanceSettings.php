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
            'rates' => ['label' => setting('finance.finance_settings.groups_1', 'أسعار الصرف'), 'prefix' => 'finance.rates.', 'hint' => setting('finance.finance_settings.groups_2', 'مبنيّة على الدولار — وأيّ تعديل يسري على العمليّات الجديدة.')],
            'transfer_fees' => ['label' => setting('finance.finance_settings.groups_3', 'رسوم إرسال الحوالة'), 'prefix' => 'finance.transfer.', 'hint' => setting('finance.finance_settings.groups_4', 'رسوم مرتفعة عمدًا على XP حفاظًا على نزاهة الليدر بورد.')],
            'exchange_fees' => ['label' => setting('finance.finance_settings.groups_5', 'رسوم تحويل العملة'), 'prefix' => 'finance.exchange.', 'hint' => setting('finance.finance_settings.groups_6', 'نسبة موحّدة لكلّ المسارات المسموحة.')],
            'topup' => ['label' => setting('finance.finance_settings.groups_7', 'الشحن'), 'prefix' => 'finance.topup.', 'hint' => setting('finance.finance_settings.groups_8', 'حدود العمليّة والمهل وإعادة محاولة الويب هوك.')],
            'withdraw' => ['label' => setting('finance.finance_settings.groups_9', 'السحب'), 'prefix' => 'finance.withdraw.', 'hint' => setting('finance.finance_settings.groups_10', 'الرسوم والحدود وطرق التحويل وSLA المعالجة.')],
            'referral' => ['label' => setting('finance.finance_settings.groups_11', 'عمولة الريفيرال'), 'prefix' => 'finance.referral.', 'hint' => setting('finance.finance_settings.groups_12', 'تُصرف عند نجاح الشحن إلى أرباح الداعي.')],
            'pricing' => ['label' => setting('finance.finance_settings.groups_13', 'التسعير العامّ'), 'prefix' => 'finance.pricing.', 'hint' => setting('finance.finance_settings.groups_14', 'العملة الافتراضيّة وسياسة التقريب ونصّ الـPaywall.')],
            'refund' => ['label' => setting('finance.finance_settings.groups_15', 'سياسة الاسترجاع'), 'prefix' => 'finance.refund.', 'hint' => setting('finance.finance_settings.groups_16', 'نصّ السياسة ونسختاه وأماكن ظهوره — بلا طلبات استرجاع.')],
            'invoice' => ['label' => setting('finance.finance_settings.groups_17', 'قالب الفاتورة'), 'prefix' => 'finance.invoice.', 'hint' => setting('finance.finance_settings.groups_18', 'الترقيم والحقول والتذييل وإشارة سياسة الاسترجاع.')],
        ];
    }

    public function assertOwner(User $user): void
    {
        if (! $user->isPlatformOwner()) {
            abort(403, setting('finance.finance_settings.assert_owner_1', 'المجموعة الماليّة لمالك المنصّة وحده.'));
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
            'note' => strtr(setting('finance.finance_settings.preview_1', 'على :p1 كوين ⟵ رسوم :p2% = :p3، والصافي '), [':p1' => (string) ($amount), ':p2' => (string) ($percent), ':p3' => (string) ($fee)]).max(0, $amount - $fee),
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
            throw new RuntimeException(setting('finance.finance_settings.save_refund_policy_1', 'اللغة لازم تكون «ar» أو «en».'));
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new RuntimeException(setting('finance.finance_settings.save_refund_policy_2', 'اكتب سبب التعديل — إلزاميّ في كلّ تغيير ماليّ.'));
        }

        $setting = Setting::query()->firstOrCreate(
            ['key' => 'finance.refund.policy_'.$locale],
            [
                'group' => 'finance',
                'label_ar' => strtr(setting('finance.finance_settings.save_refund_policy_3', 'نصّ سياسة الاسترجاع (:p1)'), [':p1' => (string) ($locale)]),
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
            'finance.refund.show_standalone_page' => setting('finance.finance_settings.refund_placements_1', 'صفحة مستقلّة دائمة'),
            'finance.refund.show_before_payment' => setting('finance.finance_settings.refund_placements_2', 'إقرار/رابط قبل إتمام الدفع'),
            'finance.refund.show_on_invoice' => setting('finance.finance_settings.refund_placements_3', 'إشارة في الفاتورة'),
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
