<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * تاب «إحصائيّاتي» (14-ج): الأرقام الخام للرسوم الخمسة.
 * الرسم نفسه يُرسَم SVG بيدنا في الواجهة — بلا أيّ مكتبة رسوم خارجيّة (2.16-ج).
 */
class DashboardStatsService
{
    public function __construct(private readonly DashboardService $dashboard) {}

    /** المدى المسموح (7/30 يومًا) — والافتراضيّ آخر 30 يومًا (2.15-د) */
    public function rangeOptions(): array
    {
        $options = setting('dashboard.stats.range_options', [7, 30]);

        return is_array($options) ? array_map('intval', $options) : [7, 30];
    }

    public function resolveRange(?int $requested): int
    {
        $options = $this->rangeOptions();
        $default = (int) setting('ux.lists.default_range_days', 30);

        return in_array($requested, $options, true) ? $requested : (in_array($default, $options, true) ? $default : end($options));
    }

    // ------------------------------------------------------------ 1) XP عبر الزمن

    /** @return array<int, array{label:string,short:string,value:int}> */
    public function xpSeries(User $user, int $days): array
    {
        $from = Carbon::today()->subDays($days - 1);

        $sums = DB::table('transactions')
            ->join('currencies', 'currencies.id', '=', 'transactions.currency_id')
            ->where('transactions.user_id', $user->id)
            ->where('currencies.code', (string) setting('wallet.currency.xp_code', 'xp'))
            ->where('transactions.created_at', '>=', $from)
            ->selectRaw('date(transactions.created_at) as day, sum(transactions.amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $points = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i);
            $key = $date->toDateString();

            $points[] = [
                'label' => $key,
                'short' => $date->format('j/n'),
                'value' => (int) round((float) ($sums[$key] ?? 0)),
            ];
        }

        return $points;
    }

    // ------------------------------------------------------------ 2) دونات الإكمال

    /** نسبة الإكمال الكليّة عبر المسار: مكتمل · جارٍ · لسّه مابدأش */
    public function completionDonut(User $user): array
    {
        $rows = $this->dashboard->progress($user);

        $completed = $rows->where('is_completed', true)->count();
        $started = $rows->where('is_completed', false)->filter(fn ($r) => $r['percent'] > 0)->count();
        $notStarted = $rows->count() - $completed - $started;

        $lessonsTotal = (int) $rows->sum('total_lessons');
        $lessonsDone = (int) $rows->sum('completed_lessons');

        return [
            'segments' => [
                ['label' => 'مكتمل', 'value' => $completed, 'color' => 'var(--color-state-ok)', 'icon' => '●'],
                ['label' => 'جارٍ', 'value' => $started, 'color' => 'var(--color-brand-500)', 'icon' => '◐'],
                ['label' => 'لسّه مابدأش', 'value' => $notStarted, 'color' => 'var(--color-state-idle)', 'icon' => '○'],
            ],
            'percent' => $lessonsTotal > 0 ? (int) round(($lessonsDone / $lessonsTotal) * 100) : 0,
            'caption' => $lessonsDone.' من '.$lessonsTotal.' درسًا',
        ];
    }

    // ------------------------------------------------------------ 3) خريطة الحضور

    /**
     * خريطة حراريّة للحضور (نادي الخامسة 7.2) — أسابيع كاملة تنتهي باليوم.
     * ونادي الخامسة بلون الشرف ورمزه، لا بدرجة لون أعلى فقط (2.16-ب).
     */
    public function attendanceHeatmap(User $user): array
    {
        $weeks = (int) setting('dashboard.heatmap.weeks', 12);
        $end = Carbon::today();
        // الأسبوع يبدأ السبت وينتهي الجمعة، فيكون كلّ عمود أسبوعًا كاملًا
        $gridEnd = $end->copy()->addDays((5 - (int) $end->dayOfWeek + 7) % 7);
        $gridStart = $gridEnd->copy()->subDays($weeks * 7 - 1);

        $days = DB::table('streak_days')
            ->where('user_id', $user->id)
            ->whereBetween('day', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->get(['day', 'club_5am'])
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString());

        $columns = [];

        for ($w = 0; $w < $weeks; $w++) {
            $cells = [];

            for ($d = 0; $d < 7; $d++) {
                $date = $gridStart->copy()->addWeeks($w)->addDays($d);
                $row = $days->get($date->toDateString());

                $cells[] = [
                    'date' => $date->toDateString(),
                    'label' => $date->format('j/n'),
                    'future' => $date->greaterThan($end),
                    'level' => match (true) {
                        $row && (bool) $row->club_5am => 'club',
                        (bool) $row => 'present',
                        default => 'none',
                    },
                ];
            }

            $columns[] = $cells;
        }

        return [
            'columns' => $columns,
            'present' => $days->count(),
            'club' => $days->filter(fn ($row) => (bool) $row->club_5am)->count(),
        ];
    }

    // ------------------------------------------------------------ 4) رادار الإنجازات

