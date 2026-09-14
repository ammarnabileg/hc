<?php

namespace Tests\Feature\Volunteer\People;

use App\Models\AuditLog;
use App\Models\CandidateEntityFit;
use App\Models\Permission;
use App\Models\RecruitmentCandidate;
use App\Models\Role;
use App\Models\User;
use App\Services\Volunteer\People\RecruitmentFunnel;
use App\Support\Access\AccessEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * قمع التطوّع (24.4 — تحليلات التطوّع · `recruitment_analytics.*`):
 * بدأ ⟵ أتمّ ⟵ مقابلة ⟵ مقبول ⟵ مُسكَّن + متوسّط زمن كلّ مرحلة.
 *
 * كانت `recruitment_analytics` صلاحيّةً ميّتة: لا مسار ولا شاشة تشير إليها —
 * هذا الاختبار يثبت الحساب من بيانات مزروعة بأزمنة حقيقيّة، لا مجرّد تحميل الصفحة.
 */
class RecruitmentFunnelTest extends PeopleTestCase
{
    private function candidate(array $attributes = []): RecruitmentCandidate
    {
        return RecruitmentCandidate::create(array_merge([
            'user_id' => $this->makeUser('مرشّح')->id,
            'stage' => 'applied',
            'applied_at' => now()->subDays(30),
        ], $attributes));
    }

    /** ينقل مرشّحًا بين مرحلتين ويكتب سجلّ `audit_logs` — بالوقت المحدَّد بدقّة (لا `now()`) */
    private function move(RecruitmentCandidate $candidate, string $from, string $to, Carbon $at): void
    {
        $candidate->forceFill(['stage' => $to, 'stage_changed_at' => $at])->save();

        $log = AuditLog::create([
            'user_id' => null,
            'action' => 'candidate.stage_moved',
            'auditable_type' => $candidate->getMorphClass(),
            'auditable_id' => $candidate->id,
            'old_values' => ['stage' => $from],
            'new_values' => ['stage' => $to, 'reason' => 'اختبار'],
        ]);

        // الطابع الزمنيّ الحقيقيّ للنقلة — `created_at` هو مصدر حساب المدّة
        $log->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }

    // ------------------------------------------------------------ الأعداد
    //
    // ⭐ `PeopleTestCase` يزرع `VolunteerPeopleDemoSeeder` (4 مرشّحين تجريبيّين)
    // فكلّ اختبار عدّ هنا يقيس **الفرق قبل/بعد** لا رقمًا مطلقًا — كي لا يرتبط
    // بعدد بيانات العرض التجريبيّة الذي قد يتغيّر لاحقًا.

    private function counts(RecruitmentFunnel $funnel, $viewer): Collection
    {
        return collect($funnel->compute($viewer)['stages'])->pluck('count', 'key');
    }

    public function test_funnel_counts_how_many_candidates_ever_reached_each_stage(): void
    {
        $viewer = $this->userWith(['recruitment_analytics.view']);
        $funnel = app(RecruitmentFunnel::class);
        $before = $this->counts($funnel, $viewer);

        // 3 قدّموا، 2 أتمّوا الفرز، 1 وصل مقابلة — ولا أحد بعدها
        $a = $this->candidate();
        $b = $this->candidate();
        $c = $this->candidate();

        $this->move($a, 'applied', 'screening', now()->subDays(20));
        $this->move($b, 'applied', 'screening', now()->subDays(15));
        $this->move($a, 'screening', 'interview', now()->subDays(10));

        $after = $this->counts($funnel, $viewer);

        $this->assertSame($before['applied'] + 3, $after['applied']);
        $this->assertSame($before['screening'] + 2, $after['screening']);
        $this->assertSame($before['interview'] + 1, $after['interview']);
        $this->assertSame($before['final_list'], $after['final_list']);
        $this->assertSame($before['placed'], $after['placed']);
    }

    public function test_a_candidate_moved_to_rejected_still_counts_toward_stages_already_reached(): void
    {
        $viewer = $this->userWith(['recruitment_analytics.view']);
        $funnel = app(RecruitmentFunnel::class);
        $before = $this->counts($funnel, $viewer);

        $candidate = $this->candidate();
        $this->move($candidate, 'applied', 'screening', now()->subDays(10));
        $this->move($candidate, 'screening', 'rejected', now()->subDays(5));

        $after = $this->counts($funnel, $viewer);

        $this->assertSame($before['applied'] + 1, $after['applied']);
        $this->assertSame($before['screening'] + 1, $after['screening']);
        $this->assertSame($before['interview'], $after['interview'], 'رُفض قبل المقابلة فلم يبلغها.');
    }

    public function test_a_stage_skipped_entirely_is_not_falsely_marked_reached(): void
    {
        $viewer = $this->userWith(['recruitment_analytics.view']);
        $funnel = app(RecruitmentFunnel::class);
        $before = $this->counts($funnel, $viewer);

        // مرشّح انتقل مباشرةً من «تقديم» إلى «مقابلة» بلا مرور بـ«فرز»
        $candidate = $this->candidate();
        $this->move($candidate, 'applied', 'interview', now()->subDays(5));

        $after = $this->counts($funnel, $viewer);

        $this->assertSame($before['applied'] + 1, $after['applied']);
        $this->assertSame($before['screening'], $after['screening'], 'لم يمرّ بمرحلة الفرز فعلًا.');
        $this->assertSame($before['interview'] + 1, $after['interview']);
    }

