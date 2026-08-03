<?php

namespace App\Services\Account;

use App\Models\BadgeUser;
use App\Models\Certificate;
use App\Models\Cv;
use App\Models\Enrollment;
use App\Models\Membership;
use App\Models\RepScore;
use App\Models\User;
use App\Services\Engagement\AmbassadorService;
use App\Services\Gamification\LeaderboardService;
use App\Services\Gamification\LevelResolver;
use App\Services\Gamification\TicketsAccount;
use App\Services\Library\CvBuilder;

/**
 * بيانات تابات البروفايل (الدستور 10 · 10.0 · 24.5).
 *
 * الترتيب النهائيّ: نظرة عامّة · الإنجازات · الشهادات · خبراتي —
 * **ثمّ تابات التطوّع** التي يحقنها مجالُ التطوّع في `volunteer_profile_tabs`.
 * وكلّ تاب يُحمَّل عند فتحه فقط (تحميل كسول — 2.15-د).
 */
class ProfileTabs
{
    /**
     * ⭐ تاب «تفاصيل» ليس زيادةً على الدستور بل **حلُّه**: 10.0-أ يطلب سبعة كروت
     * KPI، و2.15-أ-3 يحدّ الشاشة بأربعة — والنصّ نفسه يحسم التعارض: «الأربعة
     * الأهمّ ظاهرة والباقي في تاب تفاصيل» — لا حذف الثلاثة الباقية.
     */
    public const KEYS = ['overview', 'details', 'achievements', 'certificates', 'experience'];

    public function __construct(
        private readonly ProfileVisibility $visibility,
        private readonly AchievementTracks $tracks,
        private readonly CvBuilder $cv,
        private readonly AmbassadorService $ambassadors,
        private readonly LeaderboardService $leaderboard,
        private readonly LevelResolver $levels,
        private readonly TicketsAccount $tickets,
    ) {}

    /** @return array<int, array{key:string,label:string}> */
    public static function definitions(): array
    {
        return [
            ['key' => 'overview', 'label' => (string) setting('account.profile.tab.overview_label', 'نظرة عامّة')],
            ['key' => 'details', 'label' => (string) setting('account.profile.tab.details_label', 'تفاصيل')],
            ['key' => 'achievements', 'label' => (string) setting('account.profile.tab.achievements_label', 'الإنجازات')],
            ['key' => 'certificates', 'label' => (string) setting('account.profile.tab.certificates_label', 'الشهادات')],
            ['key' => 'experience', 'label' => (string) setting('account.profile.tab.experience_label', 'خبراتي')],
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

    /**
     * الكروت السبعة التي يطلبها 10.0-أ — **الأربعة الأهمّ** منها تظهر في
     * «نظرة عامّة» والباقي في تاب «تفاصيل» (2.15-أ-3).
     *
     * @return array<int, array{key:string,label:string,value:mixed,icon:string,primary:bool}>
     */
    public function kpis(User $owner): array
    {
        $tracks = collect($this->tracks->forUser($owner))->keyBy('key');
        $ambassador = $this->ambassadors->enabled() ? $this->ambassadors->titleOf($owner) : null;

        $account = $this->levels->forUser($owner);

        $cards = [
            // ⭐ المستوى وXP من **المصدر الواحد** لا من عمود `users.level` المخبَّأ
            ['key' => 'level', 'label' => 'مستوى الحساب + XP', 'icon' => '🎯',
                'value' => $account['level'].' · '.number_format($account['xp'])],
            // «**رصيد** التذاكر» (10.0-أ) — غير «المكتسب» في تاب الإنجازات، والاسم يفرّق
            ['key' => 'tickets', 'label' => 'رصيد التذاكر', 'icon' => '🎟️',
                'value' => $this->tickets->balance($owner)],
            ['key' => 'streak', 'label' => 'ستريك نادي الخامسة', 'icon' => '🔥',
                'value' => (int) ($owner->streak?->club_5am_count ?? 0)],
            ['key' => 'certificates', 'label' => 'الشهادات', 'icon' => '🎓',
                'value' => Certificate::where('user_id', $owner->id)->where('status', 'valid')->count()],
            ['key' => 'courses', 'label' => 'التدريبات (مكتملة/جارية)', 'icon' => '📚',
                'value' => $this->trainingCounts($owner)],
            ['key' => 'rank', 'label' => 'ترتيب الليدر بورد', 'icon' => '🏆',
                'value' => $this->leaderboardRank($owner)],
            ['key' => 'ambassador', 'label' => 'لقب السفير', 'icon' => '🤝',
                'value' => $ambassador ?: (string) setting('account.profile.kpi.no_ambassador', 'لسّه')],
        ];

        $primary = (int) setting('ux.kpi.max_cards', 4);

        return array_map(
            fn (array $card, int $i) => [
                ...$card,
                'label' => (string) setting("account.profile.kpi.{$card['key']}_label", $card['label']),
                'primary' => $i < $primary,
            ],
            $cards,
            array_keys($cards),
        );
    }

    /** «مكتملة/جارية» من مصدر التسجيلات الواحد — لا حساب موازٍ (10.0-أ) */
    private function trainingCounts(User $owner): string
    {
        $rows = Enrollment::query()->where('user_id', $owner->id)->get(['status', 'progress_percent']);

        $done = $rows->filter(fn ($row) => $row->status === 'completed' || (int) $row->progress_percent >= 100)->count();

        return $done.' / '.max(0, $rows->count() - $done);
    }

    /** ترتيب صاحب البروفايل على ليدربورد الـXP — من المصدر الواحد (10.0-أ) */
    private function leaderboardRank(User $owner): string
    {
        $board = $this->leaderboard->xp($owner, 'all', (int) setting('leaderboard.profile_range_days', 30));
        $rank = $board['me']['rank'] ?? null;

        return $rank ? '#'.$rank : (string) setting('account.profile.kpi.unranked', 'خارج اللوحة');
    }

    /** بيانات تاب «نظرة عامّة» بحسب مستوى المشاهدة — و`null` لمن هو خارج الأربعة */
    public function overview(User $owner, ?User $viewer, ?string $level): array
    {
        $account = $this->levels->forUser($owner);

        return [
            'kpis' => $this->kpis($owner),
            // المستوى وXP من المصدر الواحد — لا من العمودين المخبَّأين على المستخدم
            'level' => $account['level'],
            'xp' => $account['xp'],
            'level_name' => $account['name'],
            'xp_percent' => $account['percent'],
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

    /**
     * «خبراتي» = الـCV معروضًا (قسم 9).
     *
     * والبيانات تُسلَّم **بمفاتيح المخزن نفسها** (`profile.summary` · `experience`
     * · `skills` نصًّا) لا بمفاتيح مخترَعة — فأيّ اختلاف يُسقط القسم بصمت.
     */
    public function experience(User $owner): array
    {
        $cv = Cv::where('user_id', $owner->id)->first();
        $data = is_array($cv?->data) ? $cv->data : [];

        return [
            'cv' => $cv,
            'data' => $data,
            // الربط التلقائيّ بالمنصّة: التدريبات المكتملة والشهادات (9)
            'pulled' => $cv ? $this->cv->pulled($owner, $data) : ['certificates' => collect(), 'trainings' => collect()],
        ];
    }
}
