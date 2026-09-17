<?php

namespace Tests\Feature\Ui;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * صيغة العدد بالعربيّة أربعٌ لا اثنتان — والخطأ الشائع كتابة «4 مهمّة».
 *
 * ⚠️ صوابها **«4 مهامّ»**: من 3 إلى 10 جمعُ قلّة، وما فوقها يعود إلى المفرد
 * المنصوب («11 مهمّة»). وكانت شاشة الأهداف تطبع العدد ثمّ صيغةً واحدة مهما
 * كان الرقم، فتخرج «4 مهمّة».
 */
class ArabicCountTest extends TestCase
{
    private function forms(): array
    {
        return ['one' => 'مهمّة واحدة', 'two' => 'مهمّتان', 'few' => ':n مهامّ', 'many' => ':n مهمّة'];
    }

    #[Test]
    public function it_picks_the_right_form_for_each_range(): void
    {
        $this->assertSame('مهمّة واحدة', ar_count(1, $this->forms()));
        $this->assertSame('مهمّتان', ar_count(2, $this->forms()));

        // جمع القلّة: من 3 إلى 10
        foreach ([3, 4, 7, 10] as $n) {
            $this->assertSame("{$n} مهامّ", ar_count($n, $this->forms()),
                "العدد {$n} يأخذ جمع القلّة «مهامّ» لا المفرد.");
        }

        // جمع الكثرة: 11 فأكثر يعود إلى المفرد
        foreach ([11, 25, 100] as $n) {
            $this->assertSame("{$n} مهمّة", ar_count($n, $this->forms()));
        }
    }

    /** الصفر يقع في صيغة الكثرة، ولا يُترَك بلا صيغة فيطبع رقمًا عاريًا. */
    #[Test]
    public function zero_still_gets_a_form(): void
    {
        $this->assertSame('0 مهمّة', ar_count(0, $this->forms()));
    }

    /** صيغةٌ ناقصة لا تُسقِط الشاشة — ترجع نصًّا فارغًا لا خطأ. */
    #[Test]
    public function a_missing_form_does_not_throw(): void
    {
        $this->assertSame('', ar_count(5, ['one' => 'واحدة']));
    }
}
