<?php

namespace Tests\Feature\Volunteer\Core;

use App\Models\RepScore;

/**
 * شاشات النظرة العامّة (24.4-1) وسايد بار لوحة التطوّع (13.4-ح):
 * ما لا يملكه المستخدم **يُخفى لا يُعطَّل** (2.15-أ-7)،
 * والتقرير الأسبوعيّ **بلا أيّ مؤشّر لمخاطر الفقدان**.
 */
class OverviewScreensTest extends VolunteerCoreTestCase
{
    public function test_journey_screen_shows_four_kpis_and_hides_unpermitted_sidebar_items(): void
    {
        $user = $this->makeUser('سلمى');
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity, 'team_leader');
        $this->grant($user, ['personal_reports.view', 'tasks.list']);

        $response = $this->actingAs($user)->get(route('volunteer.overview'));

        $response->assertOk();
        $response->assertSee('رحلتي في التطوّع');
        $response->assertSee('مدّة الخدمة');
        $response->assertSee('البوزشن الحاليّ');
        $response->assertSee('الشهادات');
        $response->assertSee('إجمالي VXP');

        // عناصر السايد بار بلا صلاحيّة لا تظهر أصلًا
        $response->assertSee('المهام');
        $response->assertDontSee('التقدير');
        $response->assertDontSee('التوظيف');
        $response->assertDontSee('المكتبة الداخليّة');
    }

    public function test_weekly_report_never_shows_attrition_risk(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);
        $this->grant($user, ['personal_reports.view']);

        $response = $this->actingAs($user)->get(route('volunteer.report'));

        $response->assertOk();
        $response->assertSee('صافي Rep للأسبوع');
        $response->assertSee('حضور الاجتماعات');
        $response->assertDontSee('مخاطر الفقدان');
        $response->assertDontSee('خطر الفقدان');
    }

    public function test_calendar_marks_hours_outside_the_activity_window(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);
        $this->grant($user, ['calendar.view', 'personal_reports.view']);

        $response = $this->actingAs($user)->get(route('volunteer.calendar'));

        $response->assertOk();
        $response->assertSee('تقويم نشاطي');
        $response->assertSee('نافذة النشاط');
        $response->assertSee('لا يُحتسَب تأخيرًا');
    }

    public function test_pages_are_guarded_by_permission(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);

        $this->actingAs($user)->get(route('volunteer.overview'))->assertForbidden();
        $this->actingAs($user)->get(route('volunteer.calendar'))->assertForbidden();
        $this->actingAs($user)->get(route('volunteer.tasks.index'))->assertForbidden();
    }

    public function test_rep_badge_turns_red_below_the_configured_threshold(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);
        $this->grant($user, ['personal_reports.view']);

        RepScore::create([
            'user_id' => $user->id,
            'score' => rep_rule('limit.red_indicator', -8) - 0.5,
        ]);

        $response = $this->actingAs($user)->get(route('volunteer.overview'));

        $response->assertOk();
        $response->assertSee('المؤشّر الأحمر', false);
    }
}
