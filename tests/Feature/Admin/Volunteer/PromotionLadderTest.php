<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Currency;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\Offboarding;
use App\Models\Position;
use App\Models\PromotionDecision;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\Volunteer\OffboardingService;
use App\Services\Volunteer\Org\PromotionLadder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * سلّم الترقية الفوريّ (القسم 0 · 23-0.2): «لا فترة شغور أصلًا» — الفائز من
 * الداونلاين المباشر يُصعَّد فورًا بشلّال ستّة معايير، والدايركتور «قائم
 * بأعمال» حتى الاعتماد، وتعادلٌ كاملٌ يُحسَم بشريًّا.
 */
class PromotionLadderTest extends AdminVolunteerTestCase
{
    private function entity(): Entity
    {
        return Entity::query()->where('name_ar', 'فريق المونتاج')->firstOrFail();
    }

    private function position(string $key): Position
    {
        return Position::query()->where('key', $key)->firstOrFail();
    }

    private function membership(User $user, string $positionKey, ?Membership $upline = null, int $daysAgo = 400): Membership
    {
        return Membership::create([
            'user_id' => $user->id,
            'entity_id' => $this->entity()->id,
            'position_id' => $this->position($positionKey)->id,
            'upline_id' => $upline?->id,
            'is_primary' => true,
            'started_at' => now()->subDays($daysAgo),
            'status' => 'active',
        ]);
    }

    private function rep(User $user, float $amount, int $daysAgo): void
    {
        Transaction::create([
            'user_id' => $user->id,
            'currency_id' => Currency::where('code', 'rep')->value('id'),
            'amount' => $amount,
            'applied_amount' => $amount,
            'layer' => 'volunteer',
            'source' => 'behavior',
            'created_at' => now()->subDays($daysAgo),
        ]);
    }

    private function endMembership(Membership $membership): Membership
    {
        $membership->forceFill(['status' => 'ended', 'ended_at' => now(), 'end_reason' => 'test'])->save();

        return $membership->fresh();
    }

    // ------------------------------------------------------------ الربط الحقيقيّ بالأوفبوردنج

    /** ⭐ استقالة دايركتور حقيقيّة عبر OffboardingService::complete تُصعّد السوبرفايزر الأقوى فورًا */
    public function test_offboarding_completion_fills_the_vacancy_through_the_real_hook(): void
    {
        Setting::updateOrCreate(['key' => 'volunteer.offboarding.clearance_items'], [
            'group' => 'offboarding', 'label_ar' => 'x', 'type' => 'json',
            'default_value' => json_encode(['نقل المهامّ'], JSON_UNESCAPED_UNICODE),
            'value' => json_encode(['نقل المهامّ'], JSON_UNESCAPED_UNICODE),
        ]);
        Cache::forget('settings');

        $gmAbove = $this->membership($this->makeUser('مشرف عام فوق'), 'volunteer_gm');
        $director = $this->makeUser('دايركتور مستقيل');
        $directorM = $this->membership($director, 'director', $gmAbove);
        $successor = $this->makeUser('سوبرفايزر خليفة');
        $this->membership($successor, 'supervisor', $directorM);
        $this->rep($successor, 5, 5);

        $actor = $this->makeUser('مشرف يعتمد الخروج');

        $record = Offboarding::create([
            'user_id' => $director->id,
            'type' => 'resignation',
            'initiated_by' => $actor->id,
            'notice_until' => now(),
            'clearance_checklist' => [['label' => 'نقل المهامّ', 'done' => true]],
        ]);

        OffboardingService::complete($record->fresh(), $actor);

        $this->assertSame('ended', $directorM->fresh()->status);

        $newDirectorMembership = Membership::query()
            ->where('user_id', $successor->id)
            ->where('status', 'active')
            ->whereHas('position', fn ($q) => $q->where('key', 'director'))
            ->first();

        $this->assertNotNull($newDirectorMembership, 'سلّم الترقية يملأ شغور الدايركتور فور اكتمال الأوفبوردنج.');
        $this->assertTrue($newDirectorMembership->is_acting);
    }

    // ------------------------------------------------------------ الترقية الفوريّة

