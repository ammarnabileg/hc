<?php

namespace App\Services\Volunteer\Org;

use App\Models\BehaviorTransaction;
use App\Models\Membership;
use App\Models\Objection;
use App\Models\RepScore;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * لوحة صحّة القسم (24.4-7 · 13.4-ح).
 *
 * ⛔ لا تُبنى ولا تُعرَض إلّا لمسؤول القسم والأبلاين المخوَّل — والمسار نفسه
 * محميّ بـ`team_health.view`، والعنصر يُخفى من السايد بار لمن لا يملكه (2.15-أ-7).
 * و**مؤشّر مخاطر الفقدان داخليّ لا يُعرَض للمتطوّع نفسه أبدًا** (13.4-م-4).
 */
final class HealthReport
{
    /**
     * @param  Collection<int, Membership>  $memberships
     */
    public function indicators(Collection $memberships, int $periodDays): array
    {
        $userIds = $memberships->pluck('user_id')->unique()->all();
        $tasks = $this->tasksOf($userIds, $periodDays);
        $closed = $tasks->whereIn('status', ['approved', 'closed', 'returned', 'no_delivery']);

        $onTime = $tasks->filter(fn (Task $t) => $t->delivered_at && $t->deadline_at && $t->delivered_at <= $t->deadline_at)->count();
        $late = $tasks->filter(fn (Task $t) => $this->isLate($t))->count();
        $returned = $tasks->where('return_count', '>', 0)->count();
        $delivered = $tasks->whereNotNull('delivered_at')->count();

        $reviewHours = $tasks
            ->filter(fn (Task $t) => $t->delivered_at && $t->approved_at)
            ->map(fn (Task $t) => $t->delivered_at->diffInHours($t->approved_at));

        $repAvg = RepScore::whereIn('user_id', $userIds)->avg('score');
        $inProgress = $tasks->where('status', 'in_progress')->count();
        $members = max(1, $memberships->count());

        return [
            'rep_avg' => $repAvg === null ? null : round((float) $repAvg, 2),
            'commitment_percent' => $this->percent($onTime, $delivered),
            'late_percent' => $this->percent($late, max(1, $tasks->count())),
            'return_percent' => $this->percent($returned, max(1, $closed->count() ?: $tasks->count())),
            'review_speed_hours' => $reviewHours->isEmpty() ? null : round((float) $reviewHours->avg(), 1),
            'work_pressure' => round($inProgress / $members, 1),
            'critical_tasks' => $tasks->where('priority', 1)->whereIn('status', ['in_progress', 'blocked'])->count(),
            'tasks_total' => $tasks->count(),
        ];
    }

    /**
     * لوحة القيادة: أكثر 10 أفراد بمهامّ قيد التنفيذ (من الأكثر للأقلّ).
     *
     * @param  Collection<int, Membership>  $memberships
     */
    public function leaderboard(Collection $memberships, int $periodDays): Collection
    {
        $counts = Task::query()
            ->whereIn('owner_id', $memberships->pluck('user_id')->all())
            ->where('status', 'in_progress')
            ->selectRaw('owner_id, count(*) as c')
            ->groupBy('owner_id')
            ->pluck('c', 'owner_id');

        return $memberships
            ->map(fn (Membership $m) => [
                'name' => $m->user?->shortName() ?? '',
                'code' => $m->user?->code ?? '',
                'position' => $m->position?->name_ar ?? '',
                'entity' => $m->entity?->name_ar ?? '',
                'in_progress' => (int) ($counts[$m->user_id] ?? 0),
            ])
            ->sortByDesc('in_progress')
            ->take((int) setting('volunteer.health.leaderboard_size', 10))
            ->values();
    }

