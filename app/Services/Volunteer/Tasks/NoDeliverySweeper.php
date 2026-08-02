<?php

namespace App\Services\Volunteer\Tasks;

use App\Models\Membership;
use App\Models\Position;
use App\Models\Task;
use App\Models\User;
use App\Services\Volunteer\Escalation\CaseCatalog;
use App\Services\Volunteer\Escalation\EscalationEngine;
use App\Services\Volunteer\Goals\RollupService;
use App\Services\Volunteer\Org\AbsenceService;
use Illuminate\Support\Carbon;

/**
 * مسار عدم التسليم — الحالة 4 (الدستور 23-3.8 · 23-5).
 *
 * لماذا مسحة دوريّة؟ لأنّ عدم التسليم **حدث زمنيّ لا فعل مستخدم**: لا أحد
 * يضغط زرًّا ليقول «ما سلّمتُ». فبلا مسحة تمرّ مهمّة عاديّة فات ديدلاينها بلا
 * تسليم بلا شيء إطلاقًا: لا خصم · لا تصعيد · لا مالك جديد · لا إغلاق.
 *
 * وقاعدتها بالحرف:
 *  - الخصم `task.no_delivery` **مرّة واحدة** على صاحبها بمفتاح واقعته.
 *  - ثمّ تصعد **بلا خصم تباطؤ**: «مهمّة يتيمة تدور على مالك جديد» لا قرارٌ
 *    متأخّر على مماطل — فلا يُعاقَب أبلاين على مهمّة سابها صاحبها.
 *  - والعامّة بلا ساحب تصعد **من دايركتور الكيان بلا خصم على أحد** (23-1.8).
 *  - والغائب المعذور ساعاته مجمَّدة، فلا تمسّه المسحة أصلًا (23-6).
 */
class NoDeliverySweeper
{
    public function __construct(
        private readonly EscalationEngine $engine,
        private readonly TaskWorkflow $workflow,
        private readonly AbsenceService $absences,
        private readonly LedgerBridge $bridge,
    ) {}

    /**
     * @return array{no_delivery:int, merge_window:int}
     */
    public function run(): array
    {
        $result = ['no_delivery' => 0, 'merge_window' => 0];

        $this->candidates()->each(function (Task $task) use (&$result) {
            $byMergeWindow = $this->missedByMergeWindow($task);

            if ($this->miss($task)) {
                $result['no_delivery']++;

                if ($byMergeWindow) {
                    $result['merge_window']++;
                }
            }
        });

        return $result;
    }

    /**
     * المهامّ المرشَّحة: فات عدّادها الفعليّ **ومعه مهلة الـ24** بلا تسليم.
     *
     * ولماذا ننتظر الـ24؟ لأنّ سلّم 3.7 نفسه ينتظرها: «مُسلَّمة بتأخير أقلّ من
     * 24 ساعة −0.25 · **بعد كده** = عدم تسليم −0.75». فالمهلة فرصةٌ منصوصة،
     * وليست تساهلًا.
     */
    public function candidates()
    {
        $grace = $this->workflow->graceHours();
        $cutoff = now()->subMinutes((int) round($grace * 60));

        return Task::query()
            ->whereIn('status', [TaskStatus::IN_PROGRESS, TaskStatus::RETURNED, TaskStatus::NO_DELIVERY])
            ->whereNull('delivered_at')
            ->where(function ($query) use ($cutoff) {
                $query->where('deadline_at', '<=', $cutoff)
                    ->orWhere('merge_window_at', '<=', $cutoff);
            })
            ->orderBy('deadline_at')
            ->get()
            ->filter(function (Task $task) use ($cutoff) {
                $deadline = $this->workflow->effectiveDeadline($task);

                return $deadline !== null && $deadline->lessThanOrEqualTo($cutoff);
            });
    }

    /** هل الذي فات هو نافذة الدمج لا الديدلاين؟ — للعرض في تقرير التشغيل */
    private function missedByMergeWindow(Task $task): bool
    {
        if (! $task->merge_window_at) {
            return false;
        }

        $deadline = $this->workflow->effectiveDeadline($task);

        return $deadline !== null && $deadline->equalTo(Carbon::parse($task->merge_window_at));
    }

    /**
     * إدخال مهمّة واحدة مسار عدم التسليم — خصمٌ محروس ثمّ تصعيد بلا خصم تباطؤ.
     * تستعملها المسحة **والبنود المتكرّرة** معًا، فلا يتفرّق المنطق ولا يتكرّر الخصم.
     */
    public function miss(Task $task, ?string $reason = null): bool
    {
        $owner = $task->owner_id ? User::query()->find($task->owner_id) : null;

        // الغائب المعذور ساعاته مجمَّدة — ولا نزيف خصومات عليه (23-6)
        if ($owner && $this->absences->isAbsent($owner, $task->entity_id)) {
            return false;
        }

        if ($this->engine->openFor($task, CaseCatalog::NO_DELIVERY)->isNotEmpty()) {
            return false;
        }

        // الأب الذي رفع علم «متأخّر بسبب [ابن]» لا يُخصَم منه شيء (23-3.9-4)
        if ($owner && ! $task->late_due_to_child) {
            RepOnce::record(
                RepOnce::noDeliveryKey((int) $task->id),
                fn () => $this->bridge->record(
                    $owner,
                    'rep',
                    rep_rule('task.no_delivery'),
                    'task',
                    $reason ?? 'عدم تسليم: '.$task->title,
                    $task,
                    $task->entity_id,
                ),
            );
        }

        $task->forceFill(['status' => TaskStatus::NO_DELIVERY])->save();

        // ⭐ تصعد بلا خصم تباطؤ — والمهمّة تدور على مالك جديد (23-3.8)
        $this->engine->open(
            CaseCatalog::NO_DELIVERY,
            $task,
            $owner,
            ['reason' => $reason ?? 'فات الديدلاين بلا تسليم', 'unassigned' => $owner === null],
            $owner ? null : $this->entityDirector($task),
        );

        app(RollupService::class)->recalcFromTask($task);

        return true;
    }

    /** المهمّة العامّة بلا ساحب تصعد من دايركتور كيانها بلا خصم على أحد (23-1.8) */
    private function entityDirector(Task $task): ?User
    {
        if (! $task->entity_id) {
            return null;
        }

        $directorPositions = Position::query()
            ->whereIn('key', (array) setting('workflow.no_delivery.owner_positions', ['director']))
            ->pluck('id');

        $membership = Membership::query()
            ->where('entity_id', $task->entity_id)
            ->where('status', 'active')
            ->whereIn('position_id', $directorPositions)
            ->first();

        return $membership ? User::query()->find($membership->user_id) : null;
    }
}