    /** ⭐ Rep المكتسَب خلال 90 يومًا من السجلّ الخام يحسم — والسوبرفايزر يُثبَّت فورًا بلا اعتماد */
    public function test_higher_90d_rep_wins_and_supervisor_level_needs_no_approval(): void
    {
        $directorAbove = $this->membership($this->makeUser('دايركتور فوق'), 'director');
        $supervisorVacancy = $this->membership($this->makeUser('سوبرفايزر شاغر'), 'supervisor', $directorAbove);
        $weak = $this->makeUser('تيم ليدر ضعيف');
        $strong = $this->makeUser('تيم ليدر قويّ');

        $weakM = $this->membership($weak, 'team_leader', $supervisorVacancy);
        $strongM = $this->membership($strong, 'team_leader', $supervisorVacancy);

        $this->rep($weak, 2, 10);
        $this->rep($strong, 8, 10);

        $vacated = $this->endMembership($supervisorVacancy);
        $result = app(PromotionLadder::class)->fillVacancy($vacated);

        $this->assertSame('promoted', $result['outcome']);
        $this->assertSame($strong->id, $result['user_id']);

        $newMembership = Membership::find($result['membership_id']);
        $this->assertSame('supervisor', $newMembership->position->key);
        $this->assertFalse($newMembership->is_acting);
        $this->assertSame('active', $newMembership->status);

        // خسر لم يُمَسّ
        $this->assertSame('active', $weakM->fresh()->status);

        // بوزشن الفائز القديم انتهى وسُحِب دوره
        $this->assertSame('ended', $strongM->fresh()->status);
        $this->assertFalse(DB::table('role_user')->where('membership_id', $strongM->id)->exists());

        // ودور الدايركتور مُنِح للعضويّة الجديدة
        $this->assertTrue(DB::table('role_user')->where('membership_id', $newMembership->id)->where('user_id', $strong->id)->exists());
    }

    /** ⭐ بوزشن الدايركتور: الفائز يُعيَّن «قائم بأعمال» فورًا — بلا فترة شغور، وبانتظار الاعتماد */
    public function test_director_vacancy_appoints_the_winner_as_acting(): void
    {
        $gmAbove = $this->membership($this->makeUser('مشرف عام فوق'), 'volunteer_gm');
        $directorVacancy = $this->membership($this->makeUser('دايركتور شاغر'), 'director', $gmAbove);
        $candidate = $this->makeUser('سوبرفايزر مرشّح');
        $candidateM = $this->membership($candidate, 'supervisor', $directorVacancy);
        $this->rep($candidate, 5, 5);

        $vacated = $this->endMembership($directorVacancy);
        $result = app(PromotionLadder::class)->fillVacancy($vacated);

        $this->assertSame('acting', $result['outcome']);

        $newMembership = Membership::find($result['membership_id']);
        $this->assertTrue($newMembership->is_acting);
        $this->assertSame('director', $newMembership->position->key);
    }

    /** ⭐ الشغور الثانويّ الناتج عن الترقية نفسها يُملأ تكراريًّا فورًا («لا فترة شغور») */
    public function test_the_secondary_vacancy_from_promotion_cascades_automatically(): void
    {
        $director = $this->membership($this->makeUser('دايركتور'), 'director');
        $supervisor = $this->makeUser('سوبرفايزر يترقّى');
        $supervisorM = $this->membership($supervisor, 'supervisor', $director);

        $teamLeader = $this->makeUser('تيم ليدر تحته');
        $teamLeaderM = $this->membership($teamLeader, 'team_leader', $supervisorM);

        $vacated = $this->endMembership($director);
        app(PromotionLadder::class)->fillVacancy($vacated);

        // السوبرفايزر القديم صار شاغرًا — والتيم ليدر تحته تُرُقِّي ليملأه تلقائيًّا
        $this->assertSame('ended', $supervisorM->fresh()->status);

        $newSupervisorMembership = Membership::query()
            ->where('user_id', $teamLeader->id)
            ->where('status', 'active')
            ->whereHas('position', fn ($q) => $q->where('key', 'supervisor'))
            ->first();

        $this->assertNotNull($newSupervisorMembership, 'التيم ليدر يترقّى تلقائيًّا ليملأ شغور السوبرفايزر الثانويّ.');
        $this->assertSame('ended', $teamLeaderM->fresh()->status);
    }

