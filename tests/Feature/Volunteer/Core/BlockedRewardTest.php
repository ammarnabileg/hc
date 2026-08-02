<?php

namespace Tests\Feature\Volunteer\Core;

use App\Models\Task;
use App\Services\Volunteer\Tasks\TaskBlockService;
use App\Services\Volunteer\Tasks\TaskWorkflow;
use Illuminate\Validation\ValidationException;

/**
 * التعثّر (23-3.4): مرّتان فقط، والثانية **تُنصِّف مكافأة Rep** المستحقّة على المهمّة
 * — والخصومات السالبة كما هي لا تتغيّر. والساعة تقف لحظة التسليم (23-3.7).
 */
class BlockedRewardTest extends VolunteerCoreTestCase
{
    public function test_second_block_halves_the_positive_reward_only(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);

        $task = $this->makeTask($user, $entity, ['deadline_at' => now()->addDays(3)]);

        $workflow = app(TaskWorkflow::class);
        $blocks = app(TaskBlockService::class);

        $full = $workflow->deliveryRepValue($task);
        $this->assertEqualsWithDelta(rep_rule('task.early'), $full, 0.0001);

        $blocks->block($task, $user, 'duration', 'مستنّي ردّ المدرّب', 2);
        $blocks->block($task->refresh(), $user, 'duration', 'لسّه مستنّي', 1);

        $halved = $workflow->deliveryRepValue($task->refresh());

        $this->assertEqualsWithDelta($full / 2, $halved, 0.0001);

        // الخصم السالب لا يتغيّر بالتعثّر
        $late = $this->makeTask($user, $entity, [
            'deadline_at' => now()->subHours(5),
            'blocked_count' => 2,
            'delivered_at' => now(),
        ]);

        $this->assertEqualsWithDelta(rep_rule('task.late_under_24h'), $workflow->deliveryRepValue($late), 0.0001);
    }

    public function test_blocking_more_than_twice_is_refused(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);

        $task = $this->makeTask($user, $entity, ['blocked_count' => 2]);

        $this->expectException(ValidationException::class);

        app(TaskBlockService::class)->block($task, $user, 'duration', 'سبب تالت', 1);
    }

    public function test_duration_block_cannot_exceed_the_configured_maximum(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);

        $task = $this->makeTask($user, $entity);

        $this->expectException(ValidationException::class);

        app(TaskBlockService::class)->block(
            $task,
            $user,
            'duration',
            'سبب',
            (int) setting('workflow.blocked.max_days', 3) + 1,
        );
    }

    public function test_delivery_stops_the_clock_and_grades_on_delivery_time(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);
        $this->grant($user, ['tasks.edit', 'tasks.view', 'tasks.list']);

        $task = $this->makeTask($user, $entity, ['deadline_at' => now()->addDay()]);

        $this->actingAs($user)
            ->post(route('volunteer.tasks.deliver', $task), ['body' => 'المخرج جاهز'])
            ->assertSessionHasNoErrors();

        $task->refresh();

        $this->assertNotNull($task->delivered_at, 'الساعة لازم تقف لحظة التسليم.');
        $this->assertSame('delivered', $task->status);

        // ولو المراجعة اتأخّرت، التقييم يفضل على وقت التسليم
        $this->travel(3)->days();
        $this->assertEqualsWithDelta(rep_rule('task.early'), app(TaskWorkflow::class)->deliveryRepValue($task), 0.0001);
        $this->assertFalse(app(TaskWorkflow::class)->isLate($task));
    }

    public function test_flag_is_refused_when_the_child_is_not_actually_late(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);

        $parent = $this->makeTask($user, $entity, ['deadline_at' => now()->addDays(5)]);
        $child = Task::create([
            'title' => 'ابن في وقته',
            'parent_task_id' => $parent->id,
            'owner_id' => $user->id,
            'entity_id' => $entity->id,
            'status' => 'in_progress',
            'deadline_at' => now()->addDays(2),
        ]);

        $this->expectException(ValidationException::class);

        app(TaskWorkflow::class)->flagLateDueToChild($parent, $child, $user);
    }
}
