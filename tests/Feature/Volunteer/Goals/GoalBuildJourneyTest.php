<?php

namespace Tests\Feature\Volunteer\Goals;

use App\Models\AppNotification;
use App\Models\Entity;
use App\Models\Goal;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\Track;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkPackage;
use Database\Seeders\SettingSeeder;
use Database\Seeders\VolunteerGoalsDemoSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ رحلة بناء الهدف — المرحلة صفر (الدستور 23 — 1.1 … 1.4).
 *
 * كلّ اختبار هنا يقابل **قاعدة منصوصة**، ويقيسها حيث تُفرَض: على الخادم.
 *  1.1 «هدف بلا معيار تحقّق لا يُحفَظ» · «لا يراه أحد حتى يُربَط بمسار» ·
 *      «فيصل إشعاره لمشرفي المسارات المعنيّين وحدهم».
 *  1.2 «يربط كلّ حزمة بكيان **من مساره**» · «مَعلَم واحد ⟵ حزمة لكلّ كيان بضغطة».
 *  1.3 «يرى الهدف والمَعلَم و**حزمه هو** فقط» · «مهامّ لنفسه بلا حدّ أقصى» ·
 *      «**وحتى هذه اللحظة لا يرى أحد من الداونلاينز شيئًا**».
 *  1.4 «حفظ تلقائيّ فوريّ لكلّ إنبوت» يعود فعلًا للحقل · سجلّ «تمّ التعديل» ·
 *      «التسعير لمشرف المسار» · «**القفل الطبقيّ**» بعد «رفع معاينة».
 */
class GoalBuildJourneyTest extends GoalsTestCase
{
    private Entity $entity;

    private Entity $sisterEntity;

    private Entity $govEntity;

    private User $top;

    private User $trackSupervisor;

    private User $govSupervisor;

    private User $director;

    private User $sisterDirector;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
        (new VolunteerGoalsDemoSeeder)->settings();

        Cache::forget('settings');
        Cache::forget('rep_rules');

        $this->entity = $this->makeEntity('قسم الإعلام');
        $this->sisterEntity = $this->makeEntity('قسم التدريب');
        $this->govEntity = Entity::create([
            'track_id' => Track::query()->where('key', 'governorate')->value('id'),
            'name_ar' => 'محافظة القاهرة',
            'status' => 'active',
        ]);

        // القمّة — نطاق ALL في كلّ مفاتيح الرحلة
        $this->top = $this->makeUser('مشرف عام التطوّع');
        $topMembership = $this->makeMembership($this->top, $this->entity, null, 'volunteer_gm');

        foreach (['goals.create', 'goals.view', 'goals.approve', 'milestones.edit',
            'milestones.delete', 'work_packages.delete', 'wp_items.delete'] as $key) {
            $this->grant($this->top, $key, 'ALL', $topMembership);
        }

        // مشرف عام مسار الأقسام — نطاق TRACK
        $this->trackSupervisor = $this->makeUser('مشرف عام الأقسام');
        $tsMembership = $this->makeMembership($this->trackSupervisor, $this->entity, null, 'track_supervisor');

        foreach (['milestones.create', 'work_packages.create', 'milestones.edit', 'goals.view'] as $key) {
            $this->grant($this->trackSupervisor, $key, 'TRACK', $tsMembership);
        }

        // مشرف عام مسار المحافظات — مسارٌ آخر تمامًا
        $this->govSupervisor = $this->makeUser('مشرف عام المحافظات');
        $govMembership = $this->makeMembership($this->govSupervisor, $this->govEntity, null, 'track_supervisor');

        foreach (['milestones.create', 'work_packages.create', 'milestones.edit', 'goals.view'] as $key) {
            $this->grant($this->govSupervisor, $key, 'TRACK', $govMembership);
        }

