<?php

namespace App\Services\Volunteer\Goals;

use App\Models\Membership;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkPackage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * توليد مهامّ البنود المتكرّرة في المشروع التشغيليّ (الدستور 23 — 1.8).
 *
 * ثلاث قواعد منصوصة تُنفَّذ هنا حرفيًّا:
 *  1) **التوليد آليّ في موعده**، والمهمّة المتولَّدة تدخل نظام المهامّ **مربوطةً ببندها**.
 *  2) **الموازن (Load Balancer):** الدور يلفّ لا بالتساوي الأعمى — المهمّة توجَّه
 *     **للأقلّ حملًا حاليًّا**، والحمل = المُسنَد + المسحوب فقط (**المساهمات خارج المعادلة**).
 *  3) **المتكرّرة الفائتة لا تُقفَل بصمت** — تدخل مسار عدم التسليم ويُرفَع عدّاد «فائتة».
 */
class RecurringGenerator
{
    /** الحمل المحتسَب لا يشمل ما انتهى — والحالات إعداد لا قائمة محروقة */
    public function openStatuses(): array
    {
        $value = setting('recurring.load.open_statuses', ['in_progress', 'blocked', 'in_review', 'returned']);

        return is_array($value) && $value !== [] ? array_values($value) : ['in_progress', 'blocked', 'in_review', 'returned'];
    }

    /** مصادر المهامّ الداخلة في الحمل: المُسنَد + المسحوب — لا المساهمات (23 — 1.8) */
    public function loadSources(): array
    {
        $value = setting('recurring.load.sources', ['assigned', 'public_board', 'recurring']);

        return is_array($value) && $value !== [] ? array_values($value) : ['assigned', 'public_board', 'recurring'];
    }

    /** البنود المستحقّة التوليد الآن */
    public function due(?Carbon $at = null): Collection
    {
        $at ??= now();

        return WorkItem::query()
            ->where('is_recurring', true)
            ->where('is_archived', false)
            ->whereNotNull('recurrence')
            ->where(fn ($q) => $q->whereNull('next_generation_at')->orWhere('next_generation_at', '<=', $at))
            ->orderBy('id')
            ->get();
    }

    /**
     * دورة كاملة: وسم الفائتة ثمّ توليد المستحقّ.
     *
     * @return array{generated:int,missed:int,skipped:int,tasks:array<int,Task>}
     */
    public function run(?Carbon $at = null): array
    {
        $at ??= now();
        $generated = [];
        $missed = 0;
        $skipped = 0;

        foreach ($this->due($at) as $item) {
            $missed += $this->markMissed($item, $at);

            $task = $this->generate($item, $at);

            $task ? $generated[] = $task : $skipped++;
        }

        return [
            'generated' => count($generated),
            'missed' => $missed,
            'skipped' => $skipped,
            'tasks' => $generated,
        ];
    }

    /**
     * توليد مهمّة واحدة من بند — ويُعيد null لو تعذّر إيجاد جمهور لها.
     * والبند العامّ (مرشَّح ليكون عامًّا) يُولَّد بلا مالك ليُسحَب من اللوحة العامّة.
     */
    public function generate(WorkItem $item, ?Carbon $at = null): ?Task
    {
        $at ??= now();
        $package = WorkPackage::query()->find($item->work_package_id);

        [$owner, $byBalancer] = $this->pickAssignee($item);

        if (! $owner && $item->audience_mode !== 'public_board') {
            $this->schedule($item, $at, generated: false);

            return null; // بند فرديّ بلا شخص: لا نولّد يتيمًا بلا مسؤول
        }

        $hours = (int) ($item->relative_deadline_hours ?: setting('recurring.default_relative_deadline_hours', 24));

        $task = Task::create([
            'title' => $item->name,
            'work_item_id' => $item->id,
            'entity_id' => $package?->entity_id,
            'owner_id' => $owner?->id,
            'created_by' => $owner?->id,
            'brief' => $item->brief,
            'deliverable_spec' => $item->deliverable_spec,
            // وعاء البند هو مصدر قيمة المهمّة المتولَّدة
            'vxp_value' => $item->vxp_pool,
            'deadline_at' => $at->copy()->addHours($hours),
            'status' => 'in_progress',
            'source' => 'recurring',
            'assigned_by_balancer' => $byBalancer,
        ]);

        $this->schedule($item, $at, generated: true);

        app(VxpDistributionService::class)->syncItemSpent((int) $item->id);
        app(RollupService::class)->recalcFromTask($task);

        if ($owner) {
            Integrations::notify(
                user: $owner,
                category: 'task',
                title: 'نوبتك من «'.$item->name.'» جاهزة',
                body: $byBalancer ? 'وُجِّهت إليك لأنّك الأقلّ حملًا حاليًّا.' : null,
                url: route('volunteer.recurring'),
                deadlineAt: $task->deadline_at,
                requiresAction: true,
            );
        }

        return $task;
    }

