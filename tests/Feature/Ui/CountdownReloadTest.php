<?php

namespace Tests\Feature\Ui;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * حارس **حلقة إعادة التحميل** في عدّاد الإتاحة (الدستور 5).
 *
 * ⛔ ما قبله: السكربت كان يعيد تحميل الصفحة كلّما رأى عدّادًا صفرًا أو سالبًا،
 * ومنها عدّاد **الموعد** على كارت المسار: موعدٌ فات ⟵ إعادة تحميل فور الفتح ⟵
 * صفرٌ من جديد ⟵ إعادة تحميل… بلا نهاية، والصفحة لا تُقرَأ أبدًا.
 *
 * القاعدة: إعادة التحميل مرّةً واحدة وفقط حين يبلغ العدّاد الصفر **أثناء**
 * الزيارة، وعدّاد الموعد يحمل نصّه الخاصّ عند الصفر («فات الموعد»).
 */
class CountdownReloadTest extends TestCase
{
    #[Test]
    public function the_countdown_reloads_only_when_it_crosses_zero_during_the_visit(): void
    {
        $script = (string) file_get_contents(resource_path('views/learning/partials/clock-scripts.blade.php'));

        $this->assertStringContainsString('startedPositive', $script,
            'السكربت لا يميّز عدّادًا وصل صفرًا من الخادم عن عدّادٍ بلغ الصفر أثناء الزيارة.');
        $this->assertStringContainsString('startedPositive && !reloaded', $script,
            'إعادة التحميل يجب أن تُشرَط بأنّ العدّاد بدأ موجبًا ولم يُعَد التحميل قبلًا.');
        $this->assertStringContainsString('node.dataset.availabilityZeroText', $script,
            'نصّ الصفر يجب أن يُقرَأ من الكارت (عدّاد الموعد ليس عدّاد فتح).');
    }

    #[Test]
    public function the_path_deadline_countdown_carries_its_own_zero_text(): void
    {
        $view = (string) file_get_contents(resource_path('views/learning/path.blade.php'));

        $this->assertMatchesRegularExpression(
            '/data-availability-countdown="\{\{ \$row\[\'deadline\'\]\[\'seconds_left\'\] \}\}"\s+data-availability-zero-text=/u',
            $view,
            'عدّاد الموعد على كارت المسار بلا نصّ صفرٍ خاصّ به فيقول «بيفتح دلوقتي» لموعدٍ فات.',
        );
    }
}
