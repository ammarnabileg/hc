<?php

namespace Tests\Feature\Volunteer\Flow;

use App\Models\Arbitration;
use App\Models\Escalation;
use App\Models\Task;
use App\Models\TaskBlock;
use App\Models\TaskContribution;
use App\Models\Transaction;
use App\Services\Volunteer\Escalation\CaseCatalog;
use App\Services\Volunteer\Escalation\EscalationEngine;
use Illuminate\Database\Eloquent\Model;

/**
 * التسويات الآليّة التسع عند فوات نافذة السقف (الدستور 23 — القسم 5).
 * لكلّ حالة اختبار مستقلّ لأنّ الجدول منصوص بالحرف ولا يُترَك لاجتهاد.
 */
class EscalationSettlementsTest extends FlowTestCase
{
    private function engine(): EscalationEngine
    {
        return app(EscalationEngine::class);
    }

    /** يفتح الحالة مباشرةً على مكتب السقف ثمّ يُفوِّت نافذته ويشغّل المحرّك */
    private function settle(string $caseType, Model $subject, array $payload = []): Escalation
    {
        $escalation = $this->engine()->open($caseType, $subject, $this->owner, $payload, $this->top);

        $this->assertTrue((bool) $escalation->is_top_level, 'الحالة يجب أن تكون على السقف بنافذة 48.');

        $escalation->forceFill(['window_due_at' => now()->subHour()])->save();

        $this->engine()->run();

        return $escalation->refresh();
    }

    // ------------------------------------------------------------ 1) تمديد ⟵ رفض

    public function test_extension_settles_as_rejected(): void
    {
        $task = $this->makeTask();
        $originalDeadline = $task->deadline_at;

        $escalation = $this->settle(CaseCatalog::EXTENSION, $task, [
            'new_deadline' => now()->addDays(20)->toDateTimeString(),
        ]);

        $this->assertSame('auto_settled', $escalation->status);
        $this->assertSame('rejected', $escalation->decision);
        $this->assertSame(
            $originalDeadline->toDateTimeString(),
            $task->refresh()->deadline_at->toDateTimeString(),
            'الرفض لا يحرّك الديدلاين.',
        );
    }

    // ------------------------------------------------------------ 2) تعثّر ⟵ رفض

    public function test_blocked_settles_as_rejected(): void
    {
        $task = $this->makeTask(attributes: ['status' => 'blocked']);

        $block = TaskBlock::create([
            'task_id' => $task->id,
            'type' => 'duration',
            'days' => 2,
            'reason' => 'مستنّي ردّ المدرّب',
            'status' => 'pending',
        ]);

        $escalation = $this->settle(CaseCatalog::BLOCKED, $block);

        $this->assertSame('rejected', $escalation->decision);
        $this->assertSame('rejected', $block->refresh()->status);
        $this->assertSame('in_progress', $task->refresh()->status);
    }

    // ------------------------------------------------------------ 3) اعتذار ⟵ رفض

