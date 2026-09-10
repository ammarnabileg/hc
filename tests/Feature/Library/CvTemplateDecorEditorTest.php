<?php

namespace Tests\Feature\Library;

use App\Models\CvTemplate;
use App\Models\User;
use App\Services\Library\CvTemplateDecor;
use Illuminate\Support\Facades\Storage;

/**
 * المحرّر المرئيّ (Drag-drop) لقوالب الـCV — المرحلة 2/2 (12.7-ب): شاشة
 * التحرير نفسها (`admin.cv-templates.decor.edit`) وإطار المعاينة الحيّة
 * خلف الكانفس (`admin.cv-templates.decor.preview`).
 *
 * الكانفس نفسه جافاسكربت لا يُختبَر بـPHPUnit — الدليل هنا على ما حوله:
 * الحارس · البيانات الأوّليّة المُمرَّرة لِـJS · ومسار الحفظ نفسه (المُختبَر
 * تفصيليًّا في CvTemplateDecorTest من المرحلة 1) لا يزال يعمل من هذه الشاشة.
 */
class CvTemplateDecorEditorTest extends LibraryTestCase
{
    // ------------------------------------------------------------- الحارس

    /** زرّ المحرّر المرئيّ مخفيّ عن غير صاحب cv_templates.edit — لا معطّل (2.15-أ-7) */
    public function test_the_decor_editor_button_is_hidden_without_the_edit_permission(): void
    {
        $user = $this->trainee('UCVDECED1');
        $this->grant($user, 'cv_templates.list');

        $this->actingAs($user)->get(route('admin.cv-templates.index'))
            ->assertOk()
            ->assertDontSee(setting('cv.template.admin.decor_editor_label', 'المحرّر المرئيّ (Drag-drop)'), false);
    }

    /** وبصلاحيّة cv_templates.edit يظهر الزرّ ويشير لهذا القالب بعينه */
    public function test_the_decor_editor_button_is_visible_with_the_edit_permission(): void
    {
        $template = CvTemplate::where('name', 'كلاسيك')->firstOrFail();

        $this->actingAs($this->admin())->get(route('admin.cv-templates.index'))
            ->assertOk()
            ->assertSee(setting('cv.template.admin.decor_editor_label', 'المحرّر المرئيّ (Drag-drop)'), false)
            ->assertSee(route('admin.cv-templates.decor.edit', $template), false);
    }

    /** ومحاولة فتح شاشة المحرّر مباشرةً بلا الصلاحيّة تُرفَض */
    public function test_opening_the_decor_editor_without_the_edit_permission_is_forbidden(): void
    {
        $user = $this->trainee('UCVDECED2');
        $this->grant($user, 'cv_templates.list');
        $template = CvTemplate::where('name', 'كلاسيك')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.cv-templates.decor.edit', $template))
            ->assertForbidden();
    }

    /** وإطار المعاينة الحيّة خلف الكانفس بنفس الحارس تمامًا */
    public function test_the_decor_preview_frame_is_forbidden_without_the_edit_permission(): void
    {
        $user = $this->trainee('UCVDECPV1');
        $this->grant($user, 'cv_templates.list');
        $template = CvTemplate::where('name', 'كلاسيك')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.cv-templates.decor.preview', $template))
            ->assertForbidden();
    }

    // ------------------------------------------------------- البيانات الأوّليّة

    /** طبقة صورةٍ محفوظة مسبقًا تصل صحيحةً لِـJS في HTML الصفحة (مسارٌ محلول + كلّ الحقول) */
    public function test_opening_the_editor_shows_a_previously_saved_image_layer_for_js(): void
    {
        Storage::fake('public');
        $template = CvTemplate::where('name', 'كلاسيك')->firstOrFail();
        $template->update(['decor_layers' => (new CvTemplateDecor)->sanitize([
            ['type' => 'image', 'x' => 12, 'y' => 34, 'w' => 40, 'h' => 30, 'opacity' => 70, 'z' => -3, 'path' => 'cv-decor/editor-test.png'],
        ])]);

        $response = $this->actingAs($this->admin())->get(route('admin.cv-templates.decor.edit', $template));

        // @json() (json_encode بلا JSON_UNESCAPED_SLASHES) يهرب "/" إلى "\/" —
        // فمقارنة المسار/الرابط الخامّين نفسيهما تحتاج نفس الهروب بالحرف.
        $escapedPath = str_replace('/', '\/', 'cv-decor/editor-test.png');
        $escapedUrl = str_replace('/', '\/', Storage::url('cv-decor/editor-test.png'));

        $response->assertOk()
            // المسار الخامّ + رابطه المحلول (Storage::url) معًا — الرابط يُبنى مرّةً في الخادم لا في الـJS
            ->assertSee($escapedPath, false)
            ->assertSee($escapedUrl, false)
            ->assertSee('"x":12', false)
            ->assertSee('"y":34', false)
            ->assertSee('"opacity":70', false);
    }

