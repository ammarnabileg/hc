<?php

namespace Tests\Feature\Library;

use App\Models\CvTemplate;
use App\Models\User;
use App\Services\Library\CvTemplateDecor;
use Illuminate\Support\Facades\Storage;

/**
 * المحرّر المرئيّ (Drag-drop) لقوالب الـCV — المرحلة 1/2 (12.7-ب): طبقةٌ
 * زخرفيّةٌ إضافيّة فقط فوق/خلف المحتوى المتدفّق، بلا ربطٍ بحقول بيانات الـCV.
 * هذه المرحلة خلفيّةٌ وربطُ عرضٍ فقط — لا واجهة سحب-وإفلات بعد (مرحلةٌ ثانية).
 */
class CvTemplateDecorTest extends LibraryTestCase
{
    public function test_saving_valid_layers_stores_them_sanitized_on_the_template(): void
    {
        $template = CvTemplate::where('name', 'كلاسيك')->firstOrFail();

        $this->actingAs($this->admin())->put(route('admin.cv-templates.decor.update', $template), [
            'layers' => json_encode([
                ['type' => 'image', 'x' => 5, 'y' => 5, 'w' => 30, 'h' => 20, 'opacity' => 80, 'z' => -5, 'path' => 'cv-decor/frame.png'],
                ['type' => 'text', 'x' => 10.9, 'y' => 90, 'text' => 'سرّيّ وشخصيّ', 'size' => 12, 'color' => '#334455', 'align' => 'center', 'rotate' => -15, 'z' => 3],
            ]),
        ])->assertRedirect();

        $layers = $template->fresh()->decorLayers();

        $this->assertCount(2, $layers);

        $this->assertSame('image', $layers[0]['type']);
        $this->assertSame(5, $layers[0]['x']);
        $this->assertSame(30, $layers[0]['w']);
        $this->assertSame(80, $layers[0]['opacity']);
        $this->assertSame(-5, $layers[0]['z']);
        $this->assertSame('cv-decor/frame.png', $layers[0]['path']);

        $this->assertSame('text', $layers[1]['type']);
        // الإحداثيّات نسبٌ مئويّة (int) — راجع CvTemplateDecor
        $this->assertSame(10, $layers[1]['x']);
        $this->assertSame('سرّيّ وشخصيّ', $layers[1]['text']);
        $this->assertSame('#334455', $layers[1]['color']);
        $this->assertSame('center', $layers[1]['align']);
        $this->assertSame(-15, $layers[1]['rotate']);
        $this->assertSame(3, $layers[1]['z']);
    }

    /** طبقةٌ فاسدة (لونٌ غير Hex، أو نوعٌ غير معروف) تُستبعَد صامتًا — بقيّة الطبقات الصحيحة تُحفَظ */
    public function test_a_malformed_layer_is_dropped_without_breaking_the_rest(): void
    {
        $template = CvTemplate::where('name', 'كلاسيك')->firstOrFail();

        $this->actingAs($this->admin())->put(route('admin.cv-templates.decor.update', $template), [
            'layers' => json_encode([
                ['type' => 'text', 'x' => 20, 'y' => 20, 'text' => 'صحيحة', 'color' => '#ffffff'],
                ['type' => 'text', 'x' => 20, 'y' => 20, 'text' => 'لون فاسد', 'color' => 'not-a-hex'],
                ['type' => 'sticker', 'x' => 20, 'y' => 20],
                ['type' => 'image', 'x' => 20, 'y' => 20, 'path' => 'cv-decor/ok.png'],
            ]),
        ])->assertRedirect();

        $layers = $template->fresh()->decorLayers();

        $this->assertCount(2, $layers);
        $this->assertSame('صحيحة', $layers[0]['text']);
        $this->assertSame('image', $layers[1]['type']);
    }

    /** الحارس: بلا `cv_templates.edit` لا يُقدَر على الحفظ */
    public function test_updating_decor_layers_is_forbidden_without_the_permission(): void
    {
        $user = $this->trainee('UCVDECNO');
        $this->grant($user, 'cv_templates.list');
        $template = CvTemplate::where('name', 'كلاسيك')->firstOrFail();

        $this->actingAs($user)
            ->put(route('admin.cv-templates.decor.update', $template), ['layers' => json_encode([])])
            ->assertForbidden();
    }

