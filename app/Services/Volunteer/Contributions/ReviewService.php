<?php

namespace App\Services\Volunteer\Contributions;

use App\Models\Escalation;
use App\Models\InternalLibraryItem;
use App\Models\Task;
use App\Models\TaskContribution;
use App\Models\TaskSubmission;
use App\Models\User;
use App\Services\Volunteer\Escalation\CaseCatalog;
use App\Services\Volunteer\Escalation\EscalationEngine;
use App\Services\Volunteer\Escalation\FlowLedger;
use App\Services\Volunteer\Escalation\FlowNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «بانتظار مراجعتي» (الدستور 24.4 · 23 — 3.6/3.7).
 *
 * مبدأ حاكم: **الساعة تقف لحظة التسليم لا لحظة الاعتماد** — فزمن المراجعة
 * لا يُحمَّل على المنفّذ إطلاقًا، بل يقع على **مؤشّر المراجِع** (متوسّط زمن
 * مراجعته) وعلى محرّك التصعيد.
 */
class ReviewService
{
    public function __construct(
        private readonly ContributionService $contributions,
        private readonly EscalationEngine $engine,
    ) {}

    /** نافذة مراجعة كلّ عنصر — 24 ساعة، إعداد لا رقم محروق */
    public function windowHours(): float
    {
        return (float) setting('workflow.escalation.window_hours', 24);
    }

    /** بعد كم إرجاع يتصعّد القرار التالي؟ (منع البينج بونج — 23 · 3.6) */
    public function escalateAfterReturns(): int
    {
        return max(1, (int) setting('workflow.review.escalate_after_returns', 2));
    }

    public function returnReasons(): array
    {
        return $this->contributions->returnReasons();
    }

    // -------------------------------------------------------------- الطابور

    /**
     * طابور المراجعة موحَّدًا: تسليمات المهامّ · تسليمات المساهمين ·
     * والحالات التي تنتظر قراري على محرّك التصعيد.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function queueFor(User $reviewer): Collection
    {
        $rows = collect();

        // (أ) تسليمات المهامّ التي أراجعها
        Task::query()
            ->where('reviewer_id', $reviewer->id)
            ->whereIn('status', ['delivered', 'in_review'])
            ->orderBy('delivered_at')
            ->get()
            ->each(function (Task $task) use ($rows) {
                $submission = $this->latestSubmission($task);
                $due = CarbonImmutable::parse($task->delivered_at ?? $task->updated_at)
                    ->addMinutes((int) round($this->windowHours() * 60));

                $rows->push([
                    'kind' => 'task_submission',
                    'key' => 'task:'.$task->id,
                    'id' => $task->id,
                    'title' => $task->title,
                    'user' => $task->owner_id ? User::query()->find($task->owner_id) : null,
                    'task' => $task,
                    'entity_id' => $task->entity_id,
                    'spec' => $task->deliverable_spec,
                    'link' => $submission?->link,
                    'submission' => $submission,
                    'returns' => (int) $task->return_count,
                    'due_at' => $due,
                    'state' => $this->engine->windowState($due),
                ]);
            });

        // (ب) تسليمات المساهمين على بنودي — مهلة المالك ثمّ اعتماد تلقائيّ
        TaskContribution::query()
            ->where('invited_by', $reviewer->id)
            ->where('status', 'delivered')
            ->orderBy('owner_review_due_at')
            ->get()
            ->each(function (TaskContribution $contribution) use ($rows) {
                $task = Task::query()->find($contribution->task_id);

                $rows->push([
                    'kind' => 'contribution',
                    'key' => 'contribution:'.$contribution->id,
                    'id' => $contribution->id,
                    'title' => $contribution->item_title,
                    'user' => User::query()->find($contribution->contributor_id),
                    'task' => $task,
                    'entity_id' => $task?->entity_id,
                    'spec' => $contribution->deliverable_spec,
                    'link' => $this->latestSubmission($task, $contribution->contributor_id)?->link,
                    'submission' => $this->latestSubmission($task, $contribution->contributor_id),
                    'returns' => 0,
                    'due_at' => $contribution->owner_review_due_at
                        ? CarbonImmutable::parse($contribution->owner_review_due_at)
                        : null,
                    'state' => $this->engine->windowState($contribution->owner_review_due_at),
                ]);
            });

        // (ج) حالات محرّك التصعيد المنتظِرة قراري (تمديد · تعثّر · اعتذار · سحب · دفعة)
        $this->engine->deskOf($reviewer)
            ->whereIn('case_type', [
                CaseCatalog::EXTENSION,
                CaseCatalog::BLOCKED,
                CaseCatalog::APOLOGY,
                CaseCatalog::CONTRIBUTOR_WITHDRAW,
                CaseCatalog::SUBTASK_BATCH,
            ])
            ->get()
            ->each(function (Escalation $escalation) use ($rows) {
                $subject = $this->engine->subjectOf($escalation);

                $rows->push([
                    'kind' => 'escalation',
                    'key' => 'escalation:'.$escalation->id,
                    'id' => $escalation->id,
                    'title' => CaseCatalog::label($escalation->case_type),
                    'user' => $this->engine->requesterOf($escalation),
                    'task' => $subject instanceof Task ? $subject : null,
                    'entity_id' => $subject instanceof Task ? $subject->entity_id : null,
                    'spec' => null,
                    'link' => null,
                    'submission' => null,
                    'returns' => 0,
                    'escalation' => $escalation,
                    'due_at' => CarbonImmutable::parse($escalation->window_due_at),
                    'state' => $this->engine->windowState($escalation->window_due_at),
                ]);
            });

        return $rows->sortBy(fn ($row) => $row['due_at']?->timestamp ?? PHP_INT_MAX)->values();
    }

    /** ثلاثة عدّادات ملوّنة: داخل النافذة · اقتربت · فاتت */
    public function counters(Collection $queue): array
    {
        return [
            'ok' => $queue->where('state', 'ok')->count(),
            'warn' => $queue->where('state', 'warn')->count(),
            'danger' => $queue->where('state', 'danger')->count(),
        ];
    }

