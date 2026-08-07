<?php

namespace Tests\Unit\Services\Images;

use App\Services\Certificates\ArabicText;
use App\Services\Images\Concerns\DrawsWithGd;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ⭐ 12.14 «صفر ازدواج»: استوديو الصور لا يحمل خوارزميّة تشكيلٍ عربيّ خاصّة
 * به — يستهلك محرّك مصمّم الشهادات (12.5-ب) الذي نصّت المادّة أنّه المرجعيّ.
 * كان الاستوديو يحمل نسخته المستقلّة (`ArabicShaper`)، فيُخرج شكلًا مختلفًا
 * عن الشهادات لنفس الكلمة على نفس الخطّ — وهو عين ما مُنِع صراحةً.
 */
class ArabicShapingEngineUnificationTest extends TestCase
{
    private function shaper(): object
    {
        return new class
        {
            use DrawsWithGd;
        };
    }

    #[Test]
    public function drawing_with_gd_produces_the_exact_same_shaped_output_as_the_certificate_engine(): void
    {
        $samples = ['محمد أحمد', 'شهادة إتمام', 'مرحبًا 2026', 'Ahmed محمد', ''];

        foreach ($samples as $sample) {
            $this->assertSame(
                ArabicText::prepare($sample),
                $this->invokeShapeRtl($sample),
                "التشكيل اختلف بين الاستوديو والشهادات للعيّنة: \"{$sample}\"",
            );
        }
    }

    #[Test]
    public function drawing_with_gd_no_longer_references_the_duplicate_shaper(): void
    {
        $source = file_get_contents(app_path('Services/Images/Concerns/DrawsWithGd.php'));

        $this->assertStringNotContainsString('ArabicShaper', $source);
        $this->assertStringNotContainsString('TrueTypeFont', $source);
        $this->assertStringContainsString('ArabicText', $source);
    }

    private function invokeShapeRtl(string $text): string
    {
        $reflection = new \ReflectionMethod($this->shaper(), 'shapeRtl');
        $reflection->setAccessible(true);

        return $reflection->invoke($this->shaper(), $text);
    }
}
