<?php

namespace App\Services\Volunteer\Goals;

use App\Models\Goal;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\WorkItem;
use App\Models\WorkPackage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * الصعود الآليّ للنِّسَب — Roll-up (الدستور 23 — 1.7 · 24.4).
 *
 * لماذا آليّ؟ لأنّ «محدّش كتب تقرير حالة» (23 — المثال ١٧): النسبة تصعد وحدها
 * مهمّة ⟵ بند ⟵ حزمة ⟵ مَعلَم ⟵ هدف، فلا تُقاس الحقيقة بتقرير بشريّ.
 *
 * ⭐ القاعدة الحاسمة: **المهمّة المُغلَقة تُستبعَد من المقام** ولا تُحتسَب إنجازًا،
 * وتُوسَم صراحةً في العرض حتى لا تبدو النسبة مجمَّلةً بلا سبب ظاهر (23 — سجلّ التصحيحات).
 */
class RollupService
{
    /** حالات «تمّ» التي ترفع النسبة — إعداد لا قائمة محروقة (2.13) */
    public function doneStatuses(): array
    {
        $value = setting('goals.rollup.done_statuses', ['approved']);

        return is_array($value) && $value !== [] ? array_values($value) : ['approved'];
    }

    /** ⭐ الحالات المستبعَدة من المقام — المُغلَقة أوّلها */
    public function excludedStatuses(): array
    {
        $value = setting('goals.rollup.excluded_statuses', ['closed']);

        return is_array($value) && $value !== [] ? array_values($value) : ['closed'];
    }

