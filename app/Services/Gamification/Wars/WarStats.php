<?php

namespace App\Services\Gamification\Wars;

use App\Models\User;
use App\Models\WarUserStat;

/**
 * سجلّ المحارب (15.1).
 *
 * `loss_streak` هو قلب **قاعدة الخسارات المتتالية**: مَن خسر ثلاثًا متتاليةً
 * يختفي من قائمة الجاهزين فلا «يُتفرَّس» فيه، ويظلّ قادرًا هو على التحدّي،
 * ويعود بأوّل فوز. وهي حماية لمن انقطع نتُه لا عقوبة عليه (15.2-2).
 */
class WarStats
{
    public function of(User $user): WarUserStat
    {
        return WarUserStat::firstOrCreate(['user_id' => $user->id]);
    }

    public function recordWin(User $user): void
    {
        $stat = $this->of($user);
        $stat->forceFill([
            'wins' => $stat->wins + 1,
            'loss_streak' => 0, // الفوز يكسر السلسلة فيعود للقائمة
        ])->save();
    }

    public function recordLoss(User $user, bool $withdrawal = false): void
    {
        $stat = $this->of($user);
        $stat->forceFill([
            'losses' => $stat->losses + 1,
            'withdrawals' => $stat->withdrawals + ($withdrawal ? 1 : 0),
            'loss_streak' => $stat->loss_streak + 1,
        ])->save();
    }

    /** التعادل لا يكسر السلسلة ولا يزيدها — لا فوز ولا خسارة (15.2-5) */
    public function recordDraw(User $user): void
    {
        $stat = $this->of($user);
        $stat->forceFill(['draws' => $stat->draws + 1])->save();
    }

    public function addFocusMinutes(User $user, int $minutes): int
    {
        $stat = $this->of($user);
        $stat->forceFill(['focus_minutes' => $stat->focus_minutes + max(0, $minutes)])->save();

        return (int) $stat->focus_minutes;
    }

    /** هل هو محجوب عن قائمة الجاهزين بقاعدة الخسارات المتتالية؟ */
    public function isHidden(User $user, int $limit): bool
    {
        return $this->of($user)->loss_streak >= $limit;
    }
}
