<?php

namespace App\Services\Volunteer\Goals;

use App\Models\Goal;
use App\Models\Membership;
use App\Models\Milestone;
use App\Models\Position;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkPackage;
use App\Services\Volunteer\Tasks\SubtaskBatch;
use App\Services\Volunteer\Tasks\TaskStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ «إرسال الهدف للتنفيذ» — المعاينة النهائيّة وإطلاق الهدف (الدستور 23 — 1.5 · 1.6).
 *
 * وأهمّ ما يقع بالضغطة ليس تغيير الحالة، بل **بدء نافذة التفكيك** (23-3.9-١):
 * «لحظة إشعار «إرسال للتنفيذ»، لكلّ طبقة **نافذة تفكيك** (افتراضي 24 ساعة)
 * تفكّك فيها وتوزّع على مَن تحتها».
 *
 * لماذا لا يكفي `created_at`؟ لأنّ مهامّ الدايركتور تُكتَب في **مرحلة التخطيط**
 * (1.3 ملء الحزم) وقد تمكث أسابيع تحت المراجعة والتسعير قبل الاعتماد — فقياس
 * النافذة من ميلاد الصفّ كان يُخرِج الدايركتور **مخصومًا سلفًا** لحظة الإطلاق
 * على تأخيرٍ لم يقع منه. النافذة تبدأ من الإشعار، والعمود يُختَم هنا صراحةً.
 *
 * ⛔ وما لا يمسّه هذا الملفّ: **حقّ التنفيذ الذاتيّ** (23-3.1) — الختم يضع
 * موعدًا لا خصمًا؛ والخصم يبقى حيث هو: في `SubtaskBatch::chargeBreakdownDelay`
 * التي **لا تُستدعى إلّا عند التفكيك فعلًا**. فمَن اختار أن ينفّذ بنفسه لا
 * يُخصَم على تفكيكٍ لم يختره، ومَن لم يسلّم يمسكه مسار عدم التسليم عند ديدلاينه.
 */
class GoalLaunchService
{
    public function __construct(private readonly SubtaskBatch $batches) {}

    /** نافذة التفكيك بالساعات — مصدرٌ واحد مع الخصم نفسه، فلا يفترقان */
    public function windowHours(): int
    {
        return $this->batches->breakdownWindowHours();
    }

    /**
     * **فحص الزرّ الآلي (23 — 1.5):** «إرسال للتنفيذ» يرفض الضغط إن وُجد مَعلَم
     * بلا حزم أو حزمة بلا مهامّ — بقائمة النواقص. (فحص التغطية آلة لا طبقة بشريّة.)
     *
     * @return list<string>
     */
    public function gaps(Goal $goal): array
    {
        $milestones = Milestone::query()->where('goal_id', $goal->id)->orderBy('sort_order')->orderBy('id')->get();

        if ($milestones->isEmpty()) {
            return ['الهدف بلا مَعالِم — أضِف مَعلَمًا واحدًا على الأقلّ.'];
        }

        $packages = WorkPackage::query()->whereIn('milestone_id', $milestones->pluck('id'))->get();
        $taskCounts = $this->taskCountsByPackage($packages->pluck('id')->all());

        $gaps = [];

        foreach ($milestones as $milestone) {
            $own = $packages->where('milestone_id', $milestone->id);

            if ($own->isEmpty()) {
                $gaps[] = 'المَعلَم «'.$milestone->name.'» بلا حزم عمل.';

                continue;
            }

            foreach ($own as $package) {
                if (($taskCounts[$package->id] ?? 0) === 0) {
                    $gaps[] = 'حزمة «'.$package->name.'» بلا مهامّ.';
                }
            }
        }

        return $gaps;
    }

    public function canLaunch(Goal $goal): bool
    {
        return $goal->sent_to_execution_at === null && $this->gaps($goal) === [];
    }

