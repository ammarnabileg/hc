<?php

namespace Tests\Feature\Volunteer\People;

use App\Models\AuditLog;
use App\Models\RecruitmentCandidate;
use App\Services\Volunteer\People\CandidatePipeline;

/**
 * لوحة المرشّحين (13.4-د · 13.4-ق): النقل بسبب مسجَّل، وطبقتا الخصوصيّة،
 * وشارة «عائد» بسطر خدمته السابقة.
 */
class RecruitmentBoardTest extends PeopleTestCase
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

    public function test_board_screen_loads_for_the_recruitment_team(): void
    {
        $user = $this->userWith(['candidates.list', 'candidates.view', 'candidates.edit']);
        $this->candidate();

        $this->actingAs($user)
            ->get(route('volunteer.recruitment'))
            ->assertOk()
            ->assertSee('المرشّحون');
    }

    public function test_board_is_forbidden_without_permission(): void
    {
        $this->actingAs($this->makeUser('بلا صلاحيّة'))
            ->get(route('volunteer.recruitment'))
            ->assertForbidden();
    }

    public function test_moving_a_card_requires_a_reason_and_is_written_to_audit_logs(): void
    {
        $actor = $this->userWith(['candidates.list', 'candidates.edit']);
        $candidate = $this->candidate();

        // بلا سبب: مرفوض
        $this->actingAs($actor)
            ->post(route('volunteer.recruitment.move', $candidate), ['stage' => 'screening'])
            ->assertSessionHasErrors('reason');

        $this->assertSame('applied', $candidate->fresh()->stage);

        // بسبب: يتمّ ويُسجَّل
        $this->actingAs($actor)
            ->post(route('volunteer.recruitment.move', $candidate), [
                'stage' => 'screening',
                'reason' => 'السيرة الذاتيّة مطابقة لاحتياج القسم.',
            ])
            ->assertRedirect();

        $this->assertSame('screening', $candidate->fresh()->stage);

        $log = AuditLog::where('action', 'candidate.stage_moved')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('applied', $log->old_values['stage']);
        $this->assertSame('screening', $log->new_values['stage']);
        $this->assertNotEmpty($log->new_values['reason']);
    }

    public function test_phone_is_for_the_recruitment_team_and_exit_reason_for_authorized_only(): void
    {
        $pipeline = app(CandidatePipeline::class);

        $team = $this->userWith(['candidates.list', 'candidates.view']);
        $authorized = $this->userWith(['candidates.list', 'candidates.view', 'offboarding.view']);
        $plain = $this->userWith(['candidates.list']);

        $this->assertTrue($pipeline->canSeePhone($team));
        $this->assertFalse($pipeline->canSeePhone($plain));

        // سبب الخروج ليس لفريق التوظيف — للمخوَّلين وحدهم (13.4-ق-هـ)
        $this->assertFalse($pipeline->canSeeExitReason($team));
        $this->assertTrue($pipeline->canSeeExitReason($authorized));
    }

    public function test_returning_candidate_shows_the_service_line(): void
    {
        $pipeline = app(CandidatePipeline::class);

        $candidate = $this->candidate([
            'is_returning' => true,
            'previous_service_from' => now()->subYears(2),
            'previous_service_to' => now()->subYear(),
        ]);

        $line = $pipeline->returningLine($candidate);

        $this->assertNotNull($line);
        $this->assertStringContainsString('كان معنا من', $line);
        $this->assertStringContainsString('مدّة الخدمة', $line);

        $this->assertNull($pipeline->returningLine($this->candidate()));
    }

    public function test_score_range_filter_keeps_candidates_without_a_score(): void
    {
        $pipeline = app(CandidatePipeline::class);
        $viewer = $this->userWith(['candidates.list']);

        $inside = $this->candidate(['qualifying_score' => 95]);
        $outside = $this->candidate(['qualifying_score' => 40]);
        $unscored = $this->candidate(['qualifying_score' => null]);

        $ids = $pipeline->query($viewer, ['score_min' => 90, 'score_max' => 100])->pluck('id');

        $this->assertContains($inside->id, $ids);
        $this->assertNotContains($outside->id, $ids);
        $this->assertContains($unscored->id, $ids, 'المرشّح بلا درجة لا يُقصى بصمت من نطاق الدرجات.');
    }
}
