<?php

namespace App\Services\Store;

use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use Illuminate\Support\Facades\Cache;

/**
 * ⭐ **بلوك إعدادات شاشة البندلز** — كما ينصّ 24 حرفًا بحرف:
 *
 * > «**بلوك الإعدادات:** `bundles.enabled` (ON) · **قاعدة السعر السياقيّ**: الـOverride
 * >  محصور في صفحة البندل فقط (ON — غير قابل للإطفاء منطقيًّا، **يُعرض كقاعدة
 * >  مقفولة**) 🔒 · Toggle Anchoring (ON) · Toggle القيمة الإجماليّة (ON) · قالب
 * >  نصّ البونص (ع/إ) بمتغيّرات · **لا يوجد نوع «هديّة»** (قاعدة ثابتة معروضة
 * >  كملاحظة) · ↺ Reset.»
 *
 * ولماذا كتالوجٌ **مغلق** بدل قبول ما يصل في الطلب؟ لأنّ فورمًا مزوَّرًا يقدر أن
 * يكتب أيّ مفتاحٍ في جدول الإعدادات — فأيّ مفتاحٍ خارج هذه القائمة يُهمَل بصمت.
 *
 * وهذه الشاشة **لا تعرّف افتراضيًّا جديدًا**: التعريفات كلّها في
 * `StoreDemoSeeder::settings()` (مسار الإنتاج عبر `SettingDefinitionsSeeder`)،
 * وهنا نقرأ الصفّ المزروع ونكتب قيمته — فمصدر الافتراضيّ واحدٌ لا اثنان،
 * و`↺ Reset` يرجّع `default_value` المزروع نفسه.
 */
class BundleScreenSettings
{
    /**
     * المفاتيح القابلة للتعديل من الشاشة — والترتيب هو ترتيب العرض.
     *
     * @return array<int, string>
     */
    public const KEYS = [
        // ------------------------------------------------ مفاتيح 24 الأربعة
        'store.bundles.enabled',
        'store.bundle.anchoring_enabled',
        'store.bundle.total_value_enabled',
        'store.bundle.bonus_text',
        'store.bundle.bonus_text_en',

        // ------------------------------------------------ توجّلات بلوكات اللاندنج
        'store.bundle.blocks.fit_enabled',
        'store.bundle.blocks.outcomes_enabled',
        'store.bundle.blocks.includes_enabled',
        'store.bundle.blocks.certificate_enabled',
        'store.bundle.blocks.ledger_enabled',
        'store.bundle.blocks.availability_enabled',

        // ------------------------------------------------ نصوص اللاندنج المشتركة
        'store.bundle.hero_badge',
        'store.bundle.cta_label',
        'store.bundle.fit_title',
        'store.bundle.not_fit_title',
        'store.bundle.outcomes_title',
        'store.bundle.certificate_title',
        'store.bundle.ledger_title',
        'store.bundle.countdown_title',
        'store.bundle.seats_text',
        'store.bundle.after_purchase_text',
        'store.bundle.no_refund_notice',
        'store.bundle.faq_title',
    ];

    /**
     * ⭐ **القواعد المقفولة** — تُعرَض ملاحظاتٍ لا مفاتيحَ، لأنّ نصّ الدستور
     * يجعلها قواعد لا خيارات. وإظهارها ضروريّ: القاعدة التي لا تُرى تُنسى فتُخالَف.
     *
     * @return array<int, array{title:string, body:string}>
     */
    public function lockedRules(): array
    {
        return [
            [
                'title' => (string) setting('store.bundle.rule_contextual_title', 'قاعدة السعر السياقيّ 🔒'),
                'body' => (string) setting(
                    'store.bundle.rule_contextual_text',
                    'السعر الطبيعيّ بيظهر في كلّ صفحات الموقع، والاستثناء الوحيد هو صفحة البندل نفسها. فالـOverride محصور في صفحة البندل وحدها — قاعدة مقفولة مش توجّل.',
                ),
            ],
            [
                'title' => (string) setting('store.bundle.rule_no_gift_title', 'مافيش نوع «هديّة» 🔒'),
                'body' => (string) setting(
                    'store.bundle.rule_no_gift_text',
                    'أيّ محتوى مضمَّن في البندل بيتفتح دايمًا بحكم الشراء — و«هديّة» مصطلح تسويقيّ بس، مش نوع عنصر في النظام.',
                ),
            ],
        ];
    }

    /**
     * صفوف البلوك بقيمها الحاليّة وحالة «معدَّل» — بالشكل الذي يقرؤه
     * `admin.screens24.settings` بلا قالبٍ ثانٍ يُصان مرّتين.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        $stored = Setting::query()->whereIn('key', self::KEYS)->get()->keyBy('key');
        $rows = [];

        foreach (self::KEYS as $key) {
            $setting = $stored->get($key);

            if (! $setting) {
                // المفتاح غير مزروع بعد — الشاشة لا تخترع تعريفًا، و`settings:coverage` يمسكه
                continue;
            }

            $default = (string) ($setting->default_value ?? '');
            $value = (string) ($setting->value ?? $default);

            $rows[] = [
                'key' => $key,
                'label' => (string) $setting->label_ar,
                'type' => $this->fieldType($setting->type, $value),
                'default' => $default,
                'value' => $value,
                'hint' => (string) ($setting->hint ?? ''),
                'modified' => $value !== $default,
                'owner_only' => (bool) $setting->is_owner_only,
            ];
        }

        return $rows;
    }

    /**
     * كتابة دفعة — **ومفاتيح خارج الكتالوج تُهمَل بصمت**.
     *
     * @param  array<string, mixed>  $values
     */
    public function putMany(array $values, ?User $actor = null): int
    {
        $count = 0;

        foreach ($values as $key => $value) {
            if (! in_array($key, self::KEYS, true)) {
                continue;
            }

            $setting = Setting::query()->where('key', $key)->first();

            if (! $setting) {
                continue;
            }

            $old = $setting->value;
            $setting->update(['value' => $this->normalize($setting->type, $value)]);

            AuditTrail::log($actor, 'settings.update', $setting, ['value' => $old], ['key' => $key, 'value' => $setting->value]);
            $count++;
        }

        Cache::forget('settings');

        return $count;
    }

    /** ↺ Reset — يرجّع `default_value` المزروع، لا رقمًا مكتوبًا هنا */
    public function reset(?User $actor = null): int
    {
        $defaults = Setting::query()
            ->whereIn('key', self::KEYS)
            ->pluck('default_value', 'key')
            ->all();

        return $this->putMany($defaults, $actor);
    }

    /** نوع الحقل في الفورم — والنصّ الطويل يأخذ Textarea لا سطرًا واحدًا (2.15) */
    private function fieldType(?string $type, string $value): string
    {
        if (in_array($type, ['bool', 'number'], true)) {
            return (string) $type;
        }

        return $type === 'text' || mb_strlen($value) > 120 ? 'text' : 'string';
    }

    private function normalize(?string $type, mixed $value): string
    {
        if ($type === 'bool') {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
