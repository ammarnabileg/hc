<?php

namespace App\Services\Volunteer\Tasks;

use App\Models\Task;
use App\Models\TaskBlock;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * التعثّر بنوعيه (الدستور 23-3.4):
 *  1) مدّة يختارها صاحب المهمّة بحدّ أقصى من الإعدادات + سبب إلزاميّ.
 *  2) «يعتمد على (Blocked By)» — مستثناة من حدّ المدّة.
 *
 * ⭐ الإعادة مرّتين فقط، والثانية **تُنصِّف مكافأة Rep** المستحقّة على المهمّة نفسها
 *    — والخصومات السالبة كما هي لا تتغيّر.
 */
class TaskBlockService
{
    public function __construct(private readonly LedgerBridge $bridge) {}

    /** أقصى مدّة تعثّر بالأيّام (إعداد) */
    public function maxDays(): int
    {
        return (int) setting('workflow.blocked.max_days', 3);
    }

    /** أقصى عدد مرّات تعثّر لنفس المهمّة (إعداد) */
    public function maxBlocks(): int
    {
        return (int) setting('workflow.blocked.max_retries', 2);
    }

    /** معامل مكافأة Rep بعد التعثّر الثاني (إعداد — النصف افتراضيًّا) */
    public function halvedMultiplier(): float
    {
        return (float) setting('workflow.blocked.second_block_reward_multiplier', 0.5);
    }

    /**
     * معامل المكافأة الموجبة على هذه المهمّة:
     * 1.0 عاديًّا، والنصف بعد التعثّر الثاني فأكثر.
     */
    public function repRewardMultiplier(Task $task): float
    {
        return (int) $task->blocked_count >= 2 ? $this->halvedMultiplier() : 1.0;
    }

    /**
     * تسجيل تعثّر — يقف الديدلاين بموافقة المراجِع على محرّك التصعيد.
     *
     * @param  string  $type  duration · dependency
     *
     * @throws ValidationException
     */
    public function block(
        Task $task,
        User $user,
        string $type,
        string $reason,
        ?int $days = null,
        ?Task $blockingTask = null,
    ): TaskBlock {
        if ((int) $task->blocked_count >= $this->maxBlocks()) {
            throw ValidationException::withMessages([
                'block' => 'المهمّة اتعثّرت '.$this->maxBlocks().' مرّات وده الحدّ — كلّم مراجعك أو اطلب تحكيمًا.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'السبب إلزاميّ — اكتب بوضوح إنت مستنّي إيه.',
            ]);
        }

        if ($type === 'duration') {
            $days = (int) $days;

            if ($days < 1 || $days > $this->maxDays()) {
                throw ValidationException::withMessages([
                    'days' => 'مدّة التعثّر لازم تكون من يوم لـ'.$this->maxDays().' أيّام — اختر مدّة جوّه الحدّ.',
                ]);
            }
        } elseif (! $blockingTask) {
            throw ValidationException::withMessages([
                'blocking_task_id' => 'اختر المهمّة اللي بتستنّاها — «يعتمد على» لازم يشير لمهمّة بعينها.',
            ]);
        }

        $block = TaskBlock::create([
            'task_id' => $task->id,
            'type' => $type,
            'days' => $type === 'duration' ? $days : null,
            'reason' => $reason,
            'blocking_task_id' => $blockingTask?->id,
            'status' => 'pending',
            // تعود «قيد التنفيذ» تلقائيًّا بعد المدّة، والتبعيّة تتحرّر باعتماد الأصل
            'resume_at' => $type === 'duration' ? now()->addDays((int) $days) : null,
        ]);

        $task->forceFill([
            'status' => TaskStatus::BLOCKED,
            'blocked_count' => (int) $task->blocked_count + 1,
            'blocked_by_task_id' => $blockingTask?->id ?? $task->blocked_by_task_id,
        ])->save();

        $this->openEscalation($task, $user, 'blocked', $block);

        if ($task->reviewer_id && $task->reviewer) {
            $this->bridge->notify(
                $task->reviewer,
                'task_blocked',
                'تعثّر على مهمّة: '.$task->title,
                $reason,
                route('volunteer.tasks.show', $task),
                $task,
                true,
            );
        }

        return $block;
    }

    /** تنبيه ما قبل الضغط: هل الإعادة القادمة ستنصّف المكافأة؟ */
    public function nextBlockHalvesReward(Task $task): bool
    {
        return (int) $task->blocked_count + 1 >= 2;
    }

    /** فتح حالة على محرّك التصعيد — إن كان جدوله مهيّأً (23-5) */
    private function openEscalation(Task $task, User $user, string $caseType, TaskBlock $subject): void
    {
        if (! Schema::hasTable('escalations')) {
            return;
        }

        \App\Models\Escalation::create([
            'case_type' => $caseType,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'requested_by' => $user->id,
            'current_handler_id' => $task->reviewer_id,
            'level' => 1,
            'window_due_at' => now()->addHours((int) setting('workflow.escalation.window_hours', 24)),
            'status' => 'open',
        ]);
    }
}