    /** انحدار: قالبٌ بلا طبقاتٍ زخرفيّة — لا أثر بصريّ جديد إطلاقًا (توافقٌ خلفيّ كامل) */
    public function test_a_template_without_decor_layers_renders_exactly_as_before(): void
    {
        $user = $this->trainee('UCVDECRG1');
        $template = CvTemplate::where('name', 'كلاسيك')->firstOrFail();
        $this->assertSame([], $template->decorLayers());

        $this->actingAs($user)->postJson(route('cv.template', $template))->assertOk();
        $this->actingAs($user)->postJson(route('cv.autosave'), [
            'step' => 'profile',
            'data' => ['profile' => ['job_title' => 'مصمّم منتج']],
        ])->assertOk();

        $preview = $this->actingAs($user)->get(route('cv.preview'));

        $preview->assertOk()
            ->assertSee('مصمّم منتج', false)
            ->assertDontSee('cv-decor-layer', false);

        // نفس الشيء بالضبط سواء كانت decor_layers = null أو مصفوفة فارغة صراحةً
        $template->update(['decor_layers' => []]);
        $second = $this->actingAs($user)->get(route('cv.preview'))->assertOk();

        $this->assertSame($preview->getContent(), $second->getContent());
    }

    /** طبقة نصٍّ محفوظة تظهر فعليًّا (موضع/محتوى) في مساري المعاينة والتنزيل معًا */
    public function test_a_saved_text_layer_appears_in_preview_and_download(): void
    {
        $user = $this->trainee('UCVDECTX1');
        $template = CvTemplate::where('name', 'كلاسيك')->firstOrFail();

        app(CvTemplateDecor::class); // يضمن تحميل الصنف قبل التنقية اليدويّة بالأسفل
        $template->update(['decor_layers' => (new CvTemplateDecor)->sanitize([
            ['type' => 'text', 'x' => 12, 'y' => 88, 'text' => 'نسخة داخليّة للتدريب فقط', 'color' => '#112233', 'align' => 'left', 'size' => 16, 'rotate' => 8, 'z' => 4],
        ])]);

        $this->actingAs($user)->postJson(route('cv.template', $template))->assertOk();

        $preview = $this->actingAs($user)->get(route('cv.preview'));
        $preview->assertOk()
            ->assertSee('cv-decor-layer', false)
            ->assertSee('نسخة داخليّة للتدريب فقط', false)
            ->assertSee('left:12%', false)
            ->assertSee('top:88%', false)
            ->assertSee('z-index:4', false)
            // حصر الصفحة الأولى (297mm) — قيدٌ CSS بحتٌ على حاوية الطبقات، راجع الجزء المشترك
            ->assertSee('block-size:297mm', false)
            ->assertSee('overflow:hidden', false);

        $download = $this->actingAs($user)->get(route('cv.download'));
        $download->assertOk()->assertSee('نسخة داخليّة للتدريب فقط', false);
    }

    /** طبقة صورةٍ محفوظة (خلفيّة) تظهر فعليًّا بموضعها في المعاينة والتنزيل */
    public function test_a_saved_image_layer_appears_in_preview_and_download(): void
    {
        Storage::fake('public');
        $user = $this->trainee('UCVDECIM1');
        $template = CvTemplate::where('name', 'كلاسيك')->firstOrFail();

        $template->update(['decor_layers' => (new CvTemplateDecor)->sanitize([
            ['type' => 'image', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 100, 'opacity' => 15, 'z' => -10, 'path' => 'cv-decor/bg.png'],
        ])]);

        $this->actingAs($user)->postJson(route('cv.template', $template))->assertOk();

        $expectedSrc = Storage::url('cv-decor/bg.png');

        $preview = $this->actingAs($user)->get(route('cv.preview'));
        $preview->assertOk()
            ->assertSee($expectedSrc, false)
            ->assertSee('z-index:-10', false)
            ->assertSee('opacity:0.15', false);

        $this->actingAs($user)->get(route('cv.download'))
            ->assertOk()
            ->assertSee($expectedSrc, false);
    }

    private function admin(): User
    {
        $user = $this->trainee('UCVDECADM');
        $this->grant($user, 'cv_templates.edit');

        return $user;
    }
}
