<?php

namespace App\Services\Account;

use App\Models\BadgeUser;
use App\Models\Certificate;
use App\Models\Cv;
use App\Models\Membership;
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

    public function __construct(
        private readonly ProfileVisibility $visibility,
        private readonly AchievementTracks $tracks,
    ) {}

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
     * مسارات الإنجازات الخمسة وعتباتها (10.1) — من **المصدر الواحد**
     * `AchievementTracks`، وهو نفسه الذي يقرؤه رادار اللوحة.
     *
     * كان هنا حسابٌ موازٍ بقيمٍ مختلفة (رصيد التذاكر بدل المكتسب، وكلّ الدعوات
     * بدل الناجحة، و`users.xp` بدل المحفظة) فاختلف «مستواك» بين بروفايلك ولوحتك.
     */
    public function achievements(User $owner): array
    {
        return $this->tracks->forUser($owner);
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
}
