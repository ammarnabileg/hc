<?php

namespace App\Services\Dashboard;

use App\Models\User;
use App\Services\Account\AchievementTracks;
use App\Services\Gamification\TicketsAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * تاب «إحصائيّاتي» (14-ج): الأرقام الخام للرسوم الخمسة.
 * الرسم نفسه يُرسَم SVG بيدنا في الواجهة — بلا أيّ مكتبة رسوم خارجيّة (2.16-ج).
 */
class DashboardStatsService
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly AchievementTracks $tracks,
        private readonly TicketsAccount $tickets,
    ) {}

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
        $max = max(2, (int) setting('dashboard.achievements.radar_max_level', 6));
        $axes = [];

        // ⭐ القيم والعتبات من **المصدر الواحد** — فالمستوى واحد في البروفايل واللوحة (10.1)
        foreach ($this->tracks->forUser($user) as $track) {
            $axes[] = [
                'label' => $track['label'],
                'unit' => $track['unit'],
                'value' => $track['value'],
                'level' => $track['level'],
                'next_at' => $track['next_threshold'],
                'ratio' => min(1, ($track['level'] - 1 + $track['fraction']) / ($max - 1)),
            ];
        }

        return ['axes' => $axes, 'max_level' => $max];
    }

    /**
     * عتبات مسارات الإنجازات (10.1) — واجهةٌ رفيعة فوق `AchievementTracks`،
     * والصيغة نفسها لا تُكتَب هنا ثانيةً.
     */
    public function pathLevel(int $value, int $base, int $step): array
    {
        $progress = $this->tracks->progress($value, $base, $step);

        return [
            'level' => $progress['level'],
            'next_at' => $progress['next_at'],
            'fraction' => $progress['fraction'],
        ];
    }

    // ------------------------------------------------------------ 5) بارات التذاكر

    /**
     * التذاكر: مكتسب مقابل مصروف عبر المدى المختار (24.5) — **من المصدر الواحد**
     * `TicketsAccount` لا باستعلامٍ ثانٍ هنا، فمجموع البارات ينتمي إلى الميزان
     * نفسه الذي يعطي «الرصيد» و«المكتسب» في بقيّة الشاشة.
     */
    public function ticketBars(User $user, int $days): array
    {
        return $this->tickets->flow($user, $days);
    }

    /**
     * ميزان التذاكر الكلّيّ — يُعرَض بجانب البارات فيفهم القارئ أنّ الأرقام
     * الثلاثة (رصيد · مكتسب · مصروف) وجوهُ حسابٍ واحد لا أرقامٌ متنازعة.
     *
     * @return array{balance:int, earned:int, spent:int, opening:int}
     */
    public function ticketBalanceSheet(User $user): array
    {
        return $this->tickets->snapshot($user);
    }
}
