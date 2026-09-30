<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\Blade;

/**
 * رمز التحقّق كخانات منفصلة (`.otp` في ملف الهويّة) بدل حقلٍ واحد بمسافاتٍ بين الحروف،
 * مع بقاء حقلٍ واحد يعمل بلا جافاسكربت.
 */
class OtpInputTest extends UiTestCase
{
    public function test_it_renders_one_box_per_digit_and_a_plain_fallback_field(): void
    {
        $html = Blade::render('<x-otp-input :length="6" />');

        $this->assertSame(6, substr_count($html, 'data-otp-box'));
        $this->assertStringContainsString('name="code"', $html);
        $this->assertStringContainsString('data-otp-input data-length="6"', $html);
        $this->assertStringContainsString('autocomplete="one-time-code"', $html);
        $this->assertStringContainsString('role="group"', $html);
        $this->assertStringContainsString('data-otp-row hidden', $html);
        $this->assertStringContainsString('maxlength="1"', $html);
    }

    public function test_every_verification_screen_uses_the_component(): void
    {
        foreach (['auth/password/sent', 'security/verify-email', 'auth/register/account', 'security/danger-zone', 'events/show'] as $view) {
            $source = file_get_contents(resource_path("views/{$view}.blade.php"));

            $this->assertStringContainsString('<x-otp-input', $source, "{$view} بلا خانات الرمز");
            $this->assertStringNotContainsString('letter-spacing: .5rem', $source, "{$view} ما زال بالحقل الواحد");
        }
    }
}