        // دايركتور الكيان — نطاق ENTITY
        $this->director = $this->makeUser('دايركتور الإعلام');
        $dirMembership = $this->makeMembership($this->director, $this->entity, null, 'director');

        foreach (['wp_items.create', 'wp_items.edit', 'goals.view'] as $key) {
            $this->grant($this->director, $key, 'ENTITY', $dirMembership);
        }

        $this->sisterDirector = $this->makeUser('دايركتور التدريب');
        $sisMembership = $this->makeMembership($this->sisterDirector, $this->sisterEntity, null, 'director');

        foreach (['wp_items.create', 'wp_items.edit', 'goals.view'] as $key) {
            $this->grant($this->sisterDirector, $key, 'ENTITY', $sisMembership);
        }

        /*
         | ⭐ كوردنيتور **مُمنَحٌ بالخطأ** مفاتيح الدايركتور بنطاق ENTITY: الحارس
         | المقيس ليس «هل يملك المفتاح؟» بل «هل هو من الطبقات الثلاث؟» — فلو كان
         | المفتاح وحده كافيًا لانكشفت الرحلة كلّها لأوّل منحةٍ واسعة بالخطأ.
         */
        $this->coordinator = $this->makeUser('كوردنيتور');
        $coMembership = $this->makeMembership($this->coordinator, $this->entity, null, 'coordinator');

