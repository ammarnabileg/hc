<?php

namespace App\Services\Volunteer\Tasks;

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

            // نافذة دمج الأب تُثبَّت لحظة التفكيك — عليها يُحاسَب هو وحده (23-3.9)
            $limit = $this->latestAllowedChildDeadline($parent);

            if ($limit) {
                $parent->forceFill(['merge_window_at' => $parent->deadline_at])->save();
            }

            $this->openBatchEscalation($parent, $author);

            return $created;
        });
    }

    /** مراجعة الدفعة حالةٌ على محرّك التصعيد — الحالة 9 (23-5) */
    private function openBatchEscalation(Task $parent, User $author): void
    {
        if (! Schema::hasTable('escalations')) {
            return;
        }

        \App\Models\Escalation::create([
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
