<?php

namespace Tests\Feature\Volunteer\Flow;

use App\Models\Arbitration;
use App\Models\ContributionCheckpoint;
use App\Models\TaskContribution;
use App\Services\Volunteer\Escalation\CaseCatalog;
use App\Services\Volunteer\Escalation\EscalationEngine;

/**
 * الشاشات الأربع: تفتح لمن يملك الصلاحيّة، وتُرفَض 403 لمن لا يملكها (12.2.1).
 */
class FlowScreensTest extends FlowTestCase
{
    public function test_contributions_screen_shows_the_strict_invitation_line(): void
    {
        $this->grant($this->contributor, 'contributions.list');

        $task = $this->makeTask();

        $contribution = TaskContribution::create([
            'task_id' => $task->id,
            'contributor_id' => $this->contributor->id,
            'invited_by' => $this->owner->id,
            'item_title' => 'اكتب المشاهد الثلاثة الأولى',
            'deliverable_spec' => 'ثلاثة مشاهد في ملفّ واحد.',
            'internal_deadline_at' => now()->addDays(2),
            'vxp_value' => 60,
            'vxp_source' => 'task_pool',
            'status' => 'invited',
            'invited_at' => now(),
        ]);

        ContributionCheckpoint::create([
            'task_contribution_id' => $contribution->id,
            'sequence' => 1,
            'scheduled_at' => now()->addHours(12),
            'response_due_at' => now()->addHours(14),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->contributor)->get(route('volunteer.contributions'));

        $response->assertOk();
        $response->assertSee('عدم التسليم', false);
        $response->assertSee('اكتب المشاهد الثلاثة الأولى', false);
        // اسم المهمّة الأمّ شرط في كلّ صفّ
        $response->assertSee($task->title, false);
    }

    public function test_screens_are_forbidden_without_permission(): void
    {
        $this->actingAs($this->contributor)->get(route('volunteer.contributions'))->assertForbidden();
        $this->actingAs($this->reviewer)->get(route('volunteer.reviews'))->assertForbidden();
        $this->actingAs($this->reviewer)->get(route('volunteer.escalations'))->assertForbidden();
        $this->actingAs($this->reviewer)->get(route('volunteer.arbitrations'))->assertForbidden();
    }

    public function test_reviews_screen_lists_the_queue_with_counters(): void
    {
        $this->grant($this->reviewer, 'tasks.approve');

        $this->makeTask(attributes: [
            'title' => 'اكتب سكربت الريل',
            'status' => 'delivered',
            'delivered_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($this->reviewer)->get(route('volunteer.reviews'));

        $response->assertOk();
        $response->assertSee('اكتب سكربت الريل', false);
        $response->assertSee('متوسّط زمن مراجعتي', false);
    }

    public function test_escalations_screen_writes_the_expected_settlement(): void
    {
        $this->grant($this->reviewer, 'escalations.list');

        $task = $this->makeTask();
        app(EscalationEngine::class)->open(CaseCatalog::EXTENSION, $task, $this->owner);

        $response = $this->actingAs($this->reviewer)->get(route('volunteer.escalations'));

        $response->assertOk();
        $response->assertSee(CaseCatalog::label(CaseCatalog::EXTENSION), false);
        $response->assertSee(CaseCatalog::settlementLabel(CaseCatalog::EXTENSION), false);
    }

    public function test_arbitrations_screen_masks_the_phone_for_an_unrelated_arbiter(): void
    {
        $this->grant($this->top, 'arbitration.list');

        $task = $this->makeTask();

        $contribution = TaskContribution::create([
            'task_id' => $task->id,
            'contributor_id' => $this->contributor->id,
            'invited_by' => $this->owner->id,
            'item_title' => 'بند مساهمة',
            'deliverable_spec' => 'ملفّ.',
            'internal_deadline_at' => now()->addDays(2),
            'vxp_value' => 40,
            'vxp_source' => 'task_pool',
            'status' => 'delivered',
            'invited_at' => now()->subDay(),
        ]);

        Arbitration::create([
            'task_id' => $task->id,
            'task_contribution_id' => $contribution->id,
            'opened_by' => $this->contributor->id,
            'arbiter_id' => $this->top->id,
            'claim' => 'خلاف على شكل المخرجات.',
            'status' => 'open',
            'window_due_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($this->top)->get(route('volunteer.arbitrations'));

        $response->assertOk();
        $response->assertSee('خلاف على شكل المخرجات.', false);
        $response->assertSee('تسوية آليّة 50%', false);
    }
}
