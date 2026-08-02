<?php

namespace Tests\Feature\Volunteer\Goals;

use App\Models\Entity;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WorkPackage;
use App\Services\Volunteer\Goals\GoalLaunchService;
use App\Services\Volunteer\Tasks\SubtaskBatch;
use App\Services\Volunteer\Tasks\TaskStatus;
use Database\Seeders\SettingSeeder;
use Database\Seeders\VolunteerGoalsDemoSeeder;
use Illuminate\Support\Facades\Cache;

/**
 * ⭐ شاشة إطلاق الهدف و**نافذة التفكيك** (الدستور 23 — 1.5 · 1.6 · 3.9-١).
 *
 * القاعدة المقيسة هنا: النافذة تبدأ **لحظة إشعار «إرسال للتنفيذ»** لا لحظة
 * كتابة المهمّة في التخطيط — وإلّا خرج الدايركتور مخصومًا سلفًا. ومعها
 * قاعدتان لا تُكسران: خصم −0.2/يوم بسقف −1 يبقى كما هو، و**التنفيذ الذاتيّ**
 * (23-3.1) لا خصم عليه أصلًا.
 */
class GoalLaunchTest extends GoalsTestCase
{
    private Entity $entity;

    private User $top;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
        (new VolunteerGoalsDemoSeeder)->settings();

        Cache::forget('settings');
        Cache::forget('rep_rules');

        $this->entity = $this->makeEntity();
        $this->top = $this->makeUser('مشرف عام التطوّع');
        $membership = $this->makeMembership($this->top, $this->entity, null, 'volunteer_gm');

