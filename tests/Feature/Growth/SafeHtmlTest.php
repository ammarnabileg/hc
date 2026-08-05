<?php

namespace Tests\Feature\Growth;

use App\Services\Growth\SafeHtml;

/**
 * تنقية محتوى المقال (21.2-أ) — القاعدة الحاكمة **لكلّ سياق**:
 *
 *  - **وسم تنسيق مسموح** (`<b>` مثلًا) يبقى كما هو — بل هذا الغرض من «الحصر
 *    الإيجابيّ» أصلًا: لا يُنزَع، ونصّه يظهر معه.
 *  - **وسم تنسيق غير مسموح** (`<div>` مثلًا) يُنزَع الوسمُ ويبقى **نصّه** —
 *    هذا صحيح، لأنّ محتواه نصٌّ عاديّ لا كود.
 *  - **⭐ السكربت/الستايل يُنزَعان بمحتواهما معًا** — لأنّ محتواهما **كودٌ لا
 *    نصّ**، وإبقاؤه ظاهرًا (`<script>alert(1)</script>` ⟵ `alert(1)`) كان
 *    الثغرة المرصودة: نصٌّ تنفيذيّ يظهر حرفيًّا على صفحة عامّة بلا تسجيل.
 */
class SafeHtmlTest extends GrowthTestCase
{
    public function test_allowed_formatting_tag_is_kept_with_its_text(): void
    {
        $out = app(SafeHtml::class)->clean('<b>مهمّ</b>');

        $this->assertStringContainsString('مهمّ', $out);
        $this->assertStringContainsString('<b>', $out);
    }

    /** وسم تنسيق غير مسموح: الوسم يُنزَع ونصّه العاديّ يبقى — سلوكٌ صحيح لا ثغرة */
    public function test_disallowed_formatting_tag_is_stripped_but_its_text_survives(): void
    {
        $out = app(SafeHtml::class)->clean('<span>نص عاديّ</span>');

        $this->assertSame('نص عاديّ', $out);
    }

    /** ⭐ الحالة الحرجة: السكربت يخرج فارغًا تمامًا — لا الوسم ولا محتواه */
    public function test_script_tag_is_removed_with_its_content(): void
    {
        $out = app(SafeHtml::class)->clean('<script>alert(1)</script>');

        $this->assertSame('', $out);
        $this->assertStringNotContainsString('alert(1)', $out);
    }

    /** ⭐ نفس القاعدة على الستايل — محتواه كودٌ لا نصٌّ يُعرَض */
    public function test_style_tag_is_removed_with_its_content(): void
    {
        $out = app(SafeHtml::class)->clean('<style>body{display:none}</style>مرحبا');

        $this->assertSame('مرحبا', $out);
        $this->assertStringNotContainsString('display:none', $out);
    }

    /** سكربت مضمَّن وسط فقرة — يُنزَع هو وحده، وما حوله من نصٍّ مسموح يبقى */
    public function test_embedded_script_is_removed_without_touching_surrounding_text(): void
    {
        $out = app(SafeHtml::class)->clean('<p>قبل<script>evil()</script>بعد</p>');

        $this->assertSame('<p>قبلبعد</p>', $out);
        $this->assertStringNotContainsString('evil()', $out);
    }

    /** سكربتٌ بلا وسم إغلاق (محاولة تحايل) — يُحذَف هو وما بعده من نفس المحاولة */
    public function test_unclosed_script_tag_does_not_leak_its_content(): void
    {
        $out = app(SafeHtml::class)->clean('<p>نص آمن</p><script>alert(document.cookie)');

        $this->assertStringNotContainsString('alert', $out);
        $this->assertStringNotContainsString('document.cookie', $out);
        $this->assertStringContainsString('نص آمن', $out);
    }
}
