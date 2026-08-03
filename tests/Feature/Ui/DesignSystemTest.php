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
     * (10) «المسار `40×22px`/`999px`/`rgba(255,255,255,.15)`؛ عند التفعيل →
     * `--t`. · الإبهام دائرة `16px` بيضاء تنزلق `3px→21px` (RTL عبر
     * `inset-inline-start`)» — وكان السويتش مستبدَلًا بـ`<select>`.
     */
    #[Test]
    public function the_switch_is_a_switch_and_matches_2_10_1_10(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression('/\.hc-switch\s*\{/', $css,
            'مكوّن السويتش (2.10.1-10) غير مبنيّ في نظام التصميم.');

        $vars = app(DesignTokens::class)->variables();
        $this->assertSame('40px', $vars['--sw-track-w'], 'عرض مسار السويتش خالف «40×22px».');
        $this->assertSame('22px', $vars['--sw-track-h'], 'ارتفاع مسار السويتش خالف «40×22px».');
        $this->assertSame('16px', $vars['--sw-thumb'], 'قطر الإبهام خالف «دائرة 16px».');
        $this->assertSame('3px', $vars['--sw-inset'], 'حافّة الإبهام خالفت «تنزلق 3px→21px».');

        // ⭐ 21 **مشتقّة** (40−16−3) لا مكتوبة: لو تغيّر المسار تبعها الإبهام
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

    /** (23) «عرض `5px`، مقبض `--ta20`، مسار `--bg`، `radius:3px`». */
    #[Test]
    public function the_scrollbar_of_2_10_1_23_is_five_pixels(): void
    {
        $css = $this->css();
        $vars = app(DesignTokens::class)->variables();

        $this->assertSame('5px', $vars['--sb-w'], 'عرض شريط التمرير خالف «عرض 5px».');
        $this->assertSame('3px', $vars['--sb-r'], 'نصف قطر المقبض خالف «radius:3px».');

        $this->assertStringContainsString('::-webkit-scrollbar {', $css,
            'شريط التمرير المخصّص (2.10.1-23) غير مبنيّ.');
        $this->assertStringContainsString('inline-size: var(--sb-w', $css,
            'عرض شريط التمرير رقمٌ محروق لا إعداد (2.13-ب).');

        /*
         | ⚠️ القياس على **الكود بلا تعليقات**: أوّل صياغةٍ لهذا الحارس قاست
         | الملفّ خامًا، فكان تعليقُنا الذي يشرح `@supports` **يُرضيه وحده** —
         | حذفتُ القاعدة نفسها ومرّ الاختبار. طفرةٌ تمرّ = اختبارٌ وهميّ.
         */
        $code = $this->code($css);

        /*
         | ⚠️ `scrollbar-width` القياسيّة تعلو على `::-webkit-scrollbar` في كروم،
         | و`thin` عرضٌ ثابت يقرّره المحرّك (~2px) — فوجودها بلا `@supports`
         | يُلغي الخمسة **بصمت**. الحارس يمنع رجوعها.
         */
        $this->assertStringContainsString('@supports not selector(::-webkit-scrollbar)', $code,
            '`scrollbar-width` بلا `@supports` تبتلع عرض 5px في كروم بلا أثر.');

        // ولا `scrollbar-width` خارج ذلك الشرط أبدًا
        $outside = preg_replace('/@supports not selector\(::-webkit-scrollbar\)\s*\{.*?\n  \}/s', '', $code);
        $this->assertStringNotContainsString('scrollbar-width', (string) $outside,
            '`scrollbar-width` خارج `@supports` — تعلو على 5px في كروم وتبتلعها بصمت.');
    }

    /**
     * (7) «**أساسيّ (btn-p):** `linear-gradient(135deg,#2de0ca,#009e85)`، نصّ
     * `#020e18`، `700`، `radius:~.72rem`» — وكان الزرّ **مسطّحًا** بلون واحد.
     */
    #[Test]
    public function the_primary_button_carries_a_gradient_not_a_flat_colour(): void
    {
        $css = $this->css();
        $vars = app(DesignTokens::class)->variables();

        $this->assertSame('linear-gradient(135deg,#2de0ca,#009e85)', $vars['--btn-grad'],
            'تدرّج الزرّ خالف نصّ 2.10.1-7.');
        $this->assertSame('#020e18', $vars['--btn-text'], 'لون نصّ الزرّ خالف «نصّ #020e18».');

        $this->assertStringContainsString('.btn-p, .btn[style*=\'--color-brand-500\']', $css,
            'صنف الزرّ الأساسيّ غير معرَّف — و285 موضعًا تكتب `btn` بلا تعريف.');
        $this->assertStringContainsString('background-image: var(--btn-grad', $css,
            'الزرّ الأساسيّ لا يقرأ تدرّجه من الإعدادات.');
        $this->assertStringContainsString('.btn-g {', $css,
            'زرّ الشبح (btn-g) في 2.10.1-7 غير مبنيّ.');
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