    /**
     * مسارات الإنجاز الخمسة (10 · 10.1): مستوى الحساب · نادي الخامسة ·
     * الدعوات · التذاكر · استمراريّة التعلّم — بعتباتها من الإعدادات.
     */
    public function achievementsRadar(User $user): array
    {
        $ticketsCode = (string) setting('wallet.currency.tickets_code', 'tickets');

        $ticketsEarned = (float) $user->balances()
            ->whereHas('currency', fn ($q) => $q->where('code', $ticketsCode))
            ->value('lifetime_earned');

        $paths = [
            ['key' => 'account', 'label' => 'مستوى الحساب', 'unit' => 'XP', 'value' => $this->dashboard->xp($user), 'base' => 500, 'step' => 250],
            ['key' => 'club_5am', 'label' => 'نادي الخامسة', 'unit' => 'يوم', 'value' => (int) ($user->streak?->club_5am_count ?? 0), 'base' => 3, 'step' => 2],
            ['key' => 'referrals', 'label' => 'الدعوات', 'unit' => 'دعوة', 'value' => $this->successfulReferrals($user), 'base' => 5, 'step' => 2],
            ['key' => 'tickets', 'label' => 'التذاكر', 'unit' => 'تذكرة', 'value' => (int) $ticketsEarned, 'base' => 15, 'step' => 10],
            ['key' => 'learning', 'label' => 'استمراريّة التعلّم', 'unit' => 'درس', 'value' => $this->lessonsCompleted($user), 'base' => 5, 'step' => 3],
        ];

        $max = max(2, (int) setting('dashboard.achievements.radar_max_level', 6));
        $axes = [];

        foreach ($paths as $path) {
            $base = (int) setting("dashboard.achievements.{$path['key']}.base", $path['base']);
            $step = (int) setting("dashboard.achievements.{$path['key']}.step", $path['step']);
            $progress = $this->pathLevel((int) $path['value'], $base, $step);

            $axes[] = [
                'label' => $path['label'],
                'unit' => $path['unit'],
                'value' => (int) $path['value'],
                'level' => $progress['level'],
                'next_at' => $progress['next_at'],
                'ratio' => min(1, ($progress['level'] - 1 + $progress['fraction']) / ($max - 1)),
            ];
        }

        return ['axes' => $axes, 'max_level' => $max];
    }

    /**
     * عتبات مسارات الإنجازات (10.1): الزيادة للوصول للمستوى N = base + (N−2)×step،
     * والرقم التراكميّ = مجموع الزيادات.
     */
    public function pathLevel(int $value, int $base, int $step): array
    {
        $level = 1;
        $cumulative = 0;

        while ($level < 200) {
            $needed = $cumulative + $base + ($level - 1) * $step;

            if ($value < $needed) {
                $span = $needed - $cumulative;

                return [
                    'level' => $level,
                    'next_at' => $needed,
                    'fraction' => $span > 0 ? ($value - $cumulative) / $span : 0.0,
                ];
            }

            $cumulative = $needed;
            $level++;
        }

        return ['level' => $level, 'next_at' => null, 'fraction' => 1.0];
    }

    // ------------------------------------------------------------ 5) بارات التذاكر

    /** التذاكر: مكتسب مقابل مصروف عبر المدى المختار */
    public function ticketBars(User $user, int $days): array
    {
        $from = Carbon::today()->subDays($days - 1);
        $daily = $days <= (int) setting('dashboard.tickets.daily_max_days', 7);

        $rows = DB::table('transactions')
            ->join('currencies', 'currencies.id', '=', 'transactions.currency_id')
            ->where('transactions.user_id', $user->id)
            ->where('currencies.code', (string) setting('wallet.currency.tickets_code', 'tickets'))
            ->where('transactions.created_at', '>=', $from)
            ->selectRaw('date(transactions.created_at) as day, transactions.amount as amount')
            ->get();

        $buckets = [];
        $count = $daily ? $days : (int) ceil($days / 7);

        for ($i = 0; $i < $count; $i++) {
            $start = $daily ? $from->copy()->addDays($i) : $from->copy()->addWeeks($i);
            $end = $daily ? $start->copy() : $start->copy()->addDays(6);

            $buckets[] = [
                'label' => $daily ? $start->format('j/n') : $start->format('j/n').' — '.$end->format('j/n'),
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'earned' => 0,
                'spent' => 0,
            ];
        }

        foreach ($rows as $row) {
            foreach ($buckets as $index => $bucket) {
                if ($row->day >= $bucket['start'] && $row->day <= $bucket['end']) {
                    $amount = (float) $row->amount;
                    $key = $amount >= 0 ? 'earned' : 'spent';
                    $buckets[$index][$key] += (int) round(abs($amount));
                    break;
                }
            }
        }

        return $buckets;
    }

    // ------------------------------------------------------------ داخليّ

    private function successfulReferrals(User $user): int
    {
        return DB::table('referrals')
            ->where('referrer_id', $user->id)
            ->whereNotNull('referred_id')
            ->count();
    }

    private function lessonsCompleted(User $user): int
    {
        return DB::table('lesson_completions')->where('user_id', $user->id)->count();
    }
}
