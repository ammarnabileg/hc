<?php

namespace Tests\Feature\Volunteer\Goals;

use App\Models\AppNotification;
use App\Models\Entity;
use App\Models\Goal;
use App\Models\Membership;
use App\Models\Milestone;
use App\Models\Position;
use App\Models\Setting;
use App\Models\Track;
use App\Models\User;
use App\Models\WorkPackage;
use App\Services\Volunteer\Goals\EntityScope;
use App\Services\Volunteer\Goals\FileDrafts;
use App\Services\Volunteer\Goals\GoalLaunchService;
use Database\Seeders\SettingSeeder;
use Database\Seeders\VolunteerGoalsDemoSeeder;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;

/**
 * ⭐ **مسودّات الملفّات** (الدستور 23 — 1.2 سيناريو مشرف عام الملفّات · 1.6).
 *
 * النصّ: «إن وُجد ملفٌّ مناسب شغّال ربط به الحزمة مباشرةً؛ وإن لم يوجد **أنشأ
 * أثناء البناء «مسودّات ملفات» جديدة وربطها بالحزم** — **ولا تتفعّل رسميًّا
 * (عضويّات ودعوات) إلّا لحظة ضغط مشرف عام التطوّع «إرسال للتنفيذ»** — فيظلّ
 * الفتح حصريًّا للقمّة **بصفر خطوة إضافيّة**، ويظلّ الإنهاء له وحده».
 *
 * وكلّ اختبارٍ هنا يقابل جزءًا من هذه الجملة، ويقيسه حيث يُفرَض: على الخادم.
 */
class GoalFileDraftsTest extends GoalsTestCase
{
    private Entity $department;

    private Entity $fileEntity;

    private User $top;

    private User $filesSupervisor;

    private User $deptSupervisor;

    private User $invitee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
        (new VolunteerGoalsDemoSeeder)->settings();

        Cache::forget('settings');
        Cache::forget('rep_rules');

        $this->department = $this->makeEntity('قسم الإعلام');

        // كيان قائم على مسار الملفّات — فمشرفه له عضويّة على مساره
        $this->fileEntity = Entity::create([
            'track_id' => $this->fileTrackId(),
            'name_ar' => 'ملفّ معرض الكتاب',
            'status' => 'active',
            'opened_at' => now()->subMonth(),
        ]);

        $this->top = $this->makeUser('مشرف عام التطوّع');
        $topMembership = $this->makeMembership($this->top, $this->department, null, 'volunteer_gm');

        foreach (['goals.create', 'goals.view', 'goals.approve', 'milestones.edit',
            'milestones.create', 'work_packages.create'] as $key) {
            $this->grant($this->top, $key, 'ALL', $topMembership);
        }

        // ⭐ مشرف عام **مسار الملفّات** — صاحب السيناريو
        $this->filesSupervisor = $this->makeUser('مشرف عام الملفّات');
        $filesMembership = $this->makeMembership($this->filesSupervisor, $this->fileEntity, null, 'track_supervisor');

        foreach (['milestones.create', 'work_packages.create', 'milestones.edit', 'goals.view'] as $key) {
            $this->grant($this->filesSupervisor, $key, 'TRACK', $filesMembership);
        }

        // مشرف عام **الأقسام** — مسارٌ آخر، ولا شأن له بفتح الملفّات
        $this->deptSupervisor = $this->makeUser('مشرف عام الأقسام');
        $deptMembership = $this->makeMembership($this->deptSupervisor, $this->department, null, 'track_supervisor');

        foreach (['milestones.create', 'work_packages.create', 'milestones.edit', 'goals.view'] as $key) {
            $this->grant($this->deptSupervisor, $key, 'TRACK', $deptMembership);
        }

