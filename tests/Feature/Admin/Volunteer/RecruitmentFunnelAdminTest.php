<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\AuditLog;
use App\Models\RecruitmentCandidate;

/**
 * قمع التطوّع في لوحة تحليلات التطوّع بالأدمن (24.4): يظهر فوق شاشة
 * `reports_volunteer.view` القائمة، لكن بصلاحيّته الخاصّة `recruitment_analytics.*` —
 * فحاملُ `reports_volunteer.view` وحده يفتح الشاشة ولا يرى قسم القمع.
 */
class RecruitmentFunnelAdminTest extends AdminVolunteerTestCase
{
    private function candidate(array $attributes = []): RecruitmentCandidate
    {
        return RecruitmentCandidate::create(array_merge([
            'user_id' => $this->makeUser('مرشّح')->id,
            'stage' => 'applied',
            'applied_at' => now()->subDays(20),
        ], $attributes));
    }

    public function test_funnel_section_is_hidden_from_reports_viewer_without_the_analytics_permission(): void
    {
        $reportsOnly = $this->grant($this->makeUser(), 'reports_volunteer.view');
        $this->candidate();

        $this->actingAs($reportsOnly)
            ->get(route('admin.volunteer.analytics'))
            ->assertOk()
            ->assertDontSee('قمع التطوّع');
    }

    public function test_funnel_section_shows_for_holders_of_the_analytics_permission(): void
    {
        $withFunnel = $this->grant($this->makeUser(), 'reports_volunteer.view', 'recruitment_analytics.view');
        $candidate = $this->candidate();
        $candidate->forceFill(['stage' => 'screening'])->save();
        AuditLog::create([
            'action' => 'candidate.stage_moved',
            'auditable_type' => $candidate->getMorphClass(),
            'auditable_id' => $candidate->id,
            'old_values' => ['stage' => 'applied'],
            'new_values' => ['stage' => 'screening'],
        ]);

        $this->actingAs($withFunnel)
            ->get(route('admin.volunteer.analytics'))
            ->assertOk()
            ->assertSee('قمع التطوّع');
    }

    public function test_export_route_requires_its_own_export_permission_not_just_view(): void
    {
        $viewOnly = $this->grant($this->makeUser(), 'reports_volunteer.view', 'recruitment_analytics.view');

        $this->actingAs($viewOnly)
            ->get(route('admin.volunteer.analytics.recruitment-funnel.export'))
            ->assertForbidden();
    }

    public function test_export_streams_a_csv(): void
    {
        $exporter = $this->grant($this->makeUser(), 'reports_volunteer.view', 'recruitment_analytics.view', 'recruitment_analytics.export');
        $this->candidate();

        $response = $this->actingAs($exporter)->get(route('admin.volunteer.analytics.recruitment-funnel.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('تقديم', $response->streamedContent());
    }
}
