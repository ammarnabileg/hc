<?php

namespace Tests\Unit\Services\Certificates;

use App\Services\Certificates\GdEngine;
use Tests\TestCase;

/**
 * ⭐ المحرّك المشترك (12.14 · 12.5-ب): مصمّم الشهادات ومحرّكات الاستوديو
 * كانا يكتبان لون Hex وحلّ قيمة الطبقة كلٌّ بمنطقه الخاصّ — صار مرّةً واحدة
 * هنا، ويثبت هذا الاختبار أنّها **نفس السلوك** لكلا المستهلكَين.
 */
class GdEngineTest extends TestCase
{
    public function test_a_bound_field_that_resolves_empty_hides_the_whole_layer(): void
    {
        $this->assertSame('', GdEngine::layerValue(['text' => 'سابقة', 'field' => 'missing'], []));
        $this->assertSame('', GdEngine::layerValue(['field' => 'missing'], ['missing' => '']));
    }

    public function test_static_text_and_a_resolved_field_join_with_one_space(): void
    {
        $this->assertSame('مرحبًا محمد', GdEngine::layerValue(['text' => 'مرحبًا', 'field' => 'name'], ['name' => 'محمد']));
    }

    public function test_static_text_alone_works_with_no_field_bound(): void
    {
        $this->assertSame('نصّ ثابت', GdEngine::layerValue(['text' => 'نصّ ثابت'], []));
    }

    public function test_field_alone_works_with_no_static_text(): void
    {
        $this->assertSame('محمد', GdEngine::layerValue(['field' => 'name'], ['name' => 'محمد']));
    }

    public function test_six_and_three_digit_hex_colors_are_valid_for_storage_but_no_others(): void
    {
        $this->assertTrue(GdEngine::isValidHex('#e8f5f2'));
        $this->assertFalse(GdEngine::isValidHex('#fff'), 'التخزين يقبل ٦ أرقام فقط لا ٣');
        $this->assertFalse(GdEngine::isValidHex('e8f5f2'), 'لازم # في البداية للتخزين');
        $this->assertFalse(GdEngine::isValidHex('#zzzzzz'));
    }

    public function test_color_allocation_expands_three_digit_hex_and_falls_back_to_white_on_garbage(): void
    {
        $canvas = imagecreatetruecolor(4, 4);

        $short = GdEngine::color($canvas, '#0f0');
        [$r, $g, $b] = array_values(imagecolorsforindex($canvas, $short));
        $this->assertSame([0, 255, 0], [$r, $g, $b]);

        $garbage = GdEngine::color($canvas, 'not-a-color');
        [$r, $g, $b] = array_values(imagecolorsforindex($canvas, $garbage));
        $this->assertSame([255, 255, 255], [$r, $g, $b], 'قيمة تالفة ترتدّ إلى الأبيض بدل كسر الرسم');

        imagedestroy($canvas);
    }

    public function test_font_path_falls_back_through_the_shared_candidate_list_when_preferred_is_missing(): void
    {
        $resolved = GdEngine::fontPath('/no/such/font-file-anywhere.ttf');

        $this->assertNotNull($resolved, 'المرشَّح غير موجود، ولازم يرتدّ لأحد خطوط النظام');
        $this->assertNotSame('/no/such/font-file-anywhere.ttf', $resolved);
        $this->assertFileExists($resolved);
    }
}
