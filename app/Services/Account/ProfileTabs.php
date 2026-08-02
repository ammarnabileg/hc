<?php

namespace App\Services\Account;

use App\Models\BadgeUser;
use App\Models\Certificate;
use App\Models\Cv;
use App\Models\LessonCompletion;
use App\Models\Membership;
use App\Models\Referral;
use App\Models\RepScore;
use App\Models\User;

/**
 * بيانات تابات البروفايل (الدستور 10 · 10.0 · 24.5).
 *
 * الترتيب النهائيّ: نظرة عامّة · الإنجازات · الشهادات · خبراتي —
 * **ثمّ تابات التطوّع** التي يحقنها مجالُ التطوّع في `volunteer_profile_tabs`.
 * وكلّ تاب يُحمَّل عند فتحه فقط (تحميل كسول — 2.15-د).
 */
class ProfileTabs
{
    public const KEYS = ['overview', 'achievements', 'certificates', 'experience'];

    public function __construct(private readonly ProfileVisibility $visibility) {}

    /** @return array<int, array{key:string,label:string}> */
    public static function definitions(): array
    {
        return [
            ['key' => 'overview', 'label' => 'نظرة عامّة'],
            ['key' => 'achievements', 'label' => 'الإنجازات'],
            ['key' => 'certificates', 'label' => 'الشهادات'],
            ['key' => 'experience', 'label' => 'خبراتي'],
        ];
    }

    public static function normalize(?string $tab): string
    {
        return in_array($tab, self::KEYS, true) ? $tab : 'overview';
    }

    /** العضويّة النشطة — بها تظهر إضافات هيدر المتطوّع (10.0-ب) */
    public function activeMembership(User $user): ?Membership
    {
        return Membership::query()
            ->with(['entity:id,name_ar', 'position:id,name_ar,rank'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->orderByDesc('is_primary')
            ->first();
    }

    /** شارة Rep المرئيّة بلونها ورمزها (2.16 · 13.4-م) */
    public function rep(User $user): ?array
    {
        $score = RepScore::where('user_id', $user->id)->value('score');

        if ($score === null) {
            return null;
        }

        $score = (float) $score;

        // موجب أخضر · بين 0 و−5 أصفر · تحت −8 أحمر (2.16-أ)
        $state = match (true) {
            $score <= (float) setting('account.profile.rep_danger_below', -8) => 'danger',
            $score < 0 => 'warn',
            default => 'ok',
        };

        return [
            'score' => $score,
            'state' => $state,
            'in_club' => $score >= (float) setting('account.profile.excellence_club_threshold', 9.5),
        ];
    }

    /** بيانات تاب «نظرة عامّة» بحسب مستوى المشاهدة */
    public function overview(User $owner, ?User $viewer, string $level): array
    {
        return [
            'level' => $owner->level,
            'xp' => $owner->xp,
            'certificates_count' => Certificate::where('user_id', $owner->id)->where('status', 'valid')->count(),
            'badges_count' => BadgeUser::where('user_id', $owner->id)->count(),
            'joined_at' => $owner->created_at,
            // نقطة نشاط فقط — **بلا «آخر ظهور»** (13.4-م)
            'is_online' => $owner->last_seen_at
                && $owner->last_seen_at->gt(now()->subMinutes((int) setting('account.profile.online_window_minutes', 10))),
            'country' => $owner->country?->name_ar,
            // ⭐ المحافظة عامّة دائمًا (12.14-د)
            'governorate' => $owner->governorate?->name_ar,
            'can_see_country' => $this->visibility->canSee('country', $viewer, $owner, $level),
        ];
    }

    /**
     * مسارات الإنجازات الخمسة وعتباتها (10.1): base + (N−2) × step تراكميًّا.
     *
     * ⭐ العتبات والعناوين والوحدات كلّها إعدادات `dashboard.achievements.*` (2.13)
     * — وهي **نفس** مفاتيح رادار اللوحة، لأنّ العتبة واحدة في المنصّة كلّها؛
     * فلو صارت هنا نسخةٌ ثانية اختلف «مستواك» بين بروفايلك ولوحتك.
     */
    public function achievements(User $owner): array
    {
        $values = [
            'account' => (int) $owner->xp,
            'club_5am' => (int) ($owner->streak?->club_5am_count ?? 0),
            'referrals' => Referral::where('referrer_id', $owner->id)->count(),
            'tickets' => (int) $owner->balance('tickets'),
            'learning' => LessonCompletion::where('user_id', $owner->id)->count(),
        ];

        $tracks = [];

        foreach ($this->trackKeys() as $key) {
            $tracks[] = [
                'key' => $key,
                'label' => (string) setting("dashboard.achievements.{$key}.label", $key),
                'unit' => (string) setting("dashboard.achievements.{$key}.unit", ''),
                'value' => $values[$key] ?? 0,
                'base' => max(1, (int) setting("dashboard.achievements.{$key}.base", 1)),
                'step' => max(1, (int) setting("dashboard.achievements.{$key}.step", 1)),
            ];
        }

        return array_map(function (array $track) {
            [$level, $current, $next] = $this->levelFor($track['value'], $track['base'], $track['step']);

            return [
                ...$track,
                'level' => $level,
                'current_threshold' => $current,
                'next_threshold' => $next,
                'percent' => $next > $current
                    ? (int) max(0, min(100, round(($track['value'] - $current) / ($next - $current) * 100)))
                    : 100,
            ];
        }, $tracks);
    }

    /**
     * مسارات الرادار وترتيبها — إعداد واحد يخدم البروفايل واللوحة معًا (10.1 · 2.13).
     *
     * @return array<int, string>
     */
    private function trackKeys(): array
    {
        $keys = setting('dashboard.achievements.tracks');

        return is_array($keys) && $keys !== []
            ? array_values(array_filter($keys, 'is_string'))
            : ['account', 'club_5am', 'referrals', 'tickets', 'learning'];
    }

    /** الشهادات من المصدر الواحد (12.5 / مكتبتي 20) — لا حساب موازٍ */
    public function certificates(User $owner)
    {
        return Certificate::query()
            ->with('certificate_type:id,name_ar')
            ->where('user_id', $owner->id)
            ->where('status', 'valid')
            ->orderByDesc('issued_at')
            ->get();
    }

    /** «خبراتي» = الـCV معروضًا (قسم 9) */
    public function experience(User $owner): array
    {
        $cv = Cv::where('user_id', $owner->id)->first();

        return [
            'cv' => $cv,
            'data' => is_array($cv?->data) ? $cv->data : [],
        ];
    }

    /**
     * العتبة التراكميّة للوصول للمستوى N (10.1):
     * الزيادة = base + (N − 2) × step، والمستويات مفتوحة بلا سقف.
     *
     * @return array{0:int,1:int,2:int} [المستوى, عتبة المستوى الحاليّ, عتبة التالي]
     */
    private function levelFor(int $value, int $base, int $step): array
    {
        $level = 1;
        $cumulative = 0;
        $next = $base;
        $guard = 0;

        while ($value >= $next && $guard++ < 200) {
            $level++;
            $cumulative = $next;
            $next += $base + ($level - 1) * $step;
        }

        return [$level, $cumulative, $next];
    }
}
