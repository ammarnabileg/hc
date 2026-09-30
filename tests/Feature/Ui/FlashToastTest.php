<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * رسالة الخادم بعد الفعل: النجاح توستُ المنصّة (كبسولة أسفل الشاشة كالمرجع) لا بطاقة
 * فوق المحتوى، والخطأ/التحذير بطاقة ثابتة فوق النموذج (2.17-ب).
 */
class FlashToastTest extends TestCase
{
    public function test_a_success_flash_renders_as_the_platform_toast(): void
    {
        $html = Blade::render('<x-toast message="اتحفظ ✓" />');

        $this->assertStringContainsString('class="toast show"', $html);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('data-flash-toast', $html);
        $this->assertStringContainsString('اتحفظ ✓', $html);
    }

    public function test_a_danger_flash_stays_an_inline_alert_card(): void
    {
        $html = Blade::render('<x-toast message="حصل خطأ" state="danger" />');

        $this->assertStringNotContainsString('data-flash-toast', $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('class="card p-3 mb-4', $html);
        $this->assertStringContainsString('حصل خطأ', $html);
    }

    public function test_an_empty_message_renders_nothing(): void
    {
        $this->assertSame('', trim(Blade::render('<x-toast message="" />')));
        $this->assertSame('', trim(Blade::render('<x-toast :message="null" />')));
    }
    /** الليَاوت يرسم التوست مرّة؛ صفحةٌ ترسمه ثانيةً تُظهر الرسالة مرّتين */
    public function test_pages_under_a_layout_do_not_render_the_status_flash_themselves(): void
    {
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($iterator as $entry) {
            if (! $entry->isFile() || ! str_ends_with($entry->getFilename(), '.blade.php') || str_contains($entry->getPathname(), '/layouts/')) {
                continue;
            }

            $source = file_get_contents($entry->getPathname());

            if (preg_match("/@extends\('layouts\.(app|admin|volunteer)'/", $source) && str_contains($source, "session('status')")) {
                $offenders[] = str_replace(base_path().'/', '', $entry->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'صفحات ترسم session(status) مع الليَاوت: '.implode(', ', $offenders));
    }
}
