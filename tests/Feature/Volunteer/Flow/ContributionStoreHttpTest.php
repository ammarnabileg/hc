<?php

namespace Tests\Feature\Volunteer\Flow;

use App\Models\ContributionCheckpoint;
use App\Models\Currency;
use App\Models\Membership;
use App\Models\MembershipAbsence;
use App\Models\TaskContribution;
use App\Models\WalletBalance;
use App\Services\Volunteer\Escalation\FlowLedger;

/**
 * `ContributionController::store()` — المسار الوحيد لدعوة مساهم بعد توحيده
 * مع `TaskController::inviteContributor()` المحذوف (23-4). يفحص هنا ما كان
 * غائبًا عن مسار HTTP: ملكيّة المهمّة · حارس الغياب (23-6) · الحفظ الفعليّ
 * بنقاط التفتيش وخصم رصيد المالك حين يكون المصدر `owner_balance`.
 */
class ContributionStoreHttpTest extends FlowTestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => $this->contributor->code,
            'item_title' => 'بند مساهمة عبر HTTP',
            'deliverable_spec' => 'ملفّ واحد بثلاثة أقسام.',
            'internal_deadline_at' => now()->addDays(2)->toDateTimeString(),
            'vxp_value' => 20,
            'vxp_source' => 'task_pool',
        ], $overrides);
    }

    public function test_store_is_forbidden_for_a_non_owner_even_with_the_permission(): void
    {
        $this->grant($this->reviewer, 'contributions.create');

        $task = $this->makeTask(); // مالكه $this->owner لا $this->reviewer

        $response = $this->actingAs($this->reviewer)->post(
            route('volunteer.contributions.store', $task),
            $this->payload(),
        );

        $response->assertForbidden();
        $this->assertSame(0, TaskContribution::query()->count());
    }

    public function test_store_blocks_inviting_a_contributor_who_is_currently_absent(): void
    {
        $this->grant($this->owner, 'contributions.create');

        $membership = Membership::query()->where('user_id', $this->contributor->id)->firstOrFail();
        $delegate = Membership::query()->where('user_id', $this->owner->id)->firstOrFail();

        MembershipAbsence::create([
            'membership_id' => $membership->id,
            'delegate_membership_id' => $delegate->id,
            'from_date' => now()->subDay()->toDateString(),
            'to_date' => now()->addDay()->toDateString(),
            'created_by' => $this->top->id,
        ]);

        $task = $this->makeTask();

        $response = $this->actingAs($this->owner)->post(
            route('volunteer.contributions.store', $task),
            $this->payload(),
        );

        $response->assertSessionHasErrors('code');
        $this->assertSame(0, TaskContribution::query()->count());
    }

    public function test_store_creates_the_contribution_with_checkpoints_and_holds_owner_balance(): void
    {
        $this->grant($this->owner, 'contributions.create');

        WalletBalance::create([
            'user_id' => $this->owner->id,
            'currency_id' => Currency::query()->where('code', 'vxp')->value('id'),
            'balance' => 100,
            'lifetime_earned' => 100,
            'lifetime_spent' => 0,
        ]);

        $task = $this->makeTask();

        $checkpointOne = now()->addHours(6)->toDateTimeString();
        $checkpointTwo = now()->addHours(12)->toDateTimeString();

        $response = $this->actingAs($this->owner)->post(
            route('volunteer.contributions.store', $task),
            $this->payload([
                'vxp_value' => 30,
                'vxp_source' => 'owner_balance',
                'checkpoints' => [$checkpointOne, $checkpointTwo],
            ]),
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $contribution = TaskContribution::query()->where('task_id', $task->id)->firstOrFail();

        $this->assertSame($this->contributor->id, $contribution->contributor_id);
        $this->assertSame('invited', $contribution->status);
        $this->assertSame(30.0, (float) $contribution->vxp_value);
        $this->assertSame('owner_balance', $contribution->vxp_source);
        $this->assertSame(30.0, (float) $contribution->held_amount);

        $this->assertSame(2, ContributionCheckpoint::query()->where('task_contribution_id', $contribution->id)->count());

        $this->assertSame(70.0, FlowLedger::balance($this->owner));
    }
}