    /** بقيّة الداونلاين المباشر تتبع الفائز في بوزشنه الجديد فورًا */
    public function test_remaining_direct_downline_is_reattached_to_the_winners_new_membership(): void
    {
        $director = $this->membership($this->makeUser('دايركتور'), 'director');
        $winner = $this->makeUser('الفائز');
        $loser = $this->makeUser('الخاسر');

        $winnerM = $this->membership($winner, 'supervisor', $director);
        $loserM = $this->membership($loser, 'supervisor', $director);
        $this->rep($winner, 9, 5);
        $this->rep($loser, 1, 5);

        $vacated = $this->endMembership($director);
        $result = app(PromotionLadder::class)->fillVacancy($vacated);

        $this->assertSame($loserM->fresh()->upline_id, $result['membership_id']);
    }

    /** ⭐ مشرف عام المسار مستثنًى صراحةً — لا ترقية آليّة ولا قرار تلقائيّ */
    /** ⭐ شغور مشرف عام المسار: لا ترقية آليّة، بل تصعيدٌ مؤقّت لمشرف عام التطوّع */
    public function test_track_supervisor_vacancy_escalates_to_the_gm_instead_of_auto_promoting(): void
    {
        $gm = $this->membership($this->makeUser('مشرف عام'), 'volunteer_gm');
        $trackSupervisor = $this->membership($this->makeUser('مشرف مسار'), 'track_supervisor', $gm);
        $director = $this->makeUser('دايركتور تحت المسار');
        $directorM = $this->membership($director, 'director', $trackSupervisor);

        $vacated = $this->endMembership($trackSupervisor);
        $result = app(PromotionLadder::class)->fillVacancy($vacated);

        $this->assertSame('track_escalated', $result['outcome']);
        $this->assertSame(1, PromotionDecision::query()->where('kind', 'track_vacancy')->count());

        // الدايركتور لم يترقَّ آليًّا — لكنّه صار يتبع مشرف عام التطوّع مؤقّتًا
        $this->assertSame('active', $directorM->fresh()->status);
        $this->assertSame($gm->id, $directorM->fresh()->upline_id);
    }

    /** حسم شغور المسار بمرشّح من معاينة السلّم — يترقّى، وشغوره القديم يُملأ تكراريًّا */
    public function test_resolving_a_track_vacancy_with_a_ladder_candidate_promotes_them_and_cascades(): void
    {
        $gmUser = $this->makeUser('مشرف عام');
        $gm = $this->membership($gmUser, 'volunteer_gm');
        $trackSupervisor = $this->membership($this->makeUser('مشرف مسار'), 'track_supervisor', $gm);
        $director = $this->makeUser('دايركتور مرشّح');
        $directorM = $this->membership($director, 'director', $trackSupervisor);
        $teamLeader = $this->makeUser('تيم ليدر تحت الدايركتور');
        $teamLeaderM = $this->membership($teamLeader, 'team_leader', $directorM);

        $ladder = app(PromotionLadder::class);
        $ladder->fillVacancy($this->endMembership($trackSupervisor));
        $decision = PromotionDecision::where('kind', 'track_vacancy')->firstOrFail();

        $result = $ladder->resolveTrackVacancy($decision, $director, $gmUser, 'أقدم دايركتورات المسار وأعلاهم Rep.');

        $this->assertSame('promoted', $result['outcome']);
        $this->assertTrue(Membership::where('user_id', $director->id)->where('status', 'active')
            ->whereHas('position', fn ($q) => $q->where('key', 'track_supervisor'))->exists());

        // بوزشن الدايركتور القديم شغَرَ وامتلأ تكراريًّا بالتيم ليدر تحته
        $this->assertSame('ended', $directorM->fresh()->status);
        $this->assertTrue(Membership::where('user_id', $teamLeader->id)->where('status', 'active')
            ->whereHas('position', fn ($q) => $q->where('key', 'director'))->exists());
    }

    /** حسم شغور المسار بكودٍ مباشر — شخصٌ من خارج قائمة المرشّحين تمامًا */
    public function test_resolving_a_track_vacancy_with_a_direct_code_pick_works_even_outside_the_candidates(): void
    {
        $gmUser = $this->makeUser('مشرف عام');
        $gm = $this->membership($gmUser, 'volunteer_gm');
        $trackSupervisor = $this->membership($this->makeUser('مشرف مسار'), 'track_supervisor', $gm);
        $this->membership($this->makeUser('دايركتور تحت المسار'), 'director', $trackSupervisor);

        $outsider = $this->makeUser('مُصعَّد بالكود مباشرةً');

        $ladder = app(PromotionLadder::class);
        $ladder->fillVacancy($this->endMembership($trackSupervisor));
        $decision = PromotionDecision::where('kind', 'track_vacancy')->firstOrFail();

        $result = $ladder->resolveTrackVacancy($decision, $outsider, $gmUser, 'تصعيدٌ مباشر بقرار مشرف عام التطوّع.');

        $this->assertSame('promoted', $result['outcome']);
        $this->assertSame($outsider->id, $result['user_id']);
    }

