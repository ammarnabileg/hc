<?php

namespace Tests\Feature\Certificates;

use App\Models\CertificateType;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Certificates\CertificateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Exams\ExamTestCase;

/**
 * ⭐ [2026-09-10] «ختم/توقيع معتمِد — اختياريّ» (سطر 2407 · 4644 · 4646:
 * Toggle الختم/التوقيع). كان `CertificateType::signature_enabled` و
 * `signature_path`/`stamp_path` حقولًا مزروعةً يحفظها الفورم — و`CertificateRenderer`
 * لا يقرؤها إطلاقًا: عمودان ومصادقةٌ بلا أثرٍ على الصورة الفعليّة (2.15-د).
 */
class CertificateSignatureStampTest extends ExamTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CertificateType::query()->update(['is_active' => true]);
    }

    private function fakeImage(string $path, string $hex): void
    {
        $image = imagecreatetruecolor(120, 60);
        imagefilledrectangle($image, 0, 0, 119, 59, imagecolorallocate(
            $image,
            (int) hexdec(substr($hex, 1, 2)),
            (int) hexdec(substr($hex, 3, 2)),
            (int) hexdec(substr($hex, 5, 2)),
        ));

        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        Storage::disk('public')->put($path, $binary);
    }

    /**
     * ⭐ الختم يظهر فعليًّا على الصورة المرسومة — لا حقلٌ محفوظٌ بلا أثر.
     *
     * ⚠️ المقارنة على **نفس الشهادة بالضبط** (نفس الكود ونفس اللقطة المجمَّدة
     * فيما عداها) — لا على شهادتين مختلفتين، وإلّا لَضمن اختلاف الاسم/الكود
     * وحده نجاح المقارنة بلا أن يُثبِت شيئًا عن الختم فعليًّا.
     */
    public function test_the_stamp_and_signature_are_actually_drawn_when_enabled(): void
    {
        $this->fakeImage('media/stamp-test.png', '#ff0000');
        $this->fakeImage('media/signature-test.png', '#00ff00');

        $type = CertificateType::query()->where('key', 'course')->firstOrFail();
        $type->update([
            'signature_enabled' => false,
            'stamp_path' => 'media/stamp-test.png',
            'signature_path' => 'media/signature-test.png',
        ]);

        $certificate = app(CertificateIssuer::class)->issue($this->trainee('نفس الشخص'), 'course');
        $renderer = app(CertificateRenderer::class);

        $pngWithout = $renderer->png($certificate);

        $snapshot = $certificate->template_snapshot;
        $snapshot['signature_enabled'] = true;
        $certificate->forceFill(['template_snapshot' => $snapshot])->save();
        $renderer->forget($certificate);

        $pngWith = $renderer->png($certificate->fresh());

        $this->assertNotSame(
            hash('sha256', $pngWithout),
            hash('sha256', $pngWith),
            'تفعيل الختم/التوقيع لم يغيّر بايتًا واحدًا في نفس الشهادة — الحقل محفوظٌ بلا راسمٍ يقرؤه.',
        );
    }

    /** والتجميد (12.5-ج) يشمل الختم كأيّ عنصر تصميم آخر — تعطيله لاحقًا لا يمسّ شهادةً صدرت به */
    public function test_disabling_the_signature_later_does_not_change_an_already_issued_certificate(): void
    {
        $this->fakeImage('media/stamp-frozen.png', '#0000ff');

        $type = CertificateType::query()->where('key', 'course')->firstOrFail();
        $type->update(['signature_enabled' => true, 'stamp_path' => 'media/stamp-frozen.png']);

        $certificate = app(CertificateIssuer::class)->issue($this->trainee('مختوم بالفعل'), 'course');
        $renderer = app(CertificateRenderer::class);

        $before = hash('sha256', $renderer->png($certificate));

        $type->update(['signature_enabled' => false]);
        $renderer->forget($certificate);

        $after = hash('sha256', $renderer->png($certificate->fresh()));

        $this->assertSame($before, $after, 'إيقاف الختم على النوع لاحقًا غيّر شكل شهادةٍ صدرت وهو مفعّل — كسرٌ لتجميد 12.5-ج.');
        $this->assertTrue((bool) $certificate->fresh()->template_snapshot['signature_enabled'], 'اللقطة المجمَّدة يجب أن تحمل حالة الختم وقت الإصدار.');
    }

    /** بلا تفعيلٍ من الأصل: لا فرق — نفس الشهادة تمامًا كما كانت (توافقٌ خلفيّ) */
    public function test_certificates_without_the_toggle_are_unaffected(): void
    {
        $type = CertificateType::query()->where('key', 'course')->firstOrFail();
        $type->update(['signature_enabled' => false, 'stamp_path' => null, 'signature_path' => null]);

        $certificate = app(CertificateIssuer::class)->issue($this->trainee('عاديّ'), 'course');

        $this->assertFalse((bool) $certificate->template_snapshot['signature_enabled']);
    }
}
