<?php

namespace Tests\Feature\Library;

use App\Models\Cv;
use App\Models\CvTemplate;
use App\Models\Role;
use App\Models\RoleUser;
use App\Models\User;
use App\Support\Access\AccessEngine;

/**
 * شاشة إدارة قوالب الـCV (الدستور 9): «الأدمن يضيف قوالب، وكلّ قالب له عدد
 * تذاكر خاصّ» — ولم تكن الشاشة موجودة أصلًا.
 */
class CvTemplateAdminTest extends LibraryTestCase
{
    public function test_admin_screen_lists_templates_with_their_ticket_price(): void
    {
        $this->actingAs($this->owner())
            ->get(route('admin.cv-templates.index'))
            ->assertOk()
            ->assertSee(setting('cv.template.admin.page_title', 'قوالب السيرة الذاتيّة'), false)
            ->assertSee(CvTemplate::orderBy('sort_order')->value('name'), false);
    }

    public function test_admin_adds_a_template_with_its_own_ticket_price(): void
    {
        $this->actingAs($this->owner())->post(route('admin.cv-templates.store'), [
            'name' => 'قالب تنفيذيّ',
            'view_path' => 'classic',
            'price_tickets' => 7,
            'is_active' => 1,
        ])->assertRedirect();

        $template = CvTemplate::where('name', 'قالب تنفيذيّ')->firstOrFail();

        $this->assertSame(7.0, $template->priceTickets());
        $this->assertTrue($template->is_active);
    }

    /** أثر القالب في المخرَج (9) — التباعد والأحجام تُحفَظ وتُقرأ */
    public function test_template_ats_options_are_saved_and_read_back(): void
    {
        $template = CvTemplate::where('is_free', false)->orderBy('sort_order')->firstOrFail();

        $this->actingAs($this->owner())->put(route('admin.cv-templates.update', $template), [
            'name' => $template->name,
            'view_path' => $template->view_path ?: 'classic',
            'is_active' => 1,
            'ats_options' => ['margin_pt' => 40, 'leading' => 1.8, 'bogus' => 9],
        ])->assertRedirect();

        $options = $template->fresh()->atsOptions();

        $this->assertSame(40.0, $options['margin_pt']);
        $this->assertSame(1.8, $options['leading']);
        $this->assertArrayNotHasKey('bogus', $options);
    }

    /** لا تُكسَر سيرةٌ منشورة بحذف قالبها من تحتها — الإيقاف بدل الحذف */
    public function test_a_template_in_use_is_deactivated_not_deleted(): void
    {
        $user = $this->trainee('UCVADM01');
        $template = CvTemplate::where('is_free', false)->orderBy('sort_order')->firstOrFail();

        Cv::updateOrCreate(['user_id' => $user->id], ['data' => [], 'cv_template_id' => $template->id]);

        $this->actingAs($this->owner())
            ->delete(route('admin.cv-templates.destroy', $template))
            ->assertRedirect();

        $this->assertNotNull($template->fresh());
        $this->assertFalse($template->fresh()->is_active);
    }

    public function test_a_trainee_cannot_reach_the_admin_screen(): void
    {
        $this->actingAs($this->trainee('UCVNOAD1'))
            ->get(route('admin.cv-templates.index'))
            ->assertForbidden();
    }

    private function owner(): User
    {
        $user = User::create([
            'name' => 'مالك المنصّة',
            'email' => 'cvowner@test.local',
            'password' => 'secret-password',
            'code' => 'UCVOWNER',
            'status' => 'active',
        ]);

        RoleUser::create([
            'role_id' => Role::where('key', 'platform_owner')->value('id'),
            'user_id' => $user->id,
            'assigned_at' => now(),
        ]);

        app(AccessEngine::class)->forget();

        return $user;
    }
}