    /** حسم شغور المسار عبر الواجهة الإداريّة بالكود — HTTP كاملة */
    public function test_admin_can_resolve_a_track_vacancy_via_the_http_action(): void
    {
        $gmUser = $this->grant($this->makeUser('مشرف عام'), 'promotion_ladder.approve');
        $gm = $this->membership($gmUser, 'volunteer_gm');
        $trackSupervisor = $this->membership($this->makeUser('مشرف مسار'), 'track_supervisor', $gm);
        $this->membership($this->makeUser('دايركتور تحت المسار'), 'director', $trackSupervisor);

        app(PromotionLadder::class)->fillVacancy($this->endMembership($trackSupervisor));
        $decision = PromotionDecision::where('kind', 'track_vacancy')->firstOrFail();

        $outsider = $this->makeUser('مُصعَّد بالكود عبر الواجهة');

        $this->actingAs($gmUser)
            ->post(route('admin.volunteer.org.promotion-ladder.resolve-track', $decision), [
                'winner_code' => $outsider->code,
                'reason' => 'كفاءته مثبَتة في مسارٍ مشابه سابقًا.',
            ])
            ->assertRedirect();

        $this->assertSame('decided', $decision->fresh()->status);
        $this->assertTrue(Membership::where('user_id', $outsider->id)->where('status', 'active')
            ->whereHas('position', fn ($q) => $q->where('key', 'track_supervisor'))->exists());
    }

    /** حسم شغور المسار لا يعمل عبر مسار حسم التعادل، والعكس — الأنواع منفصلة */
    public function test_decide_tie_and_resolve_track_routes_reject_the_wrong_decision_kind(): void
    {
        $director = $this->membership($this->makeUser('دايركتور'), 'director');
        $a = $this->makeUser('متعادل أ');
        $b = $this->makeUser('متعادل ب');
        $this->membership($a, 'supervisor', $director);
        $this->membership($b, 'supervisor', $director);

        app(PromotionLadder::class)->fillVacancy($this->endMembership($director));
        $tieDecision = PromotionDecision::where('kind', 'tie')->firstOrFail();

        $admin = $this->grant($this->makeUser(), 'promotion_ladder.approve');

        $this->actingAs($admin)
            ->post(route('admin.volunteer.org.promotion-ladder.resolve-track', $tieDecision), [
                'winner_code' => $a->code,
                'reason' => 'محاولة استخدام المسار الخطأ.',
            ])
            ->assertNotFound();
    }

    // ------------------------------------------------------------ التعادل الكامل

