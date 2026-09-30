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
}
