<?php

namespace App\Services\Gamification;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ترتيب المستخدم في لوحة XP — بنفس كسر التعادل في لمحة الترتيب (PathService::rankGlimpse):
 * مَن خبرته أعلى يسبق، وعند التساوي الأقدم تسجيلًا. `forXp()` يحسب الترتيب لو كانت
 * خبرته قيمةً بعينها، فنعرف «قبل» المنح و«بعد»ه من البيانات لا بالتخمين (الفكرة #23).
 */
class LeaderboardRank
{
    public function forXp(int $xp, int $userId): int
    {
        return DB::table('users')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where('id', '!=', $userId)
            ->where(fn ($q) => $q->where('xp', '>', $xp)->orWhere(fn ($i) => $i->where('xp', $xp)->where('id', '<', $userId)))
            ->count() + 1;
    }

    public function of(User $user): int
    {
        return $this->forXp((int) $user->xp, (int) $user->id);
    }
}
