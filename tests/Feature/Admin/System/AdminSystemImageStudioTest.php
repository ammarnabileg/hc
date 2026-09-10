<?php

namespace Tests\Feature\Admin\System;

use App\Models\ImageTemplate;
use App\Models\NameParticle;
use App\Services\Images\ImageRenderer;
use App\Services\Images\ImageTemplateFields;
use App\Services\Images\TemplateLayers;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * استوديو الصور (12.14):
 *  ⛔ الحقول الممنوعة مرفوضة ولا وجود لها في القائمة أصلًا ·
 *  ⭐ تغيير المقاس يعيد ترتيب الطبقات نسبيًّا ·
 *  ⭐ الاسم المختصر يعامل أدوات الاسم جزءًا من الكلمة التالية ·
 *  ⭐ والقالب يُؤرشَف لا يُحذَف.
 */
class AdminSystemImageStudioTest extends SystemTestCase
{
    private const ADMIN = [
        'image_templates.list', 'image_templates.view', 'image_templates.create',
        'image_templates.edit', 'image_templates.archive', 'image_templates.batch',
    ];

    /** ⛔ الحقول الممنوعة نهائيًّا (12.14-د) */
    public function test_template_rejects_forbidden_fields(): void
    {
        $admin = $this->admin(self::ADMIN);

        foreach (['phone', 'email', 'emergency_contact', 'admin_notes'] as $forbidden) {
            $this->actingAs($admin)->post(route('admin.studio.store'), [
                'name' => 'قالب محاولة',
                'width_px' => 1080,
                'height_px' => 1080,
                'audience' => 'everyone',
                'layers' => [
                    ['type' => 'text', 'field' => $forbidden, 'x' => 10, 'y' => 10],
                ],
            ])->assertSessionHasErrors('layers');
        }

        $this->assertDatabaseMissing('image_templates', ['name' => 'قالب محاولة']);
    }

    /** ⛔ والحقول الممنوعة غير موجودة في القائمة أصلًا — لا معطَّلة */
    public function test_forbidden_fields_are_absent_from_the_allowed_list(): void
    {
        $fields = app(ImageTemplateFields::class)->all();

        foreach (ImageTemplateFields::FORBIDDEN as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $fields);
        }