    /** ⭐ تعادلٌ كاملٌ في كلّ المعايير ⟵ لا ترقية آليّة، بل قرارٌ بشريّ مسجَّل */
    public function test_a_full_tie_across_all_criteria_creates_a_pending_decision_instead_of_auto_promoting(): void
    {
        $director = $this->membership($this->makeUser('دايركتور'), 'director');
        $a = $this->makeUser('متعادل أ');
        $b = $this->makeUser('متعادل ب');
        $this->membership($a, 'supervisor', $director);
        $this->membership($b, 'supervisor', $director);
        // بلا أيّ معاملات لأيّ منهما — تعادلٌ كاملٌ عند الصفر في كلّ معيار

        $vacated = $this->endMembership($director);
        $result = app(PromotionLadder::class)->fillVacancy($vacated);

        $this->assertSame('tie', $result['outcome']);
        $this->assertSame(1, PromotionDecision::query()->where('status', 'awaiting_decision')->count());

        $decision = PromotionDecision::first();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $decision->candidate_user_ids);
    }

    /** حسم التعادل عبر الواجهة الإداريّة — والفائز المختار يترقّى فورًا */
    public function test_admin_can_decide_a_tie_via_the_http_action(): void
    {
        $director = $this->membership($this->makeUser('دايركتور'), 'director');
        $a = $this->makeUser('متعادل أ');
        $b = $this->makeUser('متعادل ب');
        $this->membership($a, 'supervisor', $director);
        $this->membership($b, 'supervisor', $director);

        app(PromotionLadder::class)->fillVacancy($this->endMembership($director));
        $decision = PromotionDecision::firstOrFail();

        $admin = $this->grant($this->makeUser(), 'promotion_ladder.approve');

        $this->actingAs($admin)
            ->post(route('admin.volunteer.org.promotion-ladder.decide', $decision), [
                'winner_user_id' => $a->id,
                'reason' => 'أقدميّته في الفريق أطول بشهادات موثَّقة.',
            ])
            ->assertRedirect();

        $this->assertSame('decided', $decision->fresh()->status);
        $this->assertTrue(Membership::query()->where('user_id', $a->id)->where('status', 'active')
            ->whereHas('position', fn ($q) => $q->where('key', 'director'))->exists());
    }

    // ------------------------------------------------------------ اعتماد/ردّ القائم بأعمال (HTTP)

    public function test_admin_can_confirm_an_acting_director(): void
    {
        $gmAbove = $this->membership($this->makeUser('مشرف عام فوق'), 'volunteer_gm');
        $directorVacancy = $this->membership($this->makeUser('دايركتور شاغر'), 'director', $gmAbove);
        $candidate = $this->makeUser('مرشّح');
        $this->membership($candidate, 'supervisor', $directorVacancy);

        $result = app(PromotionLadder::class)->fillVacancy($this->endMembership($directorVacancy));
        $acting = Membership::findOrFail($result['membership_id']);

        $admin = $this->grant($this->makeUser(), 'vacancies.approve');

        $this->actingAs($admin)
            ->post(route('admin.volunteer.org.promotion-ladder.confirm', $acting))
            ->assertRedirect();

        $this->assertFalse($acting->fresh()->is_acting);
    }

    /** ⭐ ردّ الاعتماد بمبرّر — والسلّم يُعاد حسابه لنفس الشاغر باستثناء المردود */
    public function test_rejecting_an_acting_director_reruns_the_ladder_excluding_the_rejected_candidate(): void
    {
        $gmAbove = $this->membership($this->makeUser('مشرف عام فوق'), 'volunteer_gm');
        $directorVacancy = $this->membership($this->makeUser('دايركتور شاغر'), 'director', $gmAbove);
        $rejected = $this->makeUser('مرشّح مردود');
        $second = $this->makeUser('مرشّح تانٍ');
        $this->membership($rejected, 'supervisor', $directorVacancy);
        $secondM = $this->membership($second, 'supervisor', $directorVacancy);
        $this->rep($rejected, 9, 5);
        $this->rep($second, 1, 5);

        $result = app(PromotionLadder::class)->fillVacancy($this->endMembership($directorVacancy));
        $acting = Membership::findOrFail($result['membership_id']);
        $this->assertSame($rejected->id, $acting->user_id);

        $admin = $this->grant($this->makeUser(), 'promotion_ladder.reject');

        $this->actingAs($admin)
            ->post(route('admin.volunteer.org.promotion-ladder.reject', $acting), [
                'reason' => 'تعارض مصالح موثَّق مع كيانٍ شقيق.',
            ])
            ->assertRedirect();

        $this->assertSame('ended', $acting->fresh()->status);

        $newActing = Membership::query()->where('user_id', $second->id)->where('status', 'active')
            ->whereHas('position', fn ($q) => $q->where('key', 'director'))->first();

        $this->assertNotNull($newActing, 'المرشّح الثاني يترقّى محلّ المردود فورًا.');
        $this->assertTrue($newActing->is_acting);
    }

    public function test_confirm_and_reject_require_the_membership_to_be_an_active_acting_one(): void
    {
        $director = $this->membership($this->makeUser('دايركتور مثبَّت'), 'director');
        $admin = $this->grant($this->makeUser(), 'vacancies.approve', 'promotion_ladder.reject');

        $this->actingAs($admin)
            ->post(route('admin.volunteer.org.promotion-ladder.confirm', $director))
            ->assertNotFound();

        $this->actingAs($admin)
            ->post(route('admin.volunteer.org.promotion-ladder.reject', $director), ['reason' => 'سبب كافٍ لعشرة أحرف.'])
            ->assertNotFound();
    }
}