        foreach (['wp_items.create', 'wp_items.edit', 'goals.view'] as $key) {
            $this->grant($this->coordinator, $key, 'ENTITY', $coMembership);
        }
    }

    // ------------------------------------------------------------------ أدوات

    private function departmentTrackId(): int
    {
        return (int) Track::query()->where('key', 'department')->value('id');
    }

    /** هدف محفوظ ومربوط بمسار الأقسام — نقطة انطلاق أغلب الاختبارات */
    private function linkedGoal(): Goal
    {
        $this->actingAs($this->top)->post(route('volunteer.goals.build.store'), [
            'name' => 'رفع وعي 20 ألف شاب',
            'reason' => 'لأنّ الفجوة في وعي المتطوّعين الجدد هي عنق الزجاجة.',
            'priority' => 1,
            'end_date' => now()->addMonths(3)->toDateString(),
            'verification_type' => 'numeric',
            'target_from' => 0,
            'target_to' => 20000,
            'tracks' => [$this->departmentTrackId()],
        ])->assertRedirect();

        return Goal::query()->where('name', 'رفع وعي 20 ألف شاب')->firstOrFail();
    }

    /** شجرة جاهزة: هدف مربوط ⟵ مَعلَم ⟵ حزمة لكلّ من الكيانين */
    private function brokenDownGoal(): array
    {
        $goal = $this->linkedGoal();

        $this->actingAs($this->trackSupervisor)
            ->post(route('volunteer.goals.build.milestones', $goal), ['name' => 'إنتاج المحتوى المرئيّ'])
            ->assertRedirect();

        $milestone = Milestone::query()->where('goal_id', $goal->id)->firstOrFail();

        $this->actingAs($this->trackSupervisor)
            ->post(route('volunteer.goals.build.packages', $milestone), ['mode' => 'bulk'])
            ->assertRedirect();

        return [$goal->fresh(), $milestone];
    }

    private function packageOf(Milestone $milestone, Entity $entity): WorkPackage
    {
        return WorkPackage::query()
            ->where('milestone_id', $milestone->id)
            ->where('entity_id', $entity->id)
            ->firstOrFail();
    }

    private function addTask(User $director, WorkPackage $package, string $title): Task
    {
        $this->actingAs($director)->post(route('volunteer.goals.build.tasks', $package), [
            'title' => $title,
            'deliverable_spec' => 'فيديو 60 ثانية رأسيّ بمقاسات الهويّة.',
        ])->assertRedirect();

        return Task::query()->where('title', $title)->firstOrFail();
    }

    // ------------------------------------------------------- 1.1 إنشاء الهدف

    /** ⛔ «هدف بلا معيار تحقّق لا يُحفَظ» — والفرض على الخادم لا في الواجهة */
    public function test_a_goal_without_a_verification_criterion_is_never_saved(): void
    {
        // مدًى رقميّ بلا طرفين
        $this->actingAs($this->top)->post(route('volunteer.goals.build.store'), [
            'name' => 'هدف بلا معيار',
            'reason' => 'سبب مكتوب لكنّ المعيار غائب.',
            'priority' => 2,
            'end_date' => now()->addMonth()->toDateString(),
            'verification_type' => 'numeric',
        ])->assertSessionHasErrors('verification_type');

        // حالة بنعم/لا بلا نصّ الحالة
        $this->actingAs($this->top)->post(route('volunteer.goals.build.store'), [
            'name' => 'هدف بلا معيار',
            'reason' => 'سبب مكتوب لكنّ المعيار غائب.',
            'priority' => 2,
            'end_date' => now()->addMonth()->toDateString(),
            'verification_type' => 'boolean',
            'verification_statement' => '',
        ])->assertSessionHasErrors('verification_type');

        $this->assertSame(0, Goal::query()->where('name', 'هدف بلا معيار')->count());
    }

    /** والمعيار بصورتيه يُحفَظ: مدًى رقميّ، أو حالة تُفحَص بنعم/لا */
    public function test_both_shapes_of_the_verification_criterion_are_saved(): void
    {
        $this->actingAs($this->top)->post(route('volunteer.goals.build.store'), [
            'name' => 'هدف رقميّ',
            'reason' => 'سبب واضح ومكتوب.',
            'priority' => 1,
            'end_date' => now()->addMonth()->toDateString(),
            'verification_type' => 'numeric',
            'target_from' => 0,
            'target_to' => 500,
        ])->assertRedirect();

        $this->actingAs($this->top)->post(route('volunteer.goals.build.store'), [
            'name' => 'هدف حالة',
            'reason' => 'سبب واضح ومكتوب.',
            'priority' => 2,
            'end_date' => now()->addMonth()->toDateString(),
            'verification_type' => 'boolean',
            'verification_statement' => 'اتنشرت 12 فيديو معتمدة؟',
        ])->assertRedirect();

        $numeric = Goal::query()->where('name', 'هدف رقميّ')->firstOrFail();
        $boolean = Goal::query()->where('name', 'هدف حالة')->firstOrFail();

        $this->assertSame('500.00', (string) $numeric->target_to);
        $this->assertSame('اتنشرت 12 فيديو معتمدة؟', $boolean->verification_statement);
        $this->assertSame('draft', $numeric->build_stage);
    }

    /** ⛔ «الهدف عند إنشائه لا يراه أحد» — يظهر لحظة ربطه بمسار */
    public function test_a_goal_is_invisible_until_it_is_linked_to_a_track(): void
    {
        $this->actingAs($this->top)->post(route('volunteer.goals.build.store'), [
            'name' => 'هدف مكتوم',
            'reason' => 'سبب واضح ومكتوب.',
            'priority' => 2,
            'end_date' => now()->addMonth()->toDateString(),
            'verification_type' => 'numeric',
            'target_from' => 0,
            'target_to' => 10,
        ])->assertRedirect();

        $goal = Goal::query()->where('name', 'هدف مكتوم')->firstOrFail();

        // قبل الربط: مشرف المسار لا يراه ولا يفتح شاشته
        $this->actingAs($this->trackSupervisor)->get(route('volunteer.goals.build'))->assertDontSee('هدف مكتوم');
        $this->actingAs($this->trackSupervisor)->get(route('volunteer.goals.build.breakdown', $goal))->assertForbidden();

        $this->actingAs($this->top)
            ->post(route('volunteer.goals.build.tracks', $goal), ['tracks' => [$this->departmentTrackId()]])
            ->assertRedirect();

        // وبعد الربط: يراه ويفكّكه
        $this->actingAs($this->trackSupervisor)->get(route('volunteer.goals.build.breakdown', $goal))
            ->assertOk()->assertSee('هدف مكتوم');
    }

    /** «فيصل إشعاره لمشرفي المسارات المعنيّين وحدهم — لا لغيرهم» */
    public function test_only_the_supervisors_of_the_linked_tracks_are_notified(): void
    {
        $this->linkedGoal();

        $this->assertSame(1, AppNotification::query()->where('user_id', $this->trackSupervisor->id)->count());
        $this->assertSame(0, AppNotification::query()->where('user_id', $this->govSupervisor->id)->count());
    }

    // ---------------------------------------------------------- 1.2 التفكيك

    /** «مَعلَم واحد ⟵ حزمة لكلّ كيان من كيانات مساره» — بضغطة واحدة */
    public function test_bulk_linking_creates_one_package_per_entity_of_the_track(): void
    {
        [, $milestone] = $this->brokenDownGoal();

        $packages = WorkPackage::query()->where('milestone_id', $milestone->id)->get();

        $this->assertSame(2, $packages->count());
        $this->assertEqualsCanonicalizing(
            [$this->entity->id, $this->sisterEntity->id],
            $packages->pluck('entity_id')->map(fn ($id) => (int) $id)->all(),
        );

        // الربط بالكيان نفسه لا بشخص الدايركتور، والاسم الافتراضيّ «Work Package N»
        $this->assertTrue($packages->every(fn (WorkPackage $p) => $p->entity_id !== null));
        $this->assertStringContainsString('Work Package', (string) $packages->first()->name);
    }

    /** ⛔ الحصر الصارم: كيان خارج مسار المشرف لا يُربَط — ويُرفَض على الخادم */
    public function test_a_track_supervisor_cannot_link_a_package_outside_his_own_track(): void
    {
        $goal = $this->linkedGoal();

        $this->actingAs($this->trackSupervisor)
            ->post(route('volunteer.goals.build.milestones', $goal), ['name' => 'مَعلَم'])
            ->assertRedirect();

        $milestone = Milestone::query()->where('goal_id', $goal->id)->firstOrFail();

        // شاشته لا تعرض أصلًا كيانات مسارٍ آخر
        $this->actingAs($this->trackSupervisor)->get(route('volunteer.goals.build.breakdown', $goal))
            ->assertOk()->assertDontSee('محافظة القاهرة');

        // وحتى لو زوّر المعرّف: يُرفَض ولا يُنشأ صفّ
        $this->actingAs($this->trackSupervisor)->post(route('volunteer.goals.build.packages', $milestone), [
            'mode' => 'single',
            'entity_id' => $this->govEntity->id,
        ])->assertSessionHasErrors('entities');

        $this->assertSame(0, WorkPackage::query()->where('entity_id', $this->govEntity->id)->count());
    }

    // ------------------------------------------------------- 1.3 ملء الحزم

    /** ⛔ «حتى هذه اللحظة لا يرى أحد من الداونلاينز شيئًا» */
    public function test_a_downline_inside_the_entity_sees_nothing_of_the_journey(): void
    {
        [$goal, $milestone] = $this->brokenDownGoal();
        $this->addTask($this->director, $this->packageOf($milestone, $this->entity), 'كتابة السكربتات');

        // كوردنيتور داخل الكيان نفسه — ومعه مفاتيح الدايركتور بالخطأ
        $this->actingAs($this->coordinator)->get(route('volunteer.goals.build.fill', $goal))->assertForbidden();
        $this->actingAs($this->coordinator)->get(route('volunteer.goals.build'))
            ->assertOk()->assertDontSee($goal->name);
        $this->actingAs($this->coordinator)->get(route('volunteer.goals'))
            ->assertOk()->assertDontSee($goal->name)->assertDontSee('كتابة السكربتات');

        // وتيم ليدر كذلك — الرحلة كلّها فوق طبقته
        $leader = $this->makeUser('تيم ليدر');
        $leaderMembership = $this->makeMembership($leader, $this->entity, null, 'team_leader');
        $this->grant($leader, 'wp_items.create', 'ENTITY', $leaderMembership);
        $this->grant($leader, 'goals.view', 'ENTITY', $leaderMembership);

        $this->actingAs($leader)->get(route('volunteer.goals.build.fill', $goal))->assertForbidden();
    }

    /** ⛔ «يرى … حزمه هو فقط» — حزمة كيانٍ شقيقٍ داخل المَعلَم نفسه لا تصله */
    public function test_a_director_sees_only_the_packages_of_his_own_entity(): void
    {
        [$goal, $milestone] = $this->brokenDownGoal();

        $this->addTask($this->sisterDirector, $this->packageOf($milestone, $this->sisterEntity), 'مهمّة التدريب');

        $this->actingAs($this->director)->get(route('volunteer.goals.build.fill', $goal))
            ->assertOk()
            ->assertSee('قسم الإعلام')
            ->assertDontSee('قسم التدريب')
            ->assertDontSee('مهمّة التدريب');
    }

    /** «يضيف مهامًّا لنفسه **بلا حدّ أقصى**» — ثمّ «رفع للمراجعة» */
    public function test_a_director_adds_tasks_without_a_cap_then_submits_for_review(): void
    {
        [$goal, $milestone] = $this->brokenDownGoal();
        $package = $this->packageOf($milestone, $this->entity);

        foreach (range(1, 12) as $i) {
            $this->addTask($this->director, $package, 'مهمّة رقم '.$i);
        }

        $items = WorkItem::query()->where('work_package_id', $package->id)->pluck('id');
        $tasks = Task::query()->whereIn('work_item_id', $items)->get();

        $this->assertSame(12, $tasks->count());
        // كلّ مهمّة مربوطة ببند إجباريًّا، ومملوكة لصاحبها هو
        $this->assertTrue($tasks->every(fn (Task $t) => $t->work_item_id !== null && (int) $t->owner_id === $this->director->id));

        // وأيقونة الكيان تظهر مع مهامّه: أوّل حرف من كلّ كلمة في اسم القسم
        $this->actingAs($this->director)->get(route('volunteer.goals.build.fill', $goal))
            ->assertOk()->assertSee('قا');

        $this->actingAs($this->director)
            ->post(route('volunteer.goals.build.submit', $package))
            ->assertRedirect();

        $this->assertSame('submitted', $package->fresh()->build_status);
    }

    // ------------------------------------ 1.4 التجميع والتسعير والقفل الطبقيّ

    /** ⭐ الحفظ التلقائيّ **يعود فعلًا إلى الحقل** عند إعادة الفتح */
    public function test_autosaved_values_come_back_to_the_form_when_it_is_reopened(): void
    {
        [$goal, $milestone] = $this->brokenDownGoal();

        $this->actingAs($this->trackSupervisor)->postJson(route('volunteer.goals.build.field', $goal), [
            'subject' => 'milestone',
            'id' => $milestone->id,
            'field' => 'name',
            'value' => 'إنتاج المحتوى المرئيّ — نسخة معدّلة',
        ])->assertOk()->assertJson(['ok' => true]);

        // في الصفّ الحقيقيّ لا في مسودّة جانبيّة
        $this->assertSame('إنتاج المحتوى المرئيّ — نسخة معدّلة', $milestone->fresh()->name);

        // وفي الشاشة عند إعادة فتحها
        $this->actingAs($this->trackSupervisor)->get(route('volunteer.goals.build.aggregate', $goal))
            ->assertOk()
            ->assertSee('إنتاج المحتوى المرئيّ — نسخة معدّلة')
            ->assertSee('تمّ التعديل');
    }

    /** سجلّ «تمّ التعديل»: كلّ تعديلات **هذا الحقل بعينه** — مَن · متى · ماذا كان */
    public function test_the_edited_badge_lists_every_change_of_that_very_field(): void
    {
        [$goal, $milestone] = $this->brokenDownGoal();
        $was = $milestone->name;

        foreach (['نسخة أولى', 'نسخة ثانية'] as $value) {
            $this->actingAs($this->trackSupervisor)->postJson(route('volunteer.goals.build.field', $goal), [
                'subject' => 'milestone', 'id' => $milestone->id, 'field' => 'name', 'value' => $value,
            ])->assertOk();
        }

        $response = $this->actingAs($this->trackSupervisor)->getJson(
            route('volunteer.goals.build.revisions', $goal).'?subject=milestone&id='.$milestone->id.'&field=name',
        )->assertOk();

        $rows = $response->json('rows');

        $this->assertCount(2, $rows);
        $this->assertSame('نسخة أولى', $rows[0]['was']);
        $this->assertSame($was, $rows[1]['was']);
        $this->assertSame($this->trackSupervisor->name, $rows[0]['by']);
    }

    /** «التسعير: مشرف المسار وحده يضيف قيمة VXP لكلّ مهمّة» */
    public function test_only_the_track_supervisor_prices_the_tasks(): void
    {
        [$goal, $milestone] = $this->brokenDownGoal();
        $task = $this->addTask($this->director, $this->packageOf($milestone, $this->entity), 'كتابة السكربتات');

        $this->actingAs($this->trackSupervisor)->postJson(route('volunteer.goals.build.field', $goal), [
            'subject' => 'task', 'id' => $task->id, 'field' => 'vxp_value', 'value' => 120,
        ])->assertOk();

        $this->assertSame('120.00', (string) $task->fresh()->vxp_value);

        // والدايركتور لا يسعّر: لا مفتاح ولا طبقة
        $this->actingAs($this->director)->postJson(route('volunteer.goals.build.field', $goal), [
            'subject' => 'task', 'id' => $task->id, 'field' => 'vxp_value', 'value' => 999,
        ])->assertForbidden();

        $this->assertSame('120.00', (string) $task->fresh()->vxp_value);
    }

    /** «إضافة مهامّ جديدة مربوطة بالكيانات» — ومالكها دايركتور الكيان لا كاتبها */
    public function test_the_track_supervisor_adds_new_tasks_bound_to_the_entities(): void
    {
        [$goal, $milestone] = $this->brokenDownGoal();
        $package = $this->packageOf($milestone, $this->entity);

        $this->actingAs($this->trackSupervisor)->post(route('volunteer.goals.build.aggregate.task', $goal), [
            'package_id' => $package->id,
            'title' => 'مهمّة أضافها مشرف المسار',
            'deliverable_spec' => 'تقرير قصير بصيغة PDF.',
        ])->assertRedirect();

        $task = Task::query()->where('title', 'مهمّة أضافها مشرف المسار')->firstOrFail();

        $this->assertSame((int) $this->entity->id, (int) $task->entity_id);
        $this->assertSame((int) $this->director->id, (int) $task->owner_id);
        $this->assertNotNull($task->work_item_id);
    }

    /** ⛔ **القفل الطبقيّ**: بعد «رفع معاينة» يصير مشرف المسار قارئًا فقط */
    public function test_the_layer_lock_turns_the_track_supervisor_into_a_reader_after_the_preview(): void
    {
        [$goal, $milestone] = $this->brokenDownGoal();

        foreach ([$this->entity, $this->sisterEntity] as $entity) {
            $package = $this->packageOf($milestone, $entity);
            $director = $entity->id === $this->entity->id ? $this->director : $this->sisterDirector;

            $this->addTask($director, $package, 'مهمّة '.$entity->name_ar);
            $this->actingAs($director)->post(route('volunteer.goals.build.submit', $package))->assertRedirect();
        }

        $this->actingAs($this->trackSupervisor)
            ->post(route('volunteer.goals.build.preview', $goal))
            ->assertRedirect();

        $goal->refresh();
        $this->assertSame('top', $goal->edit_holder);
        $this->assertSame('preview', $goal->build_stage);

        // ⛔ المحاولة بعد الرفع تُرفَض على الخادم — لا بإخفاء زرّ
        $this->actingAs($this->trackSupervisor)->postJson(route('volunteer.goals.build.field', $goal), [
            'subject' => 'milestone', 'id' => $milestone->id, 'field' => 'name', 'value' => 'محاولة بعد القفل',
        ])->assertForbidden();

        $this->assertNotSame('محاولة بعد القفل', $milestone->fresh()->name);

        // والحيازة صارت للقمّة فعلًا
        $this->actingAs($this->top)->postJson(route('volunteer.goals.build.field', $goal), [
            'subject' => 'milestone', 'id' => $milestone->id, 'field' => 'name', 'value' => 'تعديل القمّة',
        ])->assertOk();

        $this->assertSame('تعديل القمّة', $milestone->fresh()->name);
    }

    /** «رفع معاينة» لا يُقبَل وفي حزمٍ لم يرفعها دايركتورها بعد */
    public function test_the_preview_cannot_be_raised_while_a_package_is_still_being_filled(): void
    {
        [$goal, $milestone] = $this->brokenDownGoal();
        $this->addTask($this->director, $this->packageOf($milestone, $this->entity), 'مهمّة');

        $this->actingAs($this->trackSupervisor)
            ->post(route('volunteer.goals.build.preview', $goal))
            ->assertStatus(409);

        $this->assertSame('track', $goal->fresh()->edit_holder);
    }

    /** الحذف في المعاينة للقمّة وحدها — ورسالته «لا» فيها أوضح وأكبر من «نعم» */
    public function test_deletion_is_confirmed_with_a_louder_no_than_yes(): void
    {
        [$goal, $milestone] = $this->brokenDownGoal();

        // مشرف المسار لا يرى زرّ الحذف أصلًا (مخفيّ لا معطَّل)
        $this->actingAs($this->trackSupervisor)->get(route('volunteer.goals.build.aggregate', $goal))
            ->assertOk()->assertDontSee('حذف المَعلَم');

        // والقمّة — بعد أن تصير الحائزة — ترى التأكيد بزرّ «لا» الأكبر
        $goal->forceFill(['edit_holder' => 'top'])->save();

        $this->actingAs($this->top)->get(route('volunteer.goals.build.aggregate', $goal))
            ->assertOk()
            ->assertSee('هل أنت متأكّد من الحذف؟')
            ->assertSee('لا، رجّعني');

        $this->actingAs($this->top)
            ->delete(route('volunteer.goals.build.milestones.destroy', $milestone))
            ->assertRedirect();

        $this->assertSame(0, Milestone::query()->where('id', $milestone->id)->count());
    }

    /** الرحلة تصبّ في الشاشة القائمة: بعد اكتمالها يقبل «إرسال للتنفيذ» الضغط */
    public function test_the_journey_feeds_the_existing_launch_screen(): void
    {
        [$goal, $milestone] = $this->brokenDownGoal();

        foreach ([[$this->entity, $this->director], [$this->sisterEntity, $this->sisterDirector]] as [$entity, $director]) {
            $this->addTask($director, $this->packageOf($milestone, $entity), 'مهمّة '.$entity->name_ar);
        }

        $this->actingAs($this->top)->post(route('volunteer.goals.launch.send', $goal))->assertRedirect();

        $this->assertNotNull($goal->fresh()->sent_to_execution_at);
        $this->assertSame(0, DB::table('goal_field_revisions')->where('goal_id', $goal->id)->count());
    }
}
