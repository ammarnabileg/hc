<?php

namespace App\Services\Volunteer\People;

use App\Models\Membership;
use App\Models\PostVote;
use App\Models\RepScore;
use App\Models\ThanksWallPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * حائط الشكر — نادي التميّز (13.4-ي · 24.4-11).
 *
 * العتبة **من جدول Rep** لا من الكود، والسباق **شهريّ متجدّد** مع التصفير الشهريّ.
 * والكارت ذهبيّ بلمعان **بلا هالة حول الأفاتار** — قاعدة صريحة في نظام التصميم.
 */
class ThanksWall
{
    public function __construct(private readonly PeopleBridge $bridge) {}

    public function threshold(): float
    {
        return rep_rule('limit.club_threshold', 9.5);
    }

    /**
     * ⭐ مَن يدخل السباق أصلًا: **متطوّع بعضويّة نشطة وغير شرفيّ**.
     *
     * 13.4-ي: «كلّ مَن Rep بتاعه +9.5 **بين المتطوّعين**»، و13.4-ص: العنصر الشرفيّ
     * «بلا أثر على أيّ شيء… **بلا Rep · بلا VXP**». وبلا هذا الفلتر كان صفّ
     * `rep_scores` وحده يكفي للدخول: فشرفيٌّ بـ9.9 يتصدّر النادي بإطار ذهبيّ،
     * ومتدرّبٌ بلا أيّ عضويّة تطوّع بـ9.8 يدخله — والاثنان خارج السباق نصًّا.
     *
     * @return Collection<int,int>
     */
    private function eligibleUserIds(): Collection
    {
        return Membership::query()
            ->where('status', 'active')
            ->whereDoesntHave('position', fn ($q) => $q->where('is_honorary', true))
            ->pluck('user_id')
            ->unique()
            ->values();
    }

    /** هل هذا الشخص داخل السباق؟ — نفس الشرط لكلّ استعمالات النادي */
    public function isEligible(User $user): bool
    {
        return Membership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereDoesntHave('position', fn ($q) => $q->where('is_honorary', true))
            ->exists();
    }

    /** أعضاء النادي هذا الشهر مرتّبين — الترتيب داخل النادي جزء من الشاشة */
    public function members(): Collection
    {
        return $this->ranked(
            RepScore::query()->where('rep_scores.score', '>=', $this->threshold()),
        );
    }

    /**
     * بلوك «اقتربت»: مَن بينه وبين العتبة أقلّ من الفجوة المحدَّدة.
     *
     * @return Collection<int, RepScore>
     */
    public function approaching(): Collection
    {
        $gap = (float) setting('thanks_wall.approaching_gap', 1.5);

        return $this->ranked(
            RepScore::query()
                ->where('rep_scores.score', '<', $this->threshold())
                ->where('rep_scores.score', '>=', $this->threshold() - $gap),
        );
    }

    /**
     * ⭐ ترتيبٌ **حاسم** لا يتركه لصدفة ترتيب الصفوف في القاعدة.
     *
     * الشاشة تعرض «ترتيبًا داخل النادي» (13.4-ي · 24.4-11)، وبـ`score` وحده
     * يتساوى اثنان فيقرّر أيّهما الأوّل **رقمُ صفٍّ في قاعدة البيانات** — فيتغيّر
     * التتويج بإعادة بذر البيانات لا بعمل صاحبه. فعند تساوي Rep يُرتَّب المتساوون
     * **أبجديًّا بالاسم**: قاعدة محايدة ومعلَنة وثابتة عبر التشغيلات.
     *
     * وبابُ النادي واحد في الحالتين — العضويّة النشطة غير الشرفيّة (13.4-ص-ج).
     *
     * @param  Builder<RepScore>  $query
     * @return Collection<int, RepScore>
     */
    private function ranked(Builder $query): Collection
    {
        return $query
            ->with('user')
            ->join('users', 'users.id', '=', 'rep_scores.user_id')
            ->select('rep_scores.*')
            ->whereIn('rep_scores.user_id', $this->eligibleUserIds())
            ->orderByDesc('rep_scores.score')
            ->orderBy('users.name')
            ->get()
            ->filter(fn (RepScore $r) => $r->user !== null)
            ->values();
    }

    /** بار التقدّم الشخصيّ نحو العتبة — يظهر حتى لو الحائط فاضي (2.17-ج) */
    public function personalProgress(User $user): array
    {
        $score = (float) (RepScore::query()->where('user_id', $user->id)->value('score') ?? 0);
        $threshold = $this->threshold();
        $percent = $threshold > 0 ? (int) max(0, min(100, round($score / $threshold * 100))) : 0;

        return [
            'score' => $score,
            'threshold' => $threshold,
            'percent' => $percent,
            'remaining' => round(max(0, $threshold - $score), 2),
            // العضويّة شرطٌ للدخول لا الرقم وحده — وإلّا احتفل بها من هو خارج السباق
            'is_member' => $score >= $threshold && $this->isEligible($user),
        ];
    }

    /** أيّام باقية على تجديد السباق (التصفير يوم 1 — 13.4-ن) */
    public function daysToReset(): int
    {
        $day = (int) setting('rep.reset.day_of_month', 1);
        $next = now()->day <= $day ? now()->setDay($day) : now()->addMonthNoOverflow()->setDay($day);

        return (int) max(0, now()->diffInDays($next));
    }

    public function monthKey(): string
    {
        return now()->format('Y-m');
    }

    /** @return Collection<int, ThanksWallPost> */
    public function discussion(): Collection
    {
        return ThanksWallPost::query()
            ->with('user')
            ->whereNull('parent_id')
            ->where('month_key', $this->monthKey())
            ->orderByDesc('votes')
            ->orderByDesc('created_at')
            ->get();
    }

    public function post(User $user, string $body): ThanksWallPost
    {
        if (trim($body) === '') {
            throw new \InvalidArgumentException(setting('recruitment.thanks_wall.post_1', 'اكتب حاجة الأوّل — البوست الفاضي مش هيوصل حد.'));
        }

        return ThanksWallPost::create([
            'user_id' => $user->id,
            'body' => trim($body),
            'month_key' => $this->monthKey(),
        ]);
    }

    /** Vote up/down — صوت واحد لكلّ شخص، وإعادة نفس الصوت تُلغيه */
    public function vote(ThanksWallPost $post, User $user, int $value): int
    {
        $value = $value >= 0 ? 1 : -1;

        $existing = PostVote::query()
            ->where('votable_type', $post->getMorphClass())
            ->where('votable_id', $post->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && (int) $existing->value === $value) {
            $existing->delete();
        } elseif ($existing) {
            $existing->forceFill(['value' => $value])->save();
        } else {
            PostVote::create([
                'votable_type' => $post->getMorphClass(),
                'votable_id' => $post->id,
                'user_id' => $user->id,
                'value' => $value,
            ]);
        }

        $total = (int) PostVote::query()
            ->where('votable_type', $post->getMorphClass())
            ->where('votable_id', $post->id)
            ->sum('value');

        $post->forceFill(['votes' => $total])->save();

        return $total;
    }

    /** دخول النادي ⟵ احتفال ذروة (2.14) — ومرّة واحدة بحكم نظام الاحتفالات نفسه */
    public function celebrateEntry(User $user): ?array
    {
        return $this->personalProgress($user)['is_member']
            ? $this->bridge->celebrate($user, 'club.joined')
            : null;
    }
}