    /** طبقة نصٍّ محفوظة مسبقًا تصل صحيحةً لِـJS كذلك */
    public function test_opening_the_editor_shows_a_previously_saved_text_layer_for_js(): void
    {
        $template = CvTemplate::where('name', 'كلاسيك')->firstOrFail();
        $template->update(['decor_layers' => (new CvTemplateDecor)->sanitize([
            ['type' => 'text', 'x' => 8, 'y' => 92, 'text' => 'Confidential Draft', 'size' => 20, 'color' => '#abcdef', 'align' => 'left', 'rotate' => -6, 'z' => 4],
        ])]);

        $this->actingAs($this->admin())
            ->get(route('admin.cv-templates.decor.edit', $template))
            ->assertOk()
            ->assertSee('Confidential Draft', false)
            ->assertSee('"color":"#abcdef"', false)
            ->assertSee('"rotate":-6', false);
    }

    /** قالبٌ بلا طبقاتٍ زخرفيّة بعد — الشاشة تفتح بمصفوفةٍ فارغة بلا خطأ */
    public function test_the_editor_opens_fine_for_a_template_with_no_layers_yet(): void
    {
        $template = CvTemplate::where('name', 'كلاسيك')->firstOrFail();
        $this->assertSame([], $template->decorLayers());

        $this->actingAs($this->admin())
            ->get(route('admin.cv-templates.decor.edit', $template))
            ->assertOk()
            ->assertSee(setting('cv.template.admin.decor_empty_hint', 'لا عناصر زخرفيّة بعد — أضف نصًّا أو صورة.'), false);
    }

    // ------------------------------------------------------------- الحفظ

