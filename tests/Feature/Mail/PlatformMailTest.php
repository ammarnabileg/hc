<?php

namespace Tests\Feature\Mail;

use App\Mail\PlatformMail;
use App\Services\Security\OtpService;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Ui\UiTestCase;

/**
 * ⛔ ما قبله: رمز التأكيد واسترجاع كلمة السرّ وتذكيرات الفعاليّات كانت تخرج `Mail::raw`
 * نصًّا خامًا بلا هويّة، والمنشورات HTML مبعثرًا داخل الكلاس. الآن قالبٌ واحد RTL بألوان
 * النظام (emails/platform) لكلّ بريد، ومع كلّ رسالة بديلٌ نصّيّ.
 */
class PlatformMailTest extends UiTestCase
{
    public function test_the_shared_template_carries_identity_heading_code_button_and_footer(): void
    {
        $html = (new PlatformMail(
            subjectLine: 'الموضوع',
            heading: 'أهلًا بيك',
            bodyText: "سطر أوّل\nسطر تاني",
            ctaLabel: 'افتح',
            ctaUrl: 'https://example.test/open',
            footer: 'تذييل الرسالة',
            code: '4821',
        ))->render();

        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $html);
        $this->assertStringContainsString(config('app.name'), $html);
        $this->assertStringContainsString('أهلًا بيك', $html);
        $this->assertStringContainsString('سطر أوّل<br />', $html);
        $this->assertStringContainsString('>4821<', $html);
        $this->assertStringContainsString('href="https://example.test/open"', $html);
        $this->assertStringContainsString('>افتح<', $html);
        $this->assertStringContainsString('تذييل الرسالة', $html);
        $this->assertStringContainsString(setting('ux.footer.tagline'), $html);
    }

    public function test_the_plain_text_alternative_lists_the_same_content(): void
    {
        $text = view('emails.platform-text', [
            'subjectLine' => 'س', 'heading' => 'عنوان', 'bodyText' => 'نصّ', 'ctaLabel' => 'افتح',
            'ctaUrl' => 'https://example.test/open', 'footer' => 'ذيل', 'code' => '1234', 'textBody' => null,
        ])->render();

        foreach (['عنوان', 'نصّ', '1234', 'افتح: https://example.test/open', 'ذيل', config('app.name')] as $needle) {
            $this->assertStringContainsString($needle, $text);
        }
    }

    public function test_the_otp_email_uses_the_template_with_the_code_boxed_and_a_text_fallback(): void
    {
        Mail::fake();

        app(OtpService::class)->send('otp@test.local', OtpService::PURPOSE_REGISTER);

        Mail::assertSent(PlatformMail::class, function (PlatformMail $mail) {
            return $mail->code !== null
                && strlen($mail->code) === app(OtpService::class)->length()
                && $mail->heading === setting('auth.otp.subject_register', 'رمز تأكيد بريدك')
                && $mail->textBody !== null
                && str_contains($mail->textBody, $mail->code);
        });
    }

    public function test_the_password_reset_email_uses_the_template_with_one_button(): void
    {
        Mail::fake();

        $user = $this->trainee();
        $this->post(route('password.email'), ['email' => $user->email]);

        Mail::assertSent(PlatformMail::class, function (PlatformMail $mail) {
            return $mail->ctaUrl !== null
                && str_starts_with($mail->ctaUrl, url('/reset-password/'))
                && $mail->ctaLabel === setting('auth.password_reset.mail_cta', 'غيّر كلمة السرّ')
                && str_contains((string) $mail->textBody, $mail->ctaUrl);
        });
    }
    /** لا بريد يخرج خارج القالب الموحّد: Mail::raw و->html() ممنوعان في الكود */
    public function test_no_service_sends_raw_or_ad_hoc_html_mail(): void
    {
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));

        foreach ($iterator as $entry) {
            if (! $entry->isFile() || ! str_ends_with($entry->getFilename(), '.php')) {
                continue;
            }

            $code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', file_get_contents($entry->getPathname()));

            if (preg_match('/Mail::raw\(|->html\(/', $code)) {
                $offenders[] = str_replace(base_path().'/', '', $entry->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'بريد خارج القالب الموحّد في: '.implode(', ', $offenders));
    }
}
