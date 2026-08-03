<?php

namespace Tests\Feature\Ui;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 2.10.1-11 «المنزلقات»: **لا `accent-color`** — نصّ القاعدة يعلّل المنع بأنّها
 * «تترك **إطارًا نايتف** لا يُزال بـ`border:none`»، والمرفوض صراحةً «أيّ
 * border/outline حول المنزلق أو مساره أو مقبضه في كلّ الحالات والمتصفّحات».
 *
 * والمنزلق المخصّص كلّه موجود في `resources/css/app.css` (تدرّج على الخلفيّة
 * يحدّثه `--range-fill` من الـJS + مقبض بلا حدّ) — فأيّ `accent-color` مضافة
 * على `type="range"` تُعيد ما بناه الملفّ لإزالته.
 *
 * ⚠️ القاعدة **مقصورة على المنزلقات**: `accent-color` على الشيك-بوكس والراديو
 * مسموحة ومستعمَلة عمدًا عبر المنصّة — فالحارس يقيس نوع الإنبوت لا مجرّد وجود
 * الكلمة، وإلّا صار يهدم ما لا تمنعه القاعدة.
 */
class SliderIdentityTest extends TestCase
{
    #[Test]
    public function no_range_slider_carries_accent_color(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            foreach ($this->inputTags((string) file_get_contents($file)) as $tag) {
                if (str_contains($tag, 'type="range"') && str_contains($tag, 'accent-color')) {
                    $offenders[] = str_replace(base_path().'/', '', $file);
                }
            }
        }

        $this->assertSame([], array_values(array_unique($offenders)),
            'منزلقات تحمل `accent-color` خلافًا لـ2.10.1-11: '.implode(' · ', array_unique($offenders)));
    }

    /**
     * الوجه الآخر من الحارس: لولاه لكان «احذف `accent-color` من كلّ مكان»
     * يُرضي الاختبار الأوّل وهو يهدم تلوين الشيك-بوكس الذي لا تمنعه أيّ قاعدة.
     */
    #[Test]
    public function checkboxes_keep_their_brand_tint(): void
    {
        $tinted = 0;

        foreach ($this->bladeFiles() as $file) {
            foreach ($this->inputTags((string) file_get_contents($file)) as $tag) {
                if (str_contains($tag, 'type="checkbox"') && str_contains($tag, 'accent-color')) {
                    $tinted++;
                }
            }
        }

        $this->assertGreaterThan(0, $tinted,
            'لا شيك-بوكس واحد ملوَّن بهويّة المنصّة — القاعدة تمنع `accent-color` على المنزلقات وحدها.');
    }

    /** @return list<string> */
    private function bladeFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $entry) {
            if ($entry->isFile() && str_ends_with($entry->getFilename(), '.blade.php')) {
                $files[] = $entry->getPathname();
            }
        }

        return $files;
    }

    /**
     * وسوم `<input …>` كاملةً — القياس على الوسم الواحد لا على الملفّ، وإلّا
     * أدان ملفًّا فيه منزلقٌ نظيف وشيك-بوكس ملوَّن.
     *
     * @return list<string>
     */
    private function inputTags(string $html): array
    {
        preg_match_all('/<input\b[^>]*>/s', $html, $matches);

        return $matches[0];
    }
}
