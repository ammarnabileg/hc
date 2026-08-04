<?php

namespace App\Services\Volunteer\Tasks;

use App\Models\Membership;
use App\Models\Task;
use App\Models\TaskContribution;
use App\Models\User;

/**
 * سقف الانشغال (الدستور 23-3.1).
 *
 * قيد شخصيّ واحد يحكم بوّابتَي «السحب من لوحة المهام العامّة» و«إنشاء مهمّة جديدة».
 * والعدّ = ما ينفّذه الشخص فعلًا (مهامّ أوّل السلسلة + المسحوب + المساهمات)
 * — لا ما يراجعه، ولا الصب-تاسكات (ترث ربط أمّها ولا تُحسب).
 *
 * ⭐ ولأنّ السقف يُفحَص لكلّ عضويّة على حدة، فوقه **سقف شخصيّ كلّي** عبر العضويّات.
 */
class TaskLoadCap
{
    /** سقف الدور من `positions.task_load_cap` — و null تعني «بلا حدّ» (دايركتور فأعلى) */
    public function capFor(?Membership $membership): ?int
    {
        $cap = $membership?->position?->task_load_cap;

        return $cap === null ? null : (int) $cap;
    }

    /** انشغال المستخدم داخل عضويّة بعينها (كيانها) */
    public function loadFor(User $user, ?Membership $membership): int
    {
        $tasks = Task::query()
            ->where('owner_id', $user->id)
            ->whereNull('parent_task_id')
            ->whereIn('status', TaskStatus::OPEN)
            ->when($membership?->entity_id, fn ($q, $entityId) => $q->where('entity_id', $entityId))
            ->count();

        return $tasks + $this->openContributions($user);
    }

    /** الانشغال الشخصيّ الكلّي عبر كلّ العضويّات */
    public function personalLoad(User $user): int
    {
        $tasks = Task::query()
            ->where('owner_id', $user->id)
            ->whereNull('parent_task_id')
            ->whereIn('status', TaskStatus::OPEN)
            ->count();

        return $tasks + $this->openContributions($user);
    }

    /**
     * السقف الشخصيّ الكلّي = أعلى سقف دور له + نسبة إضافيّة (إعداد).
     * ومن له عضويّة بلا حدّ (دايركتور فأعلى) فلا سقف كلّي عليه.
     */
    public function personalCap(User $user): ?int
    {
        $memberships = $user->memberships()->where('status', 'active')->with('position')->get();

        if ($memberships->isEmpty()) {
            return null;
        }

        $caps = [];

        foreach ($memberships as $membership) {
            $cap = $this->capFor($membership);

            if ($cap === null) {
                return null; // بلا حدّ يعلو كلّ السقوف
            }

            $caps[] = $cap;
        }

        if ($caps === []) {
            return null;
        }

        $extraPercent = (float) setting('workflow.task_cap.personal_extra_percent', 50);

        return (int) floor(max($caps) * (1 + $extraPercent / 100));
    }

    /** هل يستطيع أخذ مهمّة إضافيّة داخل هذه العضويّة؟ */
    public function canTake(User $user, ?Membership $membership): bool
    {
        return $this->blockReason($user, $membership) === null;
    }

    /**
     * سبب المنع بلغة تشرح ما حدث وما العمل (2.17-ب) — أو null لو الطريق سالك.
     * القيود تُشرَح لحظة كسرها فقط (2.15-د).
     */
    public function blockReason(User $user, ?Membership $membership): ?string
    {
        $cap = $this->capFor($membership);
        $load = $this->loadFor($user, $membership);

        if ($cap !== null && $load >= $cap) {
            return strtr(setting('workflow.task_load_cap.block_reason_1', 'وصلت لسقف انشغالك (:p1/:p2) — اعتمد مهمّة يفضى مكانها فورًا.'), [':p1' => (string) ($load), ':p2' => (string) ($cap)]);
        }

        $personalCap = $this->personalCap($user);
        $personalLoad = $this->personalLoad($user);

        if ($personalCap !== null && $personalLoad >= $personalCap) {
            return strtr(setting('workflow.task_load_cap.body_1', 'وصلت لسقفك الشخصيّ الكلّي عبر عضويّاتك (:p1/:p2) — اقفل مهمّة قبل ما تاخد جديدة.'), [':p1' => (string) ($personalLoad), ':p2' => (string) ($personalCap)]);
        }

        return null;
    }

    /** ملخّص جاهز للعرض في الهيدر: حبّة العدّاد وحالتها اللونيّة */
    public function summary(User $user, ?Membership $membership): array
    {
        $cap = $this->capFor($membership);
        $load = $this->loadFor($user, $membership);
        $personalCap = $this->personalCap($user);
        $personalLoad = $this->personalLoad($user);

        return [
            'cap' => $cap,
            'load' => $load,
            'display' => $cap === null ? strtr(setting('workflow.task_load_cap.summary_1', ':p1 / بلا حدّ'), [':p1' => (string) ($load)]) : $load.' / '.$cap,
            'state' => $this->state($load, $cap),
            'personal_cap' => $personalCap,
            'personal_load' => $personalLoad,
            'personal_display' => $personalCap === null
                ? strtr(setting('workflow.task_load_cap.summary_2', ':p1 / بلا حدّ'), [':p1' => (string) ($personalLoad)])
                : $personalLoad.' / '.$personalCap,
            'personal_state' => $this->state($personalLoad, $personalCap),
            'remaining' => $cap === null ? null : max(0, $cap - $load),
            'reason' => $this->blockReason($user, $membership),
        ];
    }

    /** المساهمات المفتوحة تُحسب ضمن السقف (23-3.1) */
    private function openContributions(User $user): int
    {
        return TaskContribution::query()
            ->where('contributor_id', $user->id)
            ->whereIn('status', ['invited', 'accepted', 'returned'])
            ->count();
    }

    private function state(int $load, ?int $cap): string
    {
        if ($cap === null) {
            return 'ok';
        }

        if ($load >= $cap) {
            return 'danger';
        }

        return $load >= $cap - 1 ? 'warn' : 'ok';
    }
}
