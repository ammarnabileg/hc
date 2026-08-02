<?php

namespace Tests\Feature\Volunteer\Flow;

use App\Models\ContributionCheckpoint;
use App\Models\TaskContribution;
use App\Models\Transaction;
use App\Services\Volunteer\Contributions\ActivityWindow;
use App\Services\Volunteer\Contributions\ContributionService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * المساهمون (الدستور 23 — القسم 4): قيد الديدلاين الداخليّ · نقطتا التفتيش
 * داخل نافذة النشاط · مهلة المالك ثمّ الاعتماد التلقائيّ بالنقاط كاملة.
 */
class ContributionFlowTest extends FlowTestCase
{
    private function service(): ContributionService
    {
        return app(ContributionService::class);
    }

    // ------------------------------------------------------------ قيد الديدلاين الداخليّ

    public function test_internal_deadline_must_precede_task_deadline_by_the_gap(): void
    {
        $task = $this->makeTask(attributes: ['deadline_at' => now()->addDays(5)]);
        $gap = $this->service()->deadlineGapHours();

        $this->expectException(ValidationException::class);

        $this->service()->invite($task, $this->owner, $this->contributor, [
            'item_title' => 'بند خارج القيد',
            'deliverable_spec' => 'ملفّ.',
            // أقلّ من الفجوة المطلوبة بساعة واحدة ⟵ يُرفَض الحفظ
            'internal_deadline_at' => now()->addDays(5)->subHours($gap - 1)->toDateTimeString(),
            'vxp_value' => 20,
            'vxp_source' => 'task_pool',
        ]);
    }

    public function test_internal_deadline_inside_the_gap_is_accepted(): void
    {
        $task = $this->makeTask(attributes: ['deadline_at' => now()->addDays(5)]);
        $gap = $this->service()->deadlineGapHours();

        $contribution = $this->service()->invite($task, $this->owner, $this->contributor, [
            'item_title' => 'بند داخل القيد',
            'deliverable_spec' => 'ملفّ واحد بثلاثة أقسام.',
            'internal_deadline_at' => now()->addDays(5)->subHours($gap + 1)->toDateTimeString(),
            'vxp_value' => 20,
            'vxp_source' => 'task_pool',
        ]);

        $this->assertSame('invited', $contribution->status);
        $this->assertNotNull($this->service()->latestInternalDeadline($task));
    }

    // ------------------------------------------------------------ نقطتا تفتيش كحدّ أقصى

    public function test_checkpoints_are_capped_at_two(): void
    {
        $task = $this->makeTask();

        $this->expectException(ValidationException::class);

        $this->service()->invite($task, $this->owner, $this->contributor, [
            'item_title' => 'ثلاث نقاط تفتيش',
            'deliverable_spec' => 'ملفّ.',
            'internal_deadline_at' => now()->addDays(3)->toDateTimeString(),
            'vxp_value' => 10,
            'vxp_source' => 'task_pool',
            'checkpoints' => [
                now()->addHours(6)->toDateTimeString(),
                now()->addHours(12)->toDateTimeString(),
                now()->addHours(18)->toDateTimeString(),
            ],
        ]);
    }

    /** مهلة الردّ تُستهلَك داخل نافذة النشاط وحدها — فلا خصم على نائم ليلًا */
    public function test_checkpoint_response_window_is_counted_inside_activity_window(): void
    {
        $timezone = ActivityWindow::timezone();
        $at = CarbonImmutable::parse('2026-08-10 23:00', $timezone);

        $due = ActivityWindow::addHours($at, 2)->setTimezone($timezone);

        // ساعة داخل نافذة اليوم، وساعة تُرحَّل لبداية نافذة الغد (09:00 ⟵ 10:00)
        $this->assertSame('2026-08-11 10:00', $due->format('Y-m-d H:i'));
    }

    public function test_missed_checkpoint_deducts_from_rep_rule(): void
    {
        $task = $this->makeTask();
        $contribution = $this->openContribution($task);

        $checkpoint = ContributionCheckpoint::create([
            'task_contribution_id' => $contribution->id,
            'sequence' => 1,
            'scheduled_at' => now()->subHours(4),
            'response_due_at' => now()->subHour(),
            'status' => 'pending',
        ]);

        $this->assertSame(1, $this->service()->runMissedCheckpoints());
        $this->assertSame('missed', $checkpoint->refresh()->status);

        $applied = (float) Transaction::query()
            ->where('user_id', $this->contributor->id)
            ->where('source', 'contribution.checkpoint_missed')
            ->value('amount');

        $this->assertSame(rep_rule('task.checkpoint_missed'), $applied);
    }

    // ------------------------------------------------------------ الاعتماد التلقائيّ

    public function test_owner_review_window_expiry_auto_approves_with_full_points(): void
    {
        $task = $this->makeTask();
        $contribution = $this->openContribution($task, vxp: 60);

        $this->service()->deliver($contribution, ['body' => 'المخرج جاهز.']);

        $contribution->refresh();
        $this->assertSame('delivered', $contribution->status);
        $this->assertNotNull($contribution->owner_review_due_at);

        // فاتت مهلة المالك ⟵ اعتماد تلقائيّ بنقاط المساهم **كاملة**
        $contribution->forceFill(['owner_review_due_at' => now()->subMinute()])->save();

        $this->assertSame(1, $this->service()->runAutoApprovals());

        $contribution->refresh();
        $this->assertSame('approved', $contribution->status);
        $this->assertTrue((bool) $contribution->auto_approved);

        $credited = (float) Transaction::query()
            ->where('user_id', $this->contributor->id)
            ->where('source', 'contribution.approved')
            ->value('amount');

        $this->assertSame(60.0, $credited, 'الاعتماد التلقائيّ يعطي النقاط كاملة بلا نقصان.');
    }

    public function test_missed_internal_deadline_applies_no_delivery_value(): void
    {
        $task = $this->makeTask();
        $contribution = $this->openContribution($task);

        $contribution->forceFill(['internal_deadline_at' => now()->subHour()])->save();

        $this->assertSame(1, $this->service()->runMissedInternalDeadlines());
        $this->assertSame('expired', $contribution->refresh()->status);

        $applied = (float) Transaction::query()
            ->where('user_id', $this->contributor->id)
            ->where('source', 'contribution.no_delivery')
            ->value('amount');

        $this->assertSame(rep_rule('task.contribution_no_delivery'), $applied);
    }

    // ------------------------------------------------------------ المعاينة والرصيد

    public function test_invite_is_blocked_when_owner_balance_is_not_enough(): void
    {
        $task = $this->makeTask();

        $preview = $this->service()->invitePreview($this->contributor, $this->owner, 500, 'owner_balance');

        $this->assertFalse($preview['sufficient'], 'الرصيد لا يكفي ⟵ المعاينة تمنع الإرسال.');

        $this->expectException(ValidationException::class);

        $this->service()->invite($task, $this->owner, $this->contributor, [
            'item_title' => 'بند فوق الرصيد',
            'deliverable_spec' => 'ملفّ.',
            'internal_deadline_at' => now()->addDays(3)->toDateTimeString(),
            'vxp_value' => 500,
            'vxp_source' => 'owner_balance',
        ]);
    }

    // ------------------------------------------------------------ أدوات

    private function openContribution($task, float $vxp = 40): TaskContribution
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
            'responded_at' => now()->subDay(),
        ]);
    }
}
