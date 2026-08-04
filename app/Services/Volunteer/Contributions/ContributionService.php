<?php

namespace App\Services\Volunteer\Contributions;

use App\Models\ContributionCheckpoint;
use App\Models\Task;
use App\Models\TaskContribution;
use App\Models\TaskSubmission;
use App\Models\User;
use App\Services\Volunteer\Escalation\FlowLedger;
use App\Services\Volunteer\Escalation\FlowNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * المساهمون — التاسكات المشتركة (الدستور 23 — القسم 4).
 *
 * القواعد التي يحرسها هذا الملفّ حرفيًّا:
 *  · الديدلاين الداخليّ ≤ ديدلاين المهمّة − 24 ساعة (يُرفَض الحفظ وإلّا).
 *  · نقطتا تفتيش كحدّ أقصى، ومهلة الردّ ساعتان **داخل نافذة النشاط**.
 *  · VXP من وعاء المهمّة، أو من رصيد المالك بعرض صريح للخصم — ويُمنَع
 *    الإرسال إن لم يكفِ رصيده.
 *  · بعد التسليم النهائيّ: مهلة المالك، ثمّ **اعتماد تلقائيّ بنقاط المساهم كاملة**.
 */
class ContributionService
{
    // -------------------------------------------------------------- الإعدادات

    /** الفجوة الإلزاميّة بين الديدلاين الداخليّ وديدلاين المهمّة الأمّ */
    public function deadlineGapHours(): float
    {
        return (float) setting('workflow.contribution.internal_deadline_gap_hours', 24);
    }

    public function maxCheckpoints(): int
    {
        return max(0, (int) setting('workflow.contribution.checkpoints_max', 2));
    }

    public function checkpointResponseHours(): float
    {
        return (float) setting('workflow.checkpoint.response_hours', 2);
    }

    public function ownerReviewHours(): float
    {
        return (float) setting('workflow.contribution.owner_review_hours', 24);
    }

    /** أقصى ديدلاين داخليّ مسموح لهذه المهمّة */
    public function latestInternalDeadline(Task $task): ?CarbonImmutable
    {
        if (! $task->deadline_at) {
            return null;
        }

        return CarbonImmutable::parse($task->deadline_at)->subHours($this->deadlineGapHours());
    }

    /** أسباب الإرجاع العشرة (23 — 3.6) — قائمة من الإعدادات لا من الكود */
    public function returnReasons(): array
    {
        $reasons = setting('workflow.review.return_reasons', []);

        return is_array($reasons) && $reasons !== [] ? $reasons : [
            'quality_below' => setting('workflow.contribution_service.return_reasons_1', 'جودة أقلّ من المطلوب'),
            'wrong_data' => setting('workflow.contribution_service.return_reasons_2', 'خطأ في البيانات أو الأرقام'),
            'has_errors' => setting('workflow.contribution_service.return_reasons_3', 'يحتوي على أخطاء'),
            'off_identity' => setting('workflow.contribution_service.return_reasons_4', 'مخالف للهويّة'),
            'copied_or_generated' => setting('workflow.contribution_service.return_reasons_5', 'منقول أو مُولَّد آليًّا بلا مراجعة'),
            'empty_or_dead_link' => setting('workflow.contribution_service.return_reasons_6', 'تسليم فارغ أو رابط لا يفتح'),
            'wrong_format' => setting('workflow.contribution_service.return_reasons_7', 'صيغة مخالفة'),
            'access_closed' => setting('workflow.contribution_service.return_reasons_8', 'صلاحيّة الوصول مغلقة'),
            'out_of_scope' => setting('workflow.contribution_service.return_reasons_9', 'خارج المطلوب'),
            'incomplete' => setting('workflow.contribution_service.return_reasons_10', 'غير مكتمل'),
        ];
    }

    // -------------------------------------------------------------- المعاينة قبل الإرسال

