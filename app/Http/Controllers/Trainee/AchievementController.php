<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Governorate;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\LeaderboardService;
use App\Services\Gamification\StreakService;
use App\Services\Geo\GovernorateLabels;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * إنجازاتي: الليدر بورد (7.3) · الشارات (7.4) · الستريك ونادي الخامسة (7.2).
 * (⛔ والألعاب — 7.5 — ملغاة بقرار المالك، الدستور v5.3.)
 */
class AchievementController extends Controller
{
    public function __construct(
        private readonly LeaderboardService $leaderboards,
        private readonly BadgeService $badges,
        private readonly StreakService $streaks,
    ) {}

    /**
     * الليدر بورد (7.3): النطاق · **الفترة (بما فيها فترة يحدّدها المستخدم)** ·
     * **فلترة بدولة/محافظة بعينها** · بحث — ومنصّة تتويج للتوب 3،
     * وكارت صاحب الحساب **في الأعلى** كما ينصّ الدستور.
     */
    public function leaderboard(Request $request): View
    {
        $scope = in_array($request->query('scope'), ['country', 'governorate'], true)
            ? (string) $request->query('scope')
            : 'all';

        $ranges = $this->leaderboards->ranges();
        $default = (int) setting('ux.lists.default_range_days', 30);
        $requested = (int) $request->query('days', $default);

        // فترةٌ خارج القائمة = فترة يحدّدها المستخدم بنفسه — تُقبَل إن كانت مسموحة
        $isCustom = ! array_key_exists($requested, $ranges);
        $days = $isCustom && ! $this->leaderboards->customRangeEnabled()
            ? $default
            : $this->leaderboards->clampDays($requested);

        $countryId = (int) $request->query('country_id') ?: null;
        $governorateId = (int) $request->query('governorate_id') ?: null;
        $search = trim((string) $request->query('q', ''));

        // اسمان عربيّان متطابقان في الدولة نفسها يخرجان خيارين لا يفرّق بينهما
        // أحد — فالفكّ عند العرض وحده (`GovernorateLabels`)، والفريد كما هو.
        $governorates = $countryId
            ? Governorate::query()->where('country_id', $countryId)->where('is_active', true)
                ->orderBy('name_ar')->get(['id', 'name_ar', 'name_en'])
            : collect();

        return view('achievements.leaderboard', [
            'board' => $this->leaderboards->xp(
                $request->user(),
                $scope,
                $days,
                $search ?: null,
                $countryId,
                $governorateId,
            ),
            'ranges' => $ranges,
            'customEnabled' => $this->leaderboards->customRangeEnabled(),
            'countries' => Country::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name_ar')->get(['id', 'name_ar']),
            'governorates' => $governorates,
            'governorateLabels' => app(GovernorateLabels::class)->labels($governorates),
            'filters' => [
                'scope' => $scope,
                'days' => $days,
                'search' => $search,
                'country_id' => $countryId,
                'governorate_id' => $governorateId,
                'is_custom' => ! array_key_exists($days, $ranges),
            ],
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
}
