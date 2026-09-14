<?php

namespace App\Services\Ui;

/**
 * توكنز نظام التصميم (2.10.1 — الهويّة البصريّة 2.0، v5.7) — **من الإعدادات
 * لا من الكود** (2.13-ب).
 *
 * كلّ مقاسٍ ومدّةٍ ولونٍ نصّ عليه 2.10.1 يُكتَب هنا **متغيّرًا في `:root`**
 * تقرأه ورقة الأنماط، فيتغيّر شكل المنصّة من «الهويّة والمظهر» بلا إعادة نشر.
 * والقيمة الافتراضيّة لكلّ مفتاح هي **نصّ الدستور حرفيًّا** — فلو غاب المفتاح
 * (تنصيبٌ قديم قبل بذرة التصميم) بقي الشكل مطابقًا للمنصوص لا فارغًا.
 *
 * ⛔ ولا توجّل هنا للحركة: «الأنيميشن حاضر دائمًا» (2.3 · 2.14-ب) — المتغيّرات
 * تضبط **مدّة** الحركة لا **وجودها**، ولا مفتاح يوقفها.
 *
 * ⭐ **جديد في v5.7:** توكنز الألوان الأساسيّة (خلفيّة/سطح/حدّ/حبر/أحمر الهويّة)
 * صارت **مزدوجة** — قيمةٌ للفاتح وأخرى للداكن — لأنّ الوضعين معًا معتمَدان
 * رسميًّا (فاتح افتراضيّ، داكن تجريبيّ). و`css()` تُخرج كتلتين: `:root{}`
 * للفاتح (والتوكنز غير اللونيّة)، و`:root[data-theme='dark']{}` للقيم
 * الداكنة وحدها — فيبقى التسلسل المصدريّ بعد `@theme`/`@layer base` في
 * `app.css` هو الحاسم (كلاهما `:root`/`:root[data-theme='dark']` بنفس
 * الخصوصيّة، فالمتأخّر مصدرًا يغلب).
 *
 * ⛔ لا تشمل التوكنز اللونيّة هنا أبعاد السايد بار/الهيدر (248/80/280px ·
 * 76/64px) — تلك **ثابتُ هويّةٍ غير قابل للتعديل** بنصّ 24 (شاشة «الهويّة
 * والمظهر»)، لا إعدادًا.
 */
class DesignTokens
{
    /**
     * المفتاح ⟵ [المتغيّر في CSS، القيمة الافتراضيّة من نصّ 2.10.1، اللاحقة].
     * توكنزٌ **غير لونيّة** — لا فرق بين الوضعين.
     *
     * @var array<string,array{0:string,1:string,2:string}>
     */
    private const MAP = [
        // (10) السويتش: «المسار 42×24px … الإبهام دائرة 18px بيضاء تنزلق 3px→21px»
        'design.switch.track_w' => ['--sw-track-w', '42', 'px'],
        'design.switch.track_h' => ['--sw-track-h', '24', 'px'],
        'design.switch.thumb' => ['--sw-thumb', '18', 'px'],
        'design.switch.inset' => ['--sw-inset', '3', 'px'],

        // (18) المؤشّر المخصّص: «نقطة 8px … تكبر إلى 42px»
        'design.cursor.dot' => ['--cursor-dot', '8', 'px'],
        'design.cursor.hover' => ['--cursor-hover', '42', 'px'],

        // (20) الحركات الثماني بمُددها المنصوصة — بلا أيّ تغيير عن ما قبل v5.7
        'design.anim.fadeup_ms' => ['--anim-fadeup', '550', 'ms'],
        'design.anim.fadeup_shift' => ['--anim-fadeup-shift', '16', 'px'],
        'design.anim.float_s' => ['--anim-float', '5', 's'],
        'design.anim.float_shift' => ['--anim-float-shift', '4', 'px'],
        'design.anim.text_shimmer_s' => ['--anim-text-shimmer', '5', 's'],
        'design.anim.shimmer_s' => ['--anim-shimmer', '3', 's'],
        'design.anim.pulse_dot_s' => ['--anim-pulse-dot', '2', 's'],
        'design.anim.spin_slow_s' => ['--anim-spin-slow', '11', 's'],
        'design.anim.ticker_s' => ['--anim-ticker', '22', 's'],
        'design.anim.blink_s' => ['--anim-blink', '2', 's'],

        // 2.15-ج: «الحدّ الأدنى لمساحة اللمس 44×44 بكسل»
        'design.touch.min' => ['--touch-min', '44', 'px'],
    ];