    /**
     * الإطلاق: الحالة والختم الزمنيّ، ثم **ختم نافذة التفكيك** على مهامّ الهدف.
     *
     * @return array{ok: bool, gaps: list<string>, stamped: int, due_at: Carbon|null}
     */
    public function launch(Goal $goal, User $actor): array
    {
        if ($goal->sent_to_execution_at !== null) {
            return ['ok' => false, 'gaps' => ['الهدف ده اتبعت للتنفيذ قبل كده.'], 'stamped' => 0, 'due_at' => null];
        }

        $gaps = $this->gaps($goal);

        if ($gaps !== []) {
            return ['ok' => false, 'gaps' => $gaps, 'stamped' => 0, 'due_at' => null];
        }

        $now = now();
        $dueAt = $now->copy()->addHours($this->windowHours());

        $stamped = DB::transaction(function () use ($goal, $actor, $now, $dueAt) {
            $goal->forceFill([
                'status' => 'sent_to_execution',
                'sent_to_execution_at' => $now,
                'created_by' => $goal->created_by ?? $actor->id,
            ])->save();

            return $this->stampBreakdownWindow($goal, $dueAt);
        });

        $this->notifyDirectors($goal, $dueAt);

        return ['ok' => true, 'gaps' => [], 'stamped' => $stamped, 'due_at' => $dueAt];
    }

    /**
     * ختم `breakdown_due_at` على مهامّ الهدف المفتوحة التي لا تحمله بعد.
     *
     * ولا يُداس ختمٌ قائم: الطبقات الأعمق تُنشأ بعد الإطلاق وتأخذ نافذتها من
     * لحظة وصولها هي (23-3.9-١: «لكلّ طبقة نافذة تفكيك») — فالإطلاق يفتح
     * نافذة **الطبقة الأولى** لا نوافذ الجميع دفعةً واحدة.
     */
    private function stampBreakdownWindow(Goal $goal, Carbon $dueAt): int
    {
        $taskIds = $this->goalTaskIds($goal);

        if ($taskIds === []) {
            return 0;
        }

        return Task::query()
            ->whereIn('id', $taskIds)
            ->whereNull('breakdown_due_at')
            ->whereIn('status', TaskStatus::OPEN)
            ->update(['breakdown_due_at' => $dueAt]);
    }

    /** @return list<int> */
    private function goalTaskIds(Goal $goal): array
    {
        return Task::query()
            ->whereIn('work_item_id', $this->goalWorkItemIds($goal))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return list<int> */
    private function goalWorkItemIds(Goal $goal): array
    {
        return WorkItem::query()
            ->whereIn('work_package_id', WorkPackage::query()
                ->whereIn('milestone_id', Milestone::query()->where('goal_id', $goal->id)->select('id'))
                ->select('id'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $packageIds
     * @return array<int, int>
     */
    private function taskCountsByPackage(array $packageIds): array
    {
        if ($packageIds === []) {
            return [];
        }

        return Task::query()
            ->join('work_items', 'work_items.id', '=', 'tasks.work_item_id')
            ->whereIn('work_items.work_package_id', $packageIds)
            ->groupBy('work_items.work_package_id')
            ->selectRaw('work_items.work_package_id as package_id, count(*) as total')
            ->pluck('total', 'package_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * «بالضغطة: إشعار لمشرف المسار ولدايركتور كلّ كيان» (23 — 1.6) — ومعه
     * موعد نافذة التفكيك، فلا يبدأ العدّ على أحدٍ لم يُخبَر متى يبدأ.
     */
    private function notifyDirectors(Goal $goal, Carbon $dueAt): void
    {
        $entityIds = WorkPackage::query()
            ->whereIn('milestone_id', Milestone::query()->where('goal_id', $goal->id)->select('id'))
            ->whereNotNull('entity_id')
            ->distinct()
            ->pluck('entity_id');

        $directorId = Position::query()->where('key', 'director')->value('id');

        $userIds = Membership::query()
            ->whereIn('entity_id', $entityIds)
            ->where('status', 'active')
            ->when($directorId, fn ($q) => $q->where('position_id', $directorId))
            ->pluck('user_id')
            ->unique();

        if ($goal->created_by) {
            $userIds = $userIds->push($goal->created_by)->unique();
        }

        foreach (User::query()->whereIn('id', $userIds)->get() as $user) {
            Integrations::notify(
                user: $user,
                category: 'goal',
                title: 'اتبعت للتنفيذ: '.$goal->name,
                body: 'فكّك مهامّك ووزّعها قبل ما تقفل نافذة التفكيك.',
                url: route('volunteer.goals'),
                deadlineAt: $dueAt,
                requiresAction: true,
            );
        }
    }
}