        // ⭐ والمحافظة حقل عامّ دائمًا ولا يجوز إخفاؤها
        $this->assertArrayHasKey('governorate', $fields);
        $this->assertArrayHasKey('rep', $fields);
    }

    public function test_allowed_fields_are_accepted(): void
    {
        $admin = $this->admin(self::ADMIN);

        $this->actingAs($admin)->post(route('admin.studio.store'), [
            'name' => 'قالب مسموح',
            'width_px' => 1080,
            'height_px' => 1080,
            'audience' => 'volunteers',
            'layers' => [
                ['type' => 'text', 'field' => 'short_name', 'x' => 10, 'y' => 10, 'size' => 40],
                ['type' => 'avatar', 'x' => 20, 'y' => 20, 'w' => 200, 'h' => 200, 'shape' => 'circle'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('image_templates', ['name' => 'قالب مسموح', 'audience' => 'volunteers']);
    }

    /** ⭐ تغيير المقاس يعيد توزيع الطبقات نسبيًّا فلا يفسد التصميم */
    public function test_resizing_rescales_layers_proportionally(): void
    {
        $layers = [['type' => 'text', 'x' => 100, 'y' => 200, 'size' => 40]];

        $scaled = app(TemplateLayers::class)->rescale($layers, 1000, 1000, 2000, 500);

        $this->assertSame(200, $scaled[0]['x']);
        $this->assertSame(100, $scaled[0]['y']);
        $this->assertSame(20, $scaled[0]['size']);
    }

    /** الطبقة المقفولة لا تتحرّك بالرفع أو الإنزال */
    public function test_locked_layer_does_not_move(): void
    {
        $layers = [
            ['type' => 'text', 'name' => 'أ', 'locked' => true],
            ['type' => 'text', 'name' => 'ب'],
        ];

        $moved = app(TemplateLayers::class)->move($layers, 0, 'up');

        $this->assertSame('أ', $moved[0]['name']);
    }

    /** ⭐ الاسم المختصر: «عبد» جزء من الكلمة التالية لا وحدة مستقلّة (12.14-ج) */
    public function test_short_name_treats_particles_as_part_of_the_next_word(): void
    {
        $user = $this->makeUser('عبد الرحمن محمد علي');
        $this->assertSame('عبد الرحمن محمد', $user->shortName());

        $second = $this->makeUser('محمد أحمد علي');
        $this->assertSame('محمد أحمد', $second->shortName());
    }

    /** وقائمة الأدوات تُدار من الاستوديو لأنّها تختلف بالثقافات */
    public function test_name_particles_are_managed_from_the_studio(): void
    {
        $admin = $this->admin(self::ADMIN);

        $this->actingAs($admin)->post(route('admin.studio.particles.store'), [
            'particle' => 'دي',
            'locale' => 'en',
        ])->assertRedirect();

        $this->assertDatabaseHas('name_particles', ['particle' => 'دي']);

        $particle = NameParticle::query()->where('particle', 'دي')->firstOrFail();

        $this->actingAs($admin)->delete(route('admin.studio.particles.delete', $particle))->assertRedirect();
        $this->assertDatabaseMissing('name_particles', ['particle' => 'دي']);
    }

    /** ⭐ المستخدَم في نشرٍ قائم يُؤرشَف لا يُحذَف */
    public function test_template_is_archived_not_deleted(): void
    {
        $admin = $this->admin(self::ADMIN);
        $template = ImageTemplate::query()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.studio.archive', $template))->assertRedirect();

        $template->refresh();

        $this->assertTrue($template->is_archived);
        $this->assertFalse($template->is_active);
        $this->assertDatabaseHas('image_templates', ['id' => $template->id]);
    }

    public function test_studio_index_lists_templates_and_audiences(): void
    {
        $admin = $this->admin(self::ADMIN);

        $this->actingAs($admin)->get(route('admin.studio.index'))
            ->assertOk()
            ->assertSee('كارت الإنجاز', false)
            ->assertSee('أدوات الاسم', false);
    }

    /** ⭐ 24.2: بحثٌ بلا نتائج يقول كده صراحةً بدل «مافيش قوالب لسه». */
    public function test_a_search_with_no_matches_shows_a_filtered_empty_message(): void
    {
        $admin = $this->admin(self::ADMIN);

        $response = $this->actingAs($admin)
            ->get(route('admin.studio.index', ['q' => 'zzzznotexist']))
            ->assertOk();

        $response->assertSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
        $response->assertDontSee(
            setting('admin.studio.index.mafysh_qwalb_lsh_abda_bawl_qalb', 'مافيش قوالب لسه — ابدأ بأوّل قالب.'),
            false,
        );
    }

    /** وشاشة القوالب الفارغة فعليًّا (بلا فلتر ولا قوالب) تفضل تعرض رسالة البداية الأصليّة. */
    public function test_actually_empty_without_filters_keeps_the_original_start_message(): void
    {
        ImageTemplate::query()->delete();
        $admin = $this->admin(self::ADMIN);

        $response = $this->actingAs($admin)
            ->get(route('admin.studio.index'))
            ->assertOk();

        $response->assertSee(
            setting('admin.studio.index.mafysh_qwalb_lsh_abda_bawl_qalb', 'مافيش قوالب لسه — ابدأ بأوّل قالب.'),
            false,
        );
        $response->assertDontSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
    }

    /** المولّد يعمل على الخادم ويكتب الصورة في الكاش بمفتاحها */
    public function test_server_side_render_produces_a_cached_png(): void
    {
        $admin = $this->admin(self::ADMIN);
        $template = ImageTemplate::query()->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.studio.preview', $template));

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertDatabaseCount('generated_images', 1);
    }

    // ================================================ أعمدة كانت بلا حقلٍ يملؤها

    /**
     * ⭐ 12.14-أ حرفيًّا: «**رفع الفريم/الخلفيّة** كصورة، وتُبنى فوقها الطبقات».
     * العمود `frame_path` كان يقرؤه المحرّك ولا سبيل لملئه — فالفريم وعدٌ بلا باب.
     */
    public function test_frame_is_saved_and_read_back_on_reopen(): void
    {
        $admin = $this->admin(self::ADMIN);
        $template = ImageTemplate::query()->firstOrFail();
        $path = $this->putFrame('#ff0000');

        $this->actingAs($admin)->put(route('admin.studio.update', $template), $this->payload($template, [
            'frame_path' => $path,
        ]))->assertRedirect();

        $this->assertDatabaseHas('image_templates', ['id' => $template->id, 'frame_path' => $path]);

        // وتُقرَأ عند إعادة الفتح — لا تُحفَظ في القاعدة وتختفي من الشاشة
        $this->actingAs($admin)->get(route('admin.studio.edit', $template))
            ->assertOk()
            ->assertSee('name="frame_path"', false)
            ->assertSee($path, false);
    }

    /**
     * ⭐ **والمحرّك يرسمه فعلًا**: نفس القالب ونفس المستخدم — والبصمة تختلف
     * قبل الفريم وبعده، والبكسل عند (1,1) يصير لون الفريم.
     */
    public function test_the_renderer_actually_draws_the_uploaded_frame(): void
    {
        $template = ImageTemplate::query()->firstOrFail();
        $user = $this->makeUser('سلمى عبد الرحمن محمود');

        $before = app(ImageRenderer::class)->draw($template, $user, []);

        $template->update(['frame_path' => $this->putFrame('#ff0000')]);

        $after = app(ImageRenderer::class)->draw($template->refresh(), $user, []);

        $this->assertNotSame(md5($before), md5($after), 'الفريم اترفع والصورة ما اتغيّرتش — يبقى المحرّك مش بيقراه.');
        $this->assertSame('ff0000', $this->pixelAt($after, 1, 1));
        $this->assertNotSame('ff0000', $this->pixelAt($before, 1, 1));
    }

    /**
     * ⭐ 2.14-ب «مصدر واحد … لا نسخ متعدّدة»: الفريم يفتح **نفس** بوب-أب
     * «اختَر من المكتبة / ارفع جديد» — لا منتقي وسائط ثانٍ في الاستوديو.
     */
    public function test_frame_opens_the_shared_media_picker_not_a_second_one(): void
    {
        $admin = $this->admin(self::ADMIN);
        $template = ImageTemplate::query()->firstOrFail();

        $this->actingAs($admin)->get(route('admin.studio.edit', $template))
            ->assertOk()
            ->assertSee('data-media-pick="frame_path"', false)
            ->assertSee('data-picker-modal', false)
            ->assertSee('data-picker-upload', false);
    }

    /** ⛔ ومسارٌ لا وجود له لا يُحفَظ — فلا يظنّ المصمّم أنّه رفع وهو لم يرفع */
    public function test_a_frame_path_that_is_not_on_disk_is_refused(): void
    {
        $admin = $this->admin(self::ADMIN);
        $template = ImageTemplate::query()->firstOrFail();

        $this->actingAs($admin)->put(route('admin.studio.update', $template), $this->payload($template, [
            'frame_path' => 'media/مش-موجود.png',
        ]))->assertRedirect();

        $this->assertNull($template->refresh()->frame_path);
    }

    /**
     * ⭐ 12.14-أ: «الحفظ والإدارة: حفظ باسم · نسخة · تفعيل/إيقاف · **مجلّدات
     * ووسوم** · بحث». العمودان كانا مُصادَقًا عليهما بلا حقلٍ ولا فلتر.
     */
    public function test_folders_and_tags_are_saved_read_back_and_filter_the_list(): void
    {
        $admin = $this->admin(self::ADMIN);
        $template = ImageTemplate::query()->firstOrFail();

        $this->actingAs($admin)->put(route('admin.studio.update', $template), $this->payload($template, [
            'folders' => 'حملات رمضان, أساسيّات',
            'tags' => 'إنجاز,أخضر,إنجاز',
        ]))->assertRedirect();

        $template->refresh();

        $this->assertSame(['حملات رمضان', 'أساسيّات'], $template->folders);
        // والمكرّر لا يتكرّر — نفس منظّف مكتبة الوسائط
        $this->assertSame(['إنجاز', 'أخضر'], $template->tags);

        $this->actingAs($admin)->get(route('admin.studio.edit', $template))
            ->assertOk()
            ->assertSee('حملات رمضان', false)
            ->assertSee('إنجاز,أخضر', false);

        // والفلتر يُظهر **ويُخفي**: فلترٌ لا يُخفي شيئًا ليس فلترًا
        $this->actingAs($admin)->get(route('admin.studio.index', ['folder' => 'حملات رمضان']))
            ->assertOk()->assertSee($template->name, false);

        $this->actingAs($admin)->get(route('admin.studio.index', ['folder' => 'مجلّد-مش-موجود']))
            ->assertOk()->assertDontSee($template->name, false);

        $this->actingAs($admin)->get(route('admin.studio.index', ['tag' => 'إنجاز']))
            ->assertOk()->assertSee($template->name, false);

        $this->actingAs($admin)->get(route('admin.studio.index', ['tag' => 'وسم-مش-موجود']))
            ->assertOk()->assertDontSee($template->name, false);
    }

    /** والمقاس الجاهز والغرض عمودان كذلك — كان `preset` بلا `name` فلا يُرسَل أصلًا */
    public function test_preset_purpose_and_language_are_persisted(): void
    {
        $admin = $this->admin(self::ADMIN);
        $template = ImageTemplate::query()->firstOrFail();

        $this->actingAs($admin)->put(route('admin.studio.update', $template), $this->payload($template, [
            'preset' => 'story',
            'purpose' => 'leaderboard',
            'language' => 'en',
        ]))->assertRedirect();

        $template->refresh();

        $this->assertSame('story', $template->preset);
        $this->assertSame('leaderboard', $template->purpose);
        $this->assertSame('en', $template->language);

        // والشاشة ترسلها فعلًا: `preset` كان `<select>` **بلا `name`** فلا يصل الخادمَ أبدًا
        $this->actingAs($admin)->get(route('admin.studio.edit', $template))
            ->assertOk()
            ->assertSee('name="preset"', false)
            ->assertSee('name="purpose"', false)
            ->assertSee('name="language"', false);
    }

    /** وقيمةٌ خارج القائمة تُرفَض — لا تُكتَب في العمود بلا حساب */
    public function test_purpose_outside_the_list_is_rejected(): void
    {
        $admin = $this->admin(self::ADMIN);
        $template = ImageTemplate::query()->firstOrFail();

        $this->actingAs($admin)->put(route('admin.studio.update', $template), $this->payload($template, [
            'purpose' => 'anything',
        ]))->assertSessionHasErrors('purpose');
    }

    public function test_forbidden_field_is_rejected_at_the_service_level_too(): void
    {
        $this->expectException(RuntimeException::class);

        app(ImageTemplateFields::class)->validateLayers([
            ['type' => 'text', 'field' => 'email'],
        ]);
    }

    // ============================================== محرّر السحب-إفلات (12.14)
    // الكانفس نفسه (pointerdown/move/up) جافاسكربت متصفّح لا يغطّيه PHPUnit،
    // لكنّ ما يصل الخادم **بعد** السحب هو نفس حقول `layers[i][key]` المخفيّة
    // التي كانت الشاشة القديمة ترسلها بالضبط (`syncHiddenInputs()` في
    // edit.blade.php) — وهذا قابلٌ للتحقّق Feature تمامًا: نرسل موضعًا جديدًا
    // كما يرسله الكانفس فعلًا ونتأكّد أنّه يصل `ImageTemplate::layers` ويُعاد
    // رسمه بنفس البكسل.

    /** ⭐ سحب طبقةٍ لموضعٍ جديد: X/Y الجديدان يصلان الخادم ويُخزَّنان فعليًّا */
    public function test_dragging_a_layer_saves_its_new_pixel_position(): void
    {
        $admin = $this->admin(self::ADMIN);
        $template = ImageTemplate::query()->firstOrFail();
        $layers = $template->layers;

        // طبقة الاسم (index 1) من بذرة العرض — نحرّكها كما يفعل pointermove فعليًّا
        $this->assertSame('text', $layers[1]['type']);
        $user = $this->makeUser('سلمى عبد الرحمن محمود');
        $before = app(ImageRenderer::class)->draw($template, $user, ['short_name' => 'سلمى']);

        $layers[1]['x'] = 733;
        $layers[1]['y'] = 291;

        $this->actingAs($admin)->put(route('admin.studio.update', $template), $this->payload($template, [
            'layers' => $layers,
        ]))->assertRedirect();

        $template->refresh();
        $this->assertSame(733, $template->layers[1]['x']);
        $this->assertSame(291, $template->layers[1]['y']);

        // والمعاينة تعيد رسمه فعليًّا بالمكان الجديد — بصمة مختلفة عن القديمة
        $after = app(ImageRenderer::class)->draw($template, $user, ['short_name' => 'سلمى']);
        $this->assertNotSame(md5($before), md5($after), 'الطبقة اتحرّكت في القاعدة لكنّ الرسم فضل بنفس الموضع القديم.');
    }

    /** ⭐ الحجم والخطّ واللون والمحاذاة لطبقة نصٍّ يُحفَظون بالمثل عبر نفس حقول layers[i][key] */
    public function test_text_layer_size_color_and_alignment_are_saved(): void
    {
        $admin = $this->admin(self::ADMIN);
        $template = ImageTemplate::query()->firstOrFail();
        $layers = $template->layers;

        $layers[1]['size'] = 71;
        $layers[1]['color'] = '#112233';
        $layers[1]['align'] = 'left';
        $layers[1]['rotate'] = 15;

        $this->actingAs($admin)->put(route('admin.studio.update', $template), $this->payload($template, [
            'layers' => $layers,
        ]))->assertRedirect();

        $saved = $template->refresh()->layers[1];
        $this->assertSame(71, $saved['size']);
        $this->assertSame('#112233', $saved['color']);
        $this->assertSame('left', $saved['align']);
        $this->assertSame(15, $saved['rotate']);
    }

    /**
     * ⚠️ Snap (شبكة المحاذاة) **تقريبٌ في الواجهة فقط**: التقريب للشبكة
     * (`Math.round(fx / GRID) * GRID`) يحدث في `pointermove` بالمتصفّح قبل
     * كتابة x/y في الطبقة أصلًا — والخادم (`TemplateLayers::sanitize`) لا
     * يعرف عن Snap شيئًا: لا تقريب ولا حتى قراءة لإعداد `images.studio.grid_step`
     * هناك. فموضعٌ **لا يقع على خطوط الشبكة إطلاقًا** يُقبَل ويُخزَّن بالحرف —
     * وهذا إثباتٌ (لا افتراض) أنّ الخادم يثق بأيّ X/Y صحيح يصله، Snap أو بدونه.
     */
    public function test_server_stores_off_grid_pixel_positions_exactly_snap_is_client_side_only(): void
    {
        $admin = $this->admin(self::ADMIN);
        $template = ImageTemplate::query()->firstOrFail();
        $layers = $template->layers;

        // شبكة 5% على عرض 1080 = خطوط كلّ 54px — 137 و 209 بعيدان عن أيّ خطّ شبكة عمدًا
        $layers[1]['x'] = 137;
        $layers[1]['y'] = 209;

        $this->actingAs($admin)->put(route('admin.studio.update', $template), $this->payload($template, [
            'layers' => $layers,
        ]))->assertRedirect();

        $saved = $template->refresh()->layers[1];
        $this->assertSame(137, $saved['x']);
        $this->assertSame(209, $saved['y']);
    }

    // ------------------------------------------------------------------ أدوات

    /** فريم حقيقيّ على قرص `public` — بلون واحد ليُقاس بالبكسل لا بالظنّ */
    private function putFrame(string $hex): string
    {
        $image = imagecreatetruecolor(40, 40);
        imagefilledrectangle($image, 0, 0, 39, 39, imagecolorallocate(
            $image,
            (int) hexdec(substr($hex, 1, 2)),
            (int) hexdec(substr($hex, 3, 2)),
            (int) hexdec(substr($hex, 5, 2)),
        ));

        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        $path = 'media/frame-'.substr($hex, 1).'.png';
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    private function pixelAt(string $png, int $x, int $y): string
    {
        $image = imagecreatefromstring($png);
        $color = imagecolorat($image, $x, $y);
        imagedestroy($image);

        return sprintf('%02x%02x%02x', ($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF);
    }

    /** الحمولة الكاملة — الفورم يرسل كلّ الحقول، فالاختبار يرسلها كذلك */
    private function payload(ImageTemplate $template, array $overrides = []): array
    {
        return array_merge([
            'name' => $template->name,
            'width_px' => $template->width_px,
            'height_px' => $template->height_px,
            'audience' => $template->audience,
            'is_active' => 1,
            'layers' => $template->layers ?? [],
        ], $overrides);
    }
}