    public function test_apology_settles_as_rejected_with_no_delivery_value(): void
    {
        $task = $this->makeTask();

        $escalation = $this->settle(CaseCatalog::APOLOGY, $task);

        $this->assertSame('rejected', $escalation->decision);
        $this->assertSame('no_delivery', $task->refresh()->status);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->owner->id,
            'source' => 'task.apology',
        ]);

        $applied = (float) Transaction::query()
            ->where('user_id', $this->owner->id)
            ->where('source', 'task.apology')
            ->value('amount');

        $this->assertSame(rep_rule('task.no_delivery'), $applied, 'الاعتذار المرفوض = سلّم عدم التسليم.');
    }

    // ------------------------------------------------------------ 4) عدم تسليم ⟵ تُغلَق

    public function test_no_delivery_settles_as_closed(): void
    {
        $task = $this->makeTask();

        $dependent = $this->makeTask(attributes: [
            'title' => 'مهمّة تابعة',
            'blocked_by_task_id' => $task->id,
            'status' => 'blocked',
        ]);

        $escalation = $this->settle(CaseCatalog::NO_DELIVERY, $task);

        $this->assertSame('closed', $escalation->decision);
        $this->assertSame('closed', $task->refresh()->status);
        // التبعيّة المكسورة تتحرّر آليًّا فلا تبقى معلّقة للأبد (23 — 3.4)
        $this->assertSame('in_progress', $dependent->refresh()->status);
    }

    /** ⭐ مسار عدم التسليم يصعد **بلا خصم تباطؤ** — قاعدة نهائيّة */
    public function test_no_delivery_escalates_without_slowdown_penalty(): void
    {
        $task = $this->makeTask();

        $escalation = $this->engine()->open(CaseCatalog::NO_DELIVERY, $task, $this->owner);
        $this->assertSame($this->reviewer->id, (int) $escalation->current_handler_id);

        $escalation->forceFill(['window_due_at' => now()->subHour()])->save();
        $this->engine()->run();

        $escalation->refresh();

        $this->assertFalse((bool) $escalation->slowdown_penalty_applied);
        $this->assertSame($this->top->id, (int) $escalation->current_handler_id);
        $this->assertDatabaseMissing('transactions', [
            'user_id' => $this->reviewer->id,
            'source' => 'escalation.slowdown',
        ]);
    }

    /** وأيّ نوع آخر يصعد **مع** أثر التباطؤ على المفوِّت */
    public function test_other_types_escalate_with_slowdown_penalty(): void
    {
        $task = $this->makeTask();

        $escalation = $this->engine()->open(CaseCatalog::EXTENSION, $task, $this->owner);
        $escalation->forceFill(['window_due_at' => now()->subHour()])->save();

        $this->engine()->run();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->reviewer->id,
            'source' => 'escalation.slowdown',
        ]);

        /*
         | ⭐ والعَلَم يُصفَّر مع الصعود: «اللي فوّتها ياخد أثر التباطؤ» بلا تخصيص
         | (23-5) — فالمستوى الثاني ليس مجّانيًّا. وبقاؤه مرفوعًا كان يعفي كلّ مَن
         | فوق المستوى الأوّل من أثره.
         */
        $this->assertFalse((bool) $escalation->refresh()->slowdown_penalty_applied);
        $this->assertSame($this->top->id, (int) $escalation->current_handler_id);
    }

    // ------------------------------------------------------------ 5) تحكيم ⟵ تسوية 50%

    public function test_arbitration_settles_as_fifty_fifty(): void
    {
        $task = $this->makeTask();
        $contribution = $this->makeContribution($task, 80);

        $arbitration = Arbitration::create([
            'task_id' => $task->id,
            'task_contribution_id' => $contribution->id,
            'opened_by' => $this->contributor->id,
            'arbiter_id' => $this->top->id,
            'claim' => 'اترجّع البند مرّتين بلا سبب.',
            'status' => 'open',
            'window_due_at' => now()->addDay(),
        ]);

        $escalation = $this->settle(CaseCatalog::ARBITRATION, $arbitration);
        $arbitration->refresh();

        $this->assertSame('split', $escalation->decision);
        $this->assertSame('decided', $arbitration->status);

        $share = round(80 * (float) setting('workflow.arbitration.settlement_percent', 50) / 100, 2);

        $this->assertSame($share, (float) $arbitration->owner_amount);
        $this->assertSame($share, (float) $arbitration->contributor_amount);
    }

    // ------------------------------------------------------------ 6) سحب مساهم ⟵ موافقة

    public function test_contributor_withdraw_settles_as_approved(): void
    {
        $task = $this->makeTask();
        $contribution = $this->makeContribution($task, 50);

        $escalation = $this->settle(CaseCatalog::CONTRIBUTOR_WITHDRAW, $contribution);

        $this->assertSame('approved', $escalation->decision);
        $this->assertSame('withdrawn', $contribution->refresh()->status);
        // بلا أثر على درجة الالتزام لأيّ طرف (23 — القسم 4)
        $this->assertDatabaseMissing('transactions', [
            'user_id' => $this->contributor->id,
            'source' => 'contribution.no_delivery',
        ]);
    }

    // ------------------------------------------------------------ 7) بلاغ رابط ⟵ يعمل بلا خصم

    public function test_broken_link_settles_as_working_without_penalty(): void
    {
        $task = $this->makeTask();

        $escalation = $this->settle(CaseCatalog::BROKEN_LINK, $task, [
            'link' => 'https://example.test/recording',
            'responsible_id' => $this->reviewer->id,
        ]);

        $this->assertSame('link_works', $escalation->decision);
        $this->assertDatabaseMissing('transactions', [
            'user_id' => $this->reviewer->id,
            'source' => 'link.broken_confirmed',
        ]);
    }

    // ------------------------------------------------------------ 8) إرجاع متكرّر ⟵ اعتماد بلا Rep

    public function test_repeated_return_settles_as_approved_without_rep(): void
    {
        $task = $this->makeTask(attributes: ['return_count' => 2, 'status' => 'returned']);

        $escalation = $this->settle(CaseCatalog::REPEATED_RETURN, $task);

        $this->assertSame('approved_no_rep', $escalation->decision);
        $this->assertSame('approved', $task->refresh()->status);
        $this->assertDatabaseMissing('transactions', [
            'user_id' => $this->owner->id,
            'source' => 'task.repeated_return',
        ]);
    }

    // ------------------------------------------------------------ 9) دفعة صب-تاسك ⟵ اعتماد كامل

    public function test_subtask_batch_settles_as_full_approval(): void
    {
        $parent = $this->makeTask();

        $children = collect(range(1, 3))->map(fn ($i) => $this->makeTask(attributes: [
            'title' => 'صب-تاسك '.$i,
            'parent_task_id' => $parent->id,
            'batch_status' => 'pending_review',
        ]));

        $escalation = $this->settle(CaseCatalog::SUBTASK_BATCH, $parent);

        $this->assertSame('approved', $escalation->decision);

        $children->each(fn (Task $child) => $this->assertSame('approved', $child->refresh()->batch_status));
        $this->assertSame('approved', $parent->refresh()->batch_status);
    }

    // ------------------------------------------------------------ أدوات

    private function makeContribution(Task $task, float $vxp): TaskContribution
    {
        return TaskContribution::create([
            'task_id' => $task->id,
            'contributor_id' => $this->contributor->id,
            'invited_by' => $this->owner->id,
            'item_title' => 'بند مساهمة',
            'deliverable_spec' => 'ملفّ واحد.',
            'internal_deadline_at' => now()->addDays(2),
            'vxp_value' => $vxp,
            'vxp_source' => 'task_pool',
            'status' => 'accepted',
            'invited_at' => now()->subDay(),
        ]);
    }
}
