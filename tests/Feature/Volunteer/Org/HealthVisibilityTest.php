<?php

namespace Tests\Feature\Volunteer\Org;

/**
 * صحّة القسم (24.4-7): **لمسؤول القسم والأبلاين المخوَّل فقط** —
 * ولا يراها العضو العاديّ، ومؤشّر مخاطر الفقدان لا يُعرَض للمتطوّع عن نفسه أبدًا.
 */
class HealthVisibilityTest extends OrgTestCase
{
    public function test_regular_member_cannot_open_department_health(): void
    {
        $member = $this->actorWithRole('VOL-C1', 'coordinator');

        $this->actingAs($member)->get(route('volunteer.health'))->assertForbidden();
        // ويظلّ يرى شاشاته العاديّة — الإخفاء انتقائيّ لا حجب شامل
        $this->actingAs($member)->get(route('volunteer.department'))->assertOk();
    }

    public function test_team_leader_without_the_permission_cannot_open_it_either(): void
    {
        $leader = $this->actorWithRole('VOL-TL1', 'team_leader');

        $this->actingAs($leader)->get(route('volunteer.health'))->assertForbidden();
    }

    public function test_department_owner_sees_indicators_and_leaderboard(): void
    {
        $director = $this->actorWithRole('VOL-DIR', 'director');

        $response = $this->actingAs($director)->get(route('volunteer.health'));

        $response->assertOk();

        $indicators = $response->viewData('indicators');

        $this->assertArrayHasKey('rep_avg', $indicators);
        $this->assertArrayHasKey('commitment_percent', $indicators);
        $this->assertArrayHasKey('review_speed_hours', $indicators);
        $this->assertArrayHasKey('critical_tasks', $indicators);
        $this->assertNotEmpty($response->viewData('leaderboard'));
    }

    public function test_retention_risk_never_includes_the_viewer_himself(): void
    {
        $director = $this->actorWithRole('VOL-DIR', 'director');

        $oversight = $this->actingAs($director)
            ->get(route('volunteer.health', ['tab' => 'oversight']))
            ->viewData('oversight');

        $names = collect($oversight['retention_risk'])->pluck('name');

        $this->assertFalse($names->contains($director->shortName()), 'مؤشّر مخاطر الفقدان داخليّ — ولا يُعرَض للشخص عن نفسه');
    }

    public function test_oversight_tab_collects_the_required_blocks(): void
    {
        $director = $this->actorWithRole('VOL-DIR', 'director');

        $oversight = $this->actingAs($director)
            ->get(route('volunteer.health', ['tab' => 'oversight']))
            ->viewData('oversight');

        foreach (['late_tasks', 'span_breaches', 'retention_risk', 'behavior_grantors', 'late_due_to_child', 'idle_members'] as $block) {
            $this->assertArrayHasKey($block, $oversight);
        }

        // الأعضاء الخاملون: بلا نشاط `offboarding.inactivity.alert_days` يومًا فأكثر
        $idle = collect($oversight['idle_members'])->pluck('name');
        $this->assertTrue($idle->isNotEmpty(), 'البيانات التجريبيّة فيها أعضاء خاملون');
    }

    /** التاب الثاني لا يُبنى إلّا عند فتحه (تحميل كسول — 2.7) */
    public function test_oversight_is_not_built_on_the_indicators_tab(): void
    {
        $director = $this->actorWithRole('VOL-DIR', 'director');

        $this->assertNull(
            $this->actingAs($director)->get(route('volunteer.health'))->viewData('oversight'),
        );
    }
}
