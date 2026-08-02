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
     * ترتيب XP **داخل النطاق الزمنيّ** (7.3).
     *
     * ⭐ لماذا لا نرتّب بعمود `users.xp`؟ لأنّه **تراكميّ منذ التسجيل**: لو رتّبنا
     * به لأعطى `days=7` و`days=30` و`days=90` نفس الترتيب حرفيًّا، فتصير اللوحة
     * أبديّة ولا يظهر فيها وافدٌ جديد مهما اجتهد أسبوعًا — وهذا يُبطِل الغرض
     * الذي جعله الدستور **قلب اللوحة**: أن تتيح النطاقاتُ الزمنيّة للجديد أن ينافس.
     * فالترتيب الآن بـ**XP المكتسب داخل الفترة** (`xpDeltas`)، والتراكميّ يبقى
     * فاصلَ تعادل ومعروضًا في الصفّ كسياق.
     *
     * @param  string  $scope  all · country · governorate
     * @param  int  $days  طول النافذة بالأيّام — 7/30 من الإعدادات أو فترة يحدّدها المستخدم
     * @param  int|null  $countryId  فلترة بدولةٍ **بعينها** لا «دولتي» فقط (7.3)
     * @param  int|null  $governorateId  فلترة بمحافظةٍ بعينها
     * @return array{rows:Collection,me:?array,total:int,podium:Collection,days:int}
     */
    public function xp(
        User $me,
        string $scope = 'all',
        int $days = 30,
        ?string $search = null,
        ?int $countryId = null,
        ?int $governorateId = null,
    ): array {
        $days = $this->clampDays($days);
        $from = CarbonImmutable::now()->subDays($days)->startOfDay();

        // «دولتي/محافظتي» اختصارٌ لفلترة صريحة — والصريحة تعلو إن جاءت معًا
        $countryId = $countryId ?: ($scope === 'country' ? $me->country_id : null);
        $governorateId = $governorateId ?: ($scope === 'governorate' ? $me->governorate_id : null);

        $all = User::query()
            ->where('status', 'active')
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->when($governorateId, fn ($q) => $q->where('governorate_id', $governorateId))
            ->with(['country:id,name_ar', 'governorate:id,name_ar'])
            ->orderByDesc('xp')
            ->orderBy('id')
            ->get(['id', 'name', 'code', 'avatar_path', 'xp', 'level', 'country_id', 'governorate_id']);

        $deltas = $this->xpDeltas($all->pluck('id')->all(), $from);

        // الترتيب: XP الفترة أوّلًا، ثمّ التراكميّ، ثمّ الأقدم تسجيلًا — حسمٌ ثابت
        $ranked = $all
            ->sortBy([
                fn (User $a, User $b) => ($deltas[$b->id] ?? 0) <=> ($deltas[$a->id] ?? 0),
                fn (User $a, User $b) => (int) $b->xp <=> (int) $a->xp,
                fn (User $a, User $b) => $a->id <=> $b->id,
            ])
            ->values()
            ->map(fn (User $u, int $i) => [
                'rank' => $i + 1,
                'user' => $u,
                'xp' => (int) $u->xp,
                'delta' => (int) ($deltas[$u->id] ?? 0),
                'is_me' => $u->id === $me->id,
            ]);

        $mine = $ranked->firstWhere('is_me', true);
        // منصّة التتويج تُبنى من الترتيب الكامل قبل أيّ بحث — التوب 3 لا يتغيّر ببحثي
        $podium = $ranked->take(3)->values();

        if ($search) {
            $ranked = $ranked->filter(fn (array $row) => str_contains($row['user']->name, $search))->values();
        }

        return [
            'rows' => $ranked->take((int) setting('leaderboard.rows_per_page', 50))->values(),
            'me' => $mine,
            'total' => $all->count(),
            'podium' => $podium,
            'days' => $days,
        ];
    }

    /**
     * النطاقات الجاهزة في القائمة (7.3) — من الإعدادات لا من الكود (2.13).
     *
     * @return array<int, string>
     */
    public function ranges(): array
    {
        $raw = setting('leaderboard.ranges', [7, 30]);
        $out = [];

        foreach (is_array($raw) ? $raw : [] as $value) {
            $days = $this->clampDays((int) $value);
            $out[$days] = str_replace(':days', (string) $days, (string) setting('leaderboard.range_label', 'آخر :days يومًا'));
        }

        ksort($out);

        return $out;
    }

    /** هل يُسمَح بفترة يحدّدها المستخدم بنفسه؟ (7.3) */
    public function customRangeEnabled(): bool
    {
        return (bool) setting('leaderboard.custom_range_enabled', true);
    }

    /** حدّ الفترة: يومٌ واحد على الأقلّ وسقفٌ من الإعدادات — فلا مدى بلا معنى */
    public function clampDays(int $days): int
    {
        return max(1, min((int) setting('leaderboard.max_range_days', 365), $days));
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
