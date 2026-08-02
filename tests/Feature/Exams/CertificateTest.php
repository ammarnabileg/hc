<?php

namespace Tests\Feature\Exams;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Certificates\CertificateRenderer;
use App\Services\Certificates\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * اختبارات الشهادات (8 · 12.5 · 13.4-ق).
 */
class CertificateTest extends ExamTestCase
{
    use RefreshDatabase;

    /** الترقيم بلا تكرار ولا فجوات (12.5-ب) */
    public function test_certificate_numbering_has_no_gaps(): void
    {
        $issuer = app(CertificateIssuer::class);
        $codes = [];

        foreach (range(1, 3) as $i) {
            $user = $this->trainee('متدرّب '.$i);
            $codes[] = $issuer->issue($user, 'course')->code;
        }

        $prefix = CertificateType::where('key', 'course')->value('numbering_prefix').'-'.now()->year.'-';

        $this->assertSame([
            $prefix.'000001',
            $prefix.'000002',
            $prefix.'000003',
        ], $codes);
    }

    /** منع التكرار: الشهادة لا تُصدَر مرّتين لنفس الشخص ونفس الموضوع (12.5-ج) */
    public function test_issuing_twice_returns_the_same_certificate(): void
    {
        $issuer = app(CertificateIssuer::class);
        $user = $this->trainee();

        $first = $issuer->issue($user, 'course');
        $second = $issuer->issue($user, 'course');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Certificate::query()->where('user_id', $user->id)->count());
    }

    /**
     * ⭐ 13.4-ق: الشهادة «منتهية» **تُعرَض ولا تُخفى** — بتاريخ إصدارها وتاريخ انتهائها،
     * و«منتهية» ليست «ملغاة»، والأثر محصورٌ في نوعها وحده.
     */
    public function test_expired_certificate_is_shown_not_hidden(): void
    {
        $issuer = app(CertificateIssuer::class);
        $user = $this->trainee();

        $qualifying = $issuer->issue($user, 'qualifying');
        $course = $issuer->issue($user, 'course');

        $issuer->expireForNewerExam($user, 'qualifying');

        $qualifying->refresh();
        $course->refresh();

        $this->assertSame('expired', $qualifying->status);
        $this->assertNotNull($qualifying->expired_at);
        $this->assertNotNull($qualifying->issued_at, 'المنتهية تبقى بتاريخ إصدارها.');
        $this->assertDatabaseHas('certificates', ['id' => $qualifying->id]);

        // الأثر محصور في الشهادة التأهيليّة وحدها
        $this->assertSame('valid', $course->status);

        // تظهر في «شهاداتي» بوسمها لا محذوفةً
        $this->actingAs($user)
            ->get(route('learning.certificates'))
            ->assertOk()
            ->assertSee($qualifying->code, false)
            ->assertSee(setting('certificates.status.expired_label'), false);

        // وتظهر في صفحة التحقّق العامّة بنصّها المعتمَد
        $this->get(route('verify.certificate', ['code' => $qualifying->code]))
            ->assertOk()
            ->assertSee(setting('certificates.status.expired_label'), false)
            ->assertSee('ليست ملغاة ولا مطعونًا في صحّتها', false);
    }

    /** «شهاداتي»: فلاتر النوع والسنة والبحث (24.5) */
    public function test_my_certificates_filters(): void
    {
        $issuer = app(CertificateIssuer::class);
        $user = $this->trainee();

        $course = $issuer->issue($user, 'course');
        $event = $issuer->issue($user, 'event');

        $this->actingAs($user)
            ->get(route('learning.certificates', ['type' => 'course']))
            ->assertOk()
            ->assertSee($course->code, false)
            ->assertDontSee($event->code, false);

        $this->actingAs($user)
            ->get(route('learning.certificates', ['q' => $event->code]))
            ->assertOk()
            ->assertSee($event->code, false)
            ->assertDontSee($course->code, false);
    }

    /** مولّد الصورة يشتغل على الخادم بـGD بلا أيّ خدمة خارجيّة (8 · 12.5-ب) */
    public function test_certificate_image_is_generated_on_the_server(): void
    {
        $certificate = app(CertificateIssuer::class)->issue($this->trainee(), 'course');

        $png = app(CertificateRenderer::class)->png($certificate);

        $this->assertStringStartsWith("\x89PNG", $png);
        $this->assertGreaterThan(1000, strlen($png));

        $this->get(route('certificates.image', $certificate->code))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    /** الـQR مولَّد عندنا ويحمل رابط صفحة التحقّق (8.1) */
    public function test_qr_matrix_is_valid_and_public(): void
    {
        $certificate = app(CertificateIssuer::class)->issue($this->trainee(), 'course');

        $matrix = QrCode::matrix(route('verify.certificate', ['code' => $certificate->code]));

        $this->assertSame(count($matrix), count($matrix[0]), 'مصفوفة الـQR مربّعة.');
        $this->assertTrue($matrix[0][0] && $matrix[0][6] && $matrix[6][0], 'نمط البحث في مكانه.');

        $this->get(route('certificates.qr', $certificate->code))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    /** البديل الطباعيّ HTML متاح ويحمل بيانات الشهادة */
    public function test_printable_html_alternative(): void
    {
        $certificate = app(CertificateIssuer::class)->issue($this->trainee('هالة منير'), 'course');

        $this->get(route('certificates.print', $certificate->code))
            ->assertOk()
            ->assertSee('هالة منير', false)
            ->assertSee($certificate->code, false);
    }
}
