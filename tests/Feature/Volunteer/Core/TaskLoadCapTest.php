<?php

namespace Tests\Feature\Volunteer\Core;

use App\Models\Task;
use App\Services\Volunteer\Tasks\TaskLoadCap;

/**
 * سقف الانشغال (23-3.1): قيدٌ واحد يحكم السحب من اللوحة العامّة وإنشاء المهامّ،
 * والعدّ على غير المكتمل فقط — والصب-تاسك لا يُحسب لأنّه يرث ربط أمّه.
 */
class TaskLoadCapTest extends VolunteerCoreTestCase
{
    public function test_cap_blocks_claiming_from_the_public_board(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity); // كوردنيتور: سقفه 3
        $this->grant($user, ['public_board.list', 'public_board.view', 'tasks.list', 'tasks.view']);

        for ($i = 0; $i < 3; $i++) {
            $this->makeTask($user, $entity);
        }

        $open = Task::create([
            'title' => 'مهمّة عامّة',
            'entity_id' => $entity->id,
            'status' => 'in_progress',
            'source' => 'public_board',
            'deadline_at' => now()->addDays(4),
        ]);

        $response = $this->actingAs($user)->post(route('volunteer.tasks.claim', $open));

        $response->assertSessionHasErrors('claim');
        $this->assertNull($open->refresh()->owner_id, 'المهمّة ما كانش المفروض تتسحب بعد بلوغ السقف.');
    }

    public function test_cap_blocks_creating_a_new_task_with_an_explaining_message(): void
    {
        $leader = $this->makeUser('قائد');
        $member = $this->makeUser('عضو');
        $entity = $this->makeEntity();

        $leaderMembership = $this->makeMembership($leader, $entity, 'team_leader'); // سقفه 7
        $this->makeMembership($member, $entity, 'coordinator', $leaderMembership);
        $this->grant($leader, ['tasks.create', 'tasks.list', 'tasks.view']);

        for ($i = 0; $i < 7; $i++) {
            $this->makeTask($leader, $entity);
        }

        $item = $this->makeWorkItem($entity);

        $response = $this->actingAs($leader)->post(route('volunteer.tasks.store'), [
            'title' => 'مهمّة زيادة',
            'deliverable_spec' => 'ملفّ',
            'deadline_at' => now()->addDays(2)->format('Y-m-d H:i'),
            'work_item_id' => $item->id,
        ]);

        $response->assertSessionHasErrors('title');
        $this->assertSame(7, Task::where('owner_id', $leader->id)->count());
    }

    public function test_subtasks_do_not_count_towards_the_cap(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $membership = $this->makeMembership($user, $entity);

        $parent = $this->makeTask($user, $entity);
        $this->makeTask($user, $entity, ['parent_task_id' => $parent->id]);

        $this->assertSame(1, app(TaskLoadCap::class)->loadFor($user, $membership));
        $this->assertTrue(app(TaskLoadCap::class)->canTake($user, $membership));
    }

    public function test_personal_cap_spans_memberships(): void
    {
        $user = $this->makeUser();
        $first = $this->makeEntity();
        $second = $this->makeEntity();

        $this->makeMembership($user, $first);
        $this->makeMembership($user, $second);

        $cap = app(TaskLoadCap::class);

        // أعلى سقف دور (3) + 50% من الإعدادات = 4
        $this->assertSame(4, $cap->personalCap($user));

        for ($i = 0; $i < 4; $i++) {
            $this->makeTask($user, $i < 2 ? $first : $second);
        }

        $this->assertNotNull($cap->blockReason($user, $user->activeMembership()));
    }
}