    /** متوسّط زمن مراجعتي — مؤشّر عليّ أنا لا على المنفّذ (23 — 3.6) */
    public function averageReviewHours(User $reviewer): ?float
    {
        $rows = TaskSubmission::query()
            ->where('reviewed_by', $reviewer->id)
            ->whereNotNull('reviewed_at')
            ->get(['created_at', 'reviewed_at']);

        if ($rows->isEmpty()) {
            return null;
        }

        $total = $rows->sum(fn ($row) => CarbonImmutable::parse($row->created_at)->diffInMinutes($row->reviewed_at, absolute: true));

        return round($total / $rows->count() / 60, 1);
    }

    // -------------------------------------------------------------- الاعتماد والإرجاع

    /**
     * اعتماد التسليم + سويتش «أضِف المخرج للمكتبة الداخليّة» بمستوى وصول
     * يحدّده دايركتور الكيان لحظة الاعتماد (23 — 3.3).
     */
    public function approveTask(Task $task, User $reviewer, array $data = []): Task
    {
        return DB::transaction(function () use ($task, $reviewer, $data) {
            $submission = $this->latestSubmission($task);

            $submission?->forceFill([
                'review_result' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();

            $alreadyApproved = $task->status === 'approved';

            $task->forceFill(['status' => 'approved', 'approved_at' => now()])->save();

            if (! $alreadyApproved) {
                $this->applyDeliveryRep($task, $reviewer);
            }

            if (! empty($data['add_to_library'])) {
                $this->addToLibrary($task, $reviewer, $data);
            }

            FlowNotifier::send(
                $task->owner_id ? User::query()->find($task->owner_id) : null,
                'task',
                'اتعمدت مهمّتك ✓',
                $task->title,
                route('volunteer.reviews'),
                about: $task,
            );

            return $task;
        });
    }

    /**
     * الإرجاع: فيدباك مكتوب إجباريّ + تصنيف السبب من العشرة + **مهلة إصلاح
     * مستقلّة** لها سلّمها المستقلّ — ولا يُحاسَب المنفّذ مرّتين على ديدلاين واحد.
     * وبعد إرجاعين يتصعّد القرار التالي (الحالة 8).
     */
    public function returnTask(Task $task, User $reviewer, array $data): Task
    {
        $reasons = $this->returnReasons();

        if (! array_key_exists($data['return_reason_code'] ?? '', $reasons)) {
            throw ValidationException::withMessages(['return_reason_code' => 'اختر سبب الإرجاع من القائمة.']);
        }

        if (trim((string) ($data['review_feedback'] ?? '')) === '') {
            throw ValidationException::withMessages(['review_feedback' => 'الفيدباك المكتوب إجباريّ مع كلّ إرجاع.']);
        }

        $fixHours = (float) ($data['fix_hours'] ?? setting('workflow.review.fix_hours', 24));
        $fixDue = now()->addMinutes((int) round($fixHours * 60));

        return DB::transaction(function () use ($task, $reviewer, $data, $reasons, $fixDue) {
            $this->latestSubmission($task)?->forceFill([
                'review_result' => 'returned',
                'return_reason_code' => $data['return_reason_code'],
                'review_feedback' => $data['review_feedback'],
                'fix_due_at' => $fixDue,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();

            $task->forceFill([
                'status' => 'returned',
                'return_count' => (int) $task->return_count + 1,
                'delivered_at' => null,
            ])->save();

            FlowNotifier::send(
                $task->owner_id ? User::query()->find($task->owner_id) : null,
                'task',
                'اترجّعت مهمّتك للإصلاح',
                $reasons[$data['return_reason_code']].' — '.$data['review_feedback'],
                route('volunteer.reviews'),
                $fixDue,
                requiresAction: true,
                about: $task,
            );

            // إرجاعان ⟵ القرار التالي يتصعّد للأبلاين الأعلى (منع البينج بونج)
            if ((int) $task->return_count >= $this->escalateAfterReturns()
                && $this->engine->openFor($task, CaseCatalog::REPEATED_RETURN)->isEmpty()) {
                $this->engine->open(CaseCatalog::REPEATED_RETURN, $task, $reviewer, [
                    'return_count' => (int) $task->return_count,
                ]);
            }

            return $task;
        });
    }

    // -------------------------------------------------------------- شاشة الدفعة

    /** صب-تاسكات الدفعة تحت مهمّة أمّ — تُعرَض معًا في شاشة واحدة */
    public function batchItems(Task $parent): Collection
    {
        return Task::query()
            ->where('parent_task_id', $parent->id)
            ->orderBy('deadline_at')
            ->get();
    }

    /** تعديل مباشر على بند — استثناء منصوص، ويُسجَّل في سجلّ النسخ */
    public function editBatchItem(Task $item, User $editor, array $data): Task
    {
        $before = $item->only(['title', 'brief', 'deadline_at', 'vxp_value']);

        $item->forceFill(array_filter([
            'title' => $data['title'] ?? null,
            'brief' => $data['brief'] ?? null,
            'deadline_at' => $data['deadline_at'] ?? null,
            'vxp_value' => $data['vxp_value'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''))->save();

        $this->recordVersion($item, $editor, $before, $item->only(['title', 'brief', 'deadline_at', 'vxp_value']));

        return $item;
    }

    /** حذف بند من الدفعة يرجعه مسودّةً لصاحبه بملاحظة — ويُعتمَد الباقي */
    public function removeBatchItem(Task $item, User $actor, ?string $note = null): Task
    {
        $item->forceFill(['batch_status' => 'draft'])->save();

        FlowNotifier::send(
            $item->owner_id ? User::query()->find($item->owner_id) : null,
            'task',
            'بندك رجع مسودّة',
            $note,
            route('volunteer.reviews'),
            about: $item,
        );

        $this->recordVersion($item, $actor, ['batch_status' => 'pending_review'], ['batch_status' => 'draft']);

        return $item;
    }

    // -------------------------------------------------------------- داخليّ

    public function latestSubmission(?Task $task, ?int $userId = null): ?TaskSubmission
    {
        if (! $task) {
            return null;
        }

        return TaskSubmission::query()
            ->where('task_id', $task->id)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('version')
            ->first();
    }

    /**
     * سلّم درجة الالتزام على **وقت التسليم** لا وقت الاعتماد (23 — 3.7):
     * قبل الموعد +0.25 · تأخير أقلّ من 24 ساعة −0.25 · بعدها عدم تسليم.
     */
    private function applyDeliveryRep(Task $task, User $reviewer): void
    {
        $owner = $task->owner_id ? User::query()->find($task->owner_id) : null;

        if (! $owner || ! $task->delivered_at || ! $task->deadline_at) {
            return;
        }

        $delivered = CarbonImmutable::parse($task->delivered_at);
        $deadline = CarbonImmutable::parse($task->deadline_at);
        $lateHours = $delivered->greaterThan($deadline) ? $delivered->diffInHours($deadline, absolute: true) : 0;
        $graceHours = (float) setting('workflow.escalation.window_hours', 24);

        $value = match (true) {
            $lateHours === 0 => rep_rule('task.early'),
            $lateHours < $graceHours => rep_rule('task.late_under_24h'),
            default => rep_rule('task.no_delivery'),
        };

        FlowLedger::rep($owner, $value, 'task.delivery', $task, 'تقييم التسليم على وقت التسليم', $reviewer->id);
    }

    private function addToLibrary(Task $task, User $reviewer, array $data): void
    {
        $levels = ['entity', 'all_volunteers', 'restricted'];
        $level = in_array($data['access_level'] ?? '', $levels, true) ? $data['access_level'] : 'entity';

        InternalLibraryItem::create([
            'task_id' => $task->id,
            'entity_id' => $task->entity_id,
            'owner_id' => $task->owner_id,
            'title' => $task->title,
            'type' => $data['library_type'] ?? 'document',
            'content_text' => $this->latestSubmission($task)?->body,
            'access_level' => $level,
            'approved_at' => now(),
        ]);
    }

    /** سجلّ النسخ: مَن عدّل · متى · ماذا كان — سجلّ آليّ لا يُعدَّل ولا يُحذَف */
    private function recordVersion(Task $item, User $editor, array $before, array $after): void
    {
        if (! DB::getSchemaBuilder()->hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert([
            'user_id' => $editor->id,
            'action' => 'subtasks.edit',
            'auditable_type' => $item->getMorphClass(),
            'auditable_id' => $item->id,
            'old_values' => json_encode($before, JSON_UNESCAPED_UNICODE),
            'new_values' => json_encode($after, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
