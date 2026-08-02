<?php

namespace Tests\Feature\Admin\Ops;

use Illuminate\Support\Facades\DB;

/**
 * محتوى الـOnboarding (12.7-أ) و«شاشة أوّل مرّة» (2.15-د):
 * الأدمن يكتب المحتوى ويرتّبه ويعاينه كما يراه المستخدم — بلا سطر كود.
 */
class AdminOpsOnboardingTest extends OpsTestCase
{
    private const EDITOR = ['onboarding.view', 'onboarding.create', 'onboarding.edit', 'onboarding.delete'];

    public function test_screen_lists_the_welcome_series(): void
    {
        $admin = $this->admin(self::EDITOR);

        $this->actingAs($admin)->get(route('admin.ops.onboarding'))
            ->assertOk()
            ->assertSee('أهلًا بيك معانا')
            ->assertSee('سلسلة الترحيب الأولى');
    }

    public function test_first_time_tab_lists_screens_and_ready_templates(): void
    {
        $admin = $this->admin(self::EDITOR);

        $this->actingAs($admin)->get(route('admin.ops.onboarding', ['tab' => 'first_time']))
            ->assertOk()
            ->assertSee('شاشة أوّل مرّة')
            ->assertSee('القوالب الجاهزة')
            ->assertSee('الرئيسيّة');
    }

    public function test_admin_creates_edits_toggles_and_deletes_a_stage(): void
    {
        $admin = $this->admin(self::EDITOR);

        $this->actingAs($admin)->post(route('admin.ops.onboarding.slides.store'), [
            'screen' => 'welcome',
            'title_ar' => 'مرحلة جديدة',
            'body_ar' => 'شرح قصير.',
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $slide = DB::table('onboarding_slides')->where('title_ar', 'مرحلة جديدة')->first();
        $this->assertNotNull($slide);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ops.onboarding.slide.created']);

        $this->actingAs($admin)->put(route('admin.ops.onboarding.slides.update', $slide->id), [
            'screen' => 'welcome',
            'title_ar' => 'مرحلة معدَّلة',
        ])->assertRedirect();
        $this->assertDatabaseHas('onboarding_slides', ['id' => $slide->id, 'title_ar' => 'مرحلة معدَّلة']);

        $this->actingAs($admin)->post(route('admin.ops.onboarding.slides.toggle', $slide->id))->assertRedirect();
        $this->assertDatabaseHas('onboarding_slides', ['id' => $slide->id, 'is_active' => false]);

        $this->actingAs($admin)->delete(route('admin.ops.onboarding.slides.destroy', $slide->id))->assertRedirect();
        $this->assertDatabaseMissing('onboarding_slides', ['id' => $slide->id]);
    }

    /** الترتيب هو تسلسل المراحل الذي يمرّ به المستخدم — فلا بدّ أن يُحفَظ كما رُتِّب */
    public function test_reorder_saves_the_exact_sequence(): void
    {
        $admin = $this->admin(self::EDITOR);
        $ids = DB::table('onboarding_slides')->where('screen', 'welcome')->orderBy('sort_order')->pluck('id')->all();

        $this->actingAs($admin)->post(route('admin.ops.onboarding.reorder'), [
            'screen' => 'welcome',
            'order' => array_reverse($ids),
        ])->assertRedirect();

        $after = DB::table('onboarding_slides')->where('screen', 'welcome')->orderBy('sort_order')->pluck('id')->all();

        $this->assertSame(array_reverse($ids), $after);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ops.onboarding.reordered']);
    }

    /** الحدّ الأقصى للشرائح إعداد — وتجاوزه يُرفَض برسالة تقول ماذا يفعل */
    public function test_stage_limit_comes_from_settings(): void
    {
        $admin = $this->admin(self::EDITOR);
        $this->set('onboarding.slides.max', 3);

        $this->actingAs($admin)->post(route('admin.ops.onboarding.slides.store'), [
            'screen' => 'welcome',
            'title_ar' => 'مرحلة زيادة',
        ])->assertSessionHasErrors('title_ar');

        $this->assertDatabaseMissing('onboarding_slides', ['title_ar' => 'مرحلة زيادة']);
    }

    /** ⭐ المعاينة كما يراها المستخدم: المفعَّل يظهر والموقوف لا يظهر */
    public function test_preview_shows_active_stages_only(): void
    {
        $admin = $this->admin(self::EDITOR);

        $hidden = DB::table('onboarding_slides')->where('screen', 'welcome')->orderBy('sort_order')->first();
        DB::table('onboarding_slides')->where('id', $hidden->id)->update(['is_active' => false]);

        $this->actingAs($admin)->get(route('admin.ops.onboarding.preview', ['screen' => 'welcome']))
            ->assertOk()
            ->assertSee('تدريب بخطوات واضحة')
            ->assertDontSee($hidden->title_ar);
    }

    /** «شاشة أوّل مرّة»: الأدمن يختار الشاشات، والاختيار يُحفَظ في الإعداد الموحّد */
    public function test_admin_chooses_the_screens_that_show_the_first_time_popup(): void
    {
        $admin = $this->admin(self::EDITOR);

        $this->actingAs($admin)->post(route('admin.ops.onboarding.first-time'), [
            'screens' => ['dashboard', 'wallet.index', 'شاشة-مش-موجودة'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $saved = setting('ux.first_time.enabled_screens');

        $this->assertContains('dashboard', $saved);
        $this->assertContains('wallet.index', $saved);
        $this->assertNotContains('شاشة-مش-موجودة', $saved, 'شاشة مجهولة اتحفظت — ده باب خلفيّ.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'ops.onboarding.first_time.updated']);
    }

    /** القالب الجاهز نقطة بداية: يضيف المراحل ولا يمسح ما كتبه الأدمن */
    public function test_ready_template_adds_stages_without_erasing_existing_ones(): void
    {
        $admin = $this->admin(self::EDITOR);
        $before = DB::table('onboarding_slides')->where('screen', 'wallet.index')->count();

        $this->actingAs($admin)->post(route('admin.ops.onboarding.template'), ['screen' => 'wallet.index'])
            ->assertRedirect();

        $this->assertGreaterThan($before, DB::table('onboarding_slides')->where('screen', 'wallet.index')->count());
        $this->assertDatabaseHas('onboarding_slides', ['screen' => 'wallet.index', 'from_template' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ops.onboarding.template.applied']);
    }
}
