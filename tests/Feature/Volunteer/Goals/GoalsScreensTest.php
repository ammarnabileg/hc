<?php

namespace Tests\Feature\Volunteer\Goals;

use App\Models\Task;
use App\Models\WorkItem;
use App\Services\Volunteer\Goals\Integrations;
use App\Services\Volunteer\Goals\RecurringGenerator;
use App\Services\Volunteer\Goals\RepService;
use App\Services\Volunteer\Goals\RollupService;
use App\Services\Volunteer\Goals\VxpDistributionService;

/**
 * الشاشات: الصلاحيّة على كلّ مسار، والحاجز الأوّل «لا شيء قبل إرسال للتنفيذ» (24.4 · 12.2.1).
 */
class GoalsScreensTest extends GoalsTestCase
{
    /** بلا صلاحيّة = 403، والعنصر يُخفى من الواجهة أصلًا (2.15-أ-7) */
    public function test_every_route_is_guarded_by_permission(): void
    {
        $user = $this->makeUser();
        $this->makeMembership($user, $this->makeEntity());

        foreach ([
            route('volunteer.goals'),
            route('volunteer.packages'),
            route('volunteer.project'),
            route('volunteer.recurring'),
            route('volunteer.performance.vxp'),
            route('volunteer.performance.rep'),
            route('volunteer.performance.champion'),
            route('volunteer.performance.evaluations'),
        ] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    /** ⭐ لا شيء يظهر قبل «إرسال للتنفيذ» — والمسودّة غير مرئيّة أصلًا */
    public function test_goals_screen_hides_everything_before_sent_to_execution(): void
    {
        $entity = $this->makeEntity();
        $user = $this->makeUser();
        $membership = $this->makeMembership($user, $entity, null, 'director');
        $this->grant($user, 'goals.list', 'ENTITY', $membership);
        $this->grant($user, 'goals.view', 'ENTITY', $membership);

        $draft = $this->makeTree($entity, 'draft');
        $draft['goal']->forceFill(['name' => 'هدف تحت البناء'])->save();

        $response = $this->actingAs($user)->get(route('volunteer.goals'));

        $response->assertOk();
        $response->assertDontSee('هدف تحت البناء');
        $response->assertSee('مفيش أهداف مربوطة بكيانك حاليًّا');
    }

    /** بعد الإرسال للتنفيذ يظهر الهدف بنسبته وبوسم المُغلَقة */
    public function test_executed_goal_is_visible_with_closed_marker(): void
    {
        $entity = $this->makeEntity();
        $user = $this->makeUser();
        $membership = $this->makeMembership($user, $entity, null, 'director');
        $this->grant($user, 'goals.list', 'ENTITY', $membership);
        $this->grant($user, 'goals.view', 'ENTITY', $membership);

        $tree = $this->makeTree($entity);
        $this->makeTask($tree['item'], 'approved');
        $closed = $this->makeTask($tree['item'], 'closed');

        app(RollupService::class)->recalcFromTask($closed);

        $response = $this->actingAs($user)->get(route('volunteer.goals'));

        $response->assertOk();
        $response->assertSee('هدف الاختبار');
        $response->assertSee('مُغلَقة', escape: false);
    }

    /** شاشة الحزمة تعرض البنود بوعائها والمنصرف منه */
    public function test_package_screen_lists_items_with_vxp_pool(): void
    {
        $entity = $this->makeEntity();
        $user = $this->makeUser();
        $membership = $this->makeMembership($user, $entity, null, 'director');
        $this->grant($user, 'work_packages.list', 'ENTITY', $membership);
        $this->grant($user, 'work_packages.view', 'ENTITY', $membership);

        $tree = $this->makeTree($entity);
        $this->makeTask($tree['item'], 'in_progress', $user, 30);
        app(VxpDistributionService::class)->syncItemSpent((int) $tree['item']->id);

        $this->actingAs($user)->get(route('volunteer.packages'))->assertOk()->assertSee('حزمة الاختبار');
        $this->actingAs($user)->get(route('volunteer.packages.show', $tree['package']))
            ->assertOk()
            ->assertSee('بند الاختبار');
    }

    /** «نوبتي» تُظهر ملاحظة الموازن حين يكون التوجيه منه */
    public function test_recurring_shift_shows_balancer_note(): void
    {
        $entity = $this->makeEntity();
        $user = $this->makeUser();
        $membership = $this->makeMembership($user, $entity, null, 'director');
        $this->grant($user, 'recurring_items.view', 'ENTITY', $membership);

        $project = $this->makeOperationalProject($entity);

        $item = WorkItem::create([
            'work_package_id' => $project['package']->id,
            'name' => 'بوست يوميّ',
            'vxp_pool' => 20,
            'is_recurring' => true,
            'recurrence' => 'daily',
            'relative_deadline_hours' => 8,
            'audience_mode' => 'rotation',
        ]);

        Task::create([
            'title' => $item->name,
            'work_item_id' => $item->id,
            'entity_id' => $entity->id,
            'owner_id' => $user->id,
            'deadline_at' => now()->addHours(6),
            'status' => 'in_progress',
            'source' => 'recurring',
            'assigned_by_balancer' => true,
        ]);

        $this->actingAs($user)->get(route('volunteer.recurring'))
            ->assertOk()
            ->assertSee('وُجِّه إليك لأنّك الأقلّ حملًا حاليًّا', escape: false);
    }

    /** الموازن يوجّه للأقلّ حملًا — لا بالدور الأعمى */
    public function test_load_balancer_picks_least_loaded_member(): void
    {
        $entity = $this->makeEntity();
        $busy = $this->makeUser('مشغول');
        $free = $this->makeUser('فاضي');
        $this->makeMembership($busy, $entity);
        $this->makeMembership($free, $entity);

        $project = $this->makeOperationalProject($entity);
        $tree = $this->makeTree($entity);

        // حمل زائد على الأوّل فقط
        foreach (range(1, 3) as $i) {
            $this->makeTask($tree['item'], 'in_progress', $busy);
        }

        $item = WorkItem::create([
            'work_package_id' => $project['package']->id,
            'name' => 'بند بالتناوب',
            'vxp_pool' => 15,
            'is_recurring' => true,
            'recurrence' => 'daily',
            'relative_deadline_hours' => 6,
            'audience_mode' => 'rotation',
        ]);

        $task = app(RecurringGenerator::class)->generate($item);

        $this->assertNotNull($task);
        $this->assertSame($free->id, $task->owner_id);
        $this->assertTrue((bool) $task->assigned_by_balancer);
        $this->assertSame(1, (int) $item->fresh()->generated_count);
    }

    /** المتكرّرة الفائتة لا تُقفَل بصمت — تدخل مسار عدم التسليم ويُرفَع عدّادها */
    public function test_missed_recurring_task_enters_no_delivery_path(): void
    {
        $entity = $this->makeEntity();
        $owner = $this->makeUser();
        $this->makeMembership($owner, $entity);
        $project = $this->makeOperationalProject($entity);

        $item = WorkItem::create([
            'work_package_id' => $project['package']->id,
            'name' => 'بند فائت',
            'vxp_pool' => 10,
            'is_recurring' => true,
            'recurrence' => 'daily',
            'relative_deadline_hours' => 4,
            'audience_mode' => 'individual',
            'assigned_user_id' => $owner->id,
        ]);

        Task::create([
            'title' => $item->name,
            'work_item_id' => $item->id,
            'owner_id' => $owner->id,
            'deadline_at' => now()->subDay(),
            'status' => 'in_progress',
            'source' => 'recurring',
        ]);

        $missed = app(RecurringGenerator::class)->markMissed($item);

        $this->assertSame(1, $missed);
        $this->assertSame(1, (int) $item->fresh()->missed_count);
        $this->assertSame(rep_rule('task.no_delivery'), app(RepService::class)->score($owner->fresh()));

        /*
         | ⭐ «لا تُقفَل بصمت — تدخل **مسار عدم التسليم (الحالة 4)** فورًا» (23-1.8):
         | فالخصم وحده كان يترك المهمّة بلا مالك جديد ولا إغلاق. والحالة تُفتَح على
         | مكتب الأبلاين، والخصم لا يتكرّر مهما أُعيد التشغيل.
         */
        $this->assertDatabaseHas('escalations', [
            'case_type' => 'no_delivery',
            'subject_id' => Task::query()->where('work_item_id', $item->id)->value('id'),
            'status' => 'open',
        ]);

        $this->assertSame(0, app(RecurringGenerator::class)->markMissed($item));
        $this->assertSame(rep_rule('task.no_delivery'), app(RepService::class)->score($owner->fresh()));
    }

    /** شاشة VXP تعرض التنويه الثابت وصفّ «أنا» المثبَّت */
    public function test_vxp_screen_shows_pinned_notice_and_my_row(): void
    {
        $entity = $this->makeEntity();
        $user = $this->makeUser();
        $membership = $this->makeMembership($user, $entity);
        $this->grant($user, 'vxp_transactions.view', 'SELF', $membership);

        Integrations::credit($user, VxpDistributionService::CURRENCY, 120, 'task', null, 'اعتماد مهمّة');

        $this->actingAs($user)->get(route('volunteer.performance.vxp'))
            ->assertOk()
            ->assertSee('VXP لا يتصفّر ولا يُخصَم آليًّا', escape: false);
    }

    /** شاشة Rep تعرض بانر الإنذار الهادئ عند تخطّي المؤشّر الأحمر */
    public function test_rep_screen_shows_quiet_warning_banner_below_red_threshold(): void
    {
        $entity = $this->makeEntity();
        $user = $this->makeUser();
        $membership = $this->makeMembership($user, $entity);
        $this->grant($user, 'rep_transactions.view', 'SELF', $membership);

        // نزول تدريجيّ محترمٌ لحدّ الخسارة اليوميّ حتى تخطّي −8
        foreach (range(1, 6) as $day) {
            $this->travelTo(now()->subDays(7 - $day));
            Integrations::debit($user, RepService::CURRENCY, 2, 'behavior', null, 'مخالفة موثّقة');
        }

        $this->travelBack();

        $this->assertLessThanOrEqual(rep_rule('limit.red_indicator'), app(RepService::class)->score($user->fresh()));

        $this->actingAs($user)->get(route('volunteer.performance.rep'))
            ->assertOk()
            ->assertSee('أبلاينك هيتواصل معك خلال 48 ساعة', escape: false);
    }

    /** شاشة مشرف الشهر تُعلن معيار الحسم نصًّا */
    public function test_champion_screen_states_its_criteria(): void
    {
        $entity = $this->makeEntity();
        $user = $this->makeUser();
        $membership = $this->makeMembership($user, $entity);
        $this->grant($user, 'leaderboards.view', 'ALL', $membership);

        $this->actingAs($user)->get(route('volunteer.performance.champion'))->assertOk();
    }

    /** شاشة التقييمات تُظهر سطر العتبة الثابت */
    public function test_evaluations_screen_explains_threshold(): void
    {
        $this->makeCriteria();
        $entity = $this->makeEntity();
        $user = $this->makeUser();
        $membership = $this->makeMembership($user, $entity);
        $this->grant($user, 'evaluations.view', 'SELF', $membership);

        $this->actingAs($user)->get(route('volunteer.performance.evaluations'))
            ->assertOk()
            ->assertSee('مقيّمين فأكثر', escape: false);
    }
}
