<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Game;
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
        // الشهور تُقاس بساعة المستخدم كي لا تنزلق الخريطة يومًا كاملًا (5)
        $to = CarbonImmutable::now($this->streaks->timezoneFor($user))->endOfMonth();
        $from = $to->subMonths($months - 1)->startOfMonth();

        $clubTotal = $this->streaks->clubDaysCount($user);

        return view('achievements.streak', [
            'streak' => $streak,
            'broken' => $this->streaks->isBroken($streak),
            'recordedToday' => $this->streaks->recordedToday($user),
            'heatmap' => $this->streaks->heatmap($user, $from, $to),
            'window' => $this->streaks->clubWindow(),
            'windowOpen' => $this->streaks->windowIsOpenFor($user),
            'timezone' => $this->streaks->timezoneFor($user),
            'clubDays' => $this->streaks->clubDays($user),
            'clubTotal' => $clubTotal,
            // سلّم XP (7.2): ما يكسبه اليوم وما ينتظره في الدرجة التالية
            'ladderXp' => $this->streaks->xpForClubDay(max(1, $clubTotal + ($this->streaks->recordedToday($user) ? 0 : 1))),
            'nextStep' => $this->streaks->nextLadderStep($clubTotal),
            'rewardDue' => $this->streaks->rewardIsDue($user, $streak),
            'rewardEvery' => $this->streaks->rewardEveryDays(),
            'freezableDay' => $this->streaks->freezableDay($user),
            'freezeCost' => $this->streaks->freezeCost(),
            'freezesUsed' => $this->streaks->freezesUsedThisMonth($user),
            'freezeCap' => (int) setting('streaks.max_freezes_per_month', 2),
            'months' => $months,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** تسجيل يوم نشط — ردّ فوريّ لكلّ فعل (2.17-ب) */
    public function checkIn(Request $request): RedirectResponse
    {
        $result = $this->streaks->checkIn($request->user());
        $this->badges->evaluate($request->user());

        return back()->with('status', $result['message']);
    }

    /** تذكرة مكافأة السلسلة (7.2 · 7.1-2) — القرار والصرف في الخادم */
    public function claimStreakReward(Request $request): RedirectResponse
    {
        $result = $this->streaks->claimReward($request->user());

        return back()->with('status', $result['message']);
    }

    /** درع تجميد السلسلة بتذكرة (7.2 · 7.1-4) */
    public function freezeStreak(Request $request): RedirectResponse
    {
        $result = $this->streaks->buyFreeze($request->user());

        return back()->with('status', $result['message']);
    }

    /** الألعاب: تُلعب بتذكرة وفائدتها كسب XP (7.5 · 24.2) */
    public function games(Request $request): View
    {
        // القسم كلّه له توجل من لوحة التلعيب — والموقوف يُخفى لا يُعطَّل (2.15-أ-7)
        $enabled = (bool) setting('games.enabled', true);

        return view('achievements.games', [
            'games' => $enabled
                ? Game::query()->where('status', '!=', 'paused')->orderBy('sort_order')->orderBy('id')->get()
                : collect(),
        ]);
    }
}
