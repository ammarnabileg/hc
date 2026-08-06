<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Interview;
use App\Models\InterviewCriterion;
use App\Models\InterviewScorecard;
use App\Models\RecruitmentCandidate;
use App\Services\Volunteer\People\ScorecardEngine;

/**
 * معايير المقابلة (13.4-د): شاشة أدمن — بلاها الكتالوج رقمٌ محروق في سيدر.
 * الحذف سوفت بخيارين، ومعه Undo وسجلّ تدقيق.
 */
class ScorecardCriteriaTest extends AdminVolunteerTestCase
{
    private function scorecardWith(int $criterionId, float $score): InterviewScorecard
    {
        $candidate = RecruitmentCandidate::create([
            'user_id' => $this->makeUser('مرشّح')->id,
            'stage' => 'interview',
            'applied_at' => now()->subDays(2),
        ]);

        $interview = Interview::create([
            'recruitment_candidate_id' => $candidate->id,
            'interviewer_id' => $this->makeUser('مُقابِل')->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        return InterviewScorecard::create([
            'interview_id' => $interview->id,
            'criteria_scores' => [(string) $criterionId => $score],
            'total_score' => $score,
            'is_draft' => false,
        ]);
    }

    public function test_the_screen_is_hidden_from_whoever_does_not_own_it(): void
    {
        $this->actingAs($this->makeUser())
            ->get(route('admin.volunteer.scorecard-criteria.index'))
            ->assertForbidden();
    }

    public function test_a_new_criterion_is_created_and_appears_for_input(): void
    {
        $admin = $this->grant($this->makeUser(), 'scorecard_criteria.list', 'scorecard_criteria.create');

        $this->actingAs($admin)
            ->get(route('admin.volunteer.scorecard-criteria.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.volunteer.scorecard-criteria.store'), [
                'label_ar' => 'وضوح التواصل',
                'weight' => 2,
                'sort_order' => 5,
            ])
            ->assertRedirect(route('admin.volunteer.scorecard-criteria.index'));

        $criterion = InterviewCriterion::query()->where('label_ar', 'وضوح التواصل')->firstOrFail();

        $this->assertSame(2, (int) $criterion->weight);
        $this->assertFalse((bool) $criterion->is_archived);
        $this->assertDatabaseHas('audit_logs', ['action' => 'scorecard_criteria.created', 'auditable_id' => $criterion->id]);
    }

    public function test_editing_requires_its_own_permission(): void
    {
        $criterion = InterviewCriterion::create(['label_ar' => 'معيار', 'weight' => 1, 'sort_order' => 1]);
        $creatorOnly = $this->grant($this->makeUser(), 'scorecard_criteria.list', 'scorecard_criteria.create');

        $this->actingAs($creatorOnly)
            ->put(route('admin.volunteer.scorecard-criteria.update', $criterion), ['label_ar' => 'اسم جديد'])
            ->assertForbidden();

        $this->assertSame('معيار', $criterion->refresh()->label_ar);
    }

    /** ⭐ الأرشفة («من الجديد فقط»): يختفي من الإدخال الجديد — والقديم يبقى بدرجته موسومًا (13.4-د) */
    public function test_archiving_hides_it_from_new_input_but_keeps_the_old_score_tagged(): void
    {
        $admin = $this->grant($this->makeUser(), 'scorecard_criteria.list', 'scorecard_criteria.delete');
        $criterion = InterviewCriterion::create(['label_ar' => 'معيار قديم', 'weight' => 1, 'sort_order' => 1]);
        $card = $this->scorecardWith($criterion->id, 8.0);

        $this->actingAs($admin)
            ->delete(route('admin.volunteer.scorecard-criteria.destroy', $criterion), ['mode' => 'new_only'])
            ->assertRedirect();

        $criterion->refresh();
        $this->assertTrue((bool) $criterion->is_archived);

        $engine = app(ScorecardEngine::class);
        $this->assertFalse($engine->activeCriteria()->contains('id', $criterion->id), 'مؤرشف — مايظهرش للإدخال الجديد');

        $rows = collect($engine->rows($card->fresh()));
        $row = $rows->firstWhere('id', $criterion->id);
        $this->assertNotNull($row, 'القديم يبقى ظاهرًا بدرجته');
        $this->assertSame(8.0, $row['score']);
        $this->assertTrue($row['archived']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'scorecard_criteria.archived', 'auditable_id' => $criterion->id]);
    }

    /** ⭐ Undo: إعادة تفعيل معيار مؤرشف (13.4-د) */
    public function test_a_restored_criterion_becomes_available_for_input_again(): void
    {
        $admin = $this->grant($this->makeUser(), 'scorecard_criteria.list', 'scorecard_criteria.restore');
        $criterion = InterviewCriterion::create(['label_ar' => 'معيار', 'weight' => 1, 'sort_order' => 1, 'is_archived' => true]);

        $this->actingAs($admin)
            ->post(route('admin.volunteer.scorecard-criteria.restore', $criterion))
            ->assertRedirect();

        $this->assertFalse((bool) $criterion->refresh()->is_archived);
        $this->assertDatabaseHas('audit_logs', ['action' => 'scorecard_criteria.restored', 'auditable_id' => $criterion->id]);
    }

    /** ⭐ الحذف النهائيّ («من الجديد والقديم»): يختفي تمامًا حتى من النتائج القديمة (13.4-د) */
    public function test_deleting_everywhere_removes_it_from_old_results_too(): void
    {
        $admin = $this->grant($this->makeUser(), 'scorecard_criteria.list', 'scorecard_criteria.delete');
        $criterion = InterviewCriterion::create(['label_ar' => 'معيار للحذف', 'weight' => 1, 'sort_order' => 1]);
        $card = $this->scorecardWith($criterion->id, 5.0);

        $this->actingAs($admin)
            ->delete(route('admin.volunteer.scorecard-criteria.destroy', $criterion), ['mode' => 'new_and_old'])
            ->assertRedirect();

        $this->assertNull(InterviewCriterion::query()->find($criterion->id));

        $engine = app(ScorecardEngine::class);
        $rows = collect($engine->rows($card->fresh()));
        $this->assertNull($rows->firstWhere('id', $criterion->id), 'اتحذف نهائيًّا — مايظهرش حتى في النتيجة القديمة');

        $this->assertDatabaseHas('audit_logs', ['action' => 'scorecard_criteria.deleted_everywhere']);
    }
}
