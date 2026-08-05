<?php

namespace App\Services\Growth;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * ⭐ لوحة متصدّري الدعوات **شهريًّا** (21.1-ج).
 *
 * وتُحسَب على **الدعوة المكتملة** لا على النقرة: مَن سجّل وفُعِّل حسابه.
 * فالعدّ على الروابط المفتوحة يشجّع الرشّ، والعدّ على الاكتمال يشجّع الدعوة الحقيقيّة (2.9).
 */
class InviteLeaderboard
{
    /**
     * ⭐ مفتاح إيقافٍ لهذه الحلقة بعينها (21.1-هـ) — «تفعيل/إيقاف كلّ حلقة على حدة».
     *
     * وكانت اللوحة بلا مفتاح: مسارها يفتح دائمًا مهما قال الإعداد. والمحظور
     * يُخفى لا يُعطَّل (2.15-أ-7) — فالمتحكّم يردّ 404 حين الإيقاف، لا شاشةً
     * بزرٍّ رماديّ. راجع `InviteBoardController::index()`.
     */
    public function enabled(): bool
    {
        return (bool) setting('growth.invite_board.enabled', true);
    }

    /** دوريّة اللوحة إعدادٌ لا رقم محروق (21.1-هـ) */
    public function period(?string $month = null): array
    {
        $anchor = $month && preg_match('/^\d{4}-\d{2}$/', $month) === 1
            ? Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth()
            : Carbon::now()->startOfMonth();

        return ['from' => $anchor, 'to' => $anchor->copy()->endOfMonth(), 'key' => $anchor->format('Y-m')];
    }

    /** الشهور المتاحة للتنقّل — الحاليّ وما قبله بحدّ الإعداد */
    public function months(): array
    {
        $count = max((int) setting('growth.invite_board.months', 6), 1);
        $months = [];

        for ($i = 0; $i < $count; $i++) {
            $date = Carbon::now()->startOfMonth()->subMonths($i);
            $months[$date->format('Y-m')] = $date->translatedFormat('F Y');
        }

        return $months;
    }

    /**
     * الصفوف: الداعي · عدد الدعوات المكتملة · عدد الكلّ.
     *
     * @return Collection<int,array{rank:int, user:?User, completed:int, total:int}>
     */
    public function rows(?string $month = null): Collection
    {
        $period = $this->period($month);
        $limit = max((int) setting('growth.invite_board.size', 10), 1);

        $referrals = Referral::query()
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->whereNotNull('referred_id')
            ->with('referred:id,status')
            ->get(['id', 'referrer_id', 'referred_id']);

        $grouped = $referrals->groupBy('referrer_id')
            ->map(fn (Collection $rows) => [
                'total' => $rows->count(),
                'completed' => $rows->filter(fn (Referral $r) => $r->referred?->status === 'active')->count(),
            ])
            ->sortByDesc(fn (array $row) => [$row['completed'], $row['total']])
            ->take($limit);

        $users = User::query()
            ->whereIn('id', $grouped->keys()->all())
            ->get(['id', 'code', 'name', 'avatar_path', 'xp'])
            ->keyBy('id');

        $rank = 0;

        return $grouped->map(function (array $row, $referrerId) use ($users, &$rank) {
            $rank++;

            return [
                'rank' => $rank,
                'user' => $users->get($referrerId),
                'completed' => $row['completed'],
                'total' => $row['total'],
            ];
        })->values();
    }

    /** ترتيب مستخدمٍ بعينه في الشهر — «فين أنا؟» سؤالٌ مشروع في كلّ لوحة (7.3) */
    public function positionOf(User $user, ?string $month = null): ?int
    {
        foreach ($this->rows($month) as $row) {
            if ($row['user'] && (int) $row['user']->id === (int) $user->id) {
                return $row['rank'];
            }
        }

        return null;
    }
}
