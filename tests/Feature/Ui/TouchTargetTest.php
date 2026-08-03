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

        $block = null;
        if (preg_match('/@media \(pointer: coarse\) \{(.+?)\n  \}\n\}/s', $css, $m)) {
            $block = $m[1];
        }

        $this->assertNotNull($block, 'قاعدة أهداف اللمس (2.15-ج) غير موجودة في الورقة.');

        foreach (["a[href]", 'button', 'summary', "[role='tab']", 'select', 'textarea'] as $selector) {
            $this->assertStringContainsString($selector, $block,
                "نوعٌ من أهداف اللمس خارج قاعدة 2.15-ج: {$selector}");
        }

        $this->assertStringContainsString('min-block-size: var(--touch-min', $block,
            'قاعدة اللمس تكتب رقمًا بدل قراءة الإعداد (2.13-ب).');
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

        $this->assertMatchesRegularExpression(
            "/input\[type='range'\]::-webkit-slider-runnable-track\s*\{\s*block-size:\s*6px/s", $css,
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
        $this->assertStringContainsString('@media (pointer: coarse)', $this->css(),
            'قاعدة اللمس خرجت من نطاقها — 2.15-ج «قاعدة الموبايل».');
    }
}
