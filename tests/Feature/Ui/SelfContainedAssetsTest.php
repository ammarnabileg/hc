<?php

namespace Tests\Feature\Ui;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * **لا أصلَ من شبكةٍ خارجيّة** — لا خطّ ولا سكربت ولا ستايل ولا أيقونة.
 *
 * القاعدة مكتوبة في الدستور بوصفها منعَ مكتباتٍ جاهزة (2.16-ج وما حولها)،
 * لكنّ أثرها التشغيليّ أهمّ من صياغتها: أيّ أصلٍ يُجلَب من الخارج **يسقط
 * بصمت** حين يتعذّر الوصول إليه — بلا إنترنت، أو خلف جدارٍ ناريّ، أو لأنّ
 * الخدمة محجوبة في بلد المستخدم — فتظهر الصفحة ناقصةً بلا رسالة خطأ.
 *
 * وأخطر مواضعه **الأوراق التي تُطبَع وتُسلَّم**: السيرة الذاتيّة وشهادة
 * الخبرة. وقد كان كلاهما يجلب خطّ «القاهرة» من CDN بينما الخطّ نفسه مبنيٌّ
 * داخل الحزمة ويستعمله باقي المنصّة — فكان المستند يخرج بخطٍّ بديل، ولا
 * يكتشف ذلك أحدٌ إلّا صاحبُه بعد أن يكون قد أرسله.
 *
 * ⚠️ اكتُشِف هذا في **تشغيلٍ حقيقيّ** بمتصفّح، لا في الاختبارات: الطلب
 * الخارجيّ لا يفشل في بيئة الاختبار أصلًا لأنّ القالب لا يُحمَّل في متصفّح.
 */
class SelfContainedAssetsTest extends TestCase
{
    /** مضيفات يُمنَع الاتّصال بها من أيّ قالب — أشهر ما يُستدعى سهوًا. */
    private const FORBIDDEN = [
        'fonts.bunny.net',
        'fonts.googleapis.com',
        'fonts.gstatic.com',
        'cdn.jsdelivr.net',
        'cdnjs.cloudflare.com',
        'unpkg.com',
        'ajax.googleapis.com',
        'kit.fontawesome.com',
        'use.fontawesome.com',
        'stackpath.bootstrapcdn.com',
    ];

    #[Test]
    public function no_blade_template_pulls_an_asset_from_an_external_host(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $content = (string) file_get_contents($file);

            foreach (self::FORBIDDEN as $host) {
                if (str_contains($content, $host)) {
                    $offenders[] = str_replace(base_path().'/', '', $file).' ⟵ '.$host;
                }
            }
        }

        $this->assertSame([], $offenders,
            'قوالب تجلب أصولًا من الخارج: '.implode(' · ', $offenders));
    }

    /**
     * والوجه الآخر: الخطّ العربيّ **موجودٌ فعلًا** داخل الحزمة. فلولا هذا
     * لكان «احذف رابط الـCDN» يُرضي الحارس الأوّل ويترك الأوراق بلا خطٍّ أصلًا.
     */
    #[Test]
    public function the_arabic_font_is_bundled_locally(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('@fontsource/cairo', $css,
            'خطّ «القاهرة» مش مبنيّ داخل الحزمة — فمنع الـCDN بيسيب الأوراق بلا خطّ.');
    }

    /**
     * والأوراق المطبوعة تحديدًا تُحمِّل ستايل المنصّة — فلا تكتفي بألّا تجلب
     * من الخارج، بل تجلب من الداخل فعلًا.
     */
    #[Test]
    public function the_printed_sheets_load_the_platform_stylesheet(): void
    {
        foreach (['cv/preview.blade.php', 'cv/attestation-sheet.blade.php'] as $sheet) {
            $content = (string) file_get_contents(resource_path('views/'.$sheet));

            $this->assertStringContainsString("@vite(['resources/css/app.css'])", $content,
                $sheet.' مابيحمّلش ستايل المنصّة — فهيخرج بخطّ النظام لا بخطّ الهويّة.');
        }
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