    /**
     * معاينة نهائيّة قبل إرسال الدعوة (23 — القسم 4):
     * مهامّ المدعوّ المفتوحة · مسلَّماته · مساهماته · **ورصيدي بعد الخصم**.
     *
     * @return array<string, mixed>
     */
    public function invitePreview(User $invitee, User $owner, float $vxp, string $source): array
    {
        $balance = FlowLedger::balance($owner);
        $fromOwner = $source === 'owner_balance';
        $after = $fromOwner ? round($balance - $vxp, 2) : $balance;

        $contributions = TaskContribution::query()->where('contributor_id', $invitee->id);

        return [
            'invitee' => $invitee,
            'open_tasks' => Task::query()
                ->where('owner_id', $invitee->id)
                ->whereNotIn('status', ['approved', 'closed'])
                ->count(),
            'delivered_tasks' => Task::query()
                ->where('owner_id', $invitee->id)
                ->whereNotNull('delivered_at')
                ->count(),
            'contributions_total' => (clone $contributions)->count(),
            'contributions_done' => (clone $contributions)->where('status', 'approved')->count(),
            'balance' => $balance,
            'balance_after' => $after,
            'from_owner_balance' => $fromOwner,
            // يُمنَع الإرسال إن لم يكفِ الرصيد — والزرّ يُخفى لا يُعطَّل (2.15-أ-7)
            'sufficient' => ! $fromOwner || $after >= 0,
        ];
    }

    // -------------------------------------------------------------- الدعوة

    /**
     * إرسال الدعوة بعد فحص القيود كلّها.
     *
     * @param  array<string, mixed>  $data
     */
    public function invite(Task $task, User $owner, User $invitee, array $data): TaskContribution
    {
        $deadline = CarbonImmutable::parse($data['internal_deadline_at']);
        $vxp = round((float) ($data['vxp_value'] ?? 0), 2);
        $source = $data['vxp_source'] ?? 'task_pool';
        $checkpoints = array_values(array_filter((array) ($data['checkpoints'] ?? [])));

        $this->guardInternalDeadline($task, $deadline);
        $this->guardCheckpoints($checkpoints, $deadline);
        $this->guardBudget($task, $owner, $vxp, $source);

        return DB::transaction(function () use ($task, $owner, $invitee, $data, $deadline, $vxp, $source, $checkpoints) {
            $held = $source === 'owner_balance' ? $vxp : 0.0;

            $contribution = TaskContribution::create([
                'task_id' => $task->id,
                'contributor_id' => $invitee->id,
                'invited_by' => $owner->id,
                'item_title' => $data['item_title'],
                'instructions' => $data['instructions'] ?? null,
                'deliverable_spec' => $data['deliverable_spec'] ?? null,
                'internal_deadline_at' => $deadline,
                'vxp_value' => $vxp,
                'vxp_source' => $source,
                'held_amount' => $held,
                'status' => 'invited',
                'invited_at' => now(),
            ]);

            foreach ($checkpoints as $index => $scheduledAt) {
                $scheduled = CarbonImmutable::parse($scheduledAt);

                ContributionCheckpoint::create([
                    'task_contribution_id' => $contribution->id,
                    'sequence' => $index + 1,
                    'scheduled_at' => $scheduled,
                    // المهلة تُستهلَك داخل نافذة النشاط وحدها (23 — القسم 4)
                    'response_due_at' => ActivityWindow::addHours($scheduled, $this->checkpointResponseHours()),
                    'status' => 'pending',
                ]);
            }

            // الرصيد المعلَّق يُخصَم لحظة الإرسال بموافقة المالك الصريحة
            if ($held > 0) {
                FlowLedger::debitVxp($owner, $held, 'contribution.hold', $contribution, setting('workflow.contribution_service.invite_1', 'رصيد معلَّق لبند مساهمة'), $owner->id);
            }

            FlowNotifier::send(
                $invitee,
                'contribution',
                strtr(setting('workflow.contribution_service.invite_2', 'دعوة مساهمة: :p1'), [':p1' => (string) ($contribution->item_title)]),
                strtr(setting('workflow.contribution_service.invite_3', 'عدم التسليم = :p1 على درجة الالتزام، وعدم الردّ على تفتيش في مهلته مثله.'), [':p1' => (string) (rep_rule('task.contribution_no_delivery'))]),
                route('volunteer.contributions'),
                $deadline,
                requiresAction: true,
                about: $contribution,
            );

            return $contribution;
        });
    }

