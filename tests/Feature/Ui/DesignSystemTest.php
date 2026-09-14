<?php

namespace Tests\Feature\Ui;

use App\Services\Ui\DesignTokens;
use PHPUnit\Framework\Attributes\Test;

/**
 * حرّاس **نظام التصميم 2.10.1** — البنود التي كانت غير مبنيّة (د-12).
 *
 * كلّ حارسٍ هنا يقيس **ما تشحنه الورقة فعلًا** (`resources/css/app.css`) وما
 * يخرج من `DesignTokens`، لا ما تقوله التعليقات. والقيمة المتوقَّعة في كلّ
 * تأكيدٍ **منقولةٌ من نصّ الدستور حرفيًّا** ومكتوبةٌ في رسالة الفشل، حتى يعرف
 * مَن يكسره **أيّ بندٍ كسر** لا أنّ «اختبارًا سقط».
 */
class DesignSystemTest extends UiTestCase
{
    private function css(): string
    {
        return (string) file_get_contents(resource_path('css/app.css'));
    }

    private function js(): string
    {
        return (string) file_get_contents(resource_path('js/app.js'));
    }

    /**
     * ⚠️ الكود **بلا تعليقات**.
     *
     * تعليقاتنا تشرح المرفوض بالاسم («لا `prefers-reduced-motion`»، «لا
     * `<select>` للسويتش») — فقياسُ الملفّ خامًا يُدين الشرحَ بدل المخالفة،
     * ويدفع الكاتبَ إلى **حذف الشرح** ليمرّ الاختبار. والقياس على الكود وحده.
     */
    private function code(string $source): string
    {
        $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);   // CSS · JS · @php
        $source = (string) preg_replace('#\{\{--.*?--\}\}#s', '', $source); // Blade
        $source = (string) preg_replace('#(?<![:\'"])//[^\n]*#', '', $source); // JS سطريّ

