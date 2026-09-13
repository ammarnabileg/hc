<?php

namespace Tests\Feature\Volunteer\People;

use App\Models\AuditLog;
use App\Models\RecruitmentCandidate;

/**
 * شاشة قمع التطوّع في لوحة التطوّع (24.4): صلاحيّة القراءة `recruitment_analytics.view`
 * وصلاحيّة التصدير `recruitment_analytics.export` مستقلّتان عن `candidates.list`.
 */
class RecruitmentFunnelScreenTest extends PeopleTestCase
{
    private function candidate(array $attributes = []): RecruitmentCandidate
    {
        return RecruitmentCandidate::create(array_merge([
            'user_id' => $this->makeUser('مرشّح')->id,
            'stage' => 'applied',
            'applied_at' => now()->subDays(20),
        ], $attributes));
    }

    public function test_screen_is_forbidden_without_its_own_permission(): void
    {
        // يملك لوحة المرشّحين لكن ليس صلاحيّة التحليلات — يُمنَع رغم ذلك
        $withBoardOnly = $this->userWith(['candidates.list']);

        $this->actingAs($withBoardOnly)
            ->get(route('volunteer.recruitment.analytics'))
            ->assertForbidden();
    }

    public function test_screen_opens_for_the_analytics_permission_alone(): void
    {
        // بلا `candidates.list` إطلاقًا — الصلاحيّة مستقلّة بالكامل (12.2.1-ب)
        $viewer = $this->userWith(['recruitment_analytics.view']);
        $candidate = $this->candidate();
        $candidate->forceFill(['stage' => 'screening'])->save();
        AuditLog::create([
            'action' => 'candidate.stage_moved',
            'auditable_type' => $candidate->getMorphClass(),
            'auditable_id' => $candidate->id,
            'old_values' => ['stage' => 'applied'],
            'new_values' => ['stage' => 'screening'],
        ]);

        $this->actingAs($viewer)
            ->get(route('volunteer.recruitment.analytics'))
            ->assertOk()
            ->assertSee('قمع التطوّع');
    }

    public function test_export_link_only_appears_for_holders_of_the_export_permission(): void
    {
        $viewOnly = $this->userWith(['recruitment_analytics.view']);
        $viewAndExport = $this->userWith(['recruitment_analytics.view', 'recruitment_analytics.export']);
        $this->candidate();

        $this->actingAs($viewOnly)
            ->get(route('volunteer.recruitment.analytics'))
            ->assertOk()
            ->assertDontSee(route('volunteer.recruitment.analytics.export'), false);

        $this->actingAs($viewAndExport)
            ->get(route('volunteer.recruitment.analytics'))
            ->assertOk()
            ->assertSee(route('volunteer.recruitment.analytics.export'), false);
    }

    public function test_export_route_requires_its_own_permission_even_with_view(): void
    {
        $viewOnly = $this->userWith(['recruitment_analytics.view']);

        $this->actingAs($viewOnly)
            ->get(route('volunteer.recruitment.analytics.export'))
            ->assertForbidden();
    }

    public function test_export_streams_a_csv_with_the_funnel_rows(): void
    {
        $exporter = $this->userWith(['recruitment_analytics.view', 'recruitment_analytics.export']);
        $this->candidate();

        $response = $this->actingAs($exporter)->get(route('volunteer.recruitment.analytics.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $body = $response->streamedContent();
        $this->assertStringContainsString('المرحلة', $body);
        $this->assertStringContainsString('تقديم', $body);
    }

    public function test_funnel_link_in_the_board_header_is_gated_by_its_own_permission(): void
    {
        $withLink = $this->userWith(['candidates.list', 'recruitment_analytics.view']);
        $withoutLink = $this->userWith(['candidates.list']);
        $this->candidate();

        $this->actingAs($withLink)
            ->get(route('volunteer.recruitment'))
            ->assertOk()
            ->assertSee(route('volunteer.recruitment.analytics'), false);

        $this->actingAs($withoutLink)
            ->get(route('volunteer.recruitment'))
            ->assertOk()
            ->assertDontSee(route('volunteer.recruitment.analytics'), false);
    }
}
