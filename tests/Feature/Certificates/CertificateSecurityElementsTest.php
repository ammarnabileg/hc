<?php

namespace Tests\Feature\Certificates;

use App\Models\CertificateTemplate;
use App\Models\CertificateType;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Certificates\CertificateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Exams\ExamTestCase;

/**
 * ⭐ [2026-09-10] «عناصر أمان بصريّة اختياريّة (Guilloché/Microtext)» (سطر
 * 2407 · 4644 · 12.5-ب) — اسمان مذكوران في الدستور بلا حقلٍ في فورم النوع
 * ولا راسمٍ يقرؤهما (نفس عطب الختم/التوقيع قبل إصلاحهما في 2026-09-10).
 */
class CertificateSecurityElementsTest extends ExamTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CertificateType::query()->update(['is_active' => true]);
    }

    /**
     * ⭐ التفعيل يغيّر فعليًّا بايتات الصورة — لا حقلٌ محفوظٌ بلا أثر.
     *
     * ⚠️ المقارنة على **نفس الشهادة بالضبط** كحال اختبار الختم/التوقيع —
     * لا شهادتين مختلفتين، وإلّا لَضمن اختلاف الكود وحده نجاح المقارنة.
     */
    public function test_security_elements_are_actually_drawn_when_enabled(): void
    {
        $type = CertificateType::query()->where('key', 'course')->firstOrFail();
        $type->update(['security_elements_enabled' => false]);

        $certificate = app(CertificateIssuer::class)->issue($this->trainee('نفس الشخص'), 'course');
        $renderer = app(CertificateRenderer::class);

        $pngWithout = $renderer->png($certificate);

        $snapshot = $certificate->template_snapshot;
        $snapshot['security_elements_enabled'] = true;
        $certificate->forceFill(['template_snapshot' => $snapshot])->save();
        $renderer->forget($certificate);

        $pngWith = $renderer->png($certificate->fresh());

        $this->assertNotSame(
            hash('sha256', $pngWithout),
            hash('sha256', $pngWith),
            'تفعيل عناصر الأمان البصريّة لم يغيّر بايتًا واحدًا في نفس الشهادة — الحقل محفوظٌ بلا راسمٍ يقرؤه.',
        );
    }

    /**
     * ⭐ النقش والنصّ المصغّر مُشتقّان من كود الشهادة نفسه — فشهادتان بنفس
     * التفعيل بالضبط تخرجان بنقشين مختلفين.
     *
     * ⚠️ لا تكفي مقارنة شهادتين عاديّتين: الكود أصلًا **حقلٌ ظاهر** في
     * التصميم الافتراضيّ (طبقة `code`)، فأيّ شهادتين ستختلفان بايتيًّا بسببه
     * وحده — بلا أن يُثبِت شيئًا عن عناصر الأمان تحديدًا (تأكّدنا منه
     * بالـmutation: تثبيت seed النقش لا يُسقِط هذا الاختبار كما كُتِب أوّل
     * مرّة). فالعزل هنا بقالبٍ خاصٍّ **بلا أيّ طبقة تحمل الكود أو QR** —
     * الفرق الوحيد الممكن بين الشهادتين حينئذٍ هو عناصر الأمان نفسها.
     */
    public function test_the_pattern_is_unique_per_certificate_code(): void
    {
        $type = CertificateType::query()->where('key', 'course')->firstOrFail();
        $type->update(['security_elements_enabled' => true]);

        $template = CertificateTemplate::create([
            'certificate_type_id' => $type->id,
            'language' => 'ar',
            'name' => 'قالب معزول لاختبار النقش',
            'is_default' => false,
            'version' => 1,
            // نصٌّ ثابتٌ واحد بلا ربطٍ بأيّ بيانات — ولا كود ولا QR في القالب أصلًا
            'layers' => [['type' => 'text', 'text' => 'X', 'x' => 0.5, 'y' => 0.5]],
        ]);

        $renderer = app(CertificateRenderer::class);
        $issuer = app(CertificateIssuer::class);

        $first = $renderer->png($issuer->issue($this->trainee('شخص أوّل'), 'course', templateId: $template->id));
        $second = $renderer->png($issuer->issue($this->trainee('شخص تاني'), 'course', templateId: $template->id));

        $this->assertNotSame(hash('sha256', $first), hash('sha256', $second),
            'شهادتان بقالبٍ معزولٍ (بلا كودٍ ولا QR ظاهرَين) خرجتا بنفس بايتات الصورة رغم تفعيل عناصر الأمان — النقش مش فريد لكلّ شهادة فعليًّا.');
    }

    /** والتجميد (12.5-ج) يشمل عناصر الأمان كأيّ عنصر تصميم آخر — تعطيلها لاحقًا لا يمسّ شهادةً صدرت بها */
    public function test_disabling_security_elements_later_does_not_change_an_already_issued_certificate(): void
    {
        $type = CertificateType::query()->where('key', 'course')->firstOrFail();
        $type->update(['security_elements_enabled' => true]);

        $certificate = app(CertificateIssuer::class)->issue($this->trainee('نقش بالفعل'), 'course');
        $renderer = app(CertificateRenderer::class);

        $before = hash('sha256', $renderer->png($certificate));

        $type->update(['security_elements_enabled' => false]);
        $renderer->forget($certificate);

        $after = hash('sha256', $renderer->png($certificate->fresh()));

        $this->assertSame($before, $after, 'إيقاف عناصر الأمان على النوع لاحقًا غيّر شكل شهادةٍ صدرت وهي مفعّلة — كسرٌ لتجميد 12.5-ج.');
        $this->assertTrue((bool) $certificate->fresh()->template_snapshot['security_elements_enabled'], 'اللقطة المجمَّدة يجب أن تحمل حالة عناصر الأمان وقت الإصدار.');
    }

    /** بلا تفعيلٍ من الأصل: لا فرق — نفس الشهادة تمامًا كما كانت (توافقٌ خلفيّ) */
    public function test_certificates_without_the_toggle_are_unaffected(): void
    {
        $type = CertificateType::query()->where('key', 'course')->firstOrFail();
        $type->update(['security_elements_enabled' => false]);

        $certificate = app(CertificateIssuer::class)->issue($this->trainee('عاديّ'), 'course');

        $this->assertFalse((bool) $certificate->template_snapshot['security_elements_enabled']);
    }
}
