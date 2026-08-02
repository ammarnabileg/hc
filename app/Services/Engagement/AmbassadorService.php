<?php

namespace App\Services\Engagement;

use App\Models\Referral;
use App\Models\User;
use App\Services\Gamification\CelebrationService;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * لقب السفير (7.6.1 · 2.9-8 · 21.1-ج).
 *
 * **ألقاب فقط بلا شارات** — والعتبات كلّها من `setting('ambassadors.tiers')`
 * فلا رقم محروق (2.13). واللقب يُمنَح **آليًّا** لحظة بلوغ عدد الدعوات
 * **المفعَّلة** (المدعوّ فعّل حسابه فعلًا) العتبةَ التالية — فلا يُحسَب تسجيلٌ
 * ناقص كإنجاز، وهذا شرط الصدق في العدّادات (2.9-7).
 */
class AmbassadorService
{
    public function __construct(private readonly CelebrationService $celebrations) {}

    public function enabled(): bool
    {
        return (bool) setting('ambassadors.enabled', true);
    }

    /**
     * عتبات الألقاب مرتّبةً تصاعديًّا — الافتراضيّ 5 · 15 · 30 · 50 (7.6.1).
     *
     * @return array<int,array{key:string,label:string,threshold:int}>
     */
    public function tiers(): array
    {
        $raw = setting('ambassadors.tiers', []);
        $tiers = [];

        foreach (is_array($raw) ? $raw : [] as $row) {
            if (! is_array($row) || ! isset($row['key'], $row['label'], $row['threshold'])) {
                continue;
            }

            $tiers[] = [
                'key' => (string) $row['key'],
                'label' => (string) $row['label'],
                'threshold' => (int) $row['threshold'],
            ];
        }

        usort($tiers, fn ($a, $b) => $a['threshold'] <=> $b['threshold']);

        return $tiers;
    }

    /** عدد الدعوات التي **فعّل** أصحابها حساباتهم فعلًا */
    public function activatedInvites(User $user): int
    {
        return Referral::query()
            ->where('referrer_id', $user->id)
            ->whereNotNull('referred_id')
            ->whereHas('referred', fn ($q) => $q->where('status', 'active'))
            ->count();
    }

    /**
     * أعلى لقب بلغه هذا العدد — أو null إن لم يبلغ أوّل عتبة بعد.
     *
     * @return array{key:string,label:string,threshold:int}|null
     */
    public function tierFor(int $count): ?array
    {
        $reached = null;

        foreach ($this->tiers() as $tier) {
            if ($count >= $tier['threshold']) {
                $reached = $tier;
            }
        }

        return $reached;
    }

    /**
     * اللقب التالي وما تبقّى له — «باقي القليل» بصدق بلا ضغط (2.9-3).
     *
     * @return array{key:string,label:string,threshold:int,remaining:int}|null
     */
    public function nextTier(int $count): ?array
    {
        foreach ($this->tiers() as $tier) {
            if ($count < $tier['threshold']) {
                return [...$tier, 'remaining' => $tier['threshold'] - $count];
            }
        }

        return null;
    }

    /**
     * مزامنة لقب مستخدم واحد — آمنة للتكرار: تُحدِّث العدّاد دائمًا،
     * ولا تُشعِر ولا تحتفل إلّا عند **بلوغ لقب جديد فعلًا**.
     *
     * @return array{key:string,tier:int,label:string,message:string,sound_path:?string,sound:bool}|null
     *                                                                                                   نتيجة الاحتفال (2.14) عند المنح، وnull فيما عدا ذلك.
     */
    public function sync(User $user): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $count = $this->activatedInvites($user);
        $tier = $this->tierFor($count);

        $changed = (int) $user->ambassador_invites !== $count;
        $granted = $tier !== null && $user->ambassador_tier !== $tier['key'];

        if (! $changed && ! $granted) {
            return null;
        }

        $user->forceFill(array_filter([
            'ambassador_invites' => $count,
            'ambassador_tier' => $granted ? $tier['key'] : $user->ambassador_tier,
            'ambassador_title' => $granted ? $tier['label'] : $user->ambassador_title,
            'ambassador_granted_at' => $granted ? now() : $user->ambassador_granted_at,
        ], fn ($value) => $value !== null))->saveQuietly();

        if (! $granted) {
            return null;
        }

        $this->notify($user, $tier);

        // احتفال بمستواه (2.14) — حدث مستقلّ لكلّ لقب فلا يتكرّر لقبٌ بلغه من قبل
        return $this->celebrations->fire($user, $this->celebrationKey($tier['key']));
    }

    /** مزامنة كلّ من له دعوات — نقطة نداء واحدة تكفي لضبط الألقاب كلّها */
    public function syncAll(): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $limit = max(1, (int) setting('ambassadors.sync.batch', 200));

        $ids = Referral::query()
            ->whereNotNull('referred_id')
            ->select('referrer_id')
            ->groupBy('referrer_id')
            ->limit($limit)
            ->pluck('referrer_id');

        $synced = 0;

        foreach (User::query()->whereIn('id', $ids)->get() as $user) {
            $this->sync($user);
            $synced++;
        }

        return $synced;
    }

    /**
     * لوحة متصدّري السفراء (7.6.1) — تنافس عامّ بأرقام **حقيقيّة**،
     * ولا تُعرَض أصلًا إن أقفلها الأدمن من الإعدادات.
     *
     * @return Collection<int,User>
     */
    public function leaderboard(?int $limit = null): Collection
    {
        $limit = $limit ?: (int) setting('ambassadors.leaderboard.limit', 20);

        return User::query()
            ->whereNotNull('ambassador_tier')
            ->where('status', 'active')
            ->orderByDesc('ambassador_invites')
            ->orderBy('ambassador_granted_at')
            ->limit(max(1, $limit))
            ->get(['id', 'name', 'code', 'avatar_path', 'ambassador_tier', 'ambassador_title', 'ambassador_invites', 'ambassador_granted_at']);
    }

    /** اللقب كما يُعرَض في البروفايل وبطاقة العضو — أو null لغير السفراء */
    public function titleOf(User $user): ?string
    {
        return $this->enabled() ? ($user->ambassador_title ?: null) : null;
    }

    /** مفتاح احتفال اللقب — يُسمّى بنمط واحد فيسهل ضبطه من شاشة الاحتفالات */
    public function celebrationKey(string $tierKey): string
    {
        return (string) setting('ambassadors.celebration.prefix', 'ambassador.').$tierKey;
    }

    private function notify(User $user, array $tier): void
    {
        $title = str_replace(
            [':label', ':name'],
            [$tier['label'], $user->shortName(1)],
            (string) setting('ambassadors.notification.title', 'بقيت :label 👑'),
        );

        $body = str_replace(
            [':label', ':count'],
            [$tier['label'], (string) $tier['threshold']],
            (string) setting('ambassadors.notification.body', ':count دعوة مفعَّلة وصلتك للقب :label — شكرًا إنّك بتكبّر المكان معانا.'),
        );

        Notifier::send(
            $user,
            (string) setting('ambassadors.notification.category', 'ambassador'),
            $title,
            $body,
            Route::has('ambassadors.index') ? route('ambassadors.index') : null,
        );
    }
}
