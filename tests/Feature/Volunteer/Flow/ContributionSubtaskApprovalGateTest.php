<?php

namespace Tests\Feature\Volunteer\Flow;

use App\Models\TaskContribution;

/**
 * «دعوة المساهم لا تُفتَح إلا على صب-تاسك معتمد» (23-2.3-٥ · 23-4-الأساس) —
 * مصفوفة 12.2.2: `contributions.create` بشرط `state:subtask_approved`. الشرط
 * مُعلَنٌ `guard => 'domain'` في `config/access.php` عمدًا (لا عمود حالة عامّ
 * يقيسه) فحارسه الحقيقيّ `ContributionService::guardSubtaskApproval()`
 * (`SubtaskBatch::isApprovedForInvite()`) — لا التلميح الواجهيّ وحده.
 */
class ContributionSubtaskApprovalGateTest extends FlowTestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => $this->contributor->code,
            'item_title' => 'بند مساهمة على صب-تاسك',
            'deliverable_spec' => 'ملفّ واحد بثلاثة أقسام.',
            'internal_deadline_at' => now()->addDays(2)->toDateTimeString(),
            'vxp_value' => 20,
            'vxp_source' => 'task_pool',
        ], $overrides);
    }

    public function test_store_is_rejected_on_a_subtask_still_pending_review(): void
    {
        $this->grant($this->owner, 'contributions.create');

        $parent = $this->makeTask();
        $subtask = $this->makeTask($this->owner, [
            'parent_task_id' => $parent->id,
            'batch_status' => 'pending_review',
        ]);

        $response = $this->actingAs($this->owner)->post(
            route('volunteer.contributions.store', $subtask),
            $this->payload(),
        );

        $response->assertSessionHasErrors('code');
        $this->assertSame(0, TaskContribution::query()->count());
    }

    public function test_store_is_rejected_on_a_subtask_still_a_draft(): void
    {
        $this->grant($this->owner, 'contributions.create');

        $parent = $this->makeTask();
        $subtask = $this->makeTask($this->owner, [
            'parent_task_id' => $parent->id,
            'batch_status' => 'draft',
        ]);

        $response = $this->actingAs($this->owner)->post(
            route('volunteer.contributions.store', $subtask),
            $this->payload(),
        );

        $response->assertSessionHasErrors('code');
        $this->assertSame(0, TaskContribution::query()->count());
    }

    public function test_store_succeeds_on_an_approved_subtask(): void
    {
        $this->grant($this->owner, 'contributions.create');

        $parent = $this->makeTask();
        $subtask = $this->makeTask($this->owner, [
            'parent_task_id' => $parent->id,
            'batch_status' => 'approved',
        ]);

        $response = $this->actingAs($this->owner)->post(
            route('volunteer.contributions.store', $subtask),
            $this->payload(),
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame(1, TaskContribution::query()->where('task_id', $subtask->id)->count());
    }

    /** مهمّة جذر (بلا أب) لم تدخل دورة 2.3 أصلًا — الشرط لا يقيَّد عليها */
    public function test_store_still_succeeds_on_a_root_task_with_no_batch_status(): void
    {
        $this->grant($this->owner, 'contributions.create');

        $task = $this->makeTask(); // parent_task_id و batch_status كلاهما null

        $response = $this->actingAs($this->owner)->post(
            route('volunteer.contributions.store', $task),
            $this->payload(),
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame(1, TaskContribution::query()->where('task_id', $task->id)->count());
    }
}
