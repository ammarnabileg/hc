<?php

namespace Tests\Feature\Certificates;

use App\Models\Certificate;
use App\Services\Certificates\CertificateSignature;
use Database\Seeders\CoreSeeder;
use Database\Seeders\DashboardDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⭐⭐ **شهادة العرض توقيعُها يطابق** (8.1 · 12.5-هـ).
 *
 * ================== النصّ الحاكم ==================
 * > **8.1:** صفحة التحقّق «تتيح للجهات والشركات التحقّق من **صحّة** وصلاحيّة أيّ
 * > شهادة».
 * والصحّة غير الصلاحيّة: الصلاحيّة حالةٌ مخزَّنة، أمّا **الصحّة** فلا تُعرَف إلّا
 * بإعادة اشتقاق التوقيع من بيانات الشهادة ومقارنته بالمخزَّن.
 *
 * ================== العطب ==================
 * `DashboardDemoSeeder::certificate()` كان يزرع `hash('sha256', 'CRS-DASH-0001')`
 * — **بصمةُ محتوًى بلا مفتاح** يقدر أيّ أحدٍ إنتاجها — بلا `template_snapshot`
 * ولا `data_snapshot`. فأوّل شهادةٍ يفتحها المجرِّب في صفحة التحقّق العامّة
 * تُوسَم «**التوقيع لا يطابق**»: اتّهامٌ باطل لشهادةٍ صدرت من محرّكنا، ويجعل
 * الحارس الحقيقيّ يبدو معطوبًا فيُطفَأ يومًا لأنّه «يكذب».
 *
 * والعلاج مصدرٌ واحد: `CertificateIssuer` يجمّد اللقطتين ثمّ يوقّع بـ
 * `CertificateSignature` بمفتاح التطبيق **بعد** اكتمالهما.
 */
class DemoCertificateSignatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(DashboardDemoSeeder::class);
    }

    /** ⭐ كلّ شهادة مزروعة **موقَّعة توقيعًا يطابق** — صفر «لا يطابق» */
    public function test_every_seeded_certificate_matches_its_signature(): void
    {
        $signature = app(CertificateSignature::class);
        $certificates = Certificate::with('certificate_type')->get();

        $this->assertNotEmpty($certificates, 'لا شهادة مزروعة — الحارس بلا مادّة فهو وهميّ.');

        $verdicts = $certificates
            ->map(fn (Certificate $c) => $c->code.': '.$signature->verdict($c))
            ->reject(fn (string $line) => str_ends_with($line, CertificateSignature::MATCH))
            ->values()
            ->all();

        $this->assertSame([], $verdicts, 'شهادةٌ صدرت من محرّكنا لا تُوسَم «التوقيع لا يطابق» (8.1)');
    }

    /** واللقطتان مجمَّدتان — التوقيع يغطّي **ما يُعرَض** لا الكود وحده (12.5-ج) */
    public function test_the_seeded_certificate_carries_both_frozen_snapshots(): void
    {
        $certificate = Certificate::firstOrFail();

        $this->assertNotEmpty($certificate->template_snapshot, 'لقطة القالب مجمَّدة لحظة الإصدار (12.5-ج)');
        $this->assertNotEmpty($certificate->data_snapshot, 'لقطة البيانات مجمَّدة لحظة الإصدار (12.5-ج)');
    }

    /** ⭐ وصفحة التحقّق **العامّة** (بلا تسجيل دخول) تقولها للناس */
    public function test_the_public_verification_page_declares_it_authentic(): void
    {
        $certificate = Certificate::firstOrFail();

        $this->get(route('verify.certificate', ['code' => $certificate->code]))
            ->assertOk()
            // شارة «مطابق» تظهر، و«التوقيع لا يطابق» تختفي — نصّهما إعدادٌ لا محروق
            ->assertSee((string) setting('certificates.verify.signature_ok', 'مطابق — البيانات دي هي اللي صدرت'), false)
            ->assertDontSee((string) setting('certificates.verify.unverified_badge', 'التوقيع لا يطابق'), false);
    }

    /** ⭐ **طفرة:** حرفٌ واحد في اللقطة يكسر التوقيع — وإلّا فالحارس وهميّ */
    public function test_touching_the_frozen_snapshot_breaks_the_signature(): void
    {
        $certificate = Certificate::with('certificate_type')->firstOrFail();
        $signature = app(CertificateSignature::class);

        $this->assertSame(CertificateSignature::MATCH, $signature->verdict($certificate));

        $snapshot = (array) $certificate->data_snapshot;
        $snapshot['holder_name'] = 'اسمٌ مدسوس';
        $certificate->forceFill(['data_snapshot' => $snapshot])->save();

        $this->assertSame(
            CertificateSignature::MISMATCH,
            $signature->verdict($certificate->fresh()->load('certificate_type')),
            'تبديل اسمٍ في اللقطة يكسر التوقيع ولا يمرّ صامتًا (12.5-هـ)',
        );
    }
}