    // ------------------------------------------------------------ متوسّط الزمن

    public function test_average_time_in_stage_is_computed_from_real_transition_timestamps(): void
    {
        $viewer = $this->userWith(['recruitment_analytics.view']);

        // مرشّح أ: 10 أيّام في «تقديم» قبل الفرز
        $a = $this->candidate(['applied_at' => now()->subDays(30)]);
        $this->move($a, 'applied', 'screening', now()->subDays(20));

        // مرشّح ب: 20 يومًا في «تقديم» قبل الفرز — فالمتوسّط 15 يومًا
        $b = $this->candidate(['applied_at' => now()->subDays(30)]);
        $this->move($b, 'applied', 'screening', now()->subDays(10));

        $funnel = app(RecruitmentFunnel::class)->compute($viewer);
        $durations = collect($funnel['durations'])->keyBy('from_key');

        $applied = $durations['applied'];
        $this->assertSame(2, $applied['sample']);
        $this->assertEqualsWithDelta(15.0, $applied['avg_days'], 0.1);
        $this->assertEqualsWithDelta(15.0 * 24, $applied['avg_hours'], 2.5);

        // «فرز» ليس له عيّنة بعد (لا أحد أكمل النقلة منه)
        $this->assertNull($durations['screening']['avg_days']);
        $this->assertSame(0, $durations['screening']['sample']);
    }

    public function test_a_candidate_still_waiting_in_a_stage_is_excluded_from_its_average(): void
    {
        $viewer = $this->userWith(['recruitment_analytics.view']);

        $moved = $this->candidate(['applied_at' => now()->subDays(30)]);
        $this->move($moved, 'applied', 'screening', now()->subDays(20)); // 10 أيّام محسوبة

        $stillWaiting = $this->candidate(['applied_at' => now()->subDays(30)]); // لم يُنقَل بعد

        $funnel = app(RecruitmentFunnel::class)->compute($viewer);
        $durations = collect($funnel['durations'])->keyBy('from_key');

        $this->assertSame(1, $durations['applied']['sample'], 'المنتظِر بلا نقلة لا يدخل المتوسّط — مدّته لم تنتهِ بعد.');
        $this->assertEqualsWithDelta(10.0, $durations['applied']['avg_days'], 0.1);
    }

    public function test_a_backward_correction_does_not_corrupt_the_next_valid_transition(): void
    {
        $viewer = $this->userWith(['recruitment_analytics.view']);

        $candidate = $this->candidate(['applied_at' => now()->subDays(30)]);
        $this->move($candidate, 'applied', 'screening', now()->subDays(20));
        $this->move($candidate, 'screening', 'interview', now()->subDays(15));
        $this->move($candidate, 'interview', 'screening', now()->subDays(14)); // تصحيح للخلف
        $this->move($candidate, 'screening', 'interview', now()->subDays(9)); // 5 أيّام في الفرز بعد التصحيح

        $funnel = app(RecruitmentFunnel::class)->compute($viewer);
        $durations = collect($funnel['durations'])->keyBy('from_key');

        // applied⟵screening: 10 أيّام — وscreening⟵interview: النقلة الصحيحة الأولى (5 أيّام) فقط تُحتسَب مرّتين (15⟵20 و9⟵14)
        $this->assertEqualsWithDelta(10.0, $durations['applied']['avg_days'], 0.1);
        $this->assertSame(2, $durations['screening']['sample']);
    }

    // ------------------------------------------------------------ النطاق (12.2.1-ب)

    public function test_entity_scope_hides_candidates_outside_the_viewers_scope(): void
    {
        $myEntity = $this->makeEntity('قسمي');
        $otherEntity = $this->makeEntity('قسم آخر');

        $viewer = $this->makeUser('محلّل نطاق');
        $this->makeMembership($viewer, $myEntity);
        $this->grantEntityScoped($viewer, 'recruitment_analytics.view', 'ENTITY');

        $mine = $this->candidate();
        CandidateEntityFit::create(['recruitment_candidate_id' => $mine->id, 'entity_id' => $myEntity->id]);

        $foreign = $this->candidate();
        CandidateEntityFit::create(['recruitment_candidate_id' => $foreign->id, 'entity_id' => $otherEntity->id]);

        $funnel = app(RecruitmentFunnel::class)->compute($viewer);

        $this->assertSame(1, $funnel['total']);
    }

    /** يمنح صلاحيّة بنطاقٍ محدَّد (لا ALL) — لاختبار حصر النطاق فعليًّا */
    private function grantEntityScoped(User $user, string $key, string $scope): void
    {
        $role = Role::create(['key' => 'r_'.str()->random(8), 'name_ar' => 'دور نطاق', 'layer' => 'volunteer']);

        $permission = Permission::firstOrCreate(['key' => $key], [
            'resource' => explode('.', $key)[0],
            'action' => explode('.', $key)[1] ?? 'view',
            'group' => 'اختبار',
            'label_ar' => $key,
            'allowed_scopes' => ['SELF', 'TEAM', 'SUBTREE', 'ENTITY', 'TRACK', 'ALL'],
        ]);

        DB::table('permission_role')->insert([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'scope' => $scope,
            'effect' => 'allow',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user->roles()->attach($role->id, ['assigned_at' => now()]);
        app(AccessEngine::class)->forget($user);
    }
}
