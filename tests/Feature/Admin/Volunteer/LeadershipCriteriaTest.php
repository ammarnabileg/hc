<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\LeadershipCriterion;
use App\Models\LeadershipEvaluation;
use App\Services\Volunteer\Goals\LeadershipService;

/**
 * معايير مؤشّر القيادة (13.4-ن-د · 24 — التاب 3): شاشة أدمن — بلاها البنك
 * فارغٌ أبدًا و`LeadershipService::submit()` يرفض كلّ تقييم. الحذف سوفت
 * بخيارين مثل معايير المقابلة تمامًا، ومعه Undo وسجلّ تدقيق.
 */
class LeadershipCriteriaTest extends AdminVolunteerTestCase
{
    private function evaluationWith(string $criterionKey, float $score): LeadershipEvaluation
    {
        return LeadershipEvaluation::create([
            'evaluator_id' => $this->makeUser('مقيِّم')->id,
            'evaluatee_id' => $this->makeUser('مقيَّم')->id,
            'week_start' => now()->startOfWeek()->toDateString(),
            'criteria_scores' => [$criterionKey => $score],
            'average' => $score,
        ]);
    }

    public function test_the_screen_is_hidden_from_whoever_does_not_own_it(): void
    {
        $this->actingAs($this->makeUser())
            ->get(route('admin.volunteer.leadership-criteria.index'))
            ->assertForbidden();
    }

    public function test_a_new_criterion_is_created_and_appears_for_evaluation(): void
    {
        $admin = $this->grant($this->makeUser(), 'leadership_criteria.list', 'leadership_criteria.create');

        $this->actingAs($admin)
            ->get(route('admin.volunteer.leadership-criteria.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.volunteer.leadership-criteria.store'), [
                'key' => 'communication',
                'label_ar' => 'وضوح التواصل',
                'weight' => 2,
                'sort_order' => 5,
            ])
            ->assertRedirect(route('admin.volunteer.leadership-criteria.index'));

        $criterion = LeadershipCriterion::query()->where('key', 'communication')->firstOrFail();

        $this->assertSame('وضوح التواصل', $criterion->label_ar);
        $this->assertSame(2, (int) $criterion->weight);
        $this->assertFalse((bool) $criterion->is_archived);
        $this->assertDatabaseHas('audit_logs', ['action' => 'leadership_criteria.created', 'auditable_id' => $criterion->id]);

        $this->assertTrue(app(LeadershipService::class)->criteria()->contains('id', $criterion->id));
    }

    public function test_the_key_must_be_unique_and_slug_shaped(): void
    {
        LeadershipCriterion::create(['key' => 'support', 'label_ar' => 'الدعم', 'weight' => 1, 'sort_order' => 1]);
        $admin = $this->grant($this->makeUser(), 'leadership_criteria.list', 'leadership_criteria.create');

        $this->actingAs($admin)
            ->post(route('admin.volunteer.leadership-criteria.store'), [
                'key' => 'support',
                'label_ar' => 'معيار مكرَّر',
            ])
            ->assertSessionHasErrors('key');

        $this->actingAs($admin)
            ->post(route('admin.volunteer.leadership-criteria.store'), [
                'key' => 'مفتاح بالعربي',
                'label_ar' => 'معيار غير صالح',
            ])
            ->assertSessionHasErrors('key');
    }

    public function test_editing_requires_its_own_permission(): void
    {
        $criterion = LeadershipCriterion::create(['key' => 'support', 'label_ar' => 'الدعم', 'weight' => 1, 'sort_order' => 1]);
        $creatorOnly = $this->grant($this->makeUser(), 'leadership_criteria.list', 'leadership_criteria.create');

        $this->actingAs($creatorOnly)
            ->put(route('admin.volunteer.leadership-criteria.update', $criterion), ['label_ar' => 'اسم جديد'])
            ->assertForbidden();

        $this->assertSame('الدعم', $criterion->refresh()->label_ar);
    }

    /** ⭐ الأرشفة («من الجديد فقط»): يختفي من التقييم الجديد — والقديم يبقى بدرجته موسومًا */
    public function test_archiving_hides_it_from_new_evaluations_but_keeps_the_old_score(): void
    {
        $admin = $this->grant($this->makeUser(), 'leadership_criteria.list', 'leadership_criteria.delete');
        $criterion = LeadershipCriterion::create(['key' => 'fairness', 'label_ar' => 'عدل التقييم', 'weight' => 1, 'sort_order' => 1]);
        $evaluation = $this->evaluationWith('fairness', 8.0);

        $this->actingAs($admin)
            ->delete(route('admin.volunteer.leadership-criteria.destroy', $criterion), ['mode' => 'new_only'])
            ->assertRedirect();

        $criterion->refresh();
        $this->assertTrue((bool) $criterion->is_archived);

        $this->assertFalse(app(LeadershipService::class)->criteria()->contains('id', $criterion->id), 'مؤرشف — مايظهرش للتقييم الجديد');
        $this->assertSame(8.0, (float) $evaluation->fresh()->criteria_scores['fairness'], 'القديم يبقى بدرجته');

        $this->assertDatabaseHas('audit_logs', ['action' => 'leadership_criteria.archived', 'auditable_id' => $criterion->id]);
    }

    /** ⭐ Undo: إعادة تفعيل معيار مؤرشف */
    public function test_a_restored_criterion_becomes_available_for_evaluation_again(): void
    {
        $admin = $this->grant($this->makeUser(), 'leadership_criteria.list', 'leadership_criteria.restore');
        $criterion = LeadershipCriterion::create(['key' => 'speed', 'label_ar' => 'سرعة المراجعة', 'weight' => 1, 'sort_order' => 1, 'is_archived' => true]);

        $this->actingAs($admin)
            ->post(route('admin.volunteer.leadership-criteria.restore', $criterion))
            ->assertRedirect();

        $this->assertFalse((bool) $criterion->refresh()->is_archived);
        $this->assertDatabaseHas('audit_logs', ['action' => 'leadership_criteria.restored', 'auditable_id' => $criterion->id]);
    }

    /** ⭐ الحذف النهائيّ («من الجديد والقديم»): الصفّ يختفي تمامًا — لا رجعة */
    public function test_deleting_everywhere_removes_the_row_entirely(): void
    {
        $admin = $this->grant($this->makeUser(), 'leadership_criteria.list', 'leadership_criteria.delete');
        $criterion = LeadershipCriterion::create(['key' => 'to_delete', 'label_ar' => 'معيار للحذف', 'weight' => 1, 'sort_order' => 1]);
        $this->evaluationWith('to_delete', 5.0);

        $this->actingAs($admin)
            ->delete(route('admin.volunteer.leadership-criteria.destroy', $criterion), ['mode' => 'new_and_old'])
            ->assertRedirect();

        $this->assertNull(LeadershipCriterion::query()->find($criterion->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'leadership_criteria.deleted_everywhere']);
    }
}
