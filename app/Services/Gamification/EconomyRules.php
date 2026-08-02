<?php

namespace App\Services\Gamification;

/**
 * قواعد الاقتصاد المعلَنة في لوحة الإدارة (12.10 — «XP والتذاكر»).
 *
 * لماذا هذه الخدمة؟ لأنّ جدولَي **الكسب** و**الصرف** (`xp_rules.earn` ·
 * `xp_rules.spend`) كانا يُحرَّران من لوحة الإدارة **بلا أيّ مستهلك** — إعدادٌ
 * بلا أثر، وهو ما تمنعه القاعدة الذهبيّة (2.13). فصارا من هنا **المصدر الوحيد**
 * لقيم الكسب وتكاليف الصرف التي ليس لها قيمةٌ خاصّة بكيانٍ بعينه.
 *
 * والقاعدة: القيمة الخاصّة بالكيان (سعر امتحان المسار بالكوينز مثلًا) تسبق،
 * وما دونها يأتي من هنا، ولا رقم محروق في أيّ الحالتين.
 */
class EconomyRules
{
    /** صفّ مصدر كسب: القيمة · العملة · الحدّ اليوميّ · مفعّل؟ */
    public function earn(string $key): ?array
    {
        return $this->row('xp_rules.earn', $key);
    }

    /** صفّ وجه صرف: التكلفة · العملة · لحظة الخصم · مفعّل؟ */
    public function spend(string $key): ?array
    {
        return $this->row('xp_rules.spend', $key);
    }

    /**
     * قيمة الكسب. والتمييز مقصود:
     *  - **صفٌّ موقوف** = قرار أدمن صريح بإيقاف المصدر ⟵ صفر.
     *  - **لا صفّ أصلًا** = المفتاح غير مُدرَج بعد ⟵ الافتراضيّ المرسَل.
     */
    public function earnValue(string $key, int $default = 0): int
    {
        $row = $this->earn($key);

        if (! $row) {
            return $default;
        }

        return $this->enabled($row) ? (int) ($row['value'] ?? $default) : 0;
    }

    public function earnCurrency(string $key, string $default = 'xp'): string
    {
        return (string) ($this->earn($key)['currency'] ?? $default);
    }

    /** الحدّ اليوميّ لمصدر الكسب — و0 يعني بلا حدّ */
    public function dailyCap(string $key): int
    {
        return max(0, (int) ($this->earn($key)['daily_cap'] ?? 0));
    }

    /**
     * تكلفة وجه الصرف. والتمييز نفسه:
     *  - **صفٌّ موقوف** = قرار أدمن صريح ⟵ مجّانيّ الآن.
     *  - **لا صفّ أصلًا** ⟵ الافتراضيّ المرسَل (وهو بدوره إعداد لا رقم محروق).
     */
    public function spendCost(string $key, float $default = 0): float
    {
        $row = $this->spend($key);

        if (! $row) {
            return $default;
        }

        return $this->enabled($row) ? (float) ($row['cost'] ?? $default) : 0.0;
    }

    public function spendCurrency(string $key, string $default = 'tickets'): string
    {
        $currency = $this->spend($key)['currency'] ?? null;

        return $currency ? (string) $currency : $default;
    }

    /** لحظة الخصم المعلَنة (on_enter · on_export · on_use…) — للعرض والتوثيق */
    public function spendMoment(string $key, string $default = 'on_enter'): string
    {
        return (string) ($this->spend($key)['moment'] ?? $default);
    }

    public function spendLabel(string $key, string $default = ''): string
    {
        return (string) ($this->spend($key)['label'] ?? $default);
    }

    /**
     * ⭐ التكلفة الفعليّة لوجه صرف حين يكون للكيان عمودُ سعرٍ خاصّ به
     * (قالب سيرة · لعبة · حرب): **مصدرٌ واحد حاكم وOverride صريح**.
     *
     * كان لكلّ من هذه القيم مصدران متنازعان — صفٌّ في «أوجه الصرف» وعمودٌ في
     * جدول الكيان — فيتغيّر الجدول من لوحة الإدارة بلا أثر، وهو ما تمنعه 2.13.
     * فمن اليوم: **NULL في عمود الكيان = اتبع العامّ**، والقيمة = استثناءٌ صريح
     * لهذا الكيان وحده — على غرار عمودَي تذاكر التدريب.
     *
     * @param  int|float|string|null  $override  عمود الكيان — وNULL يعني «اتبع العامّ»
     * @param  float  $default  الافتراضيّ العامّ من إعدادات المجال حين لا يوجد صفٌّ أصلًا
     */
    public function costFor(string $key, int|float|string|null $override, float $default = 0): float
    {
        if ($override !== null && $override !== '') {
            return (float) $override;
        }

        return $this->spendCost($key, $default);
    }

    // ------------------------------------------------------------ داخليّ

    private function row(string $settingKey, string $key): ?array
    {
        $rows = setting($settingKey, []);

        if (! is_array($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            if (is_array($row) && ($row['key'] ?? null) === $key) {
                return $row;
            }
        }

        return null;
    }

    private function enabled(array $row): bool
    {
        return ! array_key_exists('enabled', $row) || (bool) $row['enabled'];
    }
}
