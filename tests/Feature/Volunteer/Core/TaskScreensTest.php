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

    /**
     * الإسناد عند الإنشاء (23-3.1): «فريقه في سلكت بوكس وجنب كلّ واحد عدد
     * المهامّ اللي بينفّذها» — والسقف/الحمل على عضويّة العضو هو لا على عضويّة القائد.
     */
    public function test_new_task_modal_shows_team_members_select_with_their_task_counts(): void
    {
        $leader = $this->makeUser('قائد');
        $member = $this->makeUser('عضو الفريق');
        $entity = $this->makeEntity();

        $leaderMembership = $this->makeMembership($leader, $entity, 'team_leader');
        $memberMembership = $this->makeMembership($member, $entity, 'coordinator', $leaderMembership);
        $this->grant($leader, ['tasks.list', 'tasks.view', 'tasks.create']);

        // كوردنيتور سقفه 3 (CoreSeeder) — مهمّتان مفتوحتان هنا تحت السقف
        $this->makeTask($member, $entity, ['title' => 'مهمّة أولى للعضو']);
        $this->makeTask($member, $entity, ['title' => 'مهمّة تانية للعضو']);

        $response = $this->actingAs($leader)->get(route('volunteer.tasks.index'));

        $response->assertOk();
        $response->assertSee('name="owner_id"', false);
        $response->assertSee($member->shortName(), false);
        // عدد المهامّ الحاليّة ظاهر جنب اسمه (2 مهمّة حاليًّا)
        $response->assertSee('2 مهمّة حاليًّا');
        // سقفه 3 وحمله 2 — بلا تجاوز فبلا علامة تحذير
        $response->assertDontSee('فوق سقف دوره');

        $response->assertSee('data-load="2"', false);
        $response->assertSee('data-cap="'.$memberMembership->position->task_load_cap.'"', false);
    }

    /** تجاوز سقف المُسنَد إليه: تحذيرٌ إلزاميّ يظهر — ولا يمنع الإسناد (23-3.1) */
    public function test_assigning_a_task_over_the_assignees_cap_shows_the_mandatory_warning_but_does_not_block(): void
    {
        $leader = $this->makeUser('قائد');
        $overCapMember = $this->makeUser('عضو مثقَل');
        $entity = $this->makeEntity();
        $workItem = $this->makeWorkItem($entity);

        $leaderMembership = $this->makeMembership($leader, $entity, 'team_leader');
        $this->makeMembership($overCapMember, $entity, 'coordinator', $leaderMembership);
        $this->grant($leader, ['tasks.list', 'tasks.view', 'tasks.create']);

        // كوردنيتور سقفه 3 — ثلاث مهامّ مفتوحة = بلغ سقفه بالضبط
        $this->makeTask($overCapMember, $entity, ['title' => 'مهمّة 1']);
        $this->makeTask($overCapMember, $entity, ['title' => 'مهمّة 2']);
        $this->makeTask($overCapMember, $entity, ['title' => 'مهمّة 3']);

        $page = $this->actingAs($leader)->get(route('volunteer.tasks.index'));
        $page->assertOk();
        // التحذير الإلزاميّ ظاهرٌ فعليًّا في خيار العضو المتجاوز لسقفه
        $page->assertSee('فوق سقف دوره');
        $page->assertSee('data-load="3"', false);
        $page->assertSee('data-cap="3"', false);

        // والإسناد المباشر بلا حدّ عدديّ — التحذير لا يمنع الحفظ
        $response = $this->actingAs($leader)->post(route('volunteer.tasks.store'), [
            'title' => 'مهمّة رابعة فوق طاقته',
            'deliverable_spec' => 'ملفّ نهائيّ',
            'deadline_at' => now()->addDays(3)->format('Y-m-d H:i'),
            'work_item_id' => $workItem->id,
            'owner_id' => $overCapMember->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tasks', [
            'title' => 'مهمّة رابعة فوق طاقته',
            'owner_id' => $overCapMember->id,
        ]);
    }

    /** إسنادٌ عاديّ بلا تجاوز — لا علامة تحذير، ولا منع (23-3.1) */
    public function test_assigning_a_task_within_the_assignees_cap_shows_no_warning(): void
    {
        $leader = $this->makeUser('قائد');
        $member = $this->makeUser('عضو مرتاح');
        $entity = $this->makeEntity();
        $workItem = $this->makeWorkItem($entity);

        $leaderMembership = $this->makeMembership($leader, $entity, 'team_leader');
        $this->makeMembership($member, $entity, 'coordinator', $leaderMembership);
        $this->grant($leader, ['tasks.list', 'tasks.view', 'tasks.create']);

        // مهمّة مفتوحة واحدة فقط — بعيدًا عن سقف الكوردنيتور (3)
        $this->makeTask($member, $entity, ['title' => 'مهمّة وحيدة']);

        $page = $this->actingAs($leader)->get(route('volunteer.tasks.index'));
        $page->assertOk();
        $page->assertSee('data-load="1"', false);
        $page->assertSee('data-cap="3"', false);
        $page->assertDontSee('فوق سقف دوره');

        $response = $this->actingAs($leader)->post(route('volunteer.tasks.store'), [
            'title' => 'مهمّة إسناد عاديّ',
            'deliverable_spec' => 'ملفّ نهائيّ',
            'deadline_at' => now()->addDays(3)->format('Y-m-d H:i'),
            'work_item_id' => $workItem->id,
            'owner_id' => $member->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tasks', [
            'title' => 'مهمّة إسناد عاديّ',
            'owner_id' => $member->id,
        ]);
    }

    public function test_demo_seeder_runs(): void
    {
        $this->seed(VolunteerCoreDemoSeeder::class);

        $this->assertGreaterThan(0, Task::where('source', 'public_board')->count());
        $this->assertGreaterThan(0, Membership::count());
    }
}
