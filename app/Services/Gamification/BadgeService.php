<?php

namespace App\Services\Gamification;

use App\Models\Badge;
use App\Models\BadgeUser;
use App\Models\ChallengeParticipation;
use App\Models\Streak;
use App\Models\User;
use App\Models\WarUserStat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * الشارات (7.4 · 24.5).
 *
 * قاعدة الشاشة: **شرط الفتح مكتوب صراحةً** في `badges.condition_text_ar` — لا ألغاز.
 * وهذه الخدمة تمنح الشارة آليًّا حين يبلغ `condition_key` قيمةَ `condition_value`.
 */
class BadgeService
{
    public function __construct(private readonly CelebrationService $celebrations) {}

    /**
     * فحص كلّ الشارات ومنح ما استُحقّ.
     *
     * @return Collection<int, Badge> الشارات الممنوحة الآن فقط
     */
    public function evaluate(User $user): Collection
    {
        $owned = BadgeUser::query()->where('user_id', $user->id)->pluck('badge_id')->all();
        $metrics = $this->metrics($user);
        $awarded = collect();

        $candidates = Badge::query()
            ->where('is_active', true)
            ->whereNotNull('condition_key')
            ->whereNotIn('id', $owned)
            ->get();

        foreach ($candidates as $badge) {
            $value = $metrics[$badge->condition_key] ?? null;

            if ($value === null || $value < (float) $badge->condition_value) {
                continue;
            }

            BadgeUser::query()->firstOrCreate(
                ['badge_id' => $badge->id, 'user_id' => $user->id],
                ['awarded_at' => now()],
            );

            $awarded->push($badge);
        }

        if ($awarded->isNotEmpty()) {
            $this->celebrations->fire($user, 'badge.unlocked', $awarded->first());
        }

        return $awarded;
    }

    /**
     * كلّ الشارات مع حالة المستخدم فيها — الشبكة تظهر دائمًا:
     * المفتوح ملوّن، والمقفول رماديّ وشرطه مكتوب.
     *
     * @return Collection<int, array{badge:Badge,unlocked:bool,awarded_at:?Carbon,progress:float}>
     */
    public function board(User $user): Collection
    {
        $owned = BadgeUser::query()->where('user_id', $user->id)->get()->keyBy('badge_id');
        $metrics = $this->metrics($user);

        return Badge::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(function (Badge $badge) use ($owned, $metrics) {
                $target = (float) ($badge->condition_value ?: 0);
                $value = (float) ($metrics[$badge->condition_key] ?? 0);

                return [
                    'badge' => $badge,
                    'unlocked' => $owned->has($badge->id),
                    'awarded_at' => $owned->get($badge->id)?->awarded_at,
                    'progress' => $target > 0 ? min(100, round($value / $target * 100)) : 0,
                ];
            });
    }

    /**
     * مقاييس الشروط المدعومة — مقفولة وصريحة (لا محرّك قواعد موازٍ).
     *
     * @return array<string, float>
     */
    public function metrics(User $user): array
    {
        $streak = Streak::query()->where('user_id', $user->id)->first();

        return [
            'xp.total' => (float) $user->xp,
            'level.reached' => (float) $user->level,
            'streak.best_days' => (float) ($streak?->best_days ?? 0),
            'streak.current_days' => (float) ($streak?->current_days ?? 0),
            'club_5am.days' => (float) ($streak?->club_5am_count ?? 0),
            'challenges.finished' => (float) ChallengeParticipation::query()
                ->where('user_id', $user->id)->where('status', 'finished')->count(),
            'challenges.wins' => (float) ChallengeParticipation::query()
                ->where('user_id', $user->id)->where('result', 'win')->count(),
            // دقائق حرب التركيز — شارة 24 ساعة تراكميّة (15.3)
            'focus.minutes' => (float) WarUserStat::query()
                ->where('user_id', $user->id)->value('focus_minutes'),
        ];
    }
}
