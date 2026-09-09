<?php

namespace App\Services\Volunteer\Tasks;

use App\Models\Membership;
use App\Models\Task;
use App\Models\TaskContribution;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * استعلامات المهامّ: «مهامّي» و«لوحة المهام العامّة» ورؤية المهمّة الواحدة.
 *
 * ⭐ لا سلطة عابرة للكيانات: كلّ استعلام يُقيَّد بالعضويّة النشطة، والاتّساع
 *    يأتي من أوسع نطاق يملكه المستخدم في الصلاحيّة نفسها (12.2.1).
 */
class TaskBoard
{
    /** مهامّي داخل العضويّة النشطة، بفلاتر الشاشة (24.4) */
    public function mine(User $user, ?Membership $membership, array $filters = []): Builder
    {
        $query = Task::query()
            ->with(['entity', 'task_type', 'work_item', 'reviewer'])
            ->where(function (Builder $q) use ($user) {
                $q->where('owner_id', $user->id)->orWhere('created_by', $user->id);
            })
            ->when($membership?->entity_id, fn (Builder $q, $entityId) => $q->where(function (Builder $inner) use ($entityId) {
                $inner->where('entity_id', $entityId)->orWhereNull('entity_id');
            }));

        return $this->applyFilters($query, $filters);
    }

    /**
     * لوحة المهام العامّة: مهامّ مفتوحة بلا مالك يسحبها المتطوّع بنفسه.
     *
     * ⭐ **ومصدرها اثنان لا واحد** (23 — 1.8): `public_board` المباشر،
     * و`recurring` حين يُرشَّح بند متكرّر «مهمّة عامّة» — `RecurringGenerator`
     * لا يُنشئ صفًّا بلا مالكٍ إطلاقًا إلّا في هذه الحالة بعينها (البنود
     * الفرديّة بلا مالكٍ لا تُولَّد أصلًا)، فالتقاطع هنا آمنٌ بالبناء لا حَدسًا.
     * وبقاء `source='recurring'` (لا تحويله إلى `public_board`) مقصودٌ: هو ما
     * يُبقي `RecurringGenerator::markMissed()` قادرةً على إيجادها لو فاتت.
     */
    public function publicBoard(array $filters = []): Builder
    {
        $query = Task::query()
            ->with(['entity', 'task_type', 'work_item'])
            ->whereIn('source', ['public_board', 'recurring'])
            ->whereNull('owner_id')
            ->whereIn('status', [TaskStatus::IN_PROGRESS, TaskStatus::BLOCKED]);

        return $this->applyFilters($query, $filters);
    }

    /** عدّادات الحالات لرقائق الفلترة وأعمدة الكانبان */
    public function statusCounts(User $user, ?Membership $membership): array
    {
        $counts = (clone $this->mine($user, $membership))
            ->reorder()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $out = [];

        foreach (array_keys(TaskStatus::labels()) as $status) {
            $out[$status] = (int) ($counts[$status] ?? 0);
        }

        return $out;
    }

    /**
     * هل يرى هذا المستخدم هذه المهمّة؟
     * المالك · المُنشئ · المراجِع · المساهم · ومَن يملك نطاقًا عليها داخل عضويّته.
     */
    public function canSee(User $user, Task $task): bool
    {
        if (in_array($user->id, array_filter([$task->owner_id, $task->created_by, $task->reviewer_id]), true)) {
            return true;
        }

        $isContributor = TaskContribution::query()
            ->where('task_id', $task->id)
            ->where('contributor_id', $user->id)
            ->exists();

        if ($isContributor) {
            return true;
        }

        // النطاق يُقيَّم داخل العضويّة النشطة — والمحرّك هو الحكم
        return $user->allows('tasks.view', $task);
    }

    /** المهامّ التي تقترب ديدلايناتها — تغذّي «ما يستحقّ انتباهك» والتقويم */
    public function upcoming(User $user, int $days = 7)
    {
        return Task::query()
            ->with('entity')
            ->where('owner_id', $user->id)
            ->whereIn('status', TaskStatus::OPEN)
            ->whereNotNull('deadline_at')
            ->whereBetween('deadline_at', [now()->subDay(), now()->addDays($days)])
            ->orderBy('deadline_at')
            ->get();
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['type'] ?? null, fn (Builder $q, $type) => $q->where('task_type_id', $type))
            ->when($filters['entity'] ?? null, fn (Builder $q, $entity) => $q->where('entity_id', $entity))
            ->when($filters['work_item'] ?? null, fn (Builder $q, $item) => $q->where('work_item_id', $item))
            ->when($filters['q'] ?? null, function (Builder $q, $term) {
                $q->where(function (Builder $inner) use ($term) {
                    $inner->where('title', 'like', "%{$term}%")
                        ->orWhere('id', ltrim($term, '#'));
                });
            })
            ->when($filters['due_soon'] ?? null, fn (Builder $q) => $q
                ->whereNotNull('deadline_at')
                ->where('deadline_at', '<=', Carbon::now()->addDays((int) setting('workflow.deadline.soon_days', 3))))
            ->orderByRaw('CASE WHEN deadline_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('deadline_at');
    }
}