    /**
     * اختيار الجمهور: فرد بعينه · تناوب موزون بالموازن · مرشَّح ليكون عامًّا.
     *
     * @return array{0:?User,1:bool} المستخدم، وهل جاء من الموازن؟
     */
    public function pickAssignee(WorkItem $item): array
    {
        if ($item->audience_mode === 'individual' && $item->assigned_user_id) {
            return [User::query()->find($item->assigned_user_id), false];
        }

        if ($item->audience_mode === 'rotation') {
            $pool = $this->rotationPool($item);

            if ($pool === []) {
                return [null, false];
            }

            // ⭐ الأقلّ حملًا حاليًّا — لا الدور الأعمى بالتساوي
            $loads = $this->loadsFor($pool);
            asort($loads);
            $winner = (int) array_key_first($loads);

            return [User::query()->find($winner), true];
        }

        return [null, false]; // public_board: تُسحَب من اللوحة العامّة بلا مالك
    }

    /** الحمل الحاليّ لمستخدم: مهامّه المفتوحة المُسنَدة والمسحوبة */
    public function loadOf(User $user): int
    {
        return $this->loadsFor([$user->id])[$user->id] ?? 0;
    }

    /**
     * أحمال مجموعة دفعةً واحدة.
     *
     * @return array<int,int>
     */
    public function loadsFor(array $userIds): array
    {
        $counts = Task::query()
            ->whereIn('owner_id', $userIds)
            ->whereIn('status', $this->openStatuses())
            ->whereIn('source', $this->loadSources())
            ->selectRaw('owner_id, COUNT(*) as total')
            ->groupBy('owner_id')
            ->pluck('total', 'owner_id');

        $loads = [];

        foreach ($userIds as $id) {
            $loads[(int) $id] = (int) ($counts[$id] ?? 0);
        }

        return $loads;
    }

    /** الدور: القائمة المحفوظة على البند، وإلّا كلّ أعضاء الكيان النشطين */
    public function rotationPool(WorkItem $item): array
    {
        $stored = $item->getAttribute('rotation_pool');
        $stored = is_string($stored) ? json_decode($stored, true) : $stored;

        if (is_array($stored) && $stored !== []) {
            return array_values(array_map('intval', $stored));
        }

        $entityId = WorkPackage::query()->where('id', $item->work_package_id)->value('entity_id');

        if (! $entityId) {
            return [];
        }

        return Membership::query()
            ->where('entity_id', $entityId)
            ->where('status', 'active')
            ->pluck('user_id')
            ->unique()->values()->map(fn ($v) => (int) $v)->all();
    }

    /**
     * ⭐ الفائتة لا تُقفَل بصمت: كلّ متولَّدة فات ديدلاينها ولم تُسلَّم
     * تدخل **مسار عدم التسليم** ويُرفَع عدّاد «فائتة» على البند.
     */
    public function markMissed(WorkItem $item, ?Carbon $at = null): int
    {
        $at ??= now();

        $stale = Task::query()
            ->where('work_item_id', $item->id)
            ->where('source', 'recurring')
            ->whereIn('status', $this->openStatuses())
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<', $at)
            ->get();

        if ($stale->isEmpty()) {
            return 0;
        }

        foreach ($stale as $task) {
            $task->forceFill(['status' => 'no_delivery'])->save();

            if ($task->owner_id && ($owner = User::query()->find($task->owner_id))) {
                // خصم عدم التسليم من جدول Rep — لا رقم محروق (13.4-ن-أ)
                Integrations::debit(
                    user: $owner,
                    currencyCode: RepService::CURRENCY,
                    amount: rep_rule('task.no_delivery'),
                    source: 'task',
                    reference: $task,
                    reason: 'بند متكرّر فائت: '.$item->name,
                );

                app(RepService::class)->syncScore($owner);
            }

            app(RollupService::class)->recalcFromTask($task);
        }

        $item->forceFill(['missed_count' => (int) $item->missed_count + $stale->count()])->save();

        return $stale->count();
    }

    /** التوليد التالي بحسب التكرار — والتكرارات المتاحة إعداد قابل للتوسّع */
    public function nextRunAt(WorkItem $item, Carbon $from): ?Carbon
    {
        return match ($item->recurrence) {
            'daily' => $from->copy()->addDay(),
            'weekly' => $from->copy()->addWeek(),
            'biweekly' => $from->copy()->addWeeks(2),
            'monthly' => $from->copy()->addMonthNoOverflow(),
            default => null,
        };
    }

    // ------------------------------------------------------------------ داخليّ

    private function schedule(WorkItem $item, Carbon $at, bool $generated): void
    {
        $payload = ['next_generation_at' => $this->nextRunAt($item, $at)];

        if ($generated) {
            $payload['last_generated_at'] = $at;
            $payload['generated_count'] = (int) $item->generated_count + 1;
        }

        $item->forceFill($payload)->save();
    }
}
