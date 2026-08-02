<?php

namespace App\Services\Volunteer\Tasks;

use App\Models\Escalation;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * دفعة الصب-تاسكات (الدستور 23-2.3 · 23-3.9).
 *
 * ⭐ قيد الحصّة عند التفكيك: **أقصى ديدلاين بين الأبناء + نافذة دمج الأب ≤ ديدلاين الأب**
 *    — وإلّا رُفض الحفظ. فمَن يضخّم ديدلاين مَن تحته يأكل من نافذة دمجه هو.
 * ⭐ وقيد الوعاء: مجموع ما يوزّعه على أبنائه ≤ وعاء مهمّته ناقصَ شريحته المحفوظة.
 */
class SubtaskBatch
{
    /** نافذة الدمج والتسليم للأب بالساعات (إعداد) */
    public function mergeWindowHours(): int
    {
        return (int) setting('workflow.merge_window_hours', 24);
    }

    /** أدنى شريحة محفوظة للأب من وعاء مهمّته (%) */
    public function parentMinSharePercent(): float
    {
        return (float) setting('workflow.vxp.parent_min_share_percent', 10);
    }

    /** أقصى ديدلاين مسموح للأبناء = ديدلاين الأب − نافذة الدمج */
    public function latestAllowedChildDeadline(Task $parent): ?Carbon
    {
        if (! $parent->deadline_at) {
            return null;
        }

        return Carbon::parse($parent->deadline_at)->subHours($this->mergeWindowHours());
    }

    /**
     * فحص الدفعة قبل الحفظ — ورسالة الرفض تشرح القيد نفسه (2.15-د · 2.17-ب).
     *
     * @param  array<int, array<string, mixed>>  $rows
     *
     * @throws ValidationException
     */
    public function validate(Task $parent, array $rows): void
    {
        $rows = array_values(array_filter($rows, fn ($row) => filled($row['title'] ?? null)));

        if ($rows === []) {
            throw ValidationException::withMessages([
                'subtasks' => 'الدفعة فاضية — اكتب صب-تاسك واحدًا على الأقلّ بعنوانه وديدلاينه.',
            ]);
        }

        $limit = $this->latestAllowedChildDeadline($parent);
        $deadlines = [];

        foreach ($rows as $index => $row) {
            if (blank($row['deadline_at'] ?? null)) {
                throw ValidationException::withMessages([
                    "subtasks.{$index}.deadline_at" => 'كلّ صب-تاسك لازم له ديدلاين داخليّ.',
                ]);
            }

            $deadlines[] = Carbon::parse($row['deadline_at']);
        }

        $maxChild = collect($deadlines)->max();

        if ($limit && $maxChild->greaterThan($limit)) {
            throw ValidationException::withMessages([
                'subtasks' => 'تعذّر الحفظ — القيد: أقصى ديدلاين للأبناء ('
                    .$maxChild->format('Y-m-d H:i').') + نافذة دمجك ('
                    .$this->mergeWindowHours().' ساعة) لازم يكون ≤ ديدلاينك ('
                    .Carbon::parse($parent->deadline_at)->format('Y-m-d H:i')
                    .'). خلّي أقصى ديدلاين للأبناء '.$limit->format('Y-m-d H:i').' أو أقرب.',
            ]);
        }

        $pool = (float) $parent->vxp_value;

        if ($pool > 0) {
            $distributed = collect($rows)->sum(fn ($row) => (float) ($row['vxp_value'] ?? 0));
            $keepShare = $pool * $this->parentMinSharePercent() / 100;

            if ($distributed > $pool - $keepShare + 0.0001) {
                throw ValidationException::withMessages([
                    'subtasks' => 'تعذّر الحفظ — القيد: مجموع VXP الأبناء ('.round($distributed, 2)
                        .') لازم يكون ≤ وعاء مهمّتك ('.round($pool, 2).') ناقصَ شريحتك المحفوظة ('
                        .$this->parentMinSharePercent().'%). وزّع '.round($pool - $keepShare, 2).' كحدّ أقصى.',
                ]);
            }
        }
    }