    /**
     * تاب «رقابة»: المتأخّرة · تجاوزات نطاق الإشراف · مخاطر الفقدان (داخليّ) ·
     * معاملات السلوك لكلّ مشرف ونسبة الاعتراضات المقبولة ضدّه · أعلام «متأخّر بسبب…» · الخاملون.
     *
     * @param  Collection<int, Membership>  $memberships
     */
    public function oversight(Collection $memberships, User $viewer, int $periodDays): array
    {
        $userIds = $memberships->pluck('user_id')->unique()->all();
        $tasks = $this->tasksOf($userIds, $periodDays);
        $names = $memberships->mapWithKeys(fn (Membership $m) => [$m->user_id => $m->user?->shortName() ?? '']);

        return [
            'late_tasks' => $tasks->filter(fn (Task $t) => $this->isLate($t))
                ->sortBy('deadline_at')
                ->take((int) setting('volunteer.health.list_size', 10))
                ->map(fn (Task $t) => [
                    'title' => $t->title,
                    'owner' => $names[$t->owner_id] ?? '',
                    'deadline' => $t->deadline_at?->translatedFormat((string) setting('volunteer.org.date_format', 'j F')),
                ])->values(),

            'span_breaches' => $this->spanBreaches($memberships),

            // ⭐ داخليّ بحت: لا يُعرَض للمتطوّع عن نفسه أبدًا (13.4-م-4)
            'retention_risk' => $this->retentionRisk($memberships, $tasks, $viewer),

            'behavior_grantors' => $this->behaviorGrantors($memberships),

            'late_due_to_child' => $tasks->where('late_due_to_child', true)
                ->take((int) setting('volunteer.health.list_size', 10))
                ->map(fn (Task $t) => [
                    'title' => $t->title,
                    'owner' => $names[$t->owner_id] ?? '',
                ])->values(),

            'idle_members' => $this->idleMembers($memberships),
        ];
    }

    /**
     * تجاوزات نطاق الإشراف — **تنبيه فقط بلا منع** (13.4-ف-ب).
     *
     * @param  Collection<int, Membership>  $memberships
     */
    public function spanBreaches(Collection $memberships): Collection
    {
        $direct = $memberships->groupBy('upline_id')->map->count();

        return $memberships
            ->filter(fn (Membership $m) => $m->position?->span_max
                && ($direct[$m->id] ?? 0) > $m->position->span_max)
            ->map(fn (Membership $m) => [
                'name' => $m->user?->shortName() ?? '',
                'position' => $m->position?->name_ar ?? '',
                'actual' => (int) ($direct[$m->id] ?? 0),
                'max' => (int) $m->position->span_max,
            ])
            ->sortByDesc('actual')
            ->values();
    }

    /**
     * الأعضاء الخاملون: بلا نشاط `offboarding.inactivity.alert_days` يومًا فأكثر،
     * ومعهم عدّاد الخصم الأسبوعيّ من جدول Rep الموحَّد (13.4-س · 13.4-ن).
     *
     * @param  Collection<int, Membership>  $memberships
     */
    public function idleMembers(Collection $memberships): Collection
    {
        $days = (int) setting('rep.inactivity.days_before_alert', 21);
        $weekly = rep_rule('inactivity.weekly', -0.5);
        $cutoff = now()->subDays($days);

        return $memberships
            ->filter(fn (Membership $m) => $m->user && ($m->user->last_seen_at === null || $m->user->last_seen_at < $cutoff))
            ->map(function (Membership $m) use ($weekly, $days) {
                $since = $m->user?->last_seen_at;
                $idleDays = $since ? (int) $since->diffInDays(now()) : $days;
                $weeks = intdiv(max(0, $idleDays - $days), 7) + 1;

                return [
                    'name' => $m->user?->shortName() ?? '',
                    'entity' => $m->entity?->name_ar ?? '',
                    'idle_days' => $idleDays,
                    'weekly_deduction' => round($weekly * $weeks, 2),
                ];
            })
            ->sortByDesc('idle_days')
            ->values();
    }

