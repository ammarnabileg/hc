<?php

namespace Tests\Feature\Volunteer\Meetings;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskType;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkPackage;
use App\Services\Volunteer\Goals\RollupService;
use App\Services\Volunteer\Tasks\TaskBoard;
use App\Services\Volunteer\Tasks\TaskStatus;

/**
 * **توليد مهمّة «تنفيذ» من بند المحضر** (الدستور 23-0.3):
 * «~~اجتماع (Meeting)~~ ⟵ الفعاليّات بكود الحضور — **وتقدر تولّد مهمّة
 * «تنفيذ» لبنود المحضر**».
 *
 * والسؤال الذي تقيسه هذه الاختبارات ليس «هل يُحفَظ صفّ؟» بل: هل يخرج من البند
 * **مهمّةٌ عاديّة تمامًا** — بعقد الإنشاء نفسه، وبأثرها على الـRoll-up، وعلى
 * لوحة «مهامّي» — أم نسخةٌ ناقصة تعيش خارج المنظومة؟
 */
class MeetingMinutesTaskTest extends MeetingsTestCase
{
    /** المحضر المستخدَم في كلّ الاختبارات: بندان بعلامة تعداد وسطرٌ فارغ بينهما */
    private const MINUTES = "- تجهيز خطّة الشهر الجاي\n\n2) مراجعة أرقام المحافظات\n";

    public function test_a_minutes_item_becomes_a_real_task_linked_back_to_its_meeting(): void
    {
        [$manager, $item] = $this->managerWithWorkItem();
        $meeting = $this->endedMeeting($manager, ['minutes' => self::MINUTES]);

        $response = $this->actingAs($manager)->post(
            route('volunteer.meetings.minutes.tasks', $meeting),
            $this->payload($item->id, itemIndex: 1),
        );

        $response->assertSessionHasNoErrors();

        $task = Task::query()->where('source_meeting_id', $meeting->id)->firstOrFail();

        // العنوان من نصّ البند بعد رفع علامة التعداد — لا من كلام المستخدم وحده
        $this->assertSame('مراجعة أرقام المحافظات', $task->title);
        // الأصل صريح: المهمّة تعرف اجتماعها، والمصدر يميّزها عن `assigned`
        $this->assertSame('meeting_minutes', $task->source);
        $this->assertSame((int) $meeting->id, (int) $task->source_meeting_id);
        // ونوعها «تنفيذ» كما تنصّ 23-0.3 حرفيًّا
        $this->assertSame(
            (int) TaskType::query()->where('key', 'execution')->value('id'),
            (int) $task->task_type_id,
        );
        // وعقد المهمّة العاديّ كاملًا: بندٌ ومالك ومراجع وكيان وحالة وديدلاين
        $this->assertSame((int) $item->id, (int) $task->work_item_id);
        $this->assertSame((int) $manager->id, (int) $task->owner_id);
        $this->assertSame((int) $manager->id, (int) $task->reviewer_id);
        $this->assertSame((int) $manager->id, (int) $task->created_by);
        $this->assertSame((int) $this->entity->id, (int) $task->entity_id);
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->status);
        $this->assertNotNull($task->deadline_at);
        // والبريف يحمل مرجعه: أيّ اجتماعٍ وأيّ بند
        $this->assertStringContainsString($meeting->title, (string) $task->brief);
        $this->assertStringContainsString('مراجعة أرقام المحافظات', (string) $task->brief);