    /**
     * حفظ الدفعة «للمراجعة»: مسودّة تتقدّم بضغطة واحدة لأبلاين صاحبها،
     * ولا اعتماد تلقائيّ إلّا بعد سقف محرّك التصعيد (23-2.3-4).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return Collection<int, Task>
     */
    public function save(Task $parent, array $rows, User $author): Collection
    {
        $this->validate($parent, $rows);

        $rows = array_values(array_filter($rows, fn ($row) => filled($row['title'] ?? null)));

        return DB::transaction(function () use ($parent, $rows, $author) {
            $created = collect();

            foreach ($rows as $row) {
                $created->push(Task::create([
                    'title' => $row['title'],
                    'brief' => $row['brief'] ?? null,
                    'deliverable_spec' => $row['deliverable_spec'] ?? null,
                    'deadline_at' => Carbon::parse($row['deadline_at']),
                    'vxp_value' => (float) ($row['vxp_value'] ?? 0),
                    'parent_task_id' => $parent->id,
                    // قاعدة الربط: الصب-تاسك يرث ربط أمّه بالبند تلقائيًّا (23-2.3)
                    'work_item_id' => $parent->work_item_id,
                    'entity_id' => $parent->entity_id,
                    'task_type_id' => $parent->task_type_id,
                    'owner_id' => $author->id,
                    'reviewer_id' => $author->id,
                    'created_by' => $author->id,
                    'status' => TaskStatus::IN_PROGRESS,
                    'batch_status' => 'pending_review',
                    'source' => 'assigned',
                ]));
            }

            /*
             | ⭐ نافذة الدمج **لا تُثبَّت هنا**: عدّاد الأب الشخصيّ يبدأ لحظة
             | اعتماد **آخر ابن** (23-3.9-3) لا لحظة التفكيك — وتثبيتها على
             | ديدلاين الأب كان يجعلها نافذةً وهميّة بلا خصمٍ على فواتها.
             | مكان بدئها: `ReviewService::startParentMergeWindow()`.
             */

            // ونافذة التفكيك نفسها تُحاسَب الآن: −0.2 عن كلّ يوم تأخير بسقف −1 (23-3.9-1)
            $this->chargeBreakdownDelay($parent, $author);

            $this->openBatchEscalation($parent, $author);

            return $created;
        });
    }

    /** آخر موعد لتفكيك المهمّة وتوزيعها — من العمود، وإلّا من لحظة وصولها */
    public function breakdownDueAt(Task $parent): ?Carbon
    {
        if ($parent->breakdown_due_at) {
            return Carbon::parse($parent->breakdown_due_at);
        }

        return $parent->created_at
            ? Carbon::parse($parent->created_at)->addHours($this->breakdownWindowHours())
            : null;
    }

    /** نافذة التفكيك بالساعات — إعداد (2.13) */
    public function breakdownWindowHours(): int
    {
        return (int) setting('workflow.breakdown_window_hours', 24);
    }

    /**
     * ⭐ خصم تأخّر التفكيك (23-3.9-1): «التأخّر عن التفكيك نفسه = −0.2 عن كلّ
     * يوم تأخير — كي لا تكون أوّل حلقة هي عنق الزجاجة الخفيّ — بسقف تراكميّ
     * −1 لكلّ مهمّة، حتى لا يصير التأخّر في التفكيك أقسى من عدم التسليم نفسه».
     *
     * ولماذا يُحسَب لحظة التفكيك لا كلّ يوم؟ لأنّ المجموع واحد، ولأنّ مَن لم
     * يفكّك أصلًا يمسكه **مسار عدم التسليم** عند ديدلاينه — فلا يُخصَم مرّتين
     * ولا يُعاقَب مَن اختار التنفيذ الذاتيّ (حقّه المنصوص في 23-3.1).
     */
    private function chargeBreakdownDelay(Task $parent, User $author): void
    {
        $due = $this->breakdownDueAt($parent);

        if (! $due || ! $due->isPast()) {
            return;
        }

        $days = (int) ceil($due->diffInHours(now()) / 24);
        $perDay = (float) rep_rule('task.breakdown_delay_per_day');
        $cap = (float) rep_rule('task.breakdown_delay_cap');
        $value = max($cap, $perDay * max(1, $days));

        if ($value == 0.0) {
            return;
        }

        RepOnce::record(
            'task.breakdown_delay:'.$parent->id,
            fn () => app(LedgerBridge::class)->record(
                $author,
                'rep',
                $value,
                'task',
                'تأخّر التفكيك '.$days.' يومًا على: '.$parent->title,
                $parent,
                $parent->entity_id,
            ),
        );
    }

    /** مراجعة الدفعة حالةٌ على محرّك التصعيد — الحالة 9 (23-5) */
    private function openBatchEscalation(Task $parent, User $author): void
    {
        if (! Schema::hasTable('escalations')) {
            return;
        }

        Escalation::create([
            'case_type' => 'subtask_batch',
            'subject_type' => $parent->getMorphClass(),
            'subject_id' => $parent->getKey(),
            'requested_by' => $author->id,
            'current_handler_id' => $parent->reviewer_id,
            'level' => 1,
            'window_due_at' => now()->addHours((int) setting('workflow.escalation.window_hours', 24)),
            'status' => 'open',
        ]);
    }
}