        $this->invitee = $this->makeUser('مدعوّ للملفّ');
    }

    // ------------------------------------------------------------------ أدوات

    private function fileTrackId(): int
    {
        return (int) Track::query()->where('key', 'case_file')->value('id');
    }

    private function coordinatorPositionId(): int
    {
        return (int) Position::query()->where('key', 'coordinator')->value('id');
    }

    private function departmentTrackId(): int
    {
        return (int) Track::query()->where('key', 'department')->value('id');
    }

    /** هدف مربوط بمسار **الملفّات** — فمشرفه هو الحائز */
    private function linkedGoal(string $name = 'تغطية معرض الكتاب'): Goal
    {
        $this->actingAs($this->top)->post(route('volunteer.goals.build.store'), [
            'name' => $name,
            'reason' => 'لأنّ التغطية الميدانيّة تحتاج ملفًّا مؤقّتًا لا قسمًا دائمًا.',
            'priority' => 1,
            'end_date' => now()->addMonths(2)->toDateString(),
            'verification_type' => 'numeric',
            'target_from' => 0,
            'target_to' => 100,
            'tracks' => [$this->fileTrackId()],
        ])->assertRedirect();

        return Goal::query()->where('name', $name)->firstOrFail();
    }

    private function makeDraft(Goal $goal, string $name = 'ملفّ التغطية الميدانيّة', bool $withInvite = true): Entity
    {
        $payload = ['name' => $name];

        if ($withInvite) {
            $payload['invitations'] = [
                ['user_id' => $this->invitee->id, 'position_id' => $this->coordinatorPositionId()],
            ];
        }

        $this->actingAs($this->filesSupervisor)
            ->post(route('volunteer.goals.build.file_drafts', $goal), $payload)
            ->assertRedirect();

        return Entity::query()->where('name_ar', $name)->firstOrFail();
    }

    /** مَعلَم بحزمةٍ مربوطة بالمسودّة وفيها مهمّة — أي هدفٌ صالح للإطلاق */
    private function readyGoal(Goal $goal, Entity $draft): void
    {
        $this->actingAs($this->filesSupervisor)
            ->post(route('volunteer.goals.build.milestones', $goal), ['name' => 'التغطية'])
            ->assertRedirect();

        $milestone = Milestone::query()->where('goal_id', $goal->id)->firstOrFail();

        $this->actingAs($this->filesSupervisor)
            ->post(route('volunteer.goals.build.packages', $milestone), [
                'mode' => 'single',
                'entity_id' => $draft->id,
                'name' => 'حزمة التغطية',
            ])->assertRedirect()->assertSessionHasNoErrors();

        $package = WorkPackage::query()->where('milestone_id', $milestone->id)->firstOrFail();

        $this->actingAs($this->filesSupervisor)
            ->post(route('volunteer.goals.build.aggregate.task', $goal), [
                'package_id' => $package->id,
                'title' => 'تغطية اليوم الأوّل',
                'deliverable_spec' => 'ألبوم صور + تقرير',
            ])->assertRedirect()->assertSessionHasNoErrors();
    }

    // ============================================== الإنشاء: مَن يملكه ومَن لا

    #[Test]
    public function the_files_track_supervisor_creates_a_draft_file_during_the_build(): void
    {
        $goal = $this->linkedGoal();
        $draft = $this->makeDraft($goal);

        $this->assertSame('draft', $draft->status);
        $this->assertSame($goal->id, (int) $draft->draft_goal_id);
        $this->assertNull($draft->opened_at, 'المسودّة اتختمت بلحظة فتح وهي لسّه ما اتفتحتش.');
    }

    #[Test]
    public function the_departments_supervisor_cannot_open_a_file(): void
    {
        /*
         * ⚠️ الهدف مربوط بـ**المسارين** عمدًا. فلو رُبِط بمسار الملفّات وحده
         * لردّ الخادمُ 403 من `canSeeBuild` — أي «الهدف مش من مسارك» — فيمرّ
         * الاختبار وهو لا يقيس شيئًا عن فتح الملفّات. وبالربط المزدوج يرى
         * مشرفُ الأقسام الرحلةَ ويحوز التحرير، فلا يبقى مانعٌ إلّا القاعدة
         * المقيسة نفسها: **الفتح لمشرف عام مسار الملفّات وحده**.
         */
        $this->actingAs($this->top)->post(route('volunteer.goals.build.store'), [
            'name' => 'هدف على المسارين',
            'reason' => 'ليقيس الاختبارُ قاعدةَ الملفّات لا قاعدةَ رؤية المسار.',
            'priority' => 1,
            'end_date' => now()->addMonths(2)->toDateString(),
            'verification_type' => 'numeric',
            'target_from' => 0,
            'target_to' => 100,
            'tracks' => [$this->fileTrackId(), $this->departmentTrackId()],
        ])->assertRedirect();

        $goal = Goal::query()->where('name', 'هدف على المسارين')->firstOrFail();

        // يرى الرحلة فعلًا — وإلّا كان الرفضُ لسببٍ آخر
        $this->actingAs($this->deptSupervisor)
            ->get(route('volunteer.goals.build.breakdown', $goal))
            ->assertOk();

        // ويملك `work_packages.create` بنطاق TRACK — فالمرفوض ليس المفتاح
        $this->actingAs($this->deptSupervisor)
            ->post(route('volunteer.goals.build.file_drafts', $goal), ['name' => 'ملفّ مسروق'])
            ->assertForbidden();

        $this->assertDatabaseMissing('entities', ['name_ar' => 'ملفّ مسروق']);

        // ولا يظهر له زرّ الفتح أصلًا — مخفيّ لا معطَّل (2.15-أ-7)
        $this->actingAs($this->deptSupervisor)
            ->get(route('volunteer.goals.build.breakdown', $goal))
            ->assertDontSee('مسودّة ملفّ جديدة');

        $this->actingAs($this->filesSupervisor)
            ->get(route('volunteer.goals.build.breakdown', $goal))
            ->assertSee('مسودّة ملفّ جديدة');
    }

    // ====================================== «لا تتفعّل رسميًّا»: موجودةٌ ومعدومة

    #[Test]
    public function a_draft_file_is_not_an_entity_anyone_can_work_in(): void
    {
        $goal = $this->linkedGoal();
        $draft = $this->makeDraft($goal);

        // خارج قوائم الكيانات القابلة للربط العاديّ (`status = active`)
        $linkable = app(EntityScope::class)
            ->linkableEntities($this->filesSupervisor)
            ->pluck('id')->all();

        $this->assertNotContains($draft->id, $linkable,
            'المسودّة ظهرت ككيانٍ شغّال في قوائم الربط العاديّة.');
    }

    #[Test]
    public function the_written_invitation_has_no_effect_before_the_launch(): void
    {
        $goal = $this->linkedGoal();
        $draft = $this->makeDraft($goal);

        $membership = Membership::query()
            ->where('entity_id', $draft->id)
            ->where('user_id', $this->invitee->id)
            ->firstOrFail();

        $this->assertSame('invited', $membership->status);
        $this->assertNull($membership->started_at, 'العضويّة بدأت قبل ما الملفّ يتفتح.');
        $this->assertNotNull($membership->invited_at);

        // ولا عضويّة **نشطة** واحدة للمدعوّ — وكلّ استعلامات المنصّة تشترط `active`
        $this->assertSame(0, Membership::query()
            ->where('user_id', $this->invitee->id)
            ->where('status', 'active')
            ->count());

        // ولا إشعار: مَن لم يُفتَح ملفُّه بعد لا يُدعى إليه
        $this->assertSame(0, AppNotification::query()->where('user_id', $this->invitee->id)->count(),
            'وصلت دعوة لملفّ لسّه ما اتفتحش — وقد لا يُفتَح أصلًا.');
    }

    #[Test]
    public function a_package_can_be_linked_to_a_draft_of_its_own_goal(): void
    {
        $goal = $this->linkedGoal();
        $draft = $this->makeDraft($goal);

        $this->actingAs($this->filesSupervisor)
            ->post(route('volunteer.goals.build.milestones', $goal), ['name' => 'التغطية'])
            ->assertRedirect();

        $milestone = Milestone::query()->where('goal_id', $goal->id)->firstOrFail();

        $this->actingAs($this->filesSupervisor)
            ->post(route('volunteer.goals.build.packages', $milestone), [
                'mode' => 'single',
                'entity_id' => $draft->id,
                'name' => 'حزمة التغطية',
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('work_packages', ['milestone_id' => $milestone->id, 'entity_id' => $draft->id]);
    }

    #[Test]
    public function a_draft_of_another_goal_cannot_be_linked_here(): void
    {
        $mine = $this->linkedGoal('هدفي');
        $other = $this->linkedGoal('هدف تاني');
        $otherDraft = $this->makeDraft($other, 'ملفّ الهدف التاني');

        $this->actingAs($this->filesSupervisor)
            ->post(route('volunteer.goals.build.milestones', $mine), ['name' => 'مَعلَم'])
            ->assertRedirect();

        $milestone = Milestone::query()->where('goal_id', $mine->id)->firstOrFail();

        $this->actingAs($this->filesSupervisor)
            ->post(route('volunteer.goals.build.packages', $milestone), [
                'mode' => 'single',
                'entity_id' => $otherDraft->id,
                'name' => 'حزمة',
            ])->assertRedirect()->assertSessionHasErrors();

        $this->assertDatabaseMissing('work_packages', ['entity_id' => $otherDraft->id]);
    }

    // ================================ «الفتح حصريًّا للقمّة بصفر خطوة إضافيّة»

    #[Test]
    public function the_launch_press_is_what_opens_the_file_and_activates_its_invitations(): void
    {
        $goal = $this->linkedGoal();
        $draft = $this->makeDraft($goal);
        $this->readyGoal($goal, $draft);

        $this->actingAs($this->top)
            ->post(route('volunteer.goals.launch.send', $goal))
            ->assertRedirect()->assertSessionHasNoErrors();

        $draft->refresh();

        $this->assertSame('active', $draft->status, 'الملفّ ما اتفتحش بضغطة الإطلاق.');
        $this->assertNotNull($draft->opened_at);
        $this->assertNull($draft->draft_goal_id, 'الملفّ فُتِح وبقي موسومًا كمسودّة هدف.');

        $membership = Membership::query()
            ->where('entity_id', $draft->id)
            ->where('user_id', $this->invitee->id)
            ->firstOrFail();

        $this->assertSame('active', $membership->status);
        $this->assertNotNull($membership->started_at);
        $this->assertNotNull($membership->activated_at);

        // والدعوة تصل **الآن** لا قبل ذلك
        $this->assertGreaterThan(0, AppNotification::query()->where('user_id', $this->invitee->id)->count());
    }

    #[Test]
    public function no_route_in_the_platform_opens_a_file_outside_the_launch(): void
    {
        /*
         * هذا هو حارس «**بصفر خطوة إضافيّة**»: لو وُجد مسارٌ ثانٍ يفتح ملفًّا
         * لصارت الحصريّة كلامًا — يفتح مشرف الملفّات ما يشاء ثمّ يُخبِر القمّة.
         * فالحارس يقيس **غياب الباب** لا سلوك بابٍ موجود.
         */
        $opening = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route) => (string) $route->getName())
            ->filter(fn (string $name) => str_contains($name, 'file_draft'))
            ->values()
            ->all();

        $this->assertSame(['volunteer.goals.build.file_drafts'], $opening,
            'ظهر مسارٌ آخر يمسّ مسودّات الملفّات — والفتح ضغطةُ القمّة وحدها.');
    }

    #[Test]
    public function launching_one_goal_never_opens_another_goals_drafts(): void
    {
        $mine = $this->linkedGoal('هدفي');
        $myDraft = $this->makeDraft($mine, 'ملفّي', withInvite: false);
        $this->readyGoal($mine, $myDraft);

        $other = $this->linkedGoal('هدف تاني');
        $otherDraft = $this->makeDraft($other, 'ملفّ الهدف التاني');

        $this->actingAs($this->top)
            ->post(route('volunteer.goals.launch.send', $mine))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('active', $myDraft->refresh()->status);

        $otherDraft->refresh();
        $this->assertSame('draft', $otherDraft->status,
            'إطلاق هدفٍ فتح ملفّ هدفٍ آخر ما زال قيد البناء — فتحٌ لم تضغطه القمّة.');

        $this->assertSame('invited', Membership::query()
            ->where('entity_id', $otherDraft->id)->value('status'));
    }

    #[Test]
    public function a_goal_that_fails_its_gap_check_opens_nothing(): void
    {
        $goal = $this->linkedGoal();
        $draft = $this->makeDraft($goal);

        // بلا مَعالِم ولا مهامّ ⟵ «إرسال للتنفيذ» يرفض بقائمة النواقص (1.5)
        $this->actingAs($this->top)
            ->post(route('volunteer.goals.launch.send', $goal))
            ->assertRedirect();

        $this->assertSame('draft', $draft->refresh()->status,
            'اتفتح ملفّ على هدفٍ اترفض إرساله — فيفضل مفتوحًا بلا عملٍ ولا مَن يُنهيه.');

        $this->assertSame(0, AppNotification::query()->where('user_id', $this->invitee->id)->count());
    }

    #[Test]
    public function activation_is_idempotent_so_a_second_press_changes_nothing(): void
    {
        $goal = $this->linkedGoal();
        $draft = $this->makeDraft($goal);
        $this->readyGoal($goal, $draft);

        $this->actingAs($this->top)->post(route('volunteer.goals.launch.send', $goal))->assertRedirect();

        $openedAt = $draft->refresh()->opened_at;
        $notifications = AppNotification::query()->where('user_id', $this->invitee->id)->count();

        // الضغطة الثانية مرفوضة أصلًا («اتبعت للتنفيذ قبل كده») — ولا تفتح شيئًا ثانية
        $this->actingAs($this->top)->post(route('volunteer.goals.launch.send', $goal))->assertRedirect();

        $this->assertEquals($openedAt, $draft->refresh()->opened_at);
        $this->assertSame($notifications, AppNotification::query()->where('user_id', $this->invitee->id)->count());
    }

    #[Test]
    public function the_service_reports_what_it_opened(): void
    {
        $goal = $this->linkedGoal();
        $draft = $this->makeDraft($goal);
        $this->readyGoal($goal, $draft);

        $result = app(GoalLaunchService::class)->launch($goal->refresh(), $this->top);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['files']);
        $this->assertSame(1, $result['memberships']);
    }

    #[Test]
    public function the_file_track_key_comes_from_settings_not_from_the_code(): void
    {
        $this->assertSame('case_file', app(FileDrafts::class)->trackKey());

        Setting::query()->where('key', 'goals.build.file_track_key')
            ->update(['value' => 'governorate']);
        Cache::forget('settings');

        $this->assertSame('governorate', app(FileDrafts::class)->trackKey());
    }
}
