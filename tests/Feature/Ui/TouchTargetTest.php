<?php

namespace Tests\Feature\Ui;

use App\Services\Ui\DesignTokens;
use PHPUnit\Framework\Attributes\Test;

/**
 * حارس **أهداف اللمس** (2.15-ج): «**الحدّ الأدنى لمساحة اللمس 44×44 بكسل**،
 * والأزرار الحسّاسة (حذف/رفض) بعيدة عن حواف السحب».
 *
 * ⚠️ **ما لا يقدر عليه هذا الحارس:** الارتفاع الفعليّ لا يُعرَف إلّا من متصفّح.
 * ولذلك يقيس هنا **مصدر المقاس** لا نتيجته: أنّ القاعدة موجودة، وأنّ نطاقها
 * يشمل ما كان خارجه (الروابط و`summary` والحقول)، وأنّ المكوّنات المشتركة
 * تحمل الحدّ الأدنى. والقياس الحقيقيّ جرى بمتصفّحٍ على 24 شاشة: **590 هدفًا
 * تحت المقاس ⟵ صفر** — وهذا الحارس يمنع رجوعها.
 */
class TouchTargetTest extends UiTestCase
{
    private function css(): string
    {
        return (string) file_get_contents(resource_path('css/app.css'));
    }

    /** المقاس **إعدادٌ** لا رقم محروق (2.13-ب) — وقيمته المنصوصة 44. */
    #[Test]
    public function the_minimum_touch_size_is_a_setting_worth_forty_four(): void
    {
        $this->assertSame(44, app(DesignTokens::class)->touchMin(),
            'الحدّ الأدنى لمساحة اللمس خالف نصّ 2.15-ج (44×44).');

        $this->assertSame('44px', app(DesignTokens::class)->variables()['--touch-min']);
    }

    /**
     * ⭐ **نطاق القاعدة**: كانت مقصورةً على `button` و`[role=button]` و`a.btn` —
     * فبقي كلّ رابطٍ في السايد بار **36px** وكلّ `summary` مثله. والقاعدة نصُّها
     * «مساحة اللمس» لا «مساحة الزرّ»: كلّ ما يُضغَط داخلٌ فيها.
     */
    #[Test]
    public function the_rule_covers_links_summaries_and_fields_not_buttons_only(): void
    {
        $css = $this->css();

        /*
         | ⚠️ **لا يكفي أن يرد `a[href]` في مكانٍ ما من الكتلة**: أوّل صياغةٍ
         | لهذا الحارس فعلت ذلك، فحذفتُ الروابط من مجموعة المُحدِّدات ومرّ
         | الاختبار — لأنّ `a[href]:not(.btn)` في قاعدةٍ أخرى أرضته. فالقياس
         | على **مجموعة المُحدِّدات التي تحمل `min-block-size`** نفسها.
         */
        preg_match_all(
            '/([^{}]+?)\{\s*min-block-size:\s*var\(--touch-min[^}]*\}/s',
            $css, $matches, PREG_SET_ORDER);

        $selectors = '';
        foreach ($matches as $rule) {
            $selectors .= ' '.preg_replace('/\s+/', ' ', $rule[1]);
        }

        $this->assertNotSame('', trim($selectors),
            'لا قاعدة واحدة تفرض الحدّ الأدنى لمساحة اللمس (2.15-ج).');

        foreach (['a[href]', 'button', 'summary', "[role='tab']", 'select', 'textarea'] as $selector) {
            $this->assertStringContainsString($selector, $selectors,
                "نوعٌ من أهداف اللمس لا يشمله الحدّ الأدنى في 2.15-ج: {$selector}");
        }
    }

    /**
     * روابط السايد بار: كانت `py-2` مع `text-sm` = **36px** بالضبط. والإصلاح في
     * المكوّن المشترك حتى تصلح سايد بارات المتدرّب والمتطوّع والإدارة **معًا** —
     * فملفّات السايد بار نفسها تحت أيدي إيجنتات أخرى.
     */
    #[Test]
    public function the_shared_nav_components_carry_the_minimum(): void
    {
        foreach (['nav-link', 'nav-group', 'page-header'] as $component) {
            $this->assertStringContainsString(
                'min-block-size: var(--touch-min',
                (string) file_get_contents(resource_path("views/components/{$component}.blade.php")),
                "المكوّن {$component} لا يضمن هدف لمسٍ 44px (2.15-ج).");
        }

        $this->assertStringContainsString('min-inline-size: var(--touch-min',
            (string) file_get_contents(resource_path('views/partials/header.blade.php')),
            'رابط الأفاتار في الهيدر كان 36px عرضًا — و2.15-ج تشترط 44 في البعدين.');
    }

