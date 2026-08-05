<?php

namespace App\Services\Ui;

/**
 * توكنز نظام التصميم (2.10.1) — **من الإعدادات لا من الكود** (2.13-ب).
 *
 * كلّ مقاسٍ ومدّةٍ ولونٍ نصّ عليه 2.10.1 يُكتَب هنا **متغيّرًا في `:root`**
 * تقرأه ورقة الأنماط، فيتغيّر شكل المنصّة من «الهويّة والمظهر» بلا إعادة نشر.
 * والقيمة الافتراضيّة لكلّ مفتاح هي **نصّ الدستور حرفيًّا** — فلو غاب المفتاح
 * (تنصيبٌ قديم قبل بذرة التصميم) بقي الشكل مطابقًا للمنصوص لا فارغًا.
 *
 * ⛔ ولا توجّل هنا للحركة: «الأنيميشن حاضر دائمًا» (2.3 · 2.14-ب) — المتغيّرات
 * تضبط **مدّة** الحركة لا **وجودها**، ولا مفتاح يوقفها.
 */
class DesignTokens
{
    /**
     * المفتاح ⟵ [المتغيّر في CSS، القيمة الافتراضيّة من نصّ 2.10.1، اللاحقة].
     *
     * @var array<string,array{0:string,1:string,2:string}>
     */
    private const MAP = [
        // (10) السويتش: «المسار 40×22px … الإبهام دائرة 16px بيضاء تنزلق 3px→21px»
        'design.switch.track_w' => ['--sw-track-w', '40', 'px'],
        'design.switch.track_h' => ['--sw-track-h', '22', 'px'],
        'design.switch.thumb' => ['--sw-thumb', '16', 'px'],
        'design.switch.inset' => ['--sw-inset', '3', 'px'],

        // (18) المؤشّر المخصّص: «نقطة 8px … تكبر إلى 42px»
        'design.cursor.dot' => ['--cursor-dot', '8', 'px'],
        'design.cursor.hover' => ['--cursor-hover', '42', 'px'],

        // (23) شريط التمرير: «عرض 5px … radius 3px»
        'design.scrollbar.width' => ['--sb-w', '5', 'px'],
        'design.scrollbar.radius' => ['--sb-r', '3', 'px'],

        // (20) الحركات الثماني بمُددها المنصوصة
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

        // (7) الأزرار: التدرّج ونصّه ونصف قطره وسلوك الـHover
        'design.button.gradient' => ['--btn-grad', 'linear-gradient(135deg,#2de0ca,#009e85)', ''],
        'design.button.text' => ['--btn-text', '#020e18', ''],
        'design.button.radius' => ['--btn-radius', '0.72', 'rem'],
        'design.button.hover_opacity' => ['--btn-hover-opacity', '0.9', ''],
        'design.button.hover_scale' => ['--btn-hover-scale', '1.015', ''],

        // (3) تدرّج الإبراز — للنصوص المميّزة وأشرطة التقدّم
        'design.accent.gradient' => ['--accent-grad', 'linear-gradient(135deg,#2de0ca 0%,#00d4b8 55%,#009e85 100%)', ''],

        // 2.15-ج: «الحدّ الأدنى لمساحة اللمس 44×44 بكسل»
        'design.touch.min' => ['--touch-min', '44', 'px'],
    ];

    /** مفاتيح تُقرأ رقمًا فيُرفَض ما ليس رقمًا (حمايةٌ من قيمةٍ تكسر الورقة كلّها) */
    private const NUMERIC = [
        '--sw-track-w', '--sw-track-h', '--sw-thumb', '--sw-inset',
        '--cursor-dot', '--cursor-hover', '--sb-w', '--sb-r',
        '--anim-fadeup', '--anim-fadeup-shift', '--anim-float', '--anim-float-shift',
        '--anim-text-shimmer', '--anim-shimmer', '--anim-pulse-dot',
        '--anim-spin-slow', '--anim-ticker', '--anim-blink',
        '--btn-radius', '--btn-hover-opacity', '--btn-hover-scale', '--touch-min',
    ];

    /**
     * ⭐ مفاتيح التوكنز كما تُقرأ فعلًا — يسجّلها `SettingsCoverage::deadKeys()`
     * فلا يُبلَّغ عنها «ميّتة»: هي مقروءةٌ بمفتاحٍ ثابت داخل `variables()` أعلاه،
     * والماسح النصّيّ لا يرى ثوابت `MAP` لأنّها مصفوفة خاصّة لا استدعاء مباشر.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::MAP);
    }

    /** @return array<string,string> المتغيّر ⟵ قيمته النهائيّة بلاحقتها */
    public function variables(): array
    {
        $out = [];

        foreach (self::MAP as $key => [$var, $fallback, $unit]) {
            $value = (string) setting($key, $fallback);

            if ($value === '') {
                $value = $fallback;
            }

            if (in_array($var, self::NUMERIC, true) && ! is_numeric($value)) {
                $value = $fallback;
            }

            /*
             | قيمة اللون/التدرّج تأتي من الأدمن، فتُنقّى من `;` و`}` حتى لا يخرج
             | نصٌّ من قيمة الخاصّيّة إلى بنية الورقة نفسها (حقن CSS).
             */
            if (! in_array($var, self::NUMERIC, true)) {
                $value = str_replace([';', '}', '{', '<', '>'], '', $value);
            }

            $out[$var] = $value.$unit;
        }

        // موضع الإبهام عند التفعيل مشتقٌّ لا محروق: المسار − الإبهام − الحافّة
        $end = (float) $out['--sw-track-w'] - (float) $out['--sw-thumb'] - (float) $out['--sw-inset'];
        $out['--sw-end'] = $end.'px';

        return $out;
    }

    /** كتلة `:root { … }` جاهزة للحقن في `<head>` */
    public function css(): string
    {
        $body = collect($this->variables())
            ->map(fn ($value, $var) => $var.':'.$value)
            ->implode(';');

        return ':root{'.$body.'}';
    }

    /** الحدّ الأدنى لمساحة اللمس بالبكسل (2.15-ج) — للاختبارات والمكوّنات */
    public function touchMin(): int
    {
        return (int) (float) $this->variables()['--touch-min'];
    }
}
