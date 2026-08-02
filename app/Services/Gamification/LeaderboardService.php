<?php

namespace App\Services\Gamification;

use App\Models\ChallengeParticipation;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * الليدر بورد (7.3) ولوحة الأبطال (24.5).
 *
 * قاعدة الشاشة: الترتيب يظهر كصفوف، و**صفّي مثبَّت أسفل القائمة دائمًا**
 * حتى لو كان ترتيبي خارج الصفحة — فلا أفقد موقعي أبدًا.
 */
class LeaderboardService
{
    /**
     * ترتيب XP مع فرق الفترة.
     *
     * @param  string  $scope  all · country · governorate
     * @return array{rows:Collection,me:?array,total:int}
     */
    public function xp(User $me, string $scope = 'all', int $days = 30, ?string $search = null): array
    {
        $from = CarbonImmutable::now()->subDays(max(1, $days))->startOfDay();

        $query = User::query()
            ->where('status', 'active')
            ->when($scope === 'country' && $me->country_id, fn ($q) => $q->where('country_id', $me->country_id))
            ->when($scope === 'governorate' && $me->governorate_id, fn ($q) => $q->where('governorate_id', $me->governorate_id))
            ->orderByDesc('xp')
            ->orderBy('id');

        $all = $query->get(['id', 'name', 'code', 'avatar_path', 'xp', 'level', 'country_id', 'governorate_id']);
        $deltas = $this->xpDeltas($all->pluck('id')->all(), $from);

        $ranked = $all->values()->map(fn (User $u, int $i) => [
            'rank' => $i + 1,
            'user' => $u,
            'xp' => (int) $u->xp,
            'delta' => (int) ($deltas[$u->id] ?? 0),
            'is_me' => $u->id === $me->id,
        ]);

        $mine = $ranked->firstWhere('is_me', true);

        if ($search) {
            $ranked = $ranked->filter(fn (array $row) => str_contains($row['user']->name, $search))->values();
        }

        return [
            'rows' => $ranked->take((int) setting('leaderboard.rows_per_page', 50))->values(),
            'me' => $mine,
            'total' => $all->count(),
        ];
    }

    /**
     * لوحة أبطال التحديات: مجموع النقاط في الفترة (وبتحدٍّ بعينه إن طُلِب).
     *
     * @return array{rows:Collection,me:?array,total:int}
     */
    public function champions(User $me, ?int $challengeId = null, int $days = 30, ?string $search = null): array
    {
        $from = CarbonImmutable::now()->subDays(max(1, $days))->startOfDay();

        $rows = ChallengeParticipation::query()
            ->where('challenge_participations.status', 'finished')
            ->where('challenge_participations.finished_at', '>=', $from)
            ->when($challengeId, fn ($q) => $q->where('challenge_id', $challengeId))
            ->join('users', 'users.id', '=', 'challenge_participations.user_id')
            ->groupBy('challenge_participations.user_id', 'users.id', 'users.name', 'users.code', 'users.avatar_path')
            ->orderByDesc('points')
            ->orderByDesc('wins')
            ->get([
                'challenge_participations.user_id',
                'users.name',
                'users.code',
                'users.avatar_path',
                DB::raw('SUM(challenge_participations.score) as points'),
                DB::raw("SUM(CASE WHEN challenge_participations.result = 'win' THEN 1 ELSE 0 END) as wins"),
                DB::raw('COUNT(*) as played'),
            ]);

        $ranked = $rows->values()->map(fn ($row, int $i) => [
            'rank' => $i + 1,
            'user_id' => (int) $row->user_id,
            'name' => $row->name,
            'code' => $row->code,
            'avatar_path' => $row->avatar_path,
            'points' => (float) $row->points,
            'wins' => (int) $row->wins,
            'played' => (int) $row->played,
            'is_me' => (int) $row->user_id === $me->id,
        ]);

        $mine = $ranked->firstWhere('is_me', true);

        if ($search) {
            $ranked = $ranked->filter(fn (array $row) => str_contains((string) $row['name'], $search))->values();
        }

        return [
            'rows' => $ranked->take((int) setting('leaderboard.rows_per_page', 50))->values(),
            'me' => $mine,
            'total' => $rows->count(),
        ];
    }

    /**
     * فرق XP خلال الفترة من جدول المعاملات.
     *
     * @return array<int, float>
     */
    private function xpDeltas(array $userIds, CarbonImmutable $from): array
    {
        if ($userIds === []) {
            return [];
        }

        return Transaction::query()
            ->whereIn('transactions.user_id', $userIds)
            ->where('transactions.created_at', '>=', $from)
            ->whereHas('currency', fn ($q) => $q->where('code', 'xp'))
            ->groupBy('transactions.user_id')
            ->get(['transactions.user_id', DB::raw('SUM(transactions.amount) as delta')])
            ->mapWithKeys(fn ($row) => [(int) $row->user_id => (float) $row->delta])
            ->all();
    }
}
