<?php

namespace Tests\Feature\Volunteer\Retention;

use App\Models\Currency;
use App\Models\Entity;
use App\Models\InvestigationCase;
use App\Models\User;
use App\Services\Admin\Volunteer\Integrations;
use App\Services\Volunteer\Retention\CommitteePath;
use App\Services\Wallet\LedgerService;
use Illuminate\Support\Facades\DB;

/**
 * الطبقة الإداريّة (routes/permissions) للجنة التحقيق — التفعيل والقرار
 * والأرشفة كلّها من خلف بوّابة `permission:investigations.*` الفعليّة،
 * ومَن ليس أحد المقعدين ولا مشرف عام التطوّع يُصدّ 403/422.
 */
class InvestigationCommitteeHttpTest extends RetentionTestCase
{
    /** @return array{0:User,1:User,2:Entity} */
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

        return [$user, $lead, $department];
    }

    private function setRep(User $user, float $target): void
    {
        DB::table('wallet_balances')->updateOrInsert(
            ['user_id' => $user->id, 'currency_id' => Currency::query()->where('code', LedgerService::REP)->value('id')],
            ['balance' => $target, 'lifetime_earned' => 0, 'lifetime_spent' => abs($target), 'created_at' => now(), 'updated_at' => now()],
        );
    }

    private function suspend(User $user): int
    {
        $this->setRep($user, -9.8);
        Integrations::post($user, LedgerService::REP, -0.5, 'behavior', 'مخالفة الاختبار', null, null, 'volunteer');

        return (int) DB::table(CommitteePath::TABLE)->where('user_id', $user->id)->where('status', 'open')->value('id');
    }

    private function gm(): User
    {
        $gm = $this->makeUser('مشرف عام التطوّع');
        $this->grant($gm, 'investigations.create');
        $this->grant($gm, 'investigations.view');
        $this->grant($gm, 'investigations.approve');
        $this->grant($gm, 'investigations.archive');
        $this->grant($gm, 'investigations.assign');

        return $gm;
    }

    public function test_the_full_happy_path_through_http(): void
    {
        [$user, $lead] = $this->tree();
        $referralId = $this->suspend($user);
        $gm = $this->gm();

        $this->actingAs($gm)
            ->post(route('admin.volunteer.investigations.activate', $referralId))
            ->assertRedirect();

        $case = InvestigationCase::query()->where('referral_id', $referralId)->firstOrFail();
        $this->grant($lead, 'investigations.view');
        $this->grant($lead, 'investigations.edit');

        $this->actingAs($lead)
            ->get(route('admin.volunteer.investigations.show', $case))
            ->assertOk();

        $this->actingAs($lead)
            ->post(route('admin.volunteer.investigations.verdict', $case), [
                'verdict' => 'recommend_dismissal',
                'reason' => 'تكرار المخالفات بلا مبرّر مقنع',
            ])
            ->assertRedirect();

        $this->assertSame('recommendation_raised', $case->fresh()->status);

        $this->actingAs($gm)
            ->post(route('admin.volunteer.investigations.decision', $case), [
                'decision' => 'dismiss',
                'reason' => 'قرار القمّة النهائيّ بعد مراجعة الملفّ',
            ])
            ->assertRedirect();

        $this->assertSame('decision_issued', $case->fresh()->status);

        $this->actingAs($gm)
            ->post(route('admin.volunteer.investigations.archive', $case))
            ->assertRedirect(route('admin.volunteer.investigations.index'));

        $this->assertSame('closed', $case->fresh()->status);
    }

    public function test_only_investigations_create_holders_may_activate(): void
    {
        [$user] = $this->tree();
        $referralId = $this->suspend($user);

        $outsider = $this->makeUser('أدمن بلا صلاحيّة');
        $this->grant($outsider, 'investigations.view');

        $this->actingAs($outsider)
            ->post(route('admin.volunteer.investigations.activate', $referralId))
            ->assertForbidden();

        $this->assertDatabaseCount('investigation_cases', 0);
    }

    public function test_only_the_platform_owner_or_gm_can_issue_the_final_decision(): void
    {
        [$user, $lead] = $this->tree();
        $referralId = $this->suspend($user);
        $gm = $this->gm();

        $this->actingAs($gm)->post(route('admin.volunteer.investigations.activate', $referralId));
        $case = InvestigationCase::query()->where('referral_id', $referralId)->firstOrFail();

        $this->grant($lead, 'investigations.view');
        $this->grant($lead, 'investigations.edit');
        $this->actingAs($lead)->post(route('admin.volunteer.investigations.verdict', $case), [
            'verdict' => 'recommend_dismissal',
            'reason' => 'مبرّر',
        ]);

        // التيم ليدر أحد مقعدَي اللجنة — لكن القرار النهائيّ ليس له
        $this->actingAs($lead)
            ->post(route('admin.volunteer.investigations.decision', $case), ['decision' => 'dismiss', 'reason' => 'محاولة تجاوز'])
            ->assertForbidden();
    }
}
