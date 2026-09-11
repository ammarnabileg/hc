<?php

namespace Tests\Feature\Certificates;

use App\Services\Certificates\CertificateIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Exams\ExamTestCase;

/**
 * 🗺️ خلفيّة زخرفيّة صرفة: خريطة عالم منقّطة بنقاطٍ نابضة في ترويسة صفحة
 * التحقّق (8.1) — عنصرٌ تصميميّ بلا منطق ولا نصّ، فالاختبار يثبت أمرَين فقط:
 * 1) العنصر الزخرفيّ موجودٌ ومخفيٌّ عن قارئات الشاشة (aria-hidden).
 * 2) وجوده **لا يمسّ** مسار التحقّق الوظيفيّ — نفس المحتوى الذي كان يظهر
 *    قبل الإضافة (حقل الكود، بطاقة النتيجة، بيانات الشهادة) ما زال يظهر.
 */
class CertificateVerifyDottedMapTest extends ExamTestCase
{
    use RefreshDatabase;

    /** الصفحة الفارغة (بلا بحث) لسّه بترجع 200 وفيها الخلفيّة الزخرفيّة */
    public function test_the_decorative_dotted_map_renders_on_the_empty_verify_page(): void
    {
        $response = $this->get(route('verify.certificate'))->assertOk();

        // توقيعٌ فريد لعنصر النبض المنقول حرفيًّا من المرجع (class="pulse")
        $response->assertSee('class="pulse 1"', false);
        $response->assertSee('class="pulse 14"', false);

        // زخرفيّ بحت: مخفيٌّ عن قارئات الشاشة
        $html = $response->getContent();
        $this->assertStringContainsString('pointer-events-none absolute inset-0 -z-10', $html);
        $this->assertMatchesRegularExpression('/<div class="mx-auto container" aria-hidden="true">/', $html);

        // ولا يزال حقل البحث بالكود موجودًا كما كان
        $response->assertSee(setting('certificates.verify.placeholder'), false);
    }

    /** وصفحة شهادةٍ حقيقيّة صالحة: الخلفيّة موجودة **ولا تكسر** عرض بياناتها */
    public function test_the_decorative_dotted_map_does_not_break_a_real_certificate_page(): void
    {
        $user = $this->trainee('محمود السيّد');
        $certificate = app(CertificateIssuer::class)->issue($user, 'course');

        $response = $this->get(route('verify.certificate', ['code' => $certificate->code]))->assertOk();

        // الخلفيّة الزخرفيّة موجودة
        $response->assertSee('class="pulse 1"', false);

        // ونفس المحتوى الوظيفيّ اللي كان بيظهر قبل الإضافة، زيّ ما هو
        $response->assertSee('محمود السيّد', false);
        $response->assertSee($certificate->code, false);
        $response->assertSee(setting('certificates.status.valid_label'), false);
        $response->assertSee(setting('certificates.verify.signature_ok'), false);
        $response->assertSee(setting('certificates.labels.download_copy', 'تنزيل النسخة'), false);
    }
}