        return $source;
    }

    /**
     * (20) «`fadeup` .55s ease (ظهور 16px) · `float` 5s (طفوّ −4px) ·
     * `text-shimmer/shimmer` 5s/3s (لمعان) · `pulse-dot` 2s · `spin-slow` 11s ·
     * `ticker` 22s · `blink` 2s» — **ثمانٍ**، وكان المبنيّ اثنتين.
     */
    #[Test]
    public function all_eight_animations_of_2_10_1_20_exist(): void
    {
        $css = $this->css();
        $missing = [];

        foreach (['fadeup', 'float', 'shimmer', 'text-shimmer', 'pulse-dot', 'spin-slow', 'ticker', 'blink'] as $name) {
            if (! preg_match('/@keyframes\s+'.preg_quote($name, '/').'\s*\{/', $css)) {
                $missing[] = '@keyframes '.$name;
            }

            if (! preg_match('/\.animate-'.preg_quote($name, '/').'\s*\{/', $css)) {
                $missing[] = '.animate-'.$name;
            }
        }

        $this->assertSame([], $missing,
            'حركات 2.10.1-20 الثماني ناقصة: '.implode(' · ', $missing));
    }

    /**
     * ⭐ إحدى عشرة حركةً جديدة (الهويّة 2.0، v5.7 — 2.10.1-20): `enter` ·
     * `stagger` · `tabline` · `dropdown` · `unlock` · `check-draw` ·
     * `xp-float` · `shake` · `flap` · `letter` — كلّها بمُددٍ ثابتةٍ حرفيّةٍ
     * من نصّ الدستور (لم يُنَصّ على جعلها إعداداتٍ كالثماني الأصليّة).
     */
    #[Test]
    public function the_eleven_new_animations_of_the_identity_2_0_exist(): void
    {
        $css = $this->css();

        $expected = [
            '.animate-enter' => '220ms',
            '.animate-stagger' => null, // مركّبة: fadeup + تأخير تراكميّ، لا مدّة واحدة
            '.animate-tabline' => '220ms',
            '.animate-dropdown' => '220ms',
            '.animate-unlock' => '550ms',
            '.animate-check-draw' => '500ms',
            '.animate-xp-float' => '1000ms',
            '.animate-shake' => '400ms',
            '.animate-flap' => '700ms',
            '.animate-letter' => '900ms',
        ];

        $missing = [];

        foreach ($expected as $class => $duration) {
            if (! str_contains($css, $class.' {') && ! preg_match('/'.preg_quote($class, '/').'(\s|,|>)/', $css)) {
                $missing[] = $class;

                continue;
            }

            if ($duration !== null && ! str_contains($css, $duration)) {
                $missing[] = "{$class} (بمدّة {$duration})";
            }
        }

        $this->assertSame([], $missing,
            'حركات الهويّة 2.0 الجديدة ناقصة أو بمدّةٍ مخالفة: '.implode(' · ', $missing));
    }

    /** ومُدد الحركات **من الإعدادات** بقيمها المنصوصة — لا رقم محروق (2.13-ب). */
    #[Test]
    public function animation_durations_match_the_constitution(): void
    {
        $vars = app(DesignTokens::class)->variables();

        $expected = [
            '--anim-fadeup' => '550ms',        // «.55s»
            '--anim-fadeup-shift' => '16px',   // «ظهور 16px»
            '--anim-float' => '5s',
            '--anim-float-shift' => '4px',     // «طفوّ −4px»
            '--anim-text-shimmer' => '5s',
            '--anim-shimmer' => '3s',
            '--anim-pulse-dot' => '2s',
            '--anim-spin-slow' => '11s',
            '--anim-ticker' => '22s',
            '--anim-blink' => '2s',
        ];

        foreach ($expected as $var => $value) {
            $this->assertSame($value, $vars[$var] ?? null,
                "مدّة الحركة {$var} خالفت نصّ 2.10.1-20 (المنصوص {$value}).");
        }

        // والورقة تقرأ المتغيّر لا الرقم: لو انقطع الخيط سقط الإعداد بلا أثر
        $this->assertStringContainsString('animation: fadeup var(--anim-fadeup', $this->css(),
            'حركة fadeup لا تقرأ مدّتها من الإعدادات — رقمٌ محروق (2.13-ب).');
    }

    /**
     * (10) الهويّة 2.0 (v5.7): «المسار `42×24px`/`999px`/خلفيّة `--line`؛ عند
     * التفعيل → `--brand`. · الإبهام دائرة `18px` بيضاء تنزلق `3px→21px`
     * (RTL عبر `inset-inline-start`)» — وكان السويتش مستبدَلًا بـ`<select>`.
     */
    #[Test]
    public function the_switch_is_a_switch_and_matches_2_10_1_10(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression('/\.hc-switch\s*\{/', $css,
            'مكوّن السويتش (2.10.1-10) غير مبنيّ في نظام التصميم.');

        $vars = app(DesignTokens::class)->variables();
        $this->assertSame('42px', $vars['--sw-track-w'], 'عرض مسار السويتش خالف «42×24px» (الهويّة 2.0).');
        $this->assertSame('24px', $vars['--sw-track-h'], 'ارتفاع مسار السويتش خالف «42×24px» (الهويّة 2.0).');
        $this->assertSame('18px', $vars['--sw-thumb'], 'قطر الإبهام خالف «دائرة 18px» (الهويّة 2.0).');
        $this->assertSame('3px', $vars['--sw-inset'], 'حافّة الإبهام خالفت «تنزلق 3px→21px».');

        // ⭐ 21 **مشتقّة** (42−18−3) لا مكتوبة: لو تغيّر المسار تبعها الإبهام
        $this->assertSame('21px', $vars['--sw-end'],
            'موضع الإبهام عند التفعيل خالف «3px→21px» — ولا بدّ أن يكون مشتقًّا من المسار.');

        // RTL بـ`inset-inline-start` لا `left` — وإلّا انقلب السويتش في العربيّ
        $this->assertStringContainsString('inset-inline-start: var(--sw-end', $css,
            'انزلاق الإبهام لا يتبع اتّجاه الصفحة (2.10.1-10 ينصّ على `inset-inline-start`).');

        // ومكوّن بليد حقيقيّ لا `<select>` بخيارَي «مفعَّل/موقوف»
        $blade = $this->code((string) file_get_contents(resource_path('views/components/switch.blade.php')));
        $this->assertStringContainsString('type="checkbox"', $blade,
            'السويتش ليس سويتشًا — الحالة من اثنتين تُقلَب بضغطة، لا قائمةً تُفتَح.');
        $this->assertStringNotContainsString('<select', $blade,
            'السويتش مبنيٌّ بـ`<select>` خلافًا لشكل 2.10.1-10.');
    }

    /**
     * (18) «نقطة `8px` تركوازيّة تكبر إلى `42px` (شفّافة بحدّ) فوق العناصر
     * التفاعليّة بانتقال `cubic-bezier(.22,1,.36,1)`؛ **تُخفى على اللمس**».
     */
    #[Test]
    public function the_custom_cursor_of_2_10_1_18_is_built(): void
    {
        $css = $this->css();
        $js = $this->js();

        $this->assertMatchesRegularExpression('/\.hc-cursor\s*\{/', $css,
            'المؤشّر المخصّص (2.10.1-18) غير مبنيّ.');

        $vars = app(DesignTokens::class)->variables();
        $this->assertSame('8px', $vars['--cursor-dot'], 'قطر النقطة خالف «نقطة 8px».');
        $this->assertSame('42px', $vars['--cursor-hover'], 'قطر المؤشّر فوق التفاعليّ خالف «تكبر إلى 42px».');

        // «شفّافة بحدّ» فوق العناصر التفاعليّة
        $this->assertMatchesRegularExpression(
            '/\.hc-cursor\[data-hot=.1.\][^}]*background:\s*transparent/s', $css,
            'المؤشّر لا يصير «شفّافًا بحدّ» فوق العناصر التفاعليّة (2.10.1-18).');

        // ⭐ «تُخفى على اللمس» — والعنصر لا يُنشَأ أصلًا بلا فأرة
        $this->assertStringContainsString("matchMedia?.('(pointer: fine)')", $js,
            'المؤشّر يُبنى على اللمس أيضًا — و2.10.1-18 «الفأرة فقط … تُخفى على اللمس».');
        $this->assertMatchesRegularExpression('/@media\s*\(pointer:\s*coarse\)\s*\{\s*\.hc-cursor\s*\{\s*display:\s*none/s', $css,
            'لا حاجز ثانٍ يخفي المؤشّر على اللمس.');
    }

    /**
     * (23) الهويّة 2.0 (v5.7): «أصليّ رفيع فقط — `scrollbar-width:thin` +
     * `scrollbar-color:var(--line) transparent`» — **يُلغى** الشريط المرسوم
     * يدويًّا (5px/`::-webkit-scrollbar`) الذي كان قبلها.
     */
    #[Test]
    public function the_scrollbar_of_2_10_1_23_is_native_thin(): void
    {
        $css = $this->css();

        $this->assertStringContainsString('scrollbar-width: thin', $css,
            'شريط التمرير الأصليّ الرفيع (2.10.1-23، الهويّة 2.0) غير مبنيّ.');
        $this->assertStringContainsString('scrollbar-color: var(--line) transparent', $css,
            'لون شريط التمرير الأصليّ لا يقرأ `--line` (2.10.1-23).');

        // ⛔ الشريط المرسوم يدويًّا (القديم) أُلغي بالكامل — لا ::-webkit-scrollbar بعد الآن
        // (القياس على الكود بلا تعليقات: شرح الإلغاء نفسه يذكر الاسم القديم)
        $this->assertStringNotContainsString('::-webkit-scrollbar', $this->code($css),
            'شريط تمرير مخصّص (`::-webkit-scrollbar`) ما زال موجودًا رغم إلغائه في الهويّة 2.0 (2.10.1-23).');

        // والتوكنان القديمان (`--sb-w`/`--sb-r`) خرجا من DesignTokens تمامًا
        $this->assertArrayNotHasKey('--sb-w', app(DesignTokens::class)->variables(),
            '`--sb-w` ما زال توكنًا حيًّا رغم إلغاء الشريط المخصّص.');
    }

    /**
     * (7) الهويّة 2.0 (v5.7): «**أساسيّ:** خلفيّة صلبة `--brand` (لا تدرّج)،
     * نصّ أبيض في الفاتح، وزن `600`، ارتفاع أدنى `48px`، راديوس `8px`.
     * Hover: خلفيّة `--brand-hover`» — عكس نصّ ما قبلها الذي كان يفرض تدرّجًا.
     */
    #[Test]
    public function the_primary_button_is_flat_not_a_gradient(): void
    {
        $css = $this->css();

        $this->assertStringContainsString('.btn-p, .btn[style*=\'--color-brand-500\']', $css,
            'صنف الزرّ الأساسيّ غير معرَّف — و337 موضعًا تكتب `btn` بلا تعريف.');
        $this->assertStringContainsString('background-color: var(--brand)', $css,
            'الزرّ الأساسيّ لا يقرأ لونًا صلبًا من `--brand` (2.10.1-7، الهويّة 2.0).');
        $this->assertStringContainsString('background-color: var(--brand-hover)', $css,
            'هوفر الزرّ الأساسيّ لا يبدّل إلى `--brand-hover` (2.10.1-7).');

        // ⛔ لا تدرّج بعد الآن على الزرّ الأساسيّ — `background-image: none` تفريغٌ متعمَّد لا تدرّج
        $btnBlock = substr($css, (int) strpos($css, ".btn-p, .btn[style*='--color-brand-500']"), 400);
        $this->assertStringNotContainsString('linear-gradient', $btnBlock,
            'الزرّ الأساسيّ ما زال يحمل تدرّجًا (`linear-gradient`) رغم إلغائه في الهويّة 2.0.');

        $this->assertStringContainsString('.btn-g {', $css,
            'زرّ الشبح (btn-g) في 2.10.1-7 غير مبنيّ.');
        $this->assertStringContainsString('border: 1px solid var(--line)', $css,
            'زرّ الشبح لا يحمل حدّ `--line` (2.10.1-7، الهويّة 2.0).');
    }

    /**
     * ⭐ ألوان الهويّة الأساسيّة (2.10.1-3) مزدوجةٌ فاتح/داكن — من الإعدادات
     * لا رقمًا محروقًا (2.13-ب)، وتخرج في كتلتين منفصلتين من `DesignTokens`.
     */
    #[Test]
    public function identity_colours_are_settings_driven_for_both_modes(): void
    {
        $tokens = app(DesignTokens::class);

        $light = $tokens->variables();
        $dark = $tokens->darkVariables();

        $this->assertSame('#d9231b', $light['--color-brand-500'], 'أحمر الهويّة الفاتح خالف 2.10.1-3.');
        $this->assertSame('#f36b60', $dark['--color-brand-500'], 'أحمر الهويّة الداكن خالف 2.10.1-3.');
        $this->assertSame('#fcfbf8', $light['--surface'], 'خلفيّة الصفحة الفاتحة خالفت 2.10.1-3.');
        $this->assertSame('#191917', $dark['--surface'], 'خلفيّة الصفحة الداكنة خالفت 2.10.1-3.');

        $css = $this->css();
        $this->assertStringContainsString(":root[data-theme='dark']", $css,
            'لا كتلة تُحدّث ألوان الوضع الداكن — الهويّة 2.0 تفترض الفاتح لا الداكن.');
    }

    /** التوكنز تُحقَن في كلّ لياوت — وإلّا بقيت الورقة على قيمها الاحتياطيّة. */
    #[Test]
    public function every_layout_injects_the_design_tokens(): void
    {
        foreach (['app', 'admin', 'guest', 'volunteer'] as $layout) {
            $this->assertStringContainsString(
                "@include('partials.design-tokens')",
                (string) file_get_contents(resource_path("views/layouts/{$layout}.blade.php")),
                "لياوت {$layout} لا يحقن توكنز 2.10.1 — فالإعدادات لا تصل إليه.");
        }
    }

    /**
     * ⛔ **لا توجّل للأنيميشن ولا `prefers-reduced-motion`** (2.3 · 2.14-ب):
     * «الأنيميشن حاضر دائمًا لأنّه روح المنصّة». والبناء الجديد أضاف عشر مُدد
     * قابلة للتعديل — فالحارس يمنع أن تتحوّل إحداها إلى **مفتاح إطفاء**.
     */
    #[Test]
    public function nothing_can_switch_the_animation_off(): void
    {
        foreach ([$this->code($this->css()), $this->code($this->js())] as $source) {
            $this->assertStringNotContainsString('prefers-reduced-motion', $source,
                '`prefers-reduced-motion` مرفوضٌ بالاسم في 2.3.');
        }

        $tokens = array_keys(app(DesignTokens::class)->variables());
        foreach ($tokens as $var) {
            $this->assertStringNotContainsString('enabled', $var,
                "التوكن {$var} يبدو مفتاح تفعيل — والأنيميشن حاضر دائمًا (2.14-ب).");
        }
    }
}