    /**
     * المفتاح ⟵ [المتغيّر في CSS، الافتراضيّ الفاتح، الافتراضيّ الداكن].
     * لكلّ دورٍ مفتاحان فعليّان: `{key}` (فاتح) و`{key}.dark` (داكن) —
     * القيم حرفيًّا من 2.10.1-3.
     *
     * @var array<string,array{0:string,1:string,2:string}>
     */
    private const MAP_COLORS = [
        'design.color.bg' => ['--surface', '#fcfbf8', '#191917'],
        'design.color.surface' => ['--surface-raised', '#ffffff', '#22221f'],
        'design.color.soft' => ['--surface-sunken', '#f3efe7', '#2c2b26'],
        'design.color.line' => ['--border', '#dfddd5', '#424139'],
        'design.color.ink' => ['--text', '#171715', '#f7f4ec'],
        'design.color.muted' => ['--text-muted', '#65645f', '#b8b5ab'],
        'design.color.brand' => ['--color-brand-500', '#d9231b', '#f36b60'],
        'design.color.brand_hover' => ['--color-brand-600', '#b61b15', '#ff8277'],
        'design.color.brand_soft' => ['--color-brand-50', '#fbece9', '#352321'],
    ];

    /** مفاتيح تُقرأ رقمًا فيُرفَض ما ليس رقمًا (حمايةٌ من قيمةٍ تكسر الورقة كلّها) */
    private const NUMERIC = [
        '--sw-track-w', '--sw-track-h', '--sw-thumb', '--sw-inset',
        '--cursor-dot', '--cursor-hover',
        '--anim-fadeup', '--anim-fadeup-shift', '--anim-float', '--anim-float-shift',
        '--anim-text-shimmer', '--anim-shimmer', '--anim-pulse-dot',
        '--anim-spin-slow', '--anim-ticker', '--anim-blink', '--touch-min',
    ];

    /**
     * ⭐ مفاتيح التوكنز كما تُقرأ فعلًا — يسجّلها `SettingsCoverage::deadKeys()`
     * فلا يُبلَّغ عنها «ميّتة»: هي مقروءةٌ بمفتاحٍ ثابت داخل `variables()`/
     * `darkVariables()` أعلاه، والماسح النصّيّ لا يرى ثوابت `MAP`/`MAP_COLORS`
     * لأنّها مصفوفاتٌ خاصّة لا استدعاء مباشر.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        $colorKeys = [];

        foreach (array_keys(self::MAP_COLORS) as $key) {
            $colorKeys[] = $key;
            $colorKeys[] = $key.'.dark';
        }

        return [...array_keys(self::MAP), ...$colorKeys];
    }

    /** ينقّي قيمة نصّيّة من محارف تكسر بنية الورقة (حقن CSS) */
    private function sanitize(string $value): string
    {
        return str_replace([';', '}', '{', '<', '>'], '', $value);
    }

    /** @return array<string,string> المتغيّر ⟵ قيمته النهائيّة بلاحقتها (الفاتح + غير اللونيّ) */
    public function variables(): array
    {
        $out = [];

        foreach (self::MAP as $key => [$var, $fallback, $unit]) {
            $value = (string) setting($key, $fallback);

            if ($value === '' || (in_array($var, self::NUMERIC, true) && ! is_numeric($value))) {
                $value = $fallback;
            }

            $out[$var] = $value.$unit;
        }

        foreach (self::MAP_COLORS as $key => [$var, $lightFallback, $darkFallback]) {
            $value = (string) setting($key, $lightFallback);
            $value = $value === '' ? $lightFallback : $this->sanitize($value);
            $out[$var] = $value;
        }

        // موضع الإبهام عند التفعيل مشتقٌّ لا محروق: المسار − الإبهام − الحافّة
        $end = (float) $out['--sw-track-w'] - (float) $out['--sw-thumb'] - (float) $out['--sw-inset'];
        $out['--sw-end'] = $end.'px';

        return $out;
    }

    /** @return array<string,string> المتغيّر ⟵ قيمته الداكنة — توكنز الألوان فقط (2.10.1-3) */
    public function darkVariables(): array
    {
        $out = [];

        foreach (self::MAP_COLORS as $key => [$var, , $darkFallback]) {
            $value = (string) setting($key.'.dark', $darkFallback);
            $out[$var] = $value === '' ? $darkFallback : $this->sanitize($value);
        }

        return $out;
    }

    /** كتلتا `:root { … }` و`:root[data-theme='dark'] { … }` جاهزتان للحقن في `<head>` */
    public function css(): string
    {
        $light = collect($this->variables())
            ->map(fn ($value, $var) => $var.':'.$value)
            ->implode(';');

        $dark = collect($this->darkVariables())
            ->map(fn ($value, $var) => $var.':'.$value)
            ->implode(';');

        return ':root{'.$light.'}:root[data-theme=\'dark\']{'.$dark.'}';
    }

    /** الحدّ الأدنى لمساحة اللمس بالبكسل (2.15-ج) — للاختبارات والمكوّنات */
    public function touchMin(): int
    {
        return (int) (float) $this->variables()['--touch-min'];
    }
}
