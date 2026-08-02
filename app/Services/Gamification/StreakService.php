<?php

namespace App\Services\Gamification;

use App\Models\Streak;
use App\Models\StreakDay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * الستريكس ونادي الخامسة صباحًا (7.2).
 *
 * المبدأ: الهدف بناء عادة لا معاقبة الانقطاع — فالانقطاع يُقابَل برسالة
 * محايدة تشجّع (2.17-ج)، والعدّ يبدأ من جديد بلا تجريح.
 */
class StreakService
{
    public function __construct(private readonly CelebrationService $celebrations) {}

    /**
     * تسجيل اليوم في `streak_days` وتحديث `streaks`.
     * تكرار التسجيل في اليوم نفسه لا يزيد العدّاد.
     */
    public function record(User $user, ?CarbonInterface $at = null): Streak
    {
        $at = CarbonImmutable::parse($at ?? now())->setTimezone($this->timezone());
        $day = $at->toDateString();

        $inClubWindow = $this->inClubWindow($at);

        $streakDay = StreakDay::query()->firstOrNew([
            'user_id' => $user->id,
            'day' => $day,
        ]);

        // مرّة واحدة تكفي: من دخل نادي الخامسة اليوم يبقى فيه ولو سجّل ثانيةً بعدها
        $streakDay->club_5am = (bool) $streakDay->club_5am || $inClubWindow;
        $streakDay->save();

        $streak = Streak::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['current_days' => 0, 'best_days' => 0, 'club_5am_count' => 0],
        );

        $last = $streak->last_active_date;

        $current = match (true) {
            $last === null => 1,
            $last->isSameDay($at) => max(1, (int) $streak->current_days),
            $last->isSameDay($at->subDay()) => (int) $streak->current_days + 1,
            default => 1, // انقطع — نبدأ من جديد بلا عقوبة
        };

        $streak->forceFill([
            'current_days' => $current,
            'best_days' => max((int) $streak->best_days, $current),
            'last_active_date' => $day,
            'club_5am_count' => StreakDay::query()->where('user_id', $user->id)->where('club_5am', true)->count(),
        ])->save();

        // ستريك 7 أيّام = احتفال متوسّط (2.14)
        if ($current > 0 && $current % (int) setting('streaks.reward.every_days', 7) === 0) {
            $this->celebrations->fire($user, 'streak.7days', $streak);
        }

        return $streak->refresh();
    }

    /** ستريك المستخدم (يُنشأ فارغًا إن لم يوجد — فالشاشة تعرض دائمًا) */
    public function forUser(User $user): Streak
    {
        return Streak::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['current_days' => 0, 'best_days' => 0, 'club_5am_count' => 0],
        );
    }

    /** هل انقطع الستريك؟ (آخر يوم نشط أقدم من أمس) */
    public function isBroken(Streak $streak): bool
    {
        if ($streak->last_active_date === null) {
            return false;
        }

        $today = CarbonImmutable::now($this->timezone())->startOfDay();

        return $streak->last_active_date->lt($today->subDay());
    }

    public function recordedToday(User $user): bool
    {
        return StreakDay::query()
            ->where('user_id', $user->id)
            ->whereDate('day', CarbonImmutable::now($this->timezone())->toDateString())
            ->exists();
    }

    /**
     * أيّام الفترة للخريطة الحراريّة: التاريخ ⟵ ['active' => bool, 'club' => bool].
     *
     * @return array<string, array{active:bool,club:bool}>
     */
    public function heatmap(User $user, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = StreakDay::query()
            ->where('user_id', $user->id)
            ->whereBetween('day', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->get()
            ->keyBy(fn (StreakDay $d) => $d->day->toDateString());

        $map = [];
        $cursor = CarbonImmutable::parse($from->format('Y-m-d'));
        $end = CarbonImmutable::parse($to->format('Y-m-d'));

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $map[$key] = [
                'active' => $rows->has($key),
                'club' => (bool) $rows->get($key)?->club_5am,
            ];
            $cursor = $cursor->addDay();
        }

        return $map;
    }

    /** آخر أيّام النادي — لعرضها في الشرح */
    public function clubDays(User $user, int $limit = 5): Collection
    {
        return StreakDay::query()
            ->where('user_id', $user->id)
            ->where('club_5am', true)
            ->orderByDesc('day')
            ->limit($limit)
            ->get();
    }

    /** نافذة نادي الخامسة كما ضبطها الأدمن — نصًّا للعرض */
    public function clubWindow(): array
    {
        return [
            'start' => (string) setting('streaks.club_5am.window_start', '04:50'),
            'end' => (string) setting('streaks.club_5am.window_end', '05:20'),
        ];
    }

    private function inClubWindow(CarbonInterface $at): bool
    {
        $window = $this->clubWindow();
        $minutes = ((int) $at->format('H') * 60) + (int) $at->format('i');

        return $minutes >= $this->toMinutes($window['start'])
            && $minutes <= $this->toMinutes($window['end']);
    }

    private function toMinutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return ((int) $h * 60) + (int) $m;
    }

    private function timezone(): string
    {
        return (string) setting('system.timezone', config('app.timezone'));
    }
}
