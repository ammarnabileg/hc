<?php

namespace Tests\Feature\Volunteer\Flow;

use App\Models\Arbitration;
use App\Models\Membership;
use App\Models\Position;
use App\Models\TaskContribution;
use App\Services\Volunteer\Escalation\ArbitrationService;
use Illuminate\Validation\ValidationException;

/**
 * التحكيم (23 — القسم 5): Masking · تنازع المصالح · القرار النهائيّ بمبرّر إجباريّ.
 */
class ArbitrationTest extends FlowTestCase
{
    private function service(): ArbitrationService
    {
        return app(ArbitrationService::class);
    }

    // ------------------------------------------------------------ Masking

    public function test_phone_is_masked_for_a_viewer_with_no_line_relation(): void
    {
        $stranger = $this->makeUser('غريب');
        // عضويّة في كيان آخر بلا صلة سلسلة بالطرف
        $other = $this->makeEntity();

        Membership::create([
            'user_id' => $stranger->id,
            'entity_id' => $other->id,
            'position_id' => Position::query()->where('key', 'supervisor')->value('id'),
            'is_primary' => true,
            'started_at' => now(),
            'status' => 'active',
        ]);

        $contact = $this->service()->contactFor($stranger, $this->contributor);

        $this->assertTrue($contact['masked']);
        $this->assertNotSame($this->contributor->phone, $contact['value']);
        $this->assertStringContainsString('•', $contact['value']);
    }

    public function test_phone_is_revealed_to_an_upline_of_the_party(): void
    {
        // المراجِع أبلاين مالك المهمّة ومن فوق المساهم في نفس السلسلة
        $contact = $this->service()->contactFor($this->reviewer, $this->contributor);

        $this->assertFalse($contact['masked']);
        $this->assertSame($this->contributor->phone, $contact['value']);
    }

    public function test_phone_is_revealed_to_a_downline_of_the_party(): void
    {
        $contact = $this->service()->contactFor($this->contributor, $this->reviewer);

        $this->assertFalse($contact['masked']);
        $this->assertSame($this->reviewer->phone, $contact['value']);
    }

    // ------------------------------------------------------------ تنازع المصالح

    public function test_conflict_of_interest_skips_the_level_and_shows_the_reason(): void
    {
        $task = $this->makeTask();

        // المساهم داونلاين المالك ⟵ أبلاين المالك (المراجِع) على صلة بالمساهم
        $contribution = $this->makeContribution($task);

        $arbitration = $this->service()->open($task, $contribution, $this->contributor, [
            'claim' => 'المطلوب مكتوب في شكل المخرجات بالحرف.',
        ]);

        $this->assertTrue((bool) $arbitration->conflict_of_interest_skipped);
        $this->assertNotNull($this->service()->skipReason($arbitration));
        $this->assertNotSame($this->reviewer->id, (int) $arbitration->arbiter_id);
    }

    // ------------------------------------------------------------ القرار النهائيّ

    public function test_decision_requires_a_written_justification(): void
    {
        $arbitration = $this->makeArbitration();

        $this->expectException(ValidationException::class);

        $this->service()->applyDecision($arbitration, $this->top, 'award', [
            'owner_amount' => 10,
            'justification' => '   ',
        ]);
    }

    public function test_shelved_decision_touches_nothing(): void
    {
        $arbitration = $this->makeArbitration();

        $this->service()->applyDecision($arbitration, $this->top, 'shelved', [
            'justification' => 'الطرفان مجتهدان والمخرج مقبول.',
        ]);

        $arbitration->refresh();

        $this->assertSame('decided', $arbitration->status);
        $this->assertSame(0.0, (float) $arbitration->owner_amount);
        $this->assertSame(0.0, (float) $arbitration->contributor_amount);
        $this->assertDatabaseMissing('transactions', ['source' => 'arbitration.award']);
        $this->assertDatabaseMissing('transactions', ['source' => 'arbitration.deduct']);
    }

    public function test_decision_is_final_and_never_repeated(): void
    {
        $arbitration = $this->makeArbitration();

        $this->service()->applyDecision($arbitration, $this->top, 'award', [
            'owner_amount' => 10,
            'contributor_amount' => 5,
            'justification' => 'المخرج مطابق جزئيًّا.',
        ]);

        $first = $arbitration->refresh()->decision_justification;

        // محاولة ثانية لا تغيّر شيئًا — القرار نهائيّ ولا يُعاد
        $this->service()->applyDecision($arbitration, $this->top, 'deduct', [
            'owner_amount' => 99,
            'justification' => 'محاولة إعادة.',
        ]);

        $this->assertSame($first, $arbitration->refresh()->decision_justification);
        $this->assertSame('award', $arbitration->decision_type);
    }

    // ------------------------------------------------------------ أدوات

    private function makeContribution($task): TaskContribution
    {
        return TaskContribution::create([
            'task_id' => $task->id,
            'contributor_id' => $this->contributor->id,
            'invited_by' => $this->owner->id,
            'item_title' => 'بند مساهمة',
            'deliverable_spec' => 'ملفّ واحد.',
            'internal_deadline_at' => now()->addDays(2),
            'vxp_value' => 40,
            'vxp_source' => 'task_pool',
            'status' => 'delivered',
            'invited_at' => now()->subDays(2),
        ]);
    }

    private function makeArbitration(): Arbitration
    {
        $task = $this->makeTask();

        return Arbitration::create([
            'task_id' => $task->id,
            'task_contribution_id' => $this->makeContribution($task)->id,
            'opened_by' => $this->contributor->id,
            'arbiter_id' => $this->top->id,
            'claim' => 'خلاف على شكل المخرجات.',
            'status' => 'open',
            'window_due_at' => now()->addDay(),
        ]);
    }
}
