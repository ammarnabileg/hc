<?php

namespace Tests\Feature\Admin\System;

use App\Models\ImageTemplate;
use App\Models\NameParticle;
use App\Services\Images\ImageTemplateFields;
use App\Services\Images\TemplateLayers;
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

    public function test_forbidden_field_is_rejected_at_the_service_level_too(): void
    {
        $this->expectException(RuntimeException::class);

        app(ImageTemplateFields::class)->validateLayers([
            ['type' => 'text', 'field' => 'email'],
        ]);
    }
}