    /** فورم الشاشة يستهدف نفس مسار الحفظ المُختبَر تفصيليًّا في المرحلة 1 — لا مسار مواز جديد */
    public function test_the_editors_form_targets_the_existing_stage1_save_route(): void
    {
        $template = CvTemplate::where('name', 'كلاسيك')->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('admin.cv-templates.decor.edit', $template))
            ->assertOk()
            ->assertSee('action="'.route('admin.cv-templates.decor.update', $template).'"', false);
    }

    /**
     * حفظ حقيقيّ عبر نفس المسار بصيغة الفورم (`layers[i][key]` مصفوفةً لا
     * JSON خامًّا) — بالضبط ما يكتبه `syncHiddenInputs()` في هذه الشاشة قبل
     * الإرسال. يعيد تأكيد أنّ نفس مسار المرحلة 1 لا يزال يعمل من واجهة هذه
     * الشاشة تحديدًا (منطق sanitize نفسه مُغطًّى بالفعل في CvTemplateDecorTest).
     */
    public function test_saving_layers_in_the_forms_array_shape_still_works(): void
    {
        $template = CvTemplate::where('name', 'كلاسيك')->firstOrFail();

        $this->actingAs($this->admin())->put(route('admin.cv-templates.decor.update', $template), [
            'layers' => [
                ['type' => 'text', 'x' => 40, 'y' => 60, 'z' => 2, 'text' => 'من شاشة المحرّر', 'size' => 18, 'color' => '#112233', 'align' => 'center', 'rotate' => 0],
                ['type' => 'image', 'x' => 0, 'y' => 0, 'z' => -1, 'path' => 'cv-decor/from-editor.png', 'w' => 100, 'h' => 100, 'opacity' => 100],
            ],
        ])->assertRedirect();

        $layers = $template->fresh()->decorLayers();

        $this->assertCount(2, $layers);
        $this->assertSame('من شاشة المحرّر', $layers[0]['text']);
        $this->assertSame('cv-decor/from-editor.png', $layers[1]['path']);
    }

    // -------------------------------------------------------- إطار المعاينة

    /** إطار المعاينة يبني رابطًا صحيحًا بمعرّف القالب الصحيح، ويعرض محتوًى حيًّا فعليًّا */
    public function test_the_preview_frame_url_carries_the_correct_template_and_renders_its_content(): void
    {
        $template = CvTemplate::where('is_free', false)->orderBy('sort_order')->firstOrFail();

        $url = route('admin.cv-templates.decor.preview', $template);
        $this->assertStringContainsString('/cv-templates/'.$template->id.'/decor/preview', $url);

        $this->actingAs($this->admin())->get($url)
            ->assertOk()
            // المحتوى المتدفّق الحقيقيّ (نفس محرّك cv.preview) — لا صفحة فارغة ولا سكرين-شوت
            ->assertSee('sheet-scale-wrap', false);
    }

    /**
     * ⭐ الطبقة الزخرفيّة نفسها **لا** تُرسَم داخل إطار المعاينة — الكانفس
     * الأب (decor.blade.php) هو من يرسمها فوقه، فلا ازدواجٌ بصريّ أثناء
     * السحب. راجع تعليق `CvTemplateAdminController::decorPreview()`.
     */
    public function test_the_preview_frame_never_renders_the_decor_layer_itself(): void
    {
        $template = CvTemplate::where('name', 'كلاسيك')->firstOrFail();
        $template->update(['decor_layers' => (new CvTemplateDecor)->sanitize([
            ['type' => 'text', 'x' => 10, 'y' => 10, 'text' => 'يجب ألّا يظهر هنا', 'color' => '#000000'],
        ])]);

        $this->actingAs($this->admin())
            ->get(route('admin.cv-templates.decor.preview', $template))
            ->assertOk()
            ->assertDontSee('cv-decor-layer', false)
            ->assertDontSee('يجب ألّا يظهر هنا', false);
    }

    /**
     * المعاينة تخصّ القالب `$template` من الرابط بعينه — لا القالب الذي
     * قد يختاره الأدمن لسيرته الذاتيّة الشخصيّة هو (فرقٌ حقيقيّ اضطُرّ هذا
     * المسار لأجله: `route('cv.preview')` لا يقبل أيّ باراميتر قالب إطلاقًا —
     * راجع تعليق `CvTemplateAdminController::decorPreview()`). نميّز بعلامة
     * HTML فعليّة تختلف بين ملفّي العرض classic/modern لا بالاسم وحده — فحقل
     * `templateName` غير مطبوعٍ أصلًا في preview.blade.php.
     */
    public function test_the_preview_frame_uses_the_specific_template_from_the_url_not_the_admins_own_choice(): void
    {
        $classic = CvTemplate::where('view_path', 'classic')->firstOrFail();
        $modern = CvTemplate::where('view_path', 'modern')->firstOrFail();

        $admin = $this->admin();

        // علامةٌ فعليّة في modern.blade.php وحده: <div class="sheet" style="padding: 0">
        $this->actingAs($admin)
            ->get(route('admin.cv-templates.decor.preview', $modern))
            ->assertOk()
            ->assertSee('style="padding: 0"', false);

        $this->actingAs($admin)
            ->get(route('admin.cv-templates.decor.preview', $classic))
            ->assertOk()
            ->assertDontSee('style="padding: 0"', false);
    }

    private function admin(): User
    {
        $user = $this->trainee('UCVDECEDA');
        // cv_templates.edit وحدها تكفي لمسارات الديكور نفسها، لكنّ اختبار ظهور
        // الزرّ يفتح شاشة الفهرس أوّلًا — وهي محروسة بـcv_templates.list زيادةً
        // (نمطٌ واقعيّ: أدمن القوالب يملك الصلاحيّتين معًا عادةً).
        $this->grant($user, 'cv_templates.list');
        $this->grant($user, 'cv_templates.edit');

        return $user;
    }
}
