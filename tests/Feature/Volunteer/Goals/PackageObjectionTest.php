<?php

namespace Tests\Feature\Volunteer\Goals;

use App\Models\Entity;
use App\Models\Escalation;
use App\Models\Setting;
use App\Models\User;
use App\Services\Volunteer\Escalation\CaseCatalog;
use App\Services\Volunteer\Escalation\EscalationEngine;
use App\Services\Volunteer\Goals\GoalBuildService;
use App\Services\Volunteer\Tasks\TaskStatus;
use Database\Seeders\SettingSeeder;
use Database\Seeders\VolunteerGoalsDemoSeeder;
use Illuminate\Support\Facades\Cache;

/**
 * ⭐ اعتراض دايركتور الكيان على نسخة اعتماد حزمته (الدستور 23 — 1.6): فرق النسخة
 * + زرّ اعتراض واحد خلال 24 ساعة على محرّك التصعيد — وسكوته قبول.
 *
 * الشاشة والكنترولر (`versionDiff` · `object()` · `objectionOpen()`) كانا
 * مبنيَّين كاملين من قبل، ولا شيء يختم `approved_snapshot`/`objection_due_at`:
 * فنافذة الاعتراض تُقفَل قبل أن تُفتَح، والفرق يعرض «مفيش فروق» دائمًا، وضغطة
 * الاعتراض تكتب علَمًا لا يقرؤه أحد رغم أنّها تقول «هيتصعّد للطبقة الأعلى».
 */
class PackageObjectionTest extends GoalsTestCase
{
    private Entity $entity;

    private User $top;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
        (new VolunteerGoalsDemoSeeder)->settings();

        Cache::forget('settings');
        Cache::forget('rep_rules');

        $this->entity = $this->makeEntity();

        $this->top = $this->makeUser('مشرف عام التطوّع');
        $topMembership = $this->makeMembership($this->top, $this->entity, null, 'volunteer_gm');
        $this->grant($this->top, 'goals.approve', 'ALL', $topMembership);

