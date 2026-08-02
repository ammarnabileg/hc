<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\LeaderboardService;
use App\Services\Gamification\StreakService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * إنجازاتي: الليدر بورد (7.3) · الشارات (7.4) · الستريك ونادي الخامسة (7.2) · الألعاب (7.5).
 */
class AchievementController extends Controller
{
    public function __construct(
        private readonly LeaderboardService $leaderboards,
        private readonly BadgeService $badges,
        private readonly StreakService $streaks,
    ) {}

    /** الليدر بورد: النطاق · الفترة · بحث — وصفّي مثبَّت أسفل القائمة */
    public function leaderboard(Request $request): View
    {
        $scope = in_array($request->query('scope'), ['country', 'governorate'], true)
            ? (string) $request->query('scope')
            : 'all';

        $days = (int) $request->query('days', (int) setting('ux.lists.default_range_days', 30));
        $search = trim((string) $request->query('q', ''));

        return view('achievements.leaderboard', [
            'board' => $this->leaderboards->xp($request->user(), $scope, $days, $search ?: null),
            'filters' => compact('scope', 'days', 'search'),
        ]);
    }

    /** الشارات: المفتوح ملوّن والمقفول رماديّ وشرطه مكتوب صراحةً */
    public function badges(Request $request): View
    {
        $user = $request->user();

        // فحصٌ عند الفتح: مَن استحقّ شارةً يجدها مفتوحةً بلا انتظار
        $this->badges->evaluate($user);

        $board = $this->badges->board($user);
        $state = (string) $request->query('state', '');
        $search = trim((string) $request->query('q', ''));

        $filtered = $board
            ->when($state === 'unlocked', fn ($rows) => $rows->where('unlocked', true))
            ->when($state === 'locked', fn ($rows) => $rows->where('unlocked', false))
            ->when($search !== '', fn ($rows) => $rows->filter(
                fn (array $row) => str_contains($row['badge']->name_ar, $search)
                    || str_contains($row['badge']->condition_text_ar, $search),
            ))
            ->values();

        return view('achievements.badges', [
            'rows' => $filtered,
            'unlockedCount' => $board->where('unlocked', true)->count(),
            'totalCount' => $board->count(),
            'filters' => compact('state', 'search'),
        ]);
    }

    /** الستريك ونادي الخامسة: العدّاد + أطول ستريك + خريطة حراريّة */
    public function streak(Request $request): View
    {
        $user = $request->user();
        $streak = $this->streaks->forUser($user);

        $months = max(1, min(6, (int) $request->query('months', (int) setting('streaks.heatmap.months', 3))));
        $to = CarbonImmutable::now()->endOfMonth();
        $from = $to->subMonths($months - 1)->startOfMonth();

        return view('achievements.streak', [
            'streak' => $streak,
            'broken' => $this->streaks->isBroken($streak),
            'recordedToday' => $this->streaks->recordedToday($user),
            'heatmap' => $this->streaks->heatmap($user, $from, $to),
            'window' => $this->streaks->clubWindow(),
            'clubDays' => $this->streaks->clubDays($user),
            'months' => $months,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** تسجيل يوم نشط — ردّ فوريّ لكلّ فعل (2.17-ب) */
    public function checkIn(Request $request): RedirectResponse
    {
        $streak = $this->streaks->record($request->user());
        $this->badges->evaluate($request->user());

        return back()->with('status', "اتسجّل ✓ — ستريكك دلوقتي {$streak->current_days} يوم.");
    }

    /** الألعاب: تُلعب بتذكرة وفائدتها كسب XP (7.5) */
    public function games(Request $request): View
    {
        $games = setting('games.catalog', []);

        return view('achievements.games', [
            'games' => is_array($games) ? $games : [],
            'ticketCost' => (float) setting('games.ticket_cost', 1),
        ]);
    }
}