    // -------------------------------------------------------------- ردّ المدعوّ

    /** القبول يفتح البند، والرفض يعيده بندًا شخصيًّا للمالك يدعو غيره */
    public function respond(TaskContribution $contribution, bool $accept): TaskContribution
    {
        abort_unless($contribution->status === 'invited', 422, setting('workflow.contribution_service.respond_1', 'تمّ الردّ على هذه الدعوة بالفعل.'));

        $contribution->forceFill([
            'status' => $accept ? 'accepted' : 'rejected',
            'responded_at' => now(),
        ])->save();

        if (! $accept) {
            $this->releaseHold($contribution);
        }

        FlowNotifier::send(
            User::query()->find($contribution->invited_by),
            'contribution',
            ($accept ? setting('workflow.contribution_service.respond_2', 'قبل المساهمة: ') : setting('workflow.contribution_service.respond_3', 'اعتذر عن المساهمة: ')).$contribution->item_title,
            null,
            route('volunteer.contributions'),
            about: $contribution,
        );

        return $contribution;
    }

    // -------------------------------------------------------------- نقاط التفتيش

    /** ردّ المساهم على نقطة تفتيش داخل مهلته — كنظام التذاكر */
    public function respondToCheckpoint(ContributionCheckpoint $checkpoint, User $user, string $body): ContributionCheckpoint
    {
        DB::table('checkpoint_messages')->insert([
            'contribution_checkpoint_id' => $checkpoint->id,
            'user_id' => $user->id,
            'body' => $body,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($checkpoint->status === 'pending') {
            $checkpoint->forceFill([
                'status' => 'answered',
                'responded_at' => now(),
                'response_body' => $body,
            ])->save();
        }

        return $checkpoint;
    }

    /**
     * فوات مهلة الردّ = خصم من `rep_rule('task.checkpoint_missed')`.
     * والمهلة نفسها محسوبة داخل نافذة النشاط، فلا يُخصَم من نائمٍ ليلًا.
     */
    public function runMissedCheckpoints(): int
    {
        $missed = 0;

        ContributionCheckpoint::query()
            ->where('status', 'pending')
            ->whereNotNull('response_due_at')
            ->where('response_due_at', '<=', now())
            ->get()
            ->each(function (ContributionCheckpoint $checkpoint) use (&$missed) {
                $contribution = TaskContribution::query()->find($checkpoint->task_contribution_id);
                $contributor = $contribution ? User::query()->find($contribution->contributor_id) : null;

                $checkpoint->forceFill(['status' => 'missed'])->save();

                if ($contributor) {
                    FlowLedger::rep(
                        $contributor,
                        rep_rule('task.checkpoint_missed'),
                        'contribution.checkpoint_missed',
                        $contribution,
                        setting('workflow.contribution_service.run_missed_checkpoints_1', 'عدم الردّ على نقطة تفتيش في مهلتها'),
                    );
                }

                $missed++;
            });

        return $missed;
    }

    // -------------------------------------------------------------- التسليم والاعتماد

    /** تسليم نهائيّ ⟵ تبدأ مهلة المالك، وبعدها اعتماد تلقائيّ */
    public function deliver(TaskContribution $contribution, array $data): TaskSubmission
    {
        abort_unless(in_array($contribution->status, ['accepted', 'returned'], true), 422, setting('workflow.contribution_service.deliver_1', 'البند غير مفتوح للتسليم.'));

        return DB::transaction(function () use ($contribution, $data) {
            $version = (int) TaskSubmission::query()
                ->where('task_id', $contribution->task_id)
                ->where('user_id', $contribution->contributor_id)
                ->max('version');

            $submission = TaskSubmission::create([
                'task_id' => $contribution->task_id,
                'user_id' => $contribution->contributor_id,
                'version' => $version + 1,
                'body' => $data['body'] ?? null,
                'link' => $data['link'] ?? null,
                'file_path' => $data['file_path'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            $contribution->forceFill([
                'status' => 'delivered',
                'delivered_at' => now(),
                'owner_review_due_at' => now()->addMinutes((int) round($this->ownerReviewHours() * 60)),
            ])->save();

            FlowNotifier::send(
                User::query()->find($contribution->invited_by),
                'contribution',
                strtr(setting('workflow.contribution_service.deliver_2', 'تسليم نهائيّ: :p1'), [':p1' => (string) ($contribution->item_title)]),
                strtr(setting('workflow.contribution_service.deliver_3', 'عندك :p1 ساعة، وبعدها اعتماد تلقائيّ بنقاط المساهم كاملة.'), [':p1' => (string) ($this->ownerReviewHours())]),
                route('volunteer.reviews'),
                $contribution->owner_review_due_at,
                requiresAction: true,
                about: $contribution,
            );

            return $submission;
        });
    }

    /**
     * اعتماد البند — وVXP يُصرَف لحظتها **مستقلًّا عن مصير المهمّة الأمّ**.
     * والاعتماد التلقائيّ يعطي المساهم نقاطه **كاملة**.
     */
    public function approve(TaskContribution $contribution, ?User $by = null, bool $auto = false): TaskContribution
    {
        if ($contribution->status === 'approved') {
            return $contribution;
        }

        return DB::transaction(function () use ($contribution, $by, $auto) {
            $contribution->forceFill([
                'status' => 'approved',
                'approved_at' => now(),
                'auto_approved' => $auto,
                'held_amount' => 0,
            ])->save();

            $contributor = User::query()->find($contribution->contributor_id);

            if ($contributor) {
                FlowLedger::creditVxp(
                    $contributor,
                    (float) $contribution->vxp_value,
                    'contribution.approved',
                    $contribution,
                    $auto ? setting('workflow.contribution_service.approve_1', 'اعتماد تلقائيّ بفوات مهلة المالك — بنقاطك كاملة') : setting('workflow.contribution_service.approve_2', 'اعتماد المالك لبند المساهمة'),
                    $by?->id,
                );

                FlowNotifier::send(
                    $contributor,
                    'contribution',
                    $auto ? setting('workflow.contribution_service.approve_3', 'اتعمد بندك تلقائيًّا ✓') : setting('workflow.contribution_service.approve_4', 'اتعمد بندك ✓'),
                    strtr(setting('workflow.contribution_service.approve_5', 'نقاطك اتصرفت كاملة: :p1 VXP.'), [':p1' => (string) ($contribution->vxp_value)]),
                    route('volunteer.contributions'),
                    about: $contribution,
                );
            }

            return $contribution;
        });
    }

    /** إرجاع البند بنفس أسباب الإرجاع العشرة ومهلة إصلاح مستقلّة */
    public function returnItem(TaskContribution $contribution, User $by, array $data): TaskContribution
    {
        $reasons = $this->returnReasons();

        if (! array_key_exists($data['return_reason_code'] ?? '', $reasons)) {
            throw ValidationException::withMessages(['return_reason_code' => setting('workflow.contribution_service.return_item_1', 'اختر سبب الإرجاع من القائمة.')]);
        }

        if (trim((string) ($data['review_feedback'] ?? '')) === '') {
            throw ValidationException::withMessages(['review_feedback' => setting('workflow.contribution_service.return_item_2', 'الفيدباك المكتوب إجباريّ مع كلّ إرجاع.')]);
        }

        $fixHours = (float) ($data['fix_hours'] ?? setting('workflow.review.fix_hours', 24));

        TaskSubmission::query()
            ->where('task_id', $contribution->task_id)
            ->where('user_id', $contribution->contributor_id)
            ->orderByDesc('version')
            ->limit(1)
            ->update([
                'review_result' => 'returned',
                'return_reason_code' => $data['return_reason_code'],
                'review_feedback' => $data['review_feedback'],
                'fix_due_at' => now()->addMinutes((int) round($fixHours * 60)),
                'reviewed_by' => $by->id,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        $contribution->forceFill(['status' => 'returned', 'owner_review_due_at' => null])->save();

        FlowNotifier::send(
            User::query()->find($contribution->contributor_id),
            'contribution',
            setting('workflow.contribution_service.return_item_3', 'اترجّع بندك للإصلاح'),
            $reasons[$data['return_reason_code']].' — '.$data['review_feedback'],
            route('volunteer.contributions'),
            now()->addMinutes((int) round($fixHours * 60)),
            requiresAction: true,
            about: $contribution,
        );

        return $contribution;
    }

    /** فاتت مهلة المالك ⟵ اعتماد تلقائيّ بنقاط المساهم كاملة (23 — القسم 4) */
    public function runAutoApprovals(): int
    {
        $approved = 0;

        TaskContribution::query()
            ->where('status', 'delivered')
            ->whereNotNull('owner_review_due_at')
            ->where('owner_review_due_at', '<=', now())
            ->get()
            ->each(function (TaskContribution $contribution) use (&$approved) {
                $this->approve($contribution, null, auto: true);
                $approved++;
            });

        return $approved;
    }

    /** فات الديدلاين الداخليّ بلا أيّ تسليم ⟵ خصم عدم تسليم المساهم آليًّا */
    public function runMissedInternalDeadlines(): int
    {
        $missed = 0;

        TaskContribution::query()
            ->where('status', 'accepted')
            ->where('internal_deadline_at', '<=', now())
            ->get()
            ->each(function (TaskContribution $contribution) use (&$missed) {
                $contributor = User::query()->find($contribution->contributor_id);

                $contribution->forceFill(['status' => 'expired'])->save();
                $this->releaseHold($contribution);

                if ($contributor) {
                    FlowLedger::rep(
                        $contributor,
                        rep_rule('task.contribution_no_delivery'),
                        'contribution.no_delivery',
                        $contribution,
                        setting('workflow.contribution_service.run_missed_internal_deadlines_1', 'فوات الديدلاين الداخليّ بلا تسليم'),
                    );
                }

                $missed++;
            });

        return $missed;
    }

    // -------------------------------------------------------------- استعلامات الشاشة

    /** @return Collection<int, ContributionCheckpoint> */
    public function checkpointsOf(TaskContribution $contribution): Collection
    {
        return ContributionCheckpoint::query()
            ->where('task_contribution_id', $contribution->id)
            ->orderBy('sequence')
            ->get();
    }

    /** عدّادات رأس الشاشة: دعوات جديدة · مفتوحة · بانتظار اعتماد المالك · مكتملة */
    public function counters(User $user): array
    {
        $rows = TaskContribution::query()
            ->where('contributor_id', $user->id)
            ->get(['status']);

        return [
            'invited' => $rows->where('status', 'invited')->count(),
            'open' => $rows->whereIn('status', ['accepted', 'returned'])->count(),
            'awaiting' => $rows->where('status', 'delivered')->count(),
            'done' => $rows->where('status', 'approved')->count(),
        ];
    }

    // -------------------------------------------------------------- الحرّاس

    private function guardInternalDeadline(Task $task, CarbonImmutable $deadline): void
    {
        $latest = $this->latestInternalDeadline($task);

        if ($latest && $deadline->greaterThan($latest)) {
            throw ValidationException::withMessages([
                'internal_deadline_at' => strtr(setting('workflow.contribution_service.guard_internal_deadline_1', 'الديدلاين الداخليّ لازم يكون قبل ديدلاين المهمّة بـ:p1 ساعة على الأقلّ — أقصى موعد: :p2.'), [':p1' => (string) ($this->deadlineGapHours()), ':p2' => (string) ($latest->format('Y-m-d H:i'))]),
            ]);
        }

        if ($deadline->isPast()) {
            throw ValidationException::withMessages([
                'internal_deadline_at' => setting('workflow.contribution_service.guard_internal_deadline_2', 'اختر موعدًا في المستقبل — الموعد ده عدّى.'),
            ]);
        }
    }

    private function guardCheckpoints(array $checkpoints, CarbonImmutable $deadline): void
    {
        if (count($checkpoints) > $this->maxCheckpoints()) {
            throw ValidationException::withMessages([
                'checkpoints' => strtr(setting('workflow.contribution_service.guard_checkpoints_1', 'نقاط التفتيش :p1 كحدّ أقصى.'), [':p1' => (string) ($this->maxCheckpoints())]),
            ]);
        }

        foreach ($checkpoints as $checkpoint) {
            if (CarbonImmutable::parse($checkpoint)->greaterThanOrEqualTo($deadline)) {
                throw ValidationException::withMessages([
                    'checkpoints' => setting('workflow.contribution_service.guard_checkpoints_2', 'نقطة التفتيش لازم تكون قبل الديدلاين الداخليّ.'),
                ]);
            }
        }
    }

    /**
     * مصدر VXP: وعاء المهمّة أو رصيد المالك — ومن رصيده يُعرَض الخصم صراحةً
     * ويُمنَع الإرسال إن لم يكفِ (23 — القسم 4).
     */
    private function guardBudget(Task $task, User $owner, float $vxp, string $source): void
    {
        if ($vxp <= 0) {
            return;
        }

        if ($source === 'owner_balance') {
            if (FlowLedger::available() && FlowLedger::balance($owner) < $vxp) {
                throw ValidationException::withMessages([
                    'vxp_value' => setting('workflow.contribution_service.guard_budget_1', 'رصيدك مايكفّيش القيمة دي — قلّلها أو خدها من وعاء المهمّة.'),
                ]);
            }

            return;
        }

        // مجموع ما يوزّعه الأب ≤ وعاء مهمّته (23 — 3.9-٥)
        $allocated = (float) TaskContribution::query()
            ->where('task_id', $task->id)
            ->where('vxp_source', 'task_pool')
            ->whereNotIn('status', ['rejected', 'withdrawn', 'expired'])
            ->sum('vxp_value');

        $minShare = (float) setting('workflow.vxp.parent_min_share_percent', 10);
        $pool = (float) $task->vxp_value;
        $available = round($pool * (100 - $minShare) / 100, 2);

        if ($allocated + $vxp > $available) {
            throw ValidationException::withMessages([
                'vxp_value' => strtr(setting('workflow.contribution_service.guard_budget_2', 'وعاء المهمّة مايسمحش — المتاح للتوزيع :p1 VXP، ووزّعت منه :p2.'), [':p1' => (string) ($available), ':p2' => (string) ($allocated)]),
            ]);
        }
    }

    private function releaseHold(TaskContribution $contribution): void
    {
        $held = (float) $contribution->held_amount;

        if ($held <= 0) {
            return;
        }

        $owner = User::query()->find($contribution->invited_by);

        if ($owner) {
            FlowLedger::creditVxp($owner, $held, 'contribution.hold_released', $contribution, setting('workflow.contribution_service.release_hold_1', 'تحرير الرصيد المعلَّق'), $owner->id);
        }

        $contribution->forceFill(['held_amount' => 0])->save();
    }
}
