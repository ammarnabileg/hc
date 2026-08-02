<?php

namespace Tests\Feature\Docs;

use App\Console\Commands\FolderStatusCommand;
use Tests\TestCase;

/**
 * القاعدة الرئيسيّة **2.12** — توثيق التقدّم داخل كلّ مجلّد.
 *
 * قاعدةٌ بلا اختبار تتعفّن: يُنشأ مجلّدٌ جديد بلا وثيقته، أو تُترَك وثيقةٌ قديمة
 * تصف كودًا لم يعد موجودًا. فهذا الاختبار هو الحارس: `docs:status --check`
 * يفشل عند أوّل مخالفة، ويقول أين وكيف تُصلَح.
 */
class FolderStatusTest extends TestCase
{
    private const SECTIONS = [
        '## 🎯 الغرض/المطلوب',
        '## ✅ المُنجَز',
        '## ⬜ المتبقّي',
        '## 🔄 الجاري الآن',
        '## 🔗 التبعيّات والملفّات المهمّة',
        '## 🕒 آخر تحديث',
    ];

    private string $sandbox = 'storage/framework/testing/status-demo';

    protected function tearDown(): void
    {
        $folder = base_path($this->sandbox);

        if (is_dir($folder)) {
            foreach ((array) glob($folder.'/*') as $file) {
                @unlink($file);
            }

            @rmdir($folder);
        }

        parent::tearDown();
    }

    /**
     * ⭐ «يُنشأ الملفّ **فور إنشاء أيّ مجلّد جديد**» — وهذا ما نحرسه هنا.
     *
     * ولماذا لا نحرس **حداثة الجرد** أيضًا في الاختبار؟ لأنّ الجرد يتحرّك مع كلّ
     * ملفٍّ يُضاف، فيصير الاختبار جرسًا يرنّ على عملٍ سليم جارٍ. حداثة الجرد
     * مكانها `php artisan docs:status --check` في الـCI وقبل التسليم — وهي
     * سطرٌ واحد يصلحها: `php artisan docs:status`.
     */
    public function test_every_folder_has_a_progress_document(): void
    {
        $missing = [];

        foreach (FolderStatusCommand::targets() as $folder) {
            if (! is_file(base_path($folder.'/'.FolderStatusCommand::FILE))) {
                $missing[] = $folder;
            }
        }

        $this->assertSame([], $missing, 'مجلّدات بلا وثيقة تقدّم (2.12) — شغّل `php artisan docs:status`.');
    }

    /** والوثيقة تحمل الأقسام الستّة التي نصّت عليها القاعدة حرفيًّا */
    public function test_documents_carry_the_six_required_sections(): void
    {
        foreach (['app/Services/Ads', 'app/Http/Controllers/AdminScreens', 'routes/parts', 'database/seeders'] as $folder) {
            $path = base_path($folder.'/'.FolderStatusCommand::FILE);

            $this->assertFileExists($path);

            $body = (string) file_get_contents($path);

            foreach (self::SECTIONS as $section) {
                $this->assertStringContainsString($section, $body, $folder.' ناقصه قسم '.$section);
            }

            // والجرد حقيقيّ لا حشو: اسم المجلّد نفسه في العنوان
            $this->assertStringContainsString('`'.$folder.'`', $body);
        }
    }

    /** ⭐ ما يكتبه الإنسان لا يمسّه المولِّد: «المتبقّي» و«الجاري الآن» يبقيان */
    public function test_hand_written_blocks_survive_regeneration(): void
    {
        $folder = base_path($this->sandbox);

        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        file_put_contents($folder.'/DemoService.php', "<?php\n\n/** خدمة تجريبيّة للاختبار. */\nclass DemoService {}\n");

        $this->artisan('docs:status --path='.$this->sandbox)->assertSuccessful();

        $path = $folder.'/'.FolderStatusCommand::FILE;
        $this->assertFileExists($path);
        $this->assertStringContainsString('خدمة تجريبيّة للاختبار.', (string) file_get_contents($path));

        // الإنسان يكتب أين وصل
        $edited = str_replace(
            '- **الحالة:** مافيش شغل جارٍ.',
            '- **الحالة:** بنكمّل شاشة التجربة — آخر نقطة: الفلاتر.',
            (string) file_get_contents($path),
        );
        file_put_contents($path, $edited);

        // ثمّ يتغيّر الكود ويُعاد التوليد
        file_put_contents($folder.'/SecondService.php', "<?php\n\n/** خدمة تانية. */\nclass SecondService {}\n");

        $this->artisan('docs:status --path='.$this->sandbox)->assertSuccessful();

        $body = (string) file_get_contents($path);

        $this->assertStringContainsString('بنكمّل شاشة التجربة — آخر نقطة: الفلاتر.', $body, 'كلام الإنسان اتمسح — والمولِّد ممنوع يمسّه.');
        $this->assertStringContainsString('خدمة تانية.', $body, 'الجرد لازم يتحدّث مع الكود.');
    }

    /** ومجلّد بلا وثيقة يكسر الفحص — وإلّا فالقاعدة بلا أسنان */
    public function test_check_fails_for_a_folder_without_a_document(): void
    {
        $folder = base_path($this->sandbox);

        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        file_put_contents($folder.'/DemoService.php', "<?php\n\nclass DemoService {}\n");

        $this->artisan('docs:status --check --path='.$this->sandbox)->assertFailed();
    }
}
