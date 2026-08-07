<?php

namespace Tests\Feature\Volunteer\Retention;

use App\Models\Currency;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\Offboarding;
use App\Models\User;
use App\Services\Admin\Volunteer\Integrations;
use App\Services\Volunteer\Retention\CommitteePath;
use App\Services\Volunteer\Retention\InvestigationCommitteeService;
use App\Services\Volunteer\Retention\SuspensionService;
use App\Services\Wallet\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ⭐ لجنة التحقيق — تشكيلها وانعقادها وقرارها (23-0.2-4). كان «التفعيل موجود
 * والشاشات الأربع غايبة بالكامل» (بند التدقيق #49): مسودّة الإحالة كانت تُفتح
 * وتتوقّف — لا ملفٍّ حقيقيّ بمقعدَيه ولا ميتينج ولا قرار.
 */
class InvestigationCommitteeTest extends RetentionTestCase
{
    private function committee(): InvestigationCommitteeService
    {
        return app(InvestigationCommitteeService::class);
    }

    /**
     * القسم: دايركتور ⟵ تيم ليدر ⟵ **البطل** — وقسمٌ ثانٍ بتيم ليدر مستقلّ
     * (المرشّح الطبيعيّ لمقعد قسم المتطوّعين).
     *
     * @return array{0:User,1:User,2:Entity,3:User}
     */
    private function tree(): array
    {
        $department = $this->makeEntity('قسم الإعلام');
        $dir = $this->makeUser('دايركتور');
        $lead = $this->makeUser('تيم ليدر');
        $user = $this->makeUser('البطل');

        $dirMembership = $this->makeMembership($dir, $department, position: 'director');
        $leadMembership = $this->makeMembership($lead, $department, $dirMembership, 'team_leader');
        $this->makeMembership($user, $department, $leadMembership);

        $otherDepartment = $this->makeEntity('قسم آخر');
        $otherLead = $this->makeUser('تيم ليدر مستقلّ');
        $this->makeMembership($otherLead, $otherDepartment, position: 'team_leader');

        return [$user, $lead, $department, $otherLead];
    }

    private function setRep(User $user, float $target): void
    {
        DB::table('wallet_balances')->updateOrInsert(
            ['user_id' => $user->id, 'currency_id' => Currency::query()->where('code', LedgerService::REP)->value('id')],
            ['balance' => $target, 'lifetime_earned' => 0, 'lifetime_spent' => abs($target), 'created_at' => now(), 'updated_at' => now()],
        );
    }

    /** يُسقط الرقم الظاهر إلى −10 فيُفتح التعليق والإحالة معًا (SuspensionService::apply) */
    private function suspend(User $user): object
    {
        $this->setRep($user, -9.8);
        Integrations::post($user, LedgerService::REP, -0.5, 'behavior', 'مخالفة الاختبار', null, null, 'volunteer');

        return DB::table(CommitteePath::TABLE)->where('user_id', $user->id)->where('status', 'open')->firstOrFail();
    }

    private function actor(): User
    {
        $gm = $this->makeUser('مشرف عام التطوّع');
        $this->grant($gm, 'investigations.create');
        $this->grant($gm, 'investigations.approve');
        $this->grant($gm, 'investigations.archive');
        $this->grant($gm, 'investigations.assign');

        return $gm;
    }

    // ------------------------------------------------------------------ التفعيل والمقعدان

    /** ⭐ «تشكيلها آليّ بالكامل»: الأبلاين المباشر + مقعد قسم المتطوّعين باستبعاد تنازع المصالح */
    public function test_activation_creates_a_real_case_with_both_seats_auto_assigned(): void
    {
        [$user, $lead, , $otherLead] = $this->tree();
        $referral = $this->suspend($user);
        $gm = $this->actor();

        $case = $this->committee()->activate($referral, $gm);

        $this->assertSame($user->id, $case->user_id);
        $this->assertSame($lead->id, $case->seat_upline_id, 'الأبلاين المباشر للبطل هو التيم ليدر — والنصّ: «الأبلاين المباشر … من السلسلة».');
        $this->assertSame($otherLead->id, $case->seat_dept_id, 'المرشّح الوحيد بلا تنازع مصالح هو تيم ليدر القسم الآخر.');
        $this->assertSame('open', $case->status);
        $this->assertNotNull($case->dossier_snapshot, 'ملفّ القضيّة يتجمّع آليًّا لحظة التفعيل — «النظام يطبع الحقيقة».');
    }

    /** تنازع المصالح: الأبلاين لا يُختار مقعدَ قسم المتطوّعين رغم كونه تيم ليدر */
    public function test_the_dept_seat_excludes_anyone_related_by_line_to_the_suspended_member(): void
    {
        [$user, $lead] = $this->tree();
        $referral = $this->suspend($user);
        $gm = $this->actor();

        $case = $this->committee()->activate($referral, $gm);

        $this->assertNotSame($lead->id, $case->seat_dept_id, 'التيم ليدر أبلاين البطل — تنازع مصالح يستبعده من مقعد قسم المتطوّعين.');
    }

    /** التفعيل مرّتين على نفس الإحالة يعيد نفس الملفّ لا يفتح ثانيًا (23-0.2-4-1 idempotent) */
    public function test_activating_twice_returns_the_same_case(): void
    {
        [$user] = $this->tree();
        $referral = $this->suspend($user);
        $gm = $this->actor();

        $first = $this->committee()->activate($referral, $gm);
        $second = $this->committee()->activate($referral, $gm);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('investigation_cases', 1);
    }

    // ------------------------------------------------------------------ قرار الميتينج

    /** ⭐ «فرصة»: +1 يدويّة لمعدّل الالتزام وإعادة تفعيل الحساب والعضويّة */
    public function test_a_chance_verdict_grants_plus_one_rep_and_reactivates_the_account(): void
    {
        [$user, $lead, $department] = $this->tree();
        $referral = $this->suspend($user);
        $gm = $this->actor();
        $case = $this->committee()->activate($referral, $gm);

        $before = round(app(LedgerService::class)->balance($user, LedgerService::REP), 2);

        $updated = $this->committee()->recordVerdict($case, 'chance', 'اللجنة رأت فرصة حقيقيّة', $lead);

        $after = round(app(LedgerService::class)->balance($user->fresh(), LedgerService::REP), 2);

        $this->assertSame($before + 1.0, $after, 'قرار الفرصة يضيف +1 يدويّة بمرجع اللجنة (task.committee_chance).');
        $this->assertSame('closed', $updated->status);
        $this->assertSame('active', Membership::query()->where('user_id', $user->id)->where('entity_id', $department->id)->value('status'));
        $this->assertTrue($user->fresh()->isVolunteer(), 'الفرصة تُعيد تفعيل الحساب فورًا — لا انتظار.');
    }

    /** توصية الإقصاء تُرفَع لمشرف عام التطوّع ولا تُنفَّذ من مقعدَي اللجنة مباشرةً */
    public function test_a_dismissal_recommendation_is_raised_not_executed_by_the_seats(): void
    {
        [$user, $lead] = $this->tree();
        $referral = $this->suspend($user);
        $gm = $this->actor();
        $case = $this->committee()->activate($referral, $gm);

        $updated = $this->committee()->recordVerdict($case, 'recommend_dismissal', 'تكرار المخالفات بلا مبرّر', $lead);

        $this->assertSame('recommendation_raised', $updated->status);
        $this->assertNull($updated->offboarding_id, 'التوصية وحدها لا تفتح ملفّ إقصاء — القرار البشريّ هو ما يفتحه.');
        $this->assertDatabaseHas(SuspensionService::TABLE, ['user_id' => $user->id, 'released_at' => null]);
    }

    /** من ليس أحد المقعدين ولا يحمل صلاحيّة الاعتماد لا يسجّل قرار الميتينج */
    public function test_someone_outside_the_two_seats_cannot_record_a_verdict(): void
    {
        [$user] = $this->tree();
        $referral = $this->suspend($user);
        $gm = $this->actor();
        $case = $this->committee()->activate($referral, $gm);

        $outsider = $this->makeUser('غريب');

        $this->expectException(ValidationException::class);
        $this->committee()->recordVerdict($case, 'chance', 'مبرّر', $outsider);
    }

    // ------------------------------------------------------------------ القرار البشريّ النهائيّ

    /** ⭐ الإقصاء يمرّ بالمسار القائم `OffboardingService::open('exclusion', …)` لا مسارًا موازيًا */
    public function test_dismiss_decision_opens_an_exclusion_offboarding_through_the_existing_service(): void
    {
        [$user, $lead] = $this->tree();
        $referral = $this->suspend($user);
        $gm = $this->actor();
        $case = $this->committee()->activate($referral, $gm);
        $this->committee()->recordVerdict($case, 'recommend_dismissal', 'مبرّر التوصية', $lead);

        $decided = $this->committee()->decide($case->fresh(), 'dismiss', 'قرار القمّة النهائيّ', $gm);

        $this->assertSame('decision_issued', $decided->status);
        $this->assertNotNull($decided->offboarding_id);
        $this->assertDatabaseHas('offboardings', ['id' => $decided->offboarding_id, 'user_id' => $user->id, 'type' => Offboarding::query()->find($decided->offboarding_id)->type]);
        $this->assertSame('exclusion', Offboarding::query()->find($decided->offboarding_id)->type);
    }

    /** رفض التوصية = فرصة بقرار القمّة بدلًا من مقعدَي اللجنة */
    public function test_rejecting_the_recommendation_grants_a_chance_via_the_gm_instead(): void
    {
        [$user, $lead] = $this->tree();
        $referral = $this->suspend($user);
        $gm = $this->actor();
        $case = $this->committee()->activate($referral, $gm);
        $this->committee()->recordVerdict($case, 'recommend_dismissal', 'مبرّر التوصية', $lead);

        $decided = $this->committee()->decide($case->fresh(), 'reject_recommendation', 'القمّة رأت الفرصة أولى', $gm);

        $this->assertSame('decision_issued', $decided->status);
        $this->assertTrue($user->fresh()->isVolunteer(), 'رفض التوصية يعيد تفعيل الحساب عبر نفس مسار الفرصة.');
    }

    /** لا قرار بلا توصية مرفوعة — الحالة تحرس نفسها */
    public function test_decide_rejects_a_case_with_no_recommendation_raised(): void
    {
        [$user] = $this->tree();
        $referral = $this->suspend($user);
        $gm = $this->actor();
        $case = $this->committee()->activate($referral, $gm);

        $this->expectException(ValidationException::class);
        $this->committee()->decide($case, 'dismiss', 'مبرّر', $gm);
    }

    // ------------------------------------------------------------------ الأرشفة

    public function test_archive_requires_a_decision_first(): void
    {
        [$user] = $this->tree();
        $referral = $this->suspend($user);
        $gm = $this->actor();
        $case = $this->committee()->activate($referral, $gm);

        $this->expectException(ValidationException::class);
        $this->committee()->archive($case, $gm);
    }

    public function test_archive_closes_the_case_after_the_decision(): void
    {
        [$user, $lead] = $this->tree();
        $referral = $this->suspend($user);
        $gm = $this->actor();
        $case = $this->committee()->activate($referral, $gm);
        $this->committee()->recordVerdict($case, 'recommend_dismissal', 'مبرّر', $lead);
        $decided = $this->committee()->decide($case->fresh(), 'dismiss', 'قرار القمّة', $gm);

        $archived = $this->committee()->archive($decided, $gm);

        $this->assertSame('closed', $archived->status);
        $this->assertNotNull($archived->closed_at);
    }

    // ------------------------------------------------------------------ الطابور

    public function test_queue_lists_open_referrals_with_no_activated_case_yet(): void
    {
        [$user] = $this->tree();
        $this->suspend($user);

        $queue = $this->committee()->queue();

        $this->assertCount(1, $queue);
        $this->assertSame($user->id, $queue->first()->user_id);
    }

    public function test_queue_excludes_referrals_already_activated(): void
    {
        [$user] = $this->tree();
        $referral = $this->suspend($user);
        $gm = $this->actor();
        $this->committee()->activate($referral, $gm);

        $this->assertCount(0, $this->committee()->queue());
    }
}
