<?php

namespace Tests\Feature\Ui;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * **2.15-ج — قاعدة الموبايل:** «لا يُعتبَر البند منفَّذًا حتى يعمل على الموبايل
 * **بلا تمرير أفقيّ**».
 *
 * ⚠️ التمرير الأفقيّ نفسه **لا يُقاس إلّا في متصفّح**: هو ناتج تخطيطٍ لا نصٍّ
 * في قالب. وقد وُجِد فعلًا في سبع شاشات بينما كانت 1541 اختبارًا خضراء —
 * فالاختبار هنا لا يدّعي قياسه، بل يحرس **الأنماط التي سبّبته** حتّى لا تعود:
 *
 *  1. صندوق تمريرٍ أفقيّ (`overflow-x-auto`) بلا `min-w-0`: عنصر الشبكة أو
 *     الـFlex افتراضيّه `min-width: auto` — **لا يصغر تحت مقاس محتواه** — فتمدّ
 *     الرقائقُ الصندوقَ فيمدّ الصفحة، ويبقى التمرير الداخليّ حِلْيةً لا تعمل.
 *
 *  2. صفّ الفعل في ترويسة الصفحة بلا `flex-wrap`: الفعل الرئيسيّ مع سويتش
 *     «وضع متقدّم» يتجاوزان 375px.
 *
 * والقياس الحقيقيّ يبقى بالتشغيل: مسحةٌ بمتصفّحٍ على عرض 375px مرّت على 171
 * شاشة وخرجت بصفر فائض.
 */
class MobileLayoutTest extends TestCase
{
    #[Test]
    public function every_horizontal_scroll_container_can_actually_shrink(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            foreach ($this->tagsWith($file, 'overflow-x-auto') as $tag) {
                if (! str_contains($tag, 'min-w-0')) {
                    $offenders[] = str_replace(base_path().'/', '', $file);
                }
            }
        }

        $offenders = array_values(array_unique($offenders));

        $this->assertSame([], $offenders,
            'صناديق تمرير أفقيّ بلا `min-w-0` — التمرير الداخليّ مش هيشتغل والصفحة هتتمدّ: '
            .implode(' · ', $offenders));
    }

    #[Test]
    public function the_page_header_action_row_wraps(): void
    {
        $header = (string) file_get_contents(resource_path('views/components/page-header.blade.php'));

        $this->assertStringContainsString('flex flex-wrap items-center gap-2', $header,
            'صفّ الفعل في الترويسة مابيلتفّش — الفعل + سويتش «وضع متقدّم» بيتجاوزوا عرض الموبايل.');
    }

    /**
     * ورقة A4 (794px) لا تسع شاشة 375px، فلا بدّ من تصغيرها للعرض.
     *
     * والحارس يقيس **وجود آليّة التصغير** لأنّ محاولةً سابقة سقطت صامتة:
     * `calc((100vw - 16px) / 794)` تُنتج **طولًا** لا عددًا، و`scale()` لا تقبل
     * إلّا عددًا — فسقط التحويل وبقي القصّ، أي اختفى نصف الورقة بدل أن تُصغَّر.
     */
    #[Test]
    public function the_a4_sheet_has_a_working_mobile_scale(): void
    {
        $sheet = (string) file_get_contents(resource_path('views/cv/preview.blade.php'));

        $this->assertStringContainsString('scale(var(--sheet-scale))', $sheet,
            'ورقة A4 بلا تصغير — هتطلع بتمرير أفقيّ على الموبايل.');

        $this->assertStringContainsString('--sheet-scale', $sheet);

        // النسبة تُحسَب بجافاسكربت لأنّ CSS لا تقسم طولًا على طول
        $this->assertMatchesRegularExpression('/room\s*\/\s*natural/', $sheet,
            'النسبة مش محسوبة من عرض الشاشة — التصغير هيبقى ثابتًا أو ساقطًا.');

        // والافتراضيّ بلا قصّ: لو تعطّل السكربت نرجع للتمرير لا لورقةٍ ناقصة
        $this->assertStringContainsString('.sheet-scale-wrap { --sheet-scale: 1; }', $sheet);
        $this->assertStringContainsString('.sheet-scale-wrap[data-scaled="true"] { overflow: hidden; }', $sheet,
            'القصّ مش مشروطًا بوقوع تصغير — ده بيخفي محتوى بلا سبب.');
    }

    /**
     * @return list<string>
     */
    private function tagsWith(string $file, string $needle): array
    {
        $content = (string) file_get_contents($file);

        preg_match_all('/<[a-zA-Z][^>]*>/s', $content, $matches);

        return array_values(array_filter($matches[0], fn (string $tag) => str_contains($tag, $needle)));
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
}
