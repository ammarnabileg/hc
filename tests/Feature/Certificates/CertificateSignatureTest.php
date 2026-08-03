<?php

namespace Tests\Feature\Certificates;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Certificates\CertificateSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Exams\ExamTestCase;

/**
 * 🔏 **التوقيع الرقميّ ضمانة لا دعوى** (8 · 8.1 · 12.5-هـ).
 *
 * كلّ اختبار هنا يقفل بابَ تزويرٍ **مُثبَتًا تشغيليًّا** قبل الإصلاح: صفٌّ بتوقيع
 * `لا-توقيع-اطلاقا` كانت صفحة التحقّق تُعلنه «سارية … وبياناتها مطابقة لسجلّنا»،
 * والفوتر يَعِد بتوقيعٍ رقميّ لا يقرؤه أحد في المشروع كلّه.
 */
class CertificateSignatureTest extends ExamTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CertificateType::query()->where('key', 'course')->update(['is_active' => true]);
    }

    // ------------------------------------------------ 1) الصفحة تتحقّق فعلًا

    /**
     * ⭐ **التزوير نفسه**: صفٌّ بتوقيعٍ خاطئ ⟵ الصفحة **لا تقول سارية**.
     *
     * ولا تقول «غير موجودة» كذلك: الفرق بين «مش عندنا» و«عندنا وتوقيعها لا يطابق»
     * معلومةٌ يحتاجها المتحقِّق ليعرف ماذا يفعل — والأولى تفيد المزوِّر.
     */
    public function test_a_forged_signature_is_never_announced_as_valid(): void
    {
        $certificate = app(CertificateIssuer::class)->issue($this->trainee('محمود السيّد'), 'course');

        $certificate->forceFill(['hash' => 'لا-توقيع-اطلاقا'])->save();

        $response = $this->get(route('verify.certificate', ['code' => $certificate->code]))->assertOk();

        // لا «سارية» — لا وسمًا ولا نصًّا
        $response->assertDontSee(setting('certificates.status.valid_label'), false);
        $response->assertDontSee(setting('certificates.verify.valid_text'), false);

        // ولا «غير موجودة» — الصفّ موجود والفرق يُعلَن
        $response->assertDontSee(setting('certificates.verify.not_found'), false);

        // بل حالة ثالثة مميّزة، بوسمٍ ونصٍّ صريحين
        $response->assertSee(setting('certificates.verify.unverified_badge'), false);
        $response->assertSee(setting('certificates.verify.unverified_title'), false);
        $response->assertSee(setting('certificates.verify.unverified_text'), false);

        // ولا تُقدَّم كوثيقة: لا اسم حائزٍ ولا تنزيل ولا فهرسة
        $response->assertDontSee('محمود السيّد', false);
        $response->assertDontSee(setting('certificates.labels.download_copy'), false);
        $response->assertSee('noindex', false);
        $response->assertDontSee('EducationalOccupationalCredential', false);
    }

    /** والشهادة الصادرة من المحرّك تُعلَن ساريةً **ومطابقًا توقيعُها** */
    public function test_a_genuine_certificate_is_announced_with_a_matching_signature(): void
    {
        $certificate = app(CertificateIssuer::class)->issue($this->trainee('سلمى عبد الرحمن'), 'course');

        $this->assertTrue(app(CertificateSignature::class)->matches($certificate->fresh()));

        $this->get(route('verify.certificate', ['code' => $certificate->code]))
            ->assertOk()
            ->assertSee('سلمى عبد الرحمن', false)
            ->assertSee(setting('certificates.status.valid_label'), false)
            ->assertSee(setting('certificates.verify.valid_text'), false)
            ->assertSee(setting('certificates.verify.signature_ok'), false)
            ->assertDontSee(setting('certificates.verify.unverified_badge'), false);
    }

    /**
     * ⭐ التوقيع يغطّي **ما يُعرَض** لا الكود وحده: تبديل اسم الحائز في اللقطة
     * المجمَّدة يكسر التوقيع — وإلّا لكان التوقيع يحرس رقمًا ويترك الوثيقة.
     */
    public function test_tampering_with_the_frozen_snapshot_breaks_the_signature(): void
    {
        $certificate = app(CertificateIssuer::class)->issue($this->trainee('هالة منير'), 'course');

        $snapshot = (array) $certificate->data_snapshot;
        $snapshot['holder_name'] = 'اسم مدسوس';
        $certificate->forceFill(['data_snapshot' => $snapshot])->save();

        $this->assertFalse(app(CertificateSignature::class)->matches($certificate->fresh()));

        $this->get(route('verify.certificate', ['code' => $certificate->code]))
            ->assertOk()
            ->assertDontSee(setting('certificates.status.valid_label'), false)
            ->assertDontSee('اسم مدسوس', false)
            ->assertSee(setting('certificates.verify.unverified_badge'), false);
    }

    /** و«بلا توقيع» حالةٌ أخرى غير «لا يطابق» — والفرق مُعلَن للمتحقِّق */
    public function test_an_unsigned_row_is_declared_unsigned_not_forged(): void
    {
        $certificate = app(CertificateIssuer::class)->issue($this->trainee(), 'course');

        $certificate->forceFill(['hash' => ''])->save();

        $this->assertSame(
            CertificateSignature::UNSIGNED,
            app(CertificateSignature::class)->verdict($certificate->fresh()),
        );

        $this->get(route('verify.certificate', ['code' => $certificate->code]))
            ->assertOk()
            ->assertSee(setting('certificates.verify.unsigned_badge'), false)
            ->assertSee(setting('certificates.verify.unsigned_text'), false)
            ->assertDontSee(setting('certificates.status.valid_label'), false);
    }

    /** والكود المزوَّر يبقى «غير موجودة» كما كان — الباب السليم لا يُمَسّ (8.1) */
    public function test_an_unknown_code_still_says_not_found(): void
    {
        $this->get(route('verify.certificate', ['code' => 'HC-2026-999999']))
            ->assertOk()
            ->assertSee(setting('certificates.verify.not_found'), false)
            ->assertDontSee(setting('certificates.verify.unverified_badge'), false);
    }

    // ------------------------------------------------ 2) التوقيع بمفتاح التطبيق

    /**
     * ⭐ **بمفتاح لا بلا مفتاح** (12.5-هـ): التوقيع الذي يقدر أيّ أحدٍ يُنتجه من
     * الحقول الظاهرة بصمةُ محتوًى لا شهادةَ جهة. فلو تغيّر `app.key` تغيّر التوقيع.
     */
    public function test_the_signature_is_keyed_by_the_application_key(): void
    {
        $certificate = app(CertificateIssuer::class)->issue($this->trainee(), 'course');
        $signature = app(CertificateSignature::class);

        $withRealKey = $signature->for($certificate);

        config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);

        $this->assertNotSame($withRealKey, $signature->for($certificate), 'التوقيع لا يتعلّق بمفتاح التطبيق — أيّ أحدٍ يُنتجه.');

        // ولا هو بصمةٌ مكشوفة من الحقول الظاهرة
        $this->assertNotSame(hash('sha256', $signature->payload($certificate)), $withRealKey);
    }

    /**
     * المقارنة **آمنة زمنيًّا** (`hash_equals`): المقارنة الحرفيّة تنتهي عند أوّل
     * حرفٍ مختلف فيُسرَّب موضع الاختلاف، و`==` تقارن نصَّين رقميَّين **عدديًّا**
     * في PHP فتساوي `0e1` بـ`0e2`.
     */
    public function test_the_comparison_is_timing_safe(): void
    {
        $source = (string) file_get_contents(app_path('Services/Certificates/CertificateSignature.php'));

        $this->assertStringContainsString('hash_equals(', $source);
        $this->assertStringContainsString('hash_hmac(', $source);
    }

    // ------------------------------------------------ 3) الشهادات القائمة

    /**
     * ⭐ **الشهادات القائمة لا تُتّهم بالتزوير** (ترحيل مُعلَن).
     *
     * الحقل `hash` عاش بلا قارئ فكُتِب بصيغٍ شتّى — منها بصمةٌ **بلا مفتاح** من
     * مسار شهادات التطوّع القديم. فلو بدأ التحقّق بلا ترحيل لظهرت شهاداتٌ صحيحة
     * بوسم «التوقيع لا يطابق»، وهو اتّهامٌ باطلٌ في وجه صاحبها. فيختمها المايجريشن
     * مرّةً واحدة بخطّ أساسٍ مُعلَن.
     */
    public function test_the_migration_seals_legacy_rows_so_they_are_not_called_forged(): void
    {
        $certificate = app(CertificateIssuer::class)->issue($this->trainee('عمر فتحي'), 'course');

        // صيغة التوقيع القديمة: `sha256` مكشوفة بلا `app.key` — كما كان مسار التطوّع يكتبها
        $legacy = hash('sha256', $certificate->code.'|'.$certificate->user_id.'|1|1');
        $certificate->forceFill(['hash' => $legacy])->save();

        $signature = app(CertificateSignature::class);

        $this->assertFalse($signature->matches($certificate->fresh()));

        // وهو نفس ما يفعله المايجريشن `…_certificates_seal_existing_signatures`
        $this->assertSame(1, $signature->backfill());

        $this->assertTrue($signature->matches($certificate->fresh()));

        $this->get(route('verify.certificate', ['code' => $certificate->code]))
            ->assertOk()
            ->assertSee(setting('certificates.status.valid_label'), false)
            ->assertSee(setting('certificates.verify.signature_ok'), false);

        // والختم لا يعيد كتابة ما هو موقَّع أصلًا
        $this->assertSame(0, $signature->backfill());
    }

    /** والانتهاء والإلغاء **لا يكسران** التوقيع: تغيّر الحالة مشروع بعد الإصدار (13.4-ق) */
    public function test_expiry_and_revocation_do_not_break_the_signature(): void
    {
        $certificate = app(CertificateIssuer::class)->issue($this->trainee(), 'course');
        $signature = app(CertificateSignature::class);

        $certificate->forceFill(['status' => 'expired', 'expired_at' => now()])->save();
        $this->assertTrue($signature->matches($certificate->fresh()));

        $certificate->forceFill(['status' => 'revoked', 'revoked_at' => now()])->save();
        $this->assertTrue($signature->matches($certificate->fresh()));

        // والشهادة الملغاة تبقى «ملغاة» لا «التوقيع لا يطابق»
        $this->get(route('verify.certificate', ['code' => $certificate->code]))
            ->assertOk()
            ->assertSee(setting('certificates.status.revoked_label'), false)
            ->assertSee(setting('certificates.verify.revoked_text'), false)
            ->assertDontSee(setting('certificates.verify.unverified_badge'), false);
    }

    /** ولا يبقى في المشروع مسارٌ يكتب توقيعًا بلا مفتاح */
    public function test_no_code_path_writes_a_keyless_certificate_signature(): void
    {
        $issuer = (string) file_get_contents(app_path('Services/Certificates/CertificateIssuer.php'));
        $volunteer = (string) file_get_contents(app_path('Services/Admin/Volunteer/CertificateEligibility.php'));

        foreach ([$issuer, $volunteer] as $source) {
            $this->assertStringNotContainsString("hash('sha256'", $source);
        }

        app(CertificateIssuer::class)->issue($this->trainee(), 'course');

        $this->assertSame(1, Certificate::query()->count());
        $this->assertSame(0, Certificate::query()->whereRaw('length(hash) <> 64')->count());
    }
}
