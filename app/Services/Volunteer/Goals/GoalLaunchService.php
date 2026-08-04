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
    public function __construct(
        private readonly SubtaskBatch $batches,
        private readonly FileDrafts $fileDrafts,
    ) {}

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
            return [setting('goals.goal_launch_service.gaps_1', 'الهدف بلا مَعالِم — أضِف مَعلَمًا واحدًا على الأقلّ.')];
        }

        $packages = WorkPackage::query()->whereIn('milestone_id', $milestones->pluck('id'))->get();
        $taskCounts = $this->taskCountsByPackage($packages->pluck('id')->all());

        $gaps = [];

        foreach ($milestones as $milestone) {
            $own = $packages->where('milestone_id', $milestone->id);

            if ($own->isEmpty()) {
                $gaps[] = strtr(setting('goals.goal_launch_service.gaps_2', 'المَعلَم «:p1» بلا حزم عمل.'), [':p1' => (string) ($milestone->name)]);

                continue;
            }

            foreach ($own as $package) {
                if (($taskCounts[$package->id] ?? 0) === 0) {
                    $gaps[] = strtr(setting('goals.goal_launch_service.gaps_3', 'حزمة «:p1» بلا مهامّ.'), [':p1' => (string) ($package->name)]);
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
     * @return array{ok: bool, gaps: list<string>, stamped: int, due_at: Carbon|null, files: int, memberships: int}
     */
    public function launch(Goal $goal, User $actor): array
    {
        if ($goal->sent_to_execution_at !== null) {
            return ['ok' => false, 'gaps' => [setting('goals.goal_launch_service.launch_1', 'الهدف ده اتبعت للتنفيذ قبل كده.')], 'stamped' => 0, 'due_at' => null, 'files' => 0, 'memberships' => 0];
        }

        $gaps = $this->gaps($goal);

        if ($gaps !== []) {
            return ['ok' => false, 'gaps' => $gaps, 'stamped' => 0, 'due_at' => null, 'files' => 0, 'memberships' => 0];
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

        /*
         * ⭐ «**تتفعّل مسودّات الملفّات** المربوطة (عضويّات ودعوات)» (23 — 1.6).
         *
         * وموضعها هنا **بعد** نجاح الإطلاق لا قبله: الفتح أثرٌ للضغطة لا شرطٌ
         * لها. ولو فُعِّلت قبل فحص النواقص لفُتِحت ملفّاتٌ على هدفٍ رُفِض إرساله،
         * فتبقى مفتوحةً بعضويّاتٍ حيّة بلا عملٍ ولا مَن يُنهيها — والإنهاء
         * للقمّة وحدها. و`activate()` لا يُستدعى من أيّ موضعٍ آخر في المنصّة:
         * هذا هو معنى «الفتح حصريًّا للقمّة بصفر خطوة إضافيّة».
         */
        $files = $this->fileDrafts->activate($goal);

        $this->notifyDirectors($goal, $dueAt);

        return [
            'ok' => true,
            'gaps' => [],
            'stamped' => $stamped,
            'due_at' => $dueAt,
            'files' => $files['files'],
            'memberships' => $files['memberships'],
        ];
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
                title: strtr(setting('goals.goal_launch_service.notify_directors_1', 'اتبعت للتنفيذ: :p1'), [':p1' => (string) ($goal->name)]),
                body: setting('goals.goal_launch_service.notify_directors_2', 'فكّك مهامّك ووزّعها قبل ما تقفل نافذة التفكيك.'),
                url: route('volunteer.goals'),
                deadlineAt: $dueAt,
                requiresAction: true,
            );
        }
    }
}
