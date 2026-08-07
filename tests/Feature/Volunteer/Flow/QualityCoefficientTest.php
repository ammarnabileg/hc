<?php

namespace Tests\Feature\Volunteer\Flow;

use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Volunteer\Contributions\ReviewService;
use App\Services\Volunteer\Tasks\TaskWorkflow;
use App\Services\Wallet\LedgerService;

/**
 * ⭐ معامل جودة الإنجاز ⟵ VXP (24.2 التاب 2 · مبدأ الفصل §6 — الدستور 4266):
 * جدول ثلاثيّ قابل للتحرير يحجِّم VXP وحده لحظة الاعتماد — بحركة تصحيحيّة
 * لا إعادة دفعٍ، لأنّ VXP يُدفَع فعلًا لحظة التسليم لا الاعتماد. ومحصورةٌ
 * بصلاحيّة `vxp_manual.create` (23 — القسم 5) — لا أيّ مراجعٍ، فالخصم
 * الآليّ على VXP ممنوعٌ صراحةً (24 تاب VXP)، ولا يفتحه كودٌ بتوقيعٍ صامت.
 */
class QualityCoefficientTest extends FlowTestCase
{
    private function vxpBalance(User $user): float
    {
        return round(app(LedgerService::class)->balance($user->fresh(), 'vxp'), 2);
    }

    private function vxpMovementCount(User $user): int
    {
        return Transaction::query()
            ->where('user_id', $user->id)
            ->whereHas('currency', fn ($q) => $q->where('code', 'vxp'))
            ->count();
    }

    private function repRows(User $user): float
    {
        return (float) Transaction::query()
            ->where('user_id', $user->id)
            ->whereHas('currency', fn ($q) => $q->where('code', 'rep'))
            ->sum('amount');
    }

    private function deliverAndApprove(?string $qualityTier, ?User $reviewer = null): Task
    {
        $task = $this->makeTask(attributes: ['deadline_at' => now()->addDays(2)]);

        app(TaskWorkflow::class)->deliver($task, $this->owner, ['body' => 'المخرج جاهز.']);
        app(ReviewService::class)->approveTask($task->refresh(), $reviewer ?? $this->reviewer, array_filter([
            'quality_tier' => $qualityTier,
        ]));

        return $task;
    }

    public function test_approving_without_a_quality_tier_leaves_the_delivered_vxp_untouched(): void
    {
        $this->grant($this->reviewer, 'vxp_manual.create');
        $this->deliverAndApprove(null);

        // وعاء المهمّة 200 (FlowTestCase::makeTask) بلا أبناء ⟵ الأب يقبض الوعاء كاملًا
        $this->assertSame(200.0, $this->vxpBalance($this->owner));
        $this->assertSame(1, $this->vxpMovementCount($this->owner), 'بلا مستوًى مُختار = بلا حركة تصحيحيّة إضافيّة.');
    }

    public function test_the_full_quality_tier_writes_no_correction_transaction(): void
    {
        $this->grant($this->reviewer, 'vxp_manual.create');
        $this->deliverAndApprove('high');

        $this->assertSame(200.0, $this->vxpBalance($this->owner));
        $this->assertSame(1, $this->vxpMovementCount($this->owner), 'المستوى الكامل 100% لا يغيّر شيئًا فلا يكتب حركة.');
    }

    public function test_a_lower_quality_tier_reduces_vxp_by_the_configured_percent(): void
    {
        $this->grant($this->reviewer, 'vxp_manual.create');
        $this->deliverAndApprove('low');

        // low = 60% افتراضيًّا ⟵ 200 × 0.6 = 120
        $this->assertSame(120.0, $this->vxpBalance($this->owner));
        $this->assertSame(2, $this->vxpMovementCount($this->owner), 'حركة التسليم + حركة التصحيح.');
    }

    public function test_the_mid_quality_tier_reduces_vxp_by_its_own_percent(): void
    {
        $this->grant($this->reviewer, 'vxp_manual.create');
        $this->deliverAndApprove('mid');

        // mid = 80% افتراضيًّا ⟵ 200 × 0.8 = 160
        $this->assertSame(160.0, $this->vxpBalance($this->owner));
    }

    /** ⭐ خصم VXP المسموح وحده لمالك `vxp_manual.create` — أيّ مراجعٍ آخر لا يحجِّم شيئًا */
    public function test_a_reviewer_without_the_manual_vxp_permission_cannot_scale_vxp(): void
    {
        $this->deliverAndApprove('low');

        $this->assertSame(200.0, $this->vxpBalance($this->owner), 'بلا الصلاحيّة: التسليم يدفع كاملًا ولا تصحيح يُكتَب.');
        $this->assertSame(1, $this->vxpMovementCount($this->owner));
    }

    /** ⭐ مبدأ الفصل (الدستور 4266): الجودة تحجِّم VXP ولا تمسّ Rep إطلاقًا */
    public function test_quality_coefficient_never_touches_rep(): void
    {
        $this->grant($this->reviewer, 'vxp_manual.create');
        $before = $this->repRows($this->owner);
        $this->deliverAndApprove('low');

        $this->assertSame($before, $this->repRows($this->owner) - rep_rule('task.early'), 'الجودة الضعيفة لا تضيف ولا تخصم حركة Rep واحدة.');
        $this->assertSame(1, Transaction::query()->where('user_id', $this->owner->id)->whereHas('currency', fn ($q) => $q->where('code', 'rep'))->count());
    }

    /** حارس واقعة واحدة: اعتمادان لنفس المهمّة (نظريًّا) لا يضاعفان التصحيح */
    public function test_reapplying_approval_does_not_double_the_correction(): void
    {
        $this->grant($this->reviewer, 'vxp_manual.create');
        $task = $this->deliverAndApprove('low');

        app(ReviewService::class)->approveTask($task->refresh(), $this->reviewer, ['quality_tier' => 'low']);

        $this->assertSame(120.0, $this->vxpBalance($this->owner));
        $this->assertSame(2, $this->vxpMovementCount($this->owner));
    }

    public function test_review_controller_rejects_an_unknown_quality_tier(): void
    {
        $this->grant($this->reviewer, 'tasks.approve', 'vxp_manual.create');
        $task = $this->makeTask(attributes: ['deadline_at' => now()->addDays(2), 'status' => 'delivered', 'delivered_at' => now()]);

        $this->actingAs($this->reviewer)
            ->post(route('volunteer.reviews.task.approve', $task), ['quality_tier' => 'not-a-real-tier'])
            ->assertSessionHasErrors('quality_tier');
    }

    public function test_review_controller_blocks_quality_tier_without_the_manual_vxp_permission(): void
    {
        $this->grant($this->reviewer, 'tasks.approve');
        $task = $this->makeTask(attributes: ['deadline_at' => now()->addDays(2), 'status' => 'delivered', 'delivered_at' => now()]);

        $this->actingAs($this->reviewer)
            ->post(route('volunteer.reviews.task.approve', $task), ['quality_tier' => 'low'])
            ->assertForbidden();
    }
}