    /**
     * ⭐ **الرابط داخل نصٍّ جارٍ**: رفعُ ارتفاعه بالمقاس يمطّ السطر ويكسر
     * الفقرة. والحلّ المنصوص عليه عمليًّا: توسيع **منطقة اللمس** لا الشكل —
     * وحشوةُ العنصر السطريّ لا تدخل في حساب التخطيط. فالحارس يمنع أن يعود
     * أحدٌ فيحوّلها إلى ارتفاعٍ صريح يكسر الأسطر.
     */
    #[Test]
    public function inline_links_grow_their_hit_area_not_their_line(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression(
            '/a\[href\]:not\(\.btn\)[^{]*\{[^}]*padding-block:\s*calc\(\(var\(--touch-min/s',
            $css,
            'الروابط داخل النصّ لا تُوسَّع منطقةُ لمسها (2.15-ج) — أو وُسِّعت بارتفاعٍ يمطّ السطر.');

        $this->assertMatchesRegularExpression(
            '/a\[href\]:not\(\.btn\)[^{]*\{[^}]*min-inline-size:\s*var\(--touch-min/s',
            $css,
            'الرابط النصّيّ القصير يبقى أضيق من 44px عرضًا.');
    }

    /**
     * ⭐ **المنزلق**: مساره `6px` بنصّ 2.10.1-11 ولا يُغلَّظ. فالهدف يُفصَل عن
     * الشكل — وإلّا وقع الحارسان في تعارض: إمّا منزلقٌ سمين وإمّا إصبعٌ يخطئ.
     */
    #[Test]
    public function the_range_keeps_its_six_pixel_track_while_the_target_grows(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression(
            "/input\[type='range'\]\s*\{\s*block-size:\s*var\(--touch-min/s", $css,
            'هدف لمس المنزلق أقلّ من 44px (2.15-ج).');

        /*
         | ⚠️ القياس **داخل كتلة اللمس وحدها**: في الورقة مساران — الأساس
         | و«اللمس». وأوّل صياغةٍ قاست الورقة كلّها، فغلّظتُ مسار اللمس إلى
         | 44px ومرّ الاختبار لأنّ مسار الأساس أرضاه.
         */
        preg_match('/@media \(pointer: coarse\) \{(.+)\n  \}\n\}/s', $css, $m);
        $coarse = $m[1] ?? '';

        $this->assertMatchesRegularExpression(
            "/input\[type='range'\]::-webkit-slider-runnable-track\s*\{\s*block-size:\s*6px/s", $coarse,
            'مسار المنزلق خالف «height:6px» في 2.10.1-11 — الهدف يكبر والشكل لا.');
    }

    /**
     * ⛔ القاعدة **قاعدة موبايل** بنصّها («ج) قاعدة الموبايل») — فلا تُفرَض على
     * الفأرة: رفعُ كلّ رابطٍ إلى 44px على الديسكتوب يمطّ القوائم بلا سبب.
     * والحارس يمنع الانزلاق إلى تطبيقها على الجميع.
     */
    #[Test]
    public function the_rule_stays_scoped_to_touch_pointers(): void
    {
        /*
         | ⚠️ وجودُ `@media (pointer: coarse)` في الورقة لا يثبت شيئًا (هو موجودٌ
         | للمؤشّر أيضًا). فالحارس ينزع **كلّ** كتل اللمس ثمّ يتأكّد أنّ الباقي
         | **خالٍ** من `min-block-size: var(--touch-min` — أي أنّ القاعدة كلّها
         | داخل نطاقها. وأوّل صياغةٍ مرّت مع مخالفةٍ صريحة.
         */
        $css = $this->css();
        $outside = preg_replace('/@media \(pointer: coarse\) \{.*?\n  \}\n/s', '', $css);

        $this->assertStringNotContainsString('min-block-size: var(--touch-min', (string) $outside,
            'قاعدة اللمس مفروضة على الفأرة أيضًا — و2.15-ج «قاعدة الموبايل» فتمطّ القوائم بلا سبب.');

        $this->assertStringContainsString('@media (pointer: coarse)', $css,
            'قاعدة اللمس خرجت من نطاقها — 2.15-ج «قاعدة الموبايل».');
    }
}
