<?php

namespace Tests\Feature\Volunteer\Core;

use App\Models\Task;
use App\Services\Volunteer\Tasks\SubtaskBatch;
use Illuminate\Validation\ValidationException;

/**
 * قيد الحصّة عند التفكيك (23-3.9-2):
 * **أقصى ديدلاين بين الأبناء + نافذة دمج الأب ≤ ديدلاين الأب** — وإلّا رُفض الحفظ.
 */
class SubtaskBatchTest extends VolunteerCoreTestCase
{
    public function test_batch_is_rejected_when_children_eat_the_merge_window(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);

        $parent = $this->makeTask($user, $entity, ['deadline_at' => now()->addDays(5)]);

        $this->expectException(ValidationException::class);

        app(SubtaskBatch::class)->save($parent, [
            ['title' => 'ابن متأخّر', 'deadline_at' => now()->addDays(5)->format('Y-m-d H:i')],
        ], $user);
    }

    public function test_batch_rejection_surfaces_on_the_screen_and_saves_nothing(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);
        $this->grant($user, ['subtasks.create', 'tasks.view', 'tasks.list']);

        $parent = $this->makeTask($user, $entity, ['deadline_at' => now()->addDays(5)]);

        $response = $this->actingAs($user)->post(route('volunteer.tasks.subtasks.store', $parent), [
            'subtasks' => [
                ['title' => 'ابن', 'deadline_at' => now()->addDays(4)->addHours(23)->format('Y-m-d H:i')],
            ],
        ]);

        $response->assertSessionHasErrors('subtasks');
        $this->assertSame(0, Task::where('parent_task_id', $parent->id)->count());
    }

    public function test_valid_batch_is_saved_for_review_and_inherits_the_work_item(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);

        $item = $this->makeWorkItem($entity);
        $parent = $this->makeTask($user, $entity, [
            'deadline_at' => now()->addDays(5),
            'work_item_id' => $item->id,
            'vxp_value' => 100,
        ]);

        $created = app(SubtaskBatch::class)->save($parent, [
            ['title' => 'ابن أوّل', 'deadline_at' => now()->addDays(3)->format('Y-m-d H:i'), 'vxp_value' => 40],
            ['title' => 'ابن تاني', 'deadline_at' => now()->addDays(2)->format('Y-m-d H:i'), 'vxp_value' => 30],
        ], $user);

        $this->assertCount(2, $created);
        $this->assertSame('pending_review', $created->first()->batch_status);
        $this->assertSame($item->id, $created->first()->work_item_id);
    }

    public function test_distribution_cannot_swallow_the_parent_reserved_share(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);

        $parent = $this->makeTask($user, $entity, ['deadline_at' => now()->addDays(5), 'vxp_value' => 100]);

        $this->expectException(ValidationException::class);

        app(SubtaskBatch::class)->save($parent, [
            ['title' => 'ابن', 'deadline_at' => now()->addDays(2)->format('Y-m-d H:i'), 'vxp_value' => 95],
        ], $user);
    }
}