        $response->assertRedirect(route('volunteer.tasks.show', $task));
        // والعلاقة تعمل في الاتّجاهين
        $this->assertTrue($meeting->generated_tasks()->whereKey($task->id)->exists());
        $this->assertSame((int) $meeting->id, (int) $task->source_meeting->id);
    }

    /** البند يُسنَد لعضو فريق كأيّ مهمّة — ولا يلزم أن يحتفظ بها المولِّد */
    public function test_the_generated_task_can_be_assigned_to_a_team_member(): void
    {
        [$manager, $item, $member] = $this->managerWithWorkItem();
        $meeting = $this->endedMeeting($manager, ['minutes' => self::MINUTES]);

        $this->actingAs($manager)->post(
            route('volunteer.meetings.minutes.tasks', $meeting),
            $this->payload($item->id, itemIndex: 0) + ['owner_id' => $member->id],
        )->assertSessionHasNoErrors();

        $task = Task::query()->where('source_meeting_id', $meeting->id)->firstOrFail();

        $this->assertSame('تجهيز خطّة الشهر الجاي', $task->title);
        $this->assertSame((int) $member->id, (int) $task->owner_id);
        // والمراجع يبقى مَن ولّدها — كما في فورم المهمّة الجديدة تمامًا
        $this->assertSame((int) $manager->id, (int) $task->reviewer_id);
    }

    /**
     * ⭐ بعد التوليد هي مهمّةٌ عاديّة بلا استثناء: تدخل مقام الـRoll-up وترفع
     * نسبة بندها عند اعتمادها (23-1.7)، وتظهر في «مهامّي» كإخوتها.
     */
    public function test_the_generated_task_is_counted_by_the_rollup_like_any_other_task(): void
    {
        [$manager, $item] = $this->managerWithWorkItem();
        $meeting = $this->endedMeeting($manager, ['minutes' => self::MINUTES]);

        $rollup = app(RollupService::class);

        $this->assertSame(0, $rollup->taskCounts($item)['denominator']);

        $this->actingAs($manager)->post(
            route('volunteer.meetings.minutes.tasks', $meeting),
            $this->payload($item->id, itemIndex: 1),
        )->assertSessionHasNoErrors();

        $task = Task::query()->where('source_meeting_id', $meeting->id)->firstOrFail();

        $counts = $rollup->taskCounts($item->refresh());
        $this->assertSame(1, $counts['active']);
        $this->assertSame(1, $counts['denominator']);
        $this->assertSame(0.0, $rollup->workItemPercent($item));

        // واعتمادها يرفع نسبة البند كأيّ مهمّة معتمَدة
        $task->forceFill(['status' => TaskStatus::APPROVED])->save();

        $this->assertSame(100.0, $rollup->recalcWorkItem($item->refresh()));

        // وتظهر في لوحة «مهامّي» بلا أيّ استثناء لمصدرها
        $this->assertTrue(
            app(TaskBoard::class)->mine($manager, $manager->activeMembership(), [])
                ->get()->contains(fn (Task $row) => (int) $row->id === (int) $task->id),
        );
    }

    /** إدارة الاجتماع شرطٌ فوق صلاحيّة إنشاء المهامّ — ومَن لا يملكها يُرَدّ */
    public function test_only_a_meeting_manager_can_generate_a_task_from_the_minutes(): void
    {
        [$manager, $item] = $this->managerWithWorkItem();
        $meeting = $this->endedMeeting($manager, ['minutes' => self::MINUTES]);

        $stranger = $this->volunteer('متطوّع بلا إدارة', null, ['tasks.create' => 'TEAM']);

        $this->actingAs($stranger)->post(
            route('volunteer.meetings.minutes.tasks', $meeting),
            $this->payload($item->id, itemIndex: 0),
        )->assertForbidden();

        $this->assertSame(0, Task::query()->where('source_meeting_id', $meeting->id)->count());
    }

    /** المحظور يُخفى لا يُعطَّل (2.15-أ-7) — الزرّ نفسه لا يُرسَم لغير المخوَّل */
    public function test_the_generate_button_shows_only_for_whoever_may_generate(): void
    {
        [$manager] = $this->managerWithWorkItem();
        $meeting = $this->endedMeeting($manager, ['minutes' => self::MINUTES]);

        $member = $this->volunteer('عضو عاديّ');

        $url = route('volunteer.meetings.show', ['meeting' => $meeting, 'tab' => 'minutes']);

        $this->actingAs($manager)->get($url)
            ->assertOk()
            ->assertSee('مراجعة أرقام المحافظات')
            ->assertSee(setting('volunteer.meetings_show.action_7', 'ولّد مهمّة تنفيذ'));

        $this->actingAs($member)->get($url)
            ->assertOk()
            ->assertSee('مراجعة أرقام المحافظات')
            ->assertDontSee(setting('volunteer.meetings_show.action_7', 'ولّد مهمّة تنفيذ'));
    }

    /** المحضر يُعدَّل بعد فتح الشاشة: بندٌ مات لا يولّد مهمّةً يتيمة */
    public function test_a_minutes_item_that_no_longer_exists_is_refused(): void
    {
        [$manager, $item] = $this->managerWithWorkItem();
        $meeting = $this->endedMeeting($manager, ['minutes' => self::MINUTES]);

        $this->actingAs($manager)->post(
            route('volunteer.meetings.minutes.tasks', $meeting),
            $this->payload($item->id, itemIndex: 42),
        )->assertSessionHasErrors('minutes_item');

        $this->assertSame(0, Task::query()->where('source_meeting_id', $meeting->id)->count());
    }

    /** نفس عقد الإنشاء: «كلّ مهمّة جديدة تُربَط ببندٍ إجباريًّا» (23-3.1) */
    public function test_the_generated_task_obeys_the_same_creation_contract(): void
    {
        [$manager, $item] = $this->managerWithWorkItem();
        $meeting = $this->endedMeeting($manager, ['minutes' => self::MINUTES]);

        $payload = $this->payload($item->id, itemIndex: 0);
        unset($payload['work_item_id'], $payload['deliverable_spec']);

        $this->actingAs($manager)->post(route('volunteer.meetings.minutes.tasks', $meeting), $payload)
            ->assertSessionHasErrors(['work_item_id', 'deliverable_spec']);

        $this->assertSame(0, Task::query()->where('source_meeting_id', $meeting->id)->count());
    }

    // ------------------------------------------------------------------ أدوات

    /** @return array{0:User,1:WorkItem,2:User} مدير اجتماعٍ له فريق، وبندٌ حقيقيّ يُربَط به */
    private function managerWithWorkItem(): array
    {
        $manager = $this->makeUser('مدير الاجتماع');
        $managerMembership = $this->makeMembership($manager, null, 'team_leader');

        // «الإنشاء لمن له فريق» (23-3.1) — فداونلاين واحد يكفي
        $member = $this->makeUser('عضو الفريق');
        $this->makeMembership($member, $managerMembership);

        $this->grant($manager, array_merge($this->baseGrants(), [
            'meetings.create' => 'ENTITY',
            'meetings.manage' => 'ENTITY',
            'tasks.create' => 'TEAM',
            'tasks.list' => 'ENTITY',
            'tasks.view' => 'ENTITY',
        ]));

        return [$manager, $this->makeWorkItem(), $member];
    }

    /** بندٌ حقيقيّ داخل حزمةٍ داخل مشروع الكيان — الربط به إلزاميّ */
    private function makeWorkItem(): WorkItem
    {
        $project = Project::create([
            'entity_id' => $this->entity->id,
            'name' => 'المشروع التشغيليّ',
            'type' => 'operational',
            'status' => 'active',
        ]);

        $package = WorkPackage::create([
            'project_id' => $project->id,
            'entity_id' => $this->entity->id,
            'name' => 'حزمة الاجتماعات',
        ]);

        return WorkItem::create([
            'work_package_id' => $package->id,
            'name' => 'بند متابعة قرارات الاجتماعات',
            'vxp_pool' => 100,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(int $workItemId, int $itemIndex): array
    {
        return [
            'minutes_item' => $itemIndex,
            'deliverable_spec' => 'مستند بالأرقام والمراجع',
            'deadline_at' => now()->addDays(3)->format('Y-m-d H:i'),
            'work_item_id' => $workItemId,
        ];
    }
}