    /**
     * عدّاد مهامّ البند: نشطة · معتمدة · مُغلَقة (مستبعَدة).
     *
     * @return array{active:int,done:int,closed:int,denominator:int}
     */
    public function taskCounts(WorkItem $item): array
    {
        $rows = Task::query()
            ->where('work_item_id', $item->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return $this->countsFrom($rows);
    }

    /** نسبة البند = المعتمدة ÷ (الكلّ − المُغلَقة) */
    public function workItemPercent(WorkItem $item): float
    {
        $counts = $this->taskCounts($item);

        return $this->percent($counts['done'], $counts['denominator']);
    }

    /** حساب نسبة البند وحفظها */
    public function recalcWorkItem(WorkItem $item): float
    {
        $percent = $this->workItemPercent($item);

        if ((float) $item->progress_percent !== $percent) {
            $item->forceFill(['progress_percent' => $percent])->save();
        }

        return $percent;
    }

    /** نسبة الحزمة = متوسّط نِسَب بنودها (البند بلا مهامّ قابلة للعدّ = صفر) */
    public function recalcWorkPackage(WorkPackage $package): float
    {
        $items = WorkItem::query()->where('work_package_id', $package->id)->get();

        $percent = $this->average($items->map(fn (WorkItem $i) => $this->recalcWorkItem($i)));

        if ((float) $package->progress_percent !== $percent) {
            $package->forceFill(['progress_percent' => $percent])->save();
        }

        return $percent;
    }

    /**
     * نسبة المَعلَم = متوسّط نِسَب حزمه.
     * والاكتمال ✅ آليّ حين تبلغ 100% — أمّا معيار الحالة (نعم/لا) فيُعلَن بدليل ويُعتمَد
     * من مشرف المسار، فلا يُعلَّم اكتمالًا بمجرّد النسبة (23 — 1.7).
     */
    public function recalcMilestone(Milestone $milestone): float
    {
        $packages = WorkPackage::query()->where('milestone_id', $milestone->id)->get();

        $percent = $this->average($packages->map(fn (WorkPackage $p) => $this->recalcWorkPackage($p)));

        $payload = ['progress_percent' => $percent];

        // معيار رقميّ ⟵ يُعلَّم آليًّا · معيار حالة ⟵ ينتظر إعلانًا معتمَدًا
        $autoVerified = $percent >= 100.0 && $milestone->verification_status !== 'declared';

        if ($autoVerified && ! $milestone->is_verified && $this->isNumericCriteria($milestone)) {
            $payload['is_verified'] = true;
            $payload['verification_status'] = 'approved';
            $payload['verified_at'] = now();
        }

        $milestone->forceFill($payload)->save();

        return $percent;
    }

    /** نسبة الهدف = متوسّط نِسَب مَعالِمه */
    public function recalcGoal(Goal $goal): float
    {
        $milestones = Milestone::query()->where('goal_id', $goal->id)->get();

        $percent = $this->average($milestones->map(fn (Milestone $m) => $this->recalcMilestone($m)));

        if ((float) $goal->progress_percent !== $percent) {
            $goal->forceFill(['progress_percent' => $percent])->save();
        }

        return $percent;
    }

    /**
     * ⭐ الصعود من مهمّة واحدة إلى قمّة الهدف — نقطة الدخول التي يناديها أيّ تغيّر حالة.
     * لا يمرّ على غير سلسلته، فالتكلفة تبقى محدودة مهما كبر المشروع.
     */
    public function recalcFromTask(Task $task): void
    {
        $item = $task->work_item_id ? WorkItem::query()->find($task->work_item_id) : null;

        if (! $item) {
            return;
        }

        $this->recalcWorkItem($item);

        $package = WorkPackage::query()->find($item->work_package_id);

        if (! $package) {
            return;
        }

        $this->recalcWorkPackage($package);

        if (! $package->milestone_id) {
            return; // حزمة المشروع التشغيليّ: وعاء دائم بلا مَعلَم ولا هدف
        }

        $milestone = Milestone::query()->find($package->milestone_id);

        if (! $milestone) {
            return;
        }

        $this->recalcMilestone($milestone);

        $goal = Goal::query()->find($milestone->goal_id);

        if ($goal) {
            $this->recalcGoal($goal);
        }
    }

    /**
     * عدّادات مجمَّعة لحزمة كاملة — تُستعمَل في رأس شاشة الحزم بلا استعلام لكلّ بند.
     *
     * @return array{active:int,done:int,closed:int,denominator:int}
     */
    public function packageTaskCounts(WorkPackage $package): array
    {
        $rows = Task::query()
            ->whereIn('work_item_id', WorkItem::query()->where('work_package_id', $package->id)->select('id'))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return $this->countsFrom($rows);
    }

    /** عدّادات كلّ بنود الحزمة دفعةً — مفتاحها work_item_id (تفادي N+1) */
    public function countsForPackage(WorkPackage $package): Collection
    {
        return Task::query()
            ->whereIn('work_item_id', WorkItem::query()->where('work_package_id', $package->id)->select('id'))
            ->selectRaw('work_item_id, status, COUNT(*) as total')
            ->groupBy('work_item_id', 'status')
            ->get()
            ->groupBy('work_item_id')
            ->map(fn ($rows) => $this->countsFrom($rows->pluck('total', 'status')));
    }

    /** أقرب ديدلاين حيّ داخل بند — للعدّاد الملوّن (2.16) */
    public function nearestDeadline(WorkItem $item): ?Carbon
    {
        $value = Task::query()
            ->where('work_item_id', $item->id)
            ->whereNotIn('status', array_merge($this->excludedStatuses(), $this->doneStatuses()))
            ->whereNotNull('deadline_at')
            ->min('deadline_at');

        return $value ? Carbon::parse($value) : null;
    }

    /** حالة العدّاد بلون ومعنًى واحد: فات · اقترب · متّسع (2.16) */
    public function deadlineState(?Carbon $deadline): string
    {
        if (! $deadline) {
            return 'idle';
        }

        if ($deadline->isPast()) {
            return 'danger';
        }

        $soon = (float) setting('goals.deadline.soon_hours', 48);

        return now()->diffInHours($deadline, absolute: true) <= $soon ? 'warn' : 'ok';
    }

    // ------------------------------------------------------------------ داخليّ

    /** @param  Collection<string,int>  $rows */
    private function countsFrom(Collection $rows): array
    {
        $done = $excluded = $total = 0;

        foreach ($rows as $status => $count) {
            $count = (int) $count;
            $total += $count;

            if (in_array($status, $this->excludedStatuses(), true)) {
                $excluded += $count;

                continue;
            }

            if (in_array($status, $this->doneStatuses(), true)) {
                $done += $count;
            }
        }

        $denominator = $total - $excluded;

        return [
            'active' => max(0, $denominator - $done),
            'done' => $done,
            'closed' => $excluded,
            'denominator' => max(0, $denominator),
        ];
    }

    private function percent(int $done, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round(($done / $denominator) * 100, 2);
    }

    /** @param  Collection<int,float>  $values */
    private function average(Collection $values): float
    {
        if ($values->isEmpty()) {
            return 0.0;
        }

        return round($values->sum() / $values->count(), 2);
    }

    /** معيار التحقّق الرقميّ يُعلَّم آليًّا — وحالة نعم/لا تحتاج إعلانًا بدليل */
    private function isNumericCriteria(Milestone $milestone): bool
    {
        $goal = Goal::query()->find($milestone->goal_id);

        return ($goal->verification_type ?? 'numeric') === 'numeric';
    }
}
