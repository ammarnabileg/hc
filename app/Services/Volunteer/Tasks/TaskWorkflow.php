<?php

namespace App\Services\Volunteer\Tasks;

use App\Models\Escalation;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * أفعال المهمّة: تسليم · تمديد · اعتذار · رفع علم «متأخّر بسبب…» (الدستور 23-3).
 *
 * ⭐ قاعدة ساعة الديدلاين (23-3.7): **الساعة تقف لحظة التسليم** لا لحظة الاعتماد،
 *    والتقييم يُحسَب على وقت التسليم — فزمن المراجعة لا يُحمَّل على المنفّذ إطلاقًا.
 */
class TaskWorkflow
{
    public function __construct(
        private readonly LedgerBridge $bridge,
        private readonly TaskBlockService $blocks,
    ) {}

    /**
     * تسليم المهمّة — يقف العدّاد ويُسجَّل أثر Rep فورًا بسببه ومرجعه.
     */
    public function deliver(Task $task, User $user, array $payload): TaskSubmission
    {
        if (in_array($task->status, [TaskStatus::APPROVED, TaskStatus::CLOSED], true)) {
            throw ValidationException::withMessages([
                'delivery' => 'المهمّة مقفولة بالفعل — مفيش تسليم جديد عليها.',
            ]);
        }

        if (blank($payload['body'] ?? null) && blank($payload['link'] ?? null) && blank($payload['file_path'] ?? null)) {
            throw ValidationException::withMessages([
                'delivery' => 'التسليم فاضي — ارفع ملفًّا أو حطّ رابطًا أو اكتب المخرج.',
            ]);
        }

        return DB::transaction(function () use ($task, $user, $payload) {
            $deliveredAt = now();

            $submission = TaskSubmission::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'version' => TaskSubmission::where('task_id', $task->id)->count() + 1,
                'body' => $payload['body'] ?? null,
                'link' => $payload['link'] ?? null,
                'file_path' => $payload['file_path'] ?? null,
                'note' => $payload['note'] ?? null,
            ]);

            $task->forceFill([
                'delivered_at' => $deliveredAt,
                'status' => TaskStatus::DELIVERED,
            ])->save();

            $this->recordDeliveryRep($task->refresh(), $user);

            if ($task->reviewer_id && $task->reviewer) {
                $this->bridge->notify(
                    $task->reviewer,
                    'task_delivered',
                    'تسليم جديد: '.$task->title,
                    'العدّاد وقف لحظة التسليم — المراجعة عليك.',
                    route('volunteer.tasks.show', $task),
                    $task,
                    true,
                );
            }

            return $submission;
        });
    }

    /**
     * ⭐ العدّاد الذي يُحاسَب عليه صاحب المهمّة فعلًا (23-3.9-3):
     * **الأقرب** بين ديدلاينه ونافذة دمجه. فمَن اعتُمد آخر أبنائه اليوم يبدأ
     * عدّاده الشخصيّ فورًا — «المماطل في منتصف السلسلة مكشوف ومخصوم في يومه»،
     * ولا ينتظر أحدٌ نهاية المهمّة الكبيرة ليُعرَف مَن عطّل.
     */
    public function effectiveDeadline(Task $task): ?Carbon
    {
        $deadline = $task->deadline_at ? Carbon::parse($task->deadline_at) : null;
        $merge = $task->merge_window_at ? Carbon::parse($task->merge_window_at) : null;

        if (! $deadline || ! $merge) {
            return $deadline ?? $merge;
        }

        return $merge->lessThan($deadline) ? $merge : $deadline;
    }

    /**
     * قيمة Rep المستحقّة على التسليم — بجدول 13.4-ن-أ ومعامل التعثّر الثاني.
     * والقيم كلّها من `rep_rule()` — ولا رقم محروق.
     *
     * ⭐ ومَن رفع علم «متأخّر بسبب [ابن]» قبل فوات نافذته **لا يُخصَم منه شيء**
     *    (23-3.9-4) — العلم بضوابطه الأربعة، وهنا موضع قراءته.
     */
    public function deliveryRepValue(Task $task): float
    {
        $deliveredAt = $task->delivered_at ? Carbon::parse($task->delivered_at) : now();
        $deadline = $this->effectiveDeadline($task);

        if (! $deadline || $deliveredAt->lessThanOrEqualTo($deadline)) {
            // المكافأة الموجبة وحدها تُنصَّف بعد التعثّر الثاني — الخصومات كما هي
            return round(rep_rule('task.early') * $this->blocks->repRewardMultiplier($task), 4);
        }

        if ($task->late_due_to_child) {
            return 0.0;
        }

        $lateHours = $deadline->diffInHours($deliveredAt);

        return $lateHours < $this->graceHours()
            ? rep_rule('task.late_under_24h')
            : rep_rule('task.no_delivery');
    }

    /** مهلة «تأخير أقلّ من 24 ساعة» — إعداد لا رقم محروق (2.13) */
    public function graceHours(): float
    {
        return (float) setting('workflow.escalation.window_hours', 24);
    }

    /**
     * طلب تمديد: قبل الديدلاين فقط · مرّة واحدة · بسبب مكتوب · بموافقة المراجِع.
     */
    public function requestExtension(Task $task, User $user, string $newDeadline, string $reason): void
    {
        $max = (int) setting('workflow.extension.max_per_task', 1);

        if ($task->deadline_at && Carbon::parse($task->deadline_at)->isPast()) {
            throw ValidationException::withMessages([
                'extension' => 'الديدلاين فات — مفيش طلب تمديد بعده. سلّم وسجّل سببك، أو اطلب اعتذارًا.',
            ]);
        }

        if ((int) $task->extension_count >= $max) {
            throw ValidationException::withMessages([
                'extension' => 'التمديد مرّة واحدة للمهمّة، وإنت استعملتها — كلّم مراجعك.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'السبب إلزاميّ — اشرح باختصار ليه محتاج وقتًا زيادة.',
            ]);
        }

        $requested = Carbon::parse($newDeadline);

        if ($task->deadline_at && $requested->lessThanOrEqualTo(Carbon::parse($task->deadline_at))) {
            throw ValidationException::withMessages([
                'new_deadline' => 'التاريخ الجديد لازم يكون بعد الديدلاين الحاليّ.',
            ]);
        }

        $task->forceFill(['extension_count' => (int) $task->extension_count + 1])->save();

        // القديم يتحفظ والجديد لا يسري إلّا بموافقة المراجِع على محرّك التصعيد (23-3.5)
        $this->openEscalation(
            $task,
            $user,
            'extension',
            'طلب: تمديد إلى '.$requested->format('Y-m-d H:i').' — السبب: '.$reason,
        );

        if ($task->reviewer) {
            $this->bridge->notify(
                $task->reviewer,
                'task_extension',
                'طلب تمديد على: '.$task->title,
                $reason,
                route('volunteer.tasks.show', $task),
                $task,
                true,
            );
        }
    }

    /** اعتذار — يُعرَض على الأبلاين، وقيمته تُطبَّق عند القبول لا عند الطلب (13.4-ن-أ) */
    public function apologize(Task $task, User $user, string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'اكتب سبب الاعتذار — المراجِع محتاج يفهم الموقف.',
            ]);
        }

        $this->openEscalation($task, $user, 'apology', 'طلب: اعتذار — السبب: '.$reason);

        if ($task->reviewer) {
            $this->bridge->notify(
                $task->reviewer,
                'task_apology',
                'اعتذار عن: '.$task->title,
                $reason,
                route('volunteer.tasks.show', $task),
                $task,
                true,
            );
        }
    }

    /**
     * علم «متأخّر بسبب [ابن]» (23-3.9-4): مرّة واحدة · قبل فوات نافذة صاحبه ·
     * ويشير لابنٍ محدَّد — **والنظام يتحقّق أنّ الابن متأخّر فعلًا وإلّا رُفض العلم**.
     */
    public function flagLateDueToChild(Task $task, Task $child, User $user): void
    {
        if ($task->late_due_to_child) {
            throw ValidationException::withMessages([
                'flag' => 'العلم اترفع مرّة واحدة على المهمّة دي بالفعل.',
            ]);
        }

        if ((int) $child->parent_task_id !== (int) $task->id) {
            throw ValidationException::withMessages([
                'flag' => 'العلم لازم يشير لصب-تاسك تابع للمهمّة دي.',
            ]);
        }

        if ($task->deadline_at && Carbon::parse($task->deadline_at)->isPast()) {
            throw ValidationException::withMessages([
                'flag' => 'نافذتك فاتت — العلم يُرفَع قبل فواتها، وبعدها الحساب على إدارة الشغل.',
            ]);
        }

        if (! $this->isLate($child)) {
            throw ValidationException::withMessages([
                'flag' => 'الصب-تاسك ده مش متأخّر فعلًا — العلم بيتقبل على ابنٍ متأخّر بس.',
            ]);
        }

        $task->forceFill(['late_due_to_child' => true])->save();

        $this->bridge->notify(
            $user,
            'task_flag',
            'اترفع علم على: '.$task->title,
            'اتسجّل إنّ التأخير بسبب: '.$child->title,
            route('volunteer.tasks.show', $task),
            $task,
        );
    }

    /** هل المهمّة متأخّرة الآن؟ — الساعة تقف بالتسليم فلا تأخير بعده */
    public function isLate(Task $task): bool
    {
        $deadline = $this->effectiveDeadline($task);

        if (! $deadline) {
            return false;
        }

        if ($task->delivered_at) {
            return Carbon::parse($task->delivered_at)->greaterThan($deadline);
        }

        return $deadline->isPast()
            && ! in_array($task->status, [TaskStatus::APPROVED, TaskStatus::CLOSED], true);
    }

    /** حالة العدّاد الملوّن (2.16): أخضر متّسع · أصفر اقترب · أحمر فات */
    public function counterState(Task $task): string
    {
        $deadline = $this->effectiveDeadline($task);

        if (! $deadline) {
            return 'idle';
        }

        if ($this->isLate($task)) {
            return 'danger';
        }

        if ($task->delivered_at) {
            return 'ok';
        }

        $soonHours = (float) setting('workflow.deadline.soon_hours', 24);

        return now()->diffInHours($deadline, absolute: true) <= $soonHours ? 'warn' : 'ok';
    }

    /**
     * ⭐ **المصدر الواحد لحركة تسليم المهمّة** (23-3.7): الساعة تقف هنا،
     * والتقييم يُحسَب على وقت التسليم، والحركة تُكتَب **مرّةً واحدة** بمفتاح
     * واقعتها (المهمّة + نسخة التسليم). ولا يكتب الاعتمادُ حركةً ثانية —
     * زمن المراجعة لا يُحمَّل على المنفّذ أصلًا، فلا معنى لأن يعيد تقييمه.
     */
    private function recordDeliveryRep(Task $task, User $user): void
    {
        $version = (int) TaskSubmission::query()->where('task_id', $task->id)->max('version');
        $value = $this->deliveryRepValue($task);
        $halved = $this->blocks->repRewardMultiplier($task) < 1 && $value > 0;

        RepOnce::record(
            RepOnce::deliveryKey((int) $task->id, $version),
            fn () => $this->bridge->record(
                $user,
                'rep',
                $value,
                'task',
                'تسليم مهمّة: '.$task->title.($halved ? ' (المكافأة منصَّفة بعد التعثّر الثاني)' : ''),
                $task,
                $task->entity_id,
            ),
        );

        if ((float) $task->vxp_value > 0 && $value > 0) {
            RepOnce::record(
                'task.vxp:'.$task->id,
                fn () => $this->bridge->record(
                    $user,
                    'vxp',
                    (float) $task->vxp_value,
                    'task',
                    'مهمّة مُسلَّمة: '.$task->title,
                    $task,
                    $task->entity_id,
                ),
            );
        }
    }

    private function openEscalation(Task $task, User $user, string $caseType, string $note): void
    {
        if (! Schema::hasTable('escalations')) {
            return;
        }

        Escalation::create([
            'case_type' => $caseType,
            'subject_type' => $task->getMorphClass(),
            'subject_id' => $task->getKey(),
            'requested_by' => $user->id,
            'current_handler_id' => $task->reviewer_id,
            'level' => 1,
            'window_due_at' => now()->addHours((int) setting('workflow.escalation.window_hours', 24)),
            'status' => 'open',
            // لا عمود لحمولة الطلب في الجدول المشترك، فنكتبها هنا موثّقةً حتى يقرأها المراجِع
            'decision_note' => $note,
        ]);
    }
}