        $this->director = $this->makeUser('دايركتور');
        $directorMembership = $this->makeMembership($this->director, $this->entity, $topMembership, 'director');
        $this->grant($this->director, 'wp_items.edit', 'ALL', $directorMembership);
        $this->grant($this->director, 'work_packages.view', 'ALL', $directorMembership);
    }

    /** شجرة جاهزة للإطلاق، وتُطلَق فعلًا فتُختَم لقطة الاعتماد ونافذة الاعتراض */
    private function launchedPackage(): array
    {
        $tree = $this->makeTree($this->entity, 'draft');
        $task = $this->makeTask($tree['item'], TaskStatus::IN_PROGRESS, $this->top);
        $task->forceFill(['deadline_at' => now()->addDays(20)])->save();

        $this->actingAs($this->top)->post(route('volunteer.goals.launch.send', $tree['goal']));

        return $tree + ['task' => $task, 'package' => $tree['package']->refresh()];
    }

    // ------------------------------------------------------------------ التفعيل عند الإطلاق

    public function test_launch_stamps_the_approved_snapshot_and_a_24_hour_objection_window(): void
    {
        $tree = $this->launchedPackage();

        $this->assertNotNull($tree['package']->approved_snapshot, 'بلا لقطةٍ يبقى الفرق «مفيش فروق» دائمًا.');
        $this->assertNotNull($tree['package']->objection_due_at, 'بلا موعدٍ تبقى نافذة الاعتراض مقفولةً للأبد.');
        $this->assertTrue($tree['package']->objection_due_at->isFuture());
        $this->assertEqualsWithDelta(
            (int) setting('goals.objection.window_hours', 24),
            now()->diffInHours($tree['package']->objection_due_at),
            1,
        );
    }

    /** نافذة الاعتراض إعدادها الخاصّ — لا نافذة التفكيك رغم أنّ افتراضهما واحد */
    public function test_the_objection_window_uses_its_own_setting_not_the_breakdown_window(): void
    {
        Setting::query()->where('key', 'goals.objection.window_hours')->update(['value' => '5']);
        Cache::forget('settings');

        $tree = $this->launchedPackage();

        $this->assertEqualsWithDelta(5, now()->diffInHours($tree['package']->objection_due_at), 1, 'نافذة الاعتراض قرأت إعدادها الخاصّ.');
        $this->assertEqualsWithDelta(24, now()->diffInHours($tree['task']->refresh()->breakdown_due_at), 1, 'نافذة التفكيك لم تتأثّر — إعدادان منفصلان لا إعدادٌ واحد.');
    }

    // ------------------------------------------------------------------ الاعتراض نفسه

    public function test_a_director_can_object_within_the_window_and_it_opens_on_the_escalation_engine(): void
    {
        $tree = $this->launchedPackage();

        $this->actingAs($this->director)
            ->post(route('volunteer.packages.object', $tree['package']), ['note' => 'التسعير مش زي ما رفعته أنا خالص.'])
            ->assertRedirect();

        $package = $tree['package']->refresh();
        $this->assertSame('raised', $package->objection_status);

        $escalation = Escalation::query()
            ->where('case_type', CaseCatalog::PACKAGE_OBJECTION)
            ->where('subject_id', $package->id)
            ->first();

        $this->assertNotNull($escalation, 'الاعتراض لازم يفتح صفًّا حقيقيًّا على محرّك التصعيد — لا علَمًا صامتًا.');
        $this->assertSame('open', $escalation->status);
        $this->assertSame($this->director->id, $escalation->requested_by);
        $this->assertSame($this->top->id, $escalation->current_handler_id, 'يصعد لمن فوق الدايركتور في سلسلة كيانه.');
    }

    public function test_objecting_twice_on_the_same_package_is_refused(): void
    {
        $tree = $this->launchedPackage();

        $this->actingAs($this->director)->post(route('volunteer.packages.object', $tree['package']), ['note' => 'اعتراض أوّل يكفي عشرة حروف.']);

        $this->actingAs($this->director)
            ->post(route('volunteer.packages.object', $tree['package']), ['note' => 'اعتراض تاني يكفي عشرة حروف.'])
            ->assertStatus(409);

        $this->assertSame(1, Escalation::query()->where('case_type', CaseCatalog::PACKAGE_OBJECTION)->count());
    }

    public function test_silence_after_the_window_closes_the_door_to_a_new_objection(): void
    {
        $tree = $this->launchedPackage();

        $this->travelTo(now()->addHours((int) setting('goals.objection.window_hours', 24))->addMinute());

        $this->actingAs($this->director)
            ->post(route('volunteer.packages.object', $tree['package']), ['note' => 'اعتراض بعد فوات المهلة يكفي عشرة حروف.'])
            ->assertStatus(409);

        $this->assertNull($tree['package']->refresh()->objection_status, 'السكوت قبول — ولا صفّ اعتراضٍ يُفتَح.');
    }

    // ------------------------------------------------------------------ الحسم على محرّك التصعيد

    public function test_upholding_the_objection_flags_the_package_for_revision(): void
    {
        $tree = $this->launchedPackage();

        $this->actingAs($this->director)->post(route('volunteer.packages.object', $tree['package']), ['note' => 'اعتراض حقيقيّ يكفي عشرة حروف.']);

        $escalation = Escalation::query()->where('case_type', CaseCatalog::PACKAGE_OBJECTION)->firstOrFail();

        app(EscalationEngine::class)->decide($escalation, $this->top, 'upheld', 'موافَق — هراجعها');

        $this->assertSame('upheld', $tree['package']->refresh()->objection_status);
    }

    public function test_rejecting_the_objection_keeps_the_approval_standing(): void
    {
        $tree = $this->launchedPackage();

        $this->actingAs($this->director)->post(route('volunteer.packages.object', $tree['package']), ['note' => 'اعتراض حقيقيّ يكفي عشرة حروف.']);

        $escalation = Escalation::query()->where('case_type', CaseCatalog::PACKAGE_OBJECTION)->firstOrFail();

        app(EscalationEngine::class)->decide($escalation, $this->top, 'rejected', 'الاعتماد سليم زي ما هو.');

        $this->assertSame('rejected', $tree['package']->refresh()->objection_status);
    }

    /** فوات نافذة القرار على محرّك التصعيد نفسه ⟵ رفض آليّ — الاعتماد يبقى قائمًا */
    public function test_missing_the_handlers_own_decision_window_auto_settles_as_rejected(): void
    {
        $tree = $this->launchedPackage();

        $this->actingAs($this->director)->post(route('volunteer.packages.object', $tree['package']), ['note' => 'اعتراض حقيقيّ يكفي عشرة حروف.']);

        $escalation = Escalation::query()->where('case_type', CaseCatalog::PACKAGE_OBJECTION)->firstOrFail();

        app(EscalationEngine::class)->autoSettle($escalation);

        $this->assertSame('rejected', $tree['package']->refresh()->objection_status);
        $this->assertSame('auto_settled', $escalation->refresh()->status);
    }

    // ------------------------------------------------------------------ فرق النسخة

    public function test_the_version_diff_shows_what_was_submitted_versus_what_was_approved(): void
    {
        $tree = $this->makeTree($this->entity, 'draft');
        $task = $this->makeTask($tree['item'], TaskStatus::IN_PROGRESS, $this->top);
        $task->forceFill(['deadline_at' => now()->addDays(20)])->save();

        // 1.3 — الدايركتور يرفع الحزمة للمراجعة باسمها الأصليّ
        app(GoalBuildService::class)->submitPackage($tree['package'], $this->director);

        // 1.4 — القمّة تعدّل الاسم مباشرةً قبل الاعتماد
        $tree['package']->forceFill(['name' => 'حزمة الاختبار بعد التعديل'])->save();

        $this->actingAs($this->top)->post(route('volunteer.goals.launch.send', $tree['goal']));

        $this->actingAs($this->director)
            ->get(route('volunteer.packages.show', $tree['package']->refresh()))
            ->assertOk()
            ->assertSee('حزمة الاختبار')
            ->assertSee('حزمة الاختبار بعد التعديل');
    }
}