    /**
     * عدد معاملات السلوك التي منحها كلّ مشرف **ونسبة الاعتراضات المقبولة ضدّه**
     * — رقابةٌ على المانح لا على الممنوح (13.4-ط).
     *
     * @param  Collection<int, Membership>  $memberships
     */
    private function behaviorGrantors(Collection $memberships): Collection
    {
        $userIds = $memberships->pluck('user_id')->unique()->all();

        $rows = BehaviorTransaction::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'applied')
            ->with('granted_by')
            ->get();

        return $rows
            ->groupBy('granted_by')
            ->map(function (Collection $group) {
                $transactionIds = $group->pluck('transaction_id')->filter()->all();
                $objections = Objection::whereIn('transaction_id', $transactionIds)->get();
                $accepted = $objections->where('status', 'accepted')->count();

                return [
                    'name' => $group->first()->granted_by?->shortName() ?? '',
                    'granted' => $group->count(),
                    'objections' => $objections->count(),
                    'accepted_percent' => $objections->isEmpty()
                        ? null
                        : (int) round($accepted / $objections->count() * 100),
                ];
            })
            ->sortByDesc('granted')
            ->values();
    }

    /**
     * مؤشّر مخاطر الفقدان: Rep نازل + مهامّ متأخّرة + خمول ⇒ تنبيه احتفاظ مبكّر للأبلاين.
     * ⛔ ويُستبعَد صاحب الحساب نفسه دائمًا — لا يراه عن نفسه أبدًا (13.4-م-4).
     *
     * @param  Collection<int, Membership>  $memberships
     * @param  Collection<int, Task>  $tasks
     */
    private function retentionRisk(Collection $memberships, Collection $tasks, User $viewer): Collection
    {
        $reps = RepScore::whereIn('user_id', $memberships->pluck('user_id')->all())->pluck('score', 'user_id');
        $lateByOwner = $tasks->filter(fn (Task $t) => $this->isLate($t))->groupBy('owner_id')->map->count();
        $idleDays = (int) setting('rep.inactivity.days_before_alert', 21);
        $threshold = (int) setting('volunteer.health.retention_risk.threshold', 2);

        return $memberships
            ->reject(fn (Membership $m) => (int) $m->user_id === $viewer->id)
            ->map(function (Membership $m) use ($reps, $lateByOwner, $idleDays) {
                $score = isset($reps[$m->user_id]) ? (float) $reps[$m->user_id] : 0.0;
                $late = (int) ($lateByOwner[$m->user_id] ?? 0);
                $idle = $m->user?->last_seen_at === null || $m->user->last_seen_at < now()->subDays($idleDays);

                $signals = 0;
                $signals += $score < rep_rule('limit.warning_threshold', -5.0) ? 2 : ($score < 0 ? 1 : 0);
                $signals += $late >= (int) setting('volunteer.health.retention_risk.late_tasks', 2) ? 1 : 0;
                $signals += $idle ? 1 : 0;

                return [
                    'name' => $m->user?->shortName() ?? '',
                    'entity' => $m->entity?->name_ar ?? '',
                    'signals' => $signals,
                    'rep' => round($score, 2),
                    'late' => $late,
                    'idle' => $idle,
                ];
            })
            ->filter(fn (array $row) => $row['signals'] >= $threshold)
            ->sortByDesc('signals')
            ->values();
    }

    /**
     * @param  list<int>  $userIds
     * @return Collection<int, Task>
     */
    private function tasksOf(array $userIds, int $periodDays): Collection
    {
        return Task::query()
            ->whereIn('owner_id', $userIds)
            ->where('created_at', '>=', now()->subDays($periodDays))
            ->get();
    }

    private function isLate(Task $task): bool
    {
        if ($task->status === 'no_delivery') {
            return true;
        }

        if ($task->delivered_at && $task->deadline_at) {
            return $task->delivered_at > $task->deadline_at;
        }

        return $task->deadline_at !== null
            && $task->deadline_at < now()
            && in_array($task->status, ['in_progress', 'blocked', 'returned'], true);
    }

    private function percent(int $part, int $total): int
    {
        return $total > 0 ? (int) round($part / $total * 100) : 0;
    }
}
