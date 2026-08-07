<?php

namespace Tests\Feature\Volunteer\People;

use App\Models\AuditLog;
use App\Models\RecruitmentCandidate;

/**
 * ثلاثة من أزرار هيدر شاشة التوظيف المنصوصة (24.4-12): + مرشّح يدويّ ·
 * تصدير CSV · رابط تقويم المقابلات — كانت الشاشة بلا هيدر إجراءات إطلاقًا.
 */
class RecruitmentHeaderActionsTest extends PeopleTestCase
{
    private function candidate(array $attributes = []): RecruitmentCandidate
    {
        return RecruitmentCandidate::create(array_merge([
            'user_id' => $this->makeUser('مرشّح')->id,
            'stage' => 'applied',
            'qualifying_score' => 80,
            'applied_at' => now()->subDays(10),
        ], $attributes));
    }

    // ------------------------------------------------------------ + مرشّح يدويّ

    public function test_manual_add_creates_a_candidate_in_the_applied_column(): void
    {
        $actor = $this->userWith(['candidates.list', 'candidates.create']);
        $target = $this->makeUser('مرشّح جديد');

        $this->actingAs($actor)
            ->post(route('volunteer.recruitment.store'), ['code' => $target->code])
            ->assertRedirect(route('volunteer.recruitment'));

        $candidate = RecruitmentCandidate::query()->where('user_id', $target->id)->firstOrFail();

        $this->assertSame('applied', $candidate->stage);
        $this->assertNull($candidate->qualifying_score);
        $this->assertDatabaseHas('audit_logs', ['action' => 'candidate.added_manually', 'auditable_id' => $candidate->id]);
    }

    public function test_manual_add_requires_its_own_permission(): void
    {
        $actor = $this->userWith(['candidates.list']);
        $target = $this->makeUser('مرشّح جديد');

        $this->actingAs($actor)
            ->post(route('volunteer.recruitment.store'), ['code' => $target->code])
            ->assertForbidden();

        $this->assertSame(0, RecruitmentCandidate::query()->where('user_id', $target->id)->count());
    }

    public function test_manual_add_rejects_a_code_that_is_already_an_active_candidate(): void
    {
        $actor = $this->userWith(['candidates.list', 'candidates.create']);
        $existing = $this->candidate();

        $this->actingAs($actor)
            ->post(route('volunteer.recruitment.store'), ['code' => $existing->user->code])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, RecruitmentCandidate::query()->where('user_id', $existing->user_id)->count());
    }

    public function test_manual_add_rejects_an_unknown_code(): void
    {
        $actor = $this->userWith(['candidates.list', 'candidates.create']);

        $this->actingAs($actor)
            ->post(route('volunteer.recruitment.store'), ['code' => 'NOSUCHCODE'])
            ->assertRedirect();

        $this->assertSame(0, AuditLog::query()->where('action', 'candidate.added_manually')->count());
    }

    // ------------------------------------------------------------ تصدير CSV

    public function test_export_requires_its_own_permission(): void
    {
        $actor = $this->userWith(['candidates.list']);

        $this->actingAs($actor)
            ->get(route('volunteer.recruitment.export'))
            ->assertForbidden();
    }

    public function test_export_streams_a_csv_with_the_candidate_row(): void
    {
        $actor = $this->userWith(['candidates.list', 'candidates.export']);
        $candidate = $this->candidate();

        $response = $this->actingAs($actor)->get(route('volunteer.recruitment.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $body = $response->streamedContent();
        $this->assertStringContainsString($candidate->user->code, $body);
    }

    public function test_export_respects_the_stage_filter(): void
    {
        $actor = $this->userWith(['candidates.list', 'candidates.export']);
        $applied = $this->candidate(['stage' => 'applied']);
        $placed = $this->candidate(['stage' => 'placed']);

        $body = $this->actingAs($actor)
            ->get(route('volunteer.recruitment.export', ['stage' => 'applied']))
            ->streamedContent();

        $this->assertStringContainsString($applied->user->code, $body);
        $this->assertStringNotContainsString($placed->user->code, $body);
    }

    // ------------------------------------------------------------ رابط تقويم المقابلات

    public function test_interviews_link_only_shows_for_holders_of_its_permission(): void
    {
        $withLink = $this->userWith(['candidates.list', 'interviews.list']);
        $withoutLink = $this->userWith(['candidates.list']);
        $this->candidate();

        $this->actingAs($withLink)
            ->get(route('volunteer.recruitment'))
            ->assertOk()
            ->assertSee(route('volunteer.interviews'), false);

        $this->actingAs($withoutLink)
            ->get(route('volunteer.recruitment'))
            ->assertOk()
            ->assertDontSee(route('volunteer.interviews'), false);
    }
}
