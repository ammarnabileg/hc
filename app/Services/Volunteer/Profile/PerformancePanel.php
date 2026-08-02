<?php

namespace App\Services\Volunteer\Profile;

use App\Models\RepScore;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Volunteer\Goals\LeadershipService;
use App\Services\Volunteer\Goals\RepService;
use Illuminate\Support\Collection;

/**
 * تاب «الأداء» (13.4-م-4): تفاصيل Rep وVXP + التقييمات **كمتوسّطات مجهولة**
 * + التطوّر الشهريّ + **مؤشّر مخاطر الفقدان**.
 *
 * ⛔ قاعدة قاطعة: **مؤشّر مخاطر الفقدان داخليّ للأبلاين فقط، ولا يُعرَض للمتطوّع
 * عن نفسه أبدًا** — تجنّبًا لأثر التوقّع السلبيّ (13.4-م-4).
 * ⛔ وهويّة مَن قيّم **لا تُكشَف لأحد** — حتى للأدمن — فالتقييم لا يصدق إلّا مجهولًا (13.4-ي).
 */
final class PerformancePanel
{
    public function __construct(
        private readonly RepService $rep,
        private readonly LeadershipService $leadership,
        private readonly ViewerLevel $levels,
    ) {}

    public function build(User $owner, ?User $viewer, string $level): array
    {
        $days = (int) setting('ux.lists.default_range_days', 30);
        // نفس مصدر الرقم في كلّ الشاشات — جدول `rep_scores` أوّلًا (13.4-ن)
        $stored = RepScore::where('user_id', $owner->id)->value('score');
        $score = $stored !== null ? (float) $stored : $this->rep->score($owner);
        $summary = $this->leadership->receivedSummary($owner);

        return [
            'score' => round($score, 2),
            'state' => $this->rep->state($score),
            'bounds' => $this->rep->bounds(),
            'warning' => $this->rep->warningThreshold(),
            'red' => $this->rep->redThreshold(),
            'rep_series' => $this->rep->dailySeries($owner, $days),
            'vxp_series' => $this->rep->vxpSeries($owner, $days),
            'movements' => $this->movements($owner, $days),
            'vxp_balance' => round($owner->balance('vxp'), 2),
            'days' => $days,
            // متوسّطات مجهولة فقط — بلا أسماء ولا درجات فرديّة (13.4-ي)
            'evaluations' => [
                'visible' => (bool) $summary['visible'],
                'average' => $summary['visible'] ? round((float) $summary['average'], 2) : null,
                'raters' => $summary['visible'] ? (int) ($summary['raters'] ?? 0) : null,
                'min_raters' => $this->leadership->minRaters(),
                'scale' => $this->leadership->maxScore(),
                'series' => $summary['visible'] ? $this->leadership->weeklySeries($owner) : [],
                'note' => (string) setting(
                    'volunteer.profile.performance.anonymous_note',
                    'التقييمات متوسّطات مجهولة — مفيش أسماء ولا درجات فرديّة.',
                ),
            ],
            'monthly' => $this->monthly($owner),
            // ⛔ لا يُعرَض لصاحب البروفايل أبدًا — ولا لزميل
            'retention_risk' => $this->levels->isPrivileged($level) ? $this->retentionRisk($owner) : null,
        ];
    }

    /**
     * كلّ المعاملات المؤثّرة على Rep داخل المدى — بمصدرها وسببها.
     *
     * @return Collection<int, Transaction>
     */
    public function movements(User $owner, int $days): Collection
    {
        return $this->rep->movements($owner, ['days' => $days]);
    }

    /** التطوّر الشهريّ: صافي حركة Rep لكلّ شهر في آخر ستّة أشهر */
    public function monthly(User $owner): array
    {
        $months = (int) setting('volunteer.profile.performance.months', 6);
        $rows = $this->rep->query($owner)
            ->where('created_at', '>=', now()->subMonths($months)->startOfMonth())
            ->get(['amount', 'applied_amount', 'created_at']);

        $series = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $key = $month->format('Y-m');

            $series[] = [
                'key' => $key,
                'label' => $month->translatedFormat('M'),
                'value' => round((float) $rows
                    ->filter(fn (Transaction $t) => $t->created_at->format('Y-m') === $key)
                    ->sum(fn (Transaction $t) => (float) ($t->applied_amount ?? $t->amount)), 2),
            ];
        }

        return $series;
    }

    /**
     * مؤشّر «مخاطر الفقدان»: Rep نازل + حضور/نشاط نازل + مهامّ متأخّرة
     * ⇒ **تنبيه احتفاظ مبكّر للأبلاين**. والعتبات كلّها إعدادات (2.13).
     */
    public function retentionRisk(User $owner): array
    {
        $score = (float) (RepScore::where('user_id', $owner->id)->value('score') ?? 0);
        $idleDays = (int) setting('rep.inactivity.days_before_alert', 21);
        $lateCap = (int) setting('volunteer.health.retention_risk.late_tasks', 2);
        $threshold = (int) setting('volunteer.health.retention_risk.threshold', 2);

        $late = Task::query()
            ->where('owner_id', $owner->id)
            ->where(function ($q) {
                $q->where('status', 'no_delivery')
                    ->orWhere(fn ($b) => $b->whereNotNull('deadline_at')
                        ->where('deadline_at', '<', now())
                        ->whereIn('status', ['in_progress', 'blocked', 'returned']));
            })
            ->count();

        $idle = $owner->last_seen_at === null || $owner->last_seen_at < now()->subDays($idleDays);

        $signals = 0;
        $signals += $score < rep_rule('limit.warning_threshold', -5.0) ? 2 : ($score < 0 ? 1 : 0);
        $signals += $late >= $lateCap ? 1 : 0;
        $signals += $idle ? 1 : 0;

        return [
            'signals' => $signals,
            'threshold' => $threshold,
            'at_risk' => $signals >= $threshold,
            'rep' => round($score, 2),
            'late_tasks' => $late,
            'idle' => $idle,
            'note' => (string) setting(
                'volunteer.health.retention_risk.note',
                'داخليّ للأبلاين فقط — ولا يُعرَض للمتطوّع عن نفسه أبدًا',
            ),
        ];
    }
}
