<?php

namespace Tests\Feature\Volunteer\Profile;

use App\Models\User;

/**
 * الفجوة 1: التابات الخمس تُحقَن فعلًا في البروفايل الواحد (13.4-م · 10.0-د)،
 * وبمستويات المشاهدة الأربعة، والحسّاس مخفيّ افتراضيًّا.
 */
class VolunteerProfileTabsTest extends ProfileTestCase
{
    public function test_the_five_volunteer_tabs_are_injected_into_the_single_profile_page(): void
    {
        $admin = $this->actorWithRole('VOL-DIR', 'super_admin');

        $response = $this->actingAs($admin)->get('/u/VOL-C1');

        $response->assertOk()
            ->assertSee('tab=volunteer', false)
            ->assertSee('tab=volunteer_contact', false)
            ->assertSee('tab=volunteer_org', false)
            ->assertSee('tab=volunteer_performance', false)
            ->assertSee('tab=volunteer_notes', false);
    }

    /** طبقة التطوّع لا تظهر ولا يُعرَف بوجودها لغير المتطوّع المُسكَّن (10.0-د) */
    public function test_layer_is_absent_for_a_non_volunteer_profile(): void
    {
        $viewer = $this->actorWithRole('VOL-TL1', 'team_leader');

        $stranger = User::create([
            'name' => 'زائر بلا تسكين', 'email' => 'plain@demo.local', 'password' => 'secret-password',
            'code' => 'PLAIN-1', 'status' => 'active',
        ]);

        $this->actingAs($viewer)->get('/u/'.$stranger->code)
            ->assertOk()
            ->assertDontSee('tab=volunteer_contact', false);
    }

    /** الزميل لا يرى تاب الأداء ولا الملاحظات أصلًا — يُخفى ولا يُعطَّل (2.15-أ-7) */
    public function test_peer_never_sees_performance_or_notes_tabs(): void
    {
        $peer = $this->actorWithRole('VOL-C3', 'coordinator');

        $this->actingAs($peer)->get('/u/VOL-C1')
            ->assertOk()
            ->assertSee('tab=volunteer_contact', false)
            ->assertDontSee('tab=volunteer_performance', false)
            ->assertDontSee('tab=volunteer_notes', false);
    }

    /** صاحب البروفايل لا يرى تاب الملاحظات الإداريّة أبدًا (13.4-م-5) */
    public function test_owner_never_sees_the_admin_notes_tab(): void
    {
        $owner = $this->actorWithRole('VOL-C1', 'coordinator');

        $this->actingAs($owner)->get(route('profile.me', ['tab' => 'volunteer']))
            ->assertOk()
            ->assertDontSee('tab=volunteer_notes', false);
    }

    public function test_overview_tab_shows_kudos_reasons_and_journey(): void
    {
        $owner = $this->actorWithRole('VOL-C1', 'coordinator');

        $this->actingAs($owner)->get(route('profile.me', ['tab' => 'volunteer']))
            ->assertOk()
            ->assertSee('رحلتي في التطوّع')
            ->assertSee('طريقك للبوزشن الجاي')
            // آخر الشكرات بأسبابها — القصّة أقوى من العدّاد
            ->assertSee('ساعدني في تجهيز الكارت وأنا مضغوطة.');
    }

    /** التنبيه الهادئ عند حدّ الإنذار للأبلاين وحده — بلا فضح أمام الزملاء */
    public function test_low_rep_alert_is_for_the_upline_only(): void
    {
        $upline = $this->actorWithRole('VOL-TL1', 'team_leader');
        $owner = $this->actorWithRole('VOL-C2', 'coordinator');

        $this->actingAs($upline)->get('/u/VOL-C2?tab=volunteer')
            ->assertOk()
            ->assertSee('وصلت لحدّ الإنذار');

        $this->actingAs($owner)->get(route('profile.me', ['tab' => 'volunteer']))
            ->assertOk()
            ->assertDontSee('وصلت لحدّ الإنذار');
    }

    /** ⛔ مؤشّر مخاطر الفقدان لا يُعرَض لصاحب البروفايل أبدًا (13.4-م-4) */
    public function test_retention_risk_is_never_shown_to_the_profile_owner(): void
    {
        $owner = $this->actorWithRole('VOL-C2', 'coordinator');

        $this->actingAs($owner)->get(route('profile.me', ['tab' => 'volunteer_performance']))
            ->assertOk()
            ->assertDontSee('مؤشّر مخاطر الفقدان');
    }

    public function test_retention_risk_is_visible_to_the_authorised_upline(): void
    {
        $upline = $this->actorWithRole('VOL-TL1', 'team_leader');

        $this->actingAs($upline)->get('/u/VOL-C2?tab=volunteer_performance')
            ->assertOk()
            ->assertSee('مؤشّر مخاطر الفقدان');
    }

    public function test_organization_tab_shows_the_full_upline_chain(): void
    {
        $owner = $this->actorWithRole('VOL-C1', 'coordinator');

        $this->actingAs($owner)->get(route('profile.me', ['tab' => 'volunteer_org']))
            ->assertOk()
            ->assertSee('سلسلة الأبلاين لأعلى')
            ->assertSee('تايم-لاين البوزشنز')
            // الأبلاين المباشر ثمّ مَن فوقه حتى أعلى الهيكل (بالاسم المختصر 12.14-ج)
            ->assertSee($this->userByCode('VOL-TL1')->shortName())
            ->assertSee($this->userByCode('VOL-SUP1')->shortName())
            ->assertSee($this->userByCode('VOL-DIR')->shortName())
            ->assertSee('الأبلاين المباشر');
    }

    public function test_admin_notes_are_private_to_the_authorised_and_audited(): void
    {
        $admin = $this->actorWithRole('VOL-DIR', 'super_admin');

        $this->actingAs($admin)
            ->post(route('volunteer.profile.notes.store', ['code' => 'VOL-C1']), [
                'body' => 'ملاحظة تجريبيّة عند مراجعة الترقية.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('volunteer_profile_notes', [
            'user_id' => $this->userByCode('VOL-C1')->id,
            'author_id' => $admin->id,
        ]);

        // Audit كامل: الملاحظة السرّيّة لا تُكتَب بلا أثر
        $this->assertDatabaseHas('audit_logs', ['action' => 'volunteer_profile.note.created']);

        // وصاحب البروفايل لا يصل للتاب ولا يرى نصّها
        $owner = $this->actorWithRole('VOL-C1', 'coordinator');

        $this->actingAs($owner)->get(route('profile.me', ['tab' => 'volunteer_notes']))
            ->assertOk()
            ->assertDontSee('ملاحظة تجريبيّة عند مراجعة الترقية.');
    }

    /** تقرير الترقية: رقم مرجع + تاريخ + تسجيل في الأوديت (13.4-م-1) */
    public function test_promotion_report_carries_a_reference_and_is_audited(): void
    {
        $upline = $this->actorWithRole('VOL-SUP1', 'supervisor');

        $this->actingAs($upline)
            ->get(route('volunteer.profile.report', ['code' => 'VOL-C1']))
            ->assertOk()
            ->assertSee('رقم المرجع')
            ->assertSee('VPR-');

        $this->assertDatabaseHas('audit_logs', ['action' => 'volunteer_profile.report.issued']);
    }

    public function test_report_is_forbidden_without_the_permission(): void
    {
        $peer = $this->actorWithRole('VOL-C3', 'coordinator');

        $this->actingAs($peer)
            ->get(route('volunteer.profile.report', ['code' => 'VOL-C1']))
            ->assertForbidden();
    }
}
