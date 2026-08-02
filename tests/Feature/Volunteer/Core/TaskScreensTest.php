<?php

namespace Tests\Feature\Volunteer\Core;

use App\Models\Membership;
use App\Models\Task;
use App\Models\TaskTodo;
use App\Models\Transaction;
use Database\Seeders\VolunteerCoreDemoSeeder;

/**
 * شاشتا «مهامّي» و«صفحة المهمّة» (24.4-2):
 * عدّاد السقف بارز · زرّ الإنشاء لمن له فريق · والتودو شخصيّ بلا أثر على أيّ درجة.
 */
class TaskScreensTest extends VolunteerCoreTestCase
{
    public function test_my_tasks_shows_both_load_counters(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);
        $this->grant($user, ['tasks.list', 'tasks.view']);

        $this->makeTask($user, $entity, ['title' => 'مهمّة ظاهرة']);

        $response = $this->actingAs($user)->get(route('volunteer.tasks.index'));

        $response->assertOk();
        $response->assertSee('مهمّة ظاهرة');
        $response->assertSee('سقف الانشغال');
        $response->assertSee('الشخصيّ الكلّي');
        // الإنشاء لمن له فريق فقط — وهذا كوردنيتور بلا داونلاين
        $response->assertDontSee('مهمّة جديدة');
    }

    public function test_create_button_appears_only_for_someone_with_a_team(): void
    {
        $leader = $this->makeUser('قائد');
        $member = $this->makeUser('عضو');
        $entity = $this->makeEntity();

        $leaderMembership = $this->makeMembership($leader, $entity, 'team_leader');
        $this->makeMembership($member, $entity, 'coordinator', $leaderMembership);
        $this->grant($leader, ['tasks.list', 'tasks.view', 'tasks.create']);

        $this->actingAs($leader)
            ->get(route('volunteer.tasks.index'))
            ->assertOk()
            ->assertSee('مهمّة جديدة');
    }

    public function test_task_page_renders_its_tabs_and_actions(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);
        $this->grant($user, ['tasks.view', 'tasks.list', 'tasks.edit']);

        $task = $this->makeTask($user, $entity, ['title' => 'اكتب سكربت الريل']);

        $response = $this->actingAs($user)->get(route('volunteer.tasks.show', $task));

        $response->assertOk();
        $response->assertSee('التفاصيل');
        $response->assertSee('التودو');
        $response->assertSee('الصب-تاسكات');
        $response->assertSee('المساهمون');
        $response->assertSee('التسليمات');
        $response->assertSee('الكومنتات');
        $response->assertSee('التحكيمات');
        $response->assertSee('تسليم');
        $response->assertSee('متعثّر');
    }

    public function test_todo_is_personal_and_never_touches_a_score(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);
        $this->grant($user, ['tasks.view', 'tasks.list', 'todos.create', 'todos.edit', 'todos.delete']);

        $task = $this->makeTask($user, $entity);

        $this->actingAs($user)
            ->post(route('volunteer.tasks.todos.store', $task), ['body' => 'اقرأ البريف'])
            ->assertSessionHasNoErrors();

        $todo = TaskTodo::where('task_id', $task->id)->firstOrFail();

        $this->actingAs($user)->post(route('volunteer.tasks.todos.toggle', [$task, $todo]));

        $this->assertTrue($todo->refresh()->is_done);
        // ولا معاملة واحدة تولّدت من التودو
        $this->assertSame(0, Transaction::count());
    }

    public function test_demo_seeder_runs(): void
    {
        $this->seed(VolunteerCoreDemoSeeder::class);

        $this->assertGreaterThan(0, Task::where('source', 'public_board')->count());
        $this->assertGreaterThan(0, Membership::count());
    }
}
