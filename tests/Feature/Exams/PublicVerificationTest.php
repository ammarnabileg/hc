<?php

namespace Tests\Feature\Exams;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Certificates\CertificateIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

/**
 * صفحة التحقّق العامّة (8.1 · 21.2-ز): **بلا تسجيل دخول ولا حساب**.
 */
class PublicVerificationTest extends ExamTestCase
{
    use RefreshDatabase;

    /** تُفتَح بلا تسجيل دخول — لا ريدايركت ولا 401 */
    public function test_verification_page_opens_without_login(): void
    {
        $this->assertGuest();

        $this->get(route('verify.certificate'))
            ->assertOk()
            ->assertSee(setting('certificates.verify.title'), false);
    }

    /** تُفتَح بالكود مباشرةً (من الـQR) والبيانات معروضة بلا حساب */
    public function test_verification_by_code_shows_holder_and_status(): void
    {
        $user = $this->trainee('محمود السيّد');
        $certificate = app(CertificateIssuer::class)->issue($user, 'course');

        $this->assertGuest();

        $this->get(route('verify.certificate', ['code' => $certificate->code]))
            ->assertOk()
            ->assertSee('محمود السيّد', false)
            ->assertSee($certificate->code, false)
            ->assertSee(setting('certificates.status.valid_label'), false)
            ->assertSee(setting('certificates.labels.download_copy', 'تنزيل النسخة'), false)
            ->assertSee(setting('certificates.labels.report', 'أبلغ عن شهادة مشبوهة'), false);
    }

    /** البحث بالكود من نموذج الصفحة نفسه */
    public function test_verification_by_search_query(): void
    {
        $certificate = app(CertificateIssuer::class)->issue($this->trainee(), 'course');

        // الكود بالبادئة `#` كما يكتبه الناس (8.1)
        $this->get(route('verify.certificate').'?code=%23'.$certificate->code)
            ->assertOk()
            ->assertSee($certificate->code, false);

        $this->get(route('verify.certificate').'?code='.$certificate->code)
            ->assertOk()
            ->assertSee($certificate->code, false);
    }

    /** كود غير موجود: رسالة تشرح وتقترح خطوةً بلا لوم (2.17-ب) */
    public function test_unknown_code_shows_a_helpful_message(): void
    {
        $this->get(route('verify.certificate', ['code' => 'HC-2026-999999']))
            ->assertOk()
            ->assertSee(setting('certificates.verify.not_found'), false);
    }

    /** الصفحة مفهرسة باحترام إعداد الفهرسة وبـSchema.org (21.1-هـ · 21.2-ب) */
    public function test_indexing_respects_the_setting_and_emits_schema_org(): void
    {
        $certificate = app(CertificateIssuer::class)->issue($this->trainee(), 'course');

        $this->get(route('verify.certificate', ['code' => $certificate->code]))
            ->assertOk()
            ->assertSee('index, follow', false)
            ->assertSee('EducationalOccupationalCredential', false)
            ->assertSee('application/ld+json', false);

        Setting::updateOrCreate(
            ['key' => 'growth.seo.index_certificates'],
            ['group' => 'growth', 'label_ar' => 'فهرسة صفحات الشهادات', 'type' => 'bool', 'value' => '0', 'default_value' => '1'],
        );
        Cache::forget('settings');

        $this->get(route('verify.certificate', ['code' => $certificate->code]))
            ->assertOk()
            ->assertSee('noindex', false)
            ->assertDontSee('EducationalOccupationalCredential', false);
    }

    /** [أبلغ عن شهادة مشبوهة] يعمل بلا حساب ويُسجَّل في سجلّ التدقيق (12.5-هـ) */
    public function test_reporting_a_suspicious_certificate_without_an_account(): void
    {
        $certificate = app(CertificateIssuer::class)->issue($this->trainee(), 'course');

        $this->assertGuest();

        $this->post(route('verify.certificate.report'), [
            'code' => $certificate->code,
            'note' => 'الاسم على النسخة اللي وصلتني مختلف.',
        ])->assertRedirect(route('verify.certificate', ['code' => $certificate->code]));

        $this->assertDatabaseHas('audit_logs', [
            'action' => setting('certificates.report.audit_action'),
            'auditable_type' => $certificate->getMorphClass(),
            'auditable_id' => $certificate->id,
        ]);

        $this->assertNull(AuditLog::query()->latest('id')->first()->user_id);
    }

    /** تنزيل النسخة متاح من الصفحة العامّة بلا حساب (8.1) */
    public function test_public_download_without_account(): void
    {
        $certificate = app(CertificateIssuer::class)->issue($this->trainee(), 'course');

        $this->get(route('certificates.download', $certificate->code))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }
}