        // «إرسال للتنفيذ» نطاقه ALL وحده في المصفوفة — وهو مشرف عام التطوّع
        $this->grant($this->top, 'goals.approve', 'ALL', $membership);
    }

    /** شجرة قابلة للإطلاق: هدف مسوّدة ⟵ مَعلَم ⟵ حزمة ⟵ بند ⟵ مهمّة دايركتور */
    private function launchableTree(int $plannedDaysAgo = 5): array
    {
        $tree = $this->makeTree($this->entity, 'draft');

        $task = $this->makeTask($tree['item'], TaskStatus::IN_PROGRESS, $this->top);
        $task->forceFill([
            'deadline_at' => now()->addDays(20),
            // كُتِبت في مرحلة التخطيط ومكثت تحت المراجعة والتسعير
            'created_at' => now()->subDays($plannedDaysAgo),
        ])->save();

        return $tree + ['task' => $task];
    }

    private function repOf(User $user): float
    {
        return (float) Transaction::query()
            ->where('user_id', $user->id)
            ->whereHas('currency', fn ($q) => $q->where('code', 'rep'))
            ->sum('amount');
    }

    // ------------------------------------------------------------------ الباب

    /** الشاشة مغلقة على مَن لا يملك `goals.approve` — لا معطَّلة (2.15-أ-7) */
    public function test_the_launch_screen_is_closed_without_the_approval_permission(): void
    {
        $director = $this->makeUser('دايركتور');
        $this->grant($director, 'goals.view', 'ENTITY', $this->makeMembership($director, $this->entity, null, 'director'));

        $this->actingAs($director)
            ->get(route('volunteer.goals.launch'))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------ فحص الزرّ الآلي

    /** «إرسال للتنفيذ» يرفض الضغط بقائمة النواقص: حزمة بلا مهامّ (23 — 1.5) */
    public function test_the_button_refuses_a_package_without_tasks_and_names_the_gap(): void
    {
        $tree = $this->makeTree($this->entity, 'draft');

        $gaps = app(GoalLaunchService::class)->gaps($tree['goal']);

        $this->assertNotEmpty($gaps);
        $this->assertStringContainsString($tree['package']->name, implode(' ', $gaps));

        $this->actingAs($this->top)
            ->post(route('volunteer.goals.launch.send', $tree['goal']))
            ->assertRedirect();

        $this->assertNull($tree['goal']->refresh()->sent_to_execution_at, 'لا إطلاق مع نواقص.');
    }

    /** ومَعلَم بلا حزم كذلك — القائمة تسمّيه باسمه */
    public function test_a_milestone_without_packages_is_named_in_the_gap_list(): void
    {
        $tree = $this->launchableTree();
        WorkPackage::query()->where('milestone_id', $tree['milestone']->id)->delete();

        $gaps = app(GoalLaunchService::class)->gaps($tree['goal']->refresh());

        $this->assertStringContainsString($tree['milestone']->name, implode(' ', $gaps));
    }

    // ------------------------------------------------------------------ ختم نافذة التفكيك

    /**
     * ⭐ الضغطة تختم `breakdown_due_at` من **لحظة الإرسال** — لا من `created_at`.
     *
     * ولو رجع الختم للسلوك القديم (فارغ ⟵ يُقرأ من `created_at`) سيقع الموعد
     * في الماضي فيسقط هذا الاختبار.
     */
    public function test_sending_to_execution_stamps_the_breakdown_window_from_that_moment(): void
    {
        $tree = $this->launchableTree(plannedDaysAgo: 5);

        $this->assertNull($tree['task']->breakdown_due_at);

        $this->actingAs($this->top)
            ->post(route('volunteer.goals.launch.send', $tree['goal']))
            ->assertRedirect(route('volunteer.goals'));

        $goal = $tree['goal']->refresh();
        $task = $tree['task']->refresh();

        $this->assertSame('sent_to_execution', $goal->status);
        $this->assertNotNull($goal->sent_to_execution_at);

        $this->assertNotNull($task->breakdown_due_at, 'العمود يُضبَط صراحةً عند الإطلاق.');
        $this->assertTrue($task->breakdown_due_at->isFuture(), 'النافذة تبدأ الآن — لا من يوم كتابة المهمّة.');
        $this->assertEqualsWithDelta(
            (int) setting('workflow.breakdown_window_hours', 24),
            now()->diffInHours($task->breakdown_due_at),
            1,
        );
    }

    /**
     * ⭐ والأثر الحقيقيّ: مَن فكّك فورًا بعد الإطلاق **لا يُخصَم** — رغم أنّ
     * مهمّته كُتِبت من خمسة أيّام. (بالسلوك القديم كان يقع عليه −1 كاملة.)
     */
    public function test_breaking_down_right_after_launch_costs_nothing_though_the_task_is_old(): void
    {
        $tree = $this->launchableTree(plannedDaysAgo: 5);

        $this->actingAs($this->top)->post(route('volunteer.goals.launch.send', $tree['goal']));

        app(SubtaskBatch::class)->save($tree['task']->refresh(), [
            ['title' => 'شريحة السوبرفايزر', 'deadline_at' => now()->addDays(10)->toDateTimeString()],
        ], $this->top);

        $this->assertEqualsWithDelta(0.0, $this->repOf($this->top), 0.001, 'العدّ يبدأ من الإشعار لا من ميلاد الصفّ.');
    }

    /** وخصم التأخّر نفسه لم يُكسَر: تجاوُز النافذة بعد الإطلاق يُخصَم بسقفه */
    public function test_missing_the_stamped_window_is_still_charged_within_its_cap(): void
    {
        $tree = $this->launchableTree();

        $this->actingAs($this->top)->post(route('volunteer.goals.launch.send', $tree['goal']));

        // فات الموعد المختوم بثلاثة أيّام
        $task = $tree['task']->refresh();
        $task->forceFill(['breakdown_due_at' => now()->subDays(3)])->save();

        app(SubtaskBatch::class)->save($task, [
            ['title' => 'شريحة متأخّرة', 'deadline_at' => now()->addDays(10)->toDateTimeString()],
        ], $this->top);

        $charged = $this->repOf($this->top);

        $this->assertLessThan(0, $charged, 'التأخّر عن النافذة المختومة له خصمه.');
        $this->assertGreaterThanOrEqual((float) rep_rule('task.breakdown_delay_cap'), $charged, 'وبسقف −1 لا أكثر.');
    }

    /**
     * ⭐ **حقّ التنفيذ الذاتيّ** (23-3.1): مَن اختار ألّا يفكّك لا يُخصَم على
     * التفكيك — ولو فاتت النافذة المختومة كلّها. الختم موعدٌ لا عقوبة.
     */
    public function test_self_execution_is_never_charged_for_a_breakdown_it_never_chose(): void
    {
        $tree = $this->launchableTree();

        $this->actingAs($this->top)->post(route('volunteer.goals.launch.send', $tree['goal']));

        $task = $tree['task']->refresh();
        $task->forceFill(['breakdown_due_at' => now()->subDays(10)])->save();

        // ولا صب-تاسك واحد: نفّذها بنفسه
        $this->assertSame(0, Task::query()->where('parent_task_id', $task->id)->count());

        $this->assertEqualsWithDelta(0.0, $this->repOf($this->top), 0.001, 'لا خصم تفكيك على مَن لم يفكّك.');
    }

    /** ولا يُطلَق الهدف مرّتين — الختم لا يُداس ولا يُعاد الإشعار */
    public function test_a_goal_is_never_launched_twice(): void
    {
        $tree = $this->launchableTree();

        $this->actingAs($this->top)->post(route('volunteer.goals.launch.send', $tree['goal']));
        $firstStamp = $tree['task']->refresh()->breakdown_due_at;
        $firstSent = $tree['goal']->refresh()->sent_to_execution_at;

        $this->travelTo(now()->addDays(2));

        $this->actingAs($this->top)->post(route('volunteer.goals.launch.send', $tree['goal']));

        $this->assertEquals($firstStamp, $tree['task']->refresh()->breakdown_due_at);
        $this->assertEquals($firstSent, $tree['goal']->refresh()->sent_to_execution_at);
    }

    /** والشاشة نفسها تعرض الجاهز والناقص وتفرّق بينهما بشارة ونصّ لا بلونٍ وحده */
    public function test_the_screen_lists_pending_goals_with_their_readiness(): void
    {
        $ready = $this->launchableTree();
        $blocked = $this->makeTree($this->entity, 'draft');
        $blocked['goal']->forceFill(['name' => 'هدف ناقص'])->save();

        $this->actingAs($this->top)
            ->get(route('volunteer.goals.launch'))
            ->assertOk()
            ->assertSee($ready['goal']->name)
            ->assertSee('جاهز للإرسال')
            ->assertSee('هدف ناقص')
            ->assertSee('بلا مهامّ');
    }
}
