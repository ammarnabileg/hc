<?php

namespace App\Services\Gamification;

use App\Models\Badge;
use App\Models\BadgeUser;
use App\Models\Certificate;
use App\Models\ChallengeParticipation;
use App\Models\CourseCompletion;
use App\Models\LessonCompletion;
use App\Models\Membership;
use App\Models\Referral;
use App\Models\Streak;
use App\Models\User;
use App\Models\WarUserStat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * الشارات (7.4 · 24.5).
 *
 * قاعدة الشاشة: **شرط الفتح مكتوب صراحةً** في `badges.condition_text_ar` — لا ألغاز.
 * وهذه الخدمة تمنح الشارة آليًّا حين يبلغ `condition_key` قيمةَ `condition_value`.
 */
class BadgeService
{
    public function __construct(
        private readonly CelebrationService $celebrations,
        private readonly LevelResolver $levels,
    ) {}

    /**
     * فحص كلّ الشارات ومنح ما استُحقّ.
     *
     * @return Collection<int, Badge> الشارات الممنوحة الآن فقط
     */
    public function evaluate(User $user): Collection
    {
        $owned = BadgeUser::query()->where('user_id', $user->id)->pluck('badge_id')->all();
        $metrics = $this->metrics($user);
        $awarded = collect();

        $candidates = Badge::query()
            ->where('is_active', true)
            ->whereNotNull('condition_key')
            ->whereNotIn('id', $owned)
            ->get();

        foreach ($candidates as $badge) {
            $value = $metrics[$badge->condition_key] ?? null;

            if ($value === null || $value < (float) $badge->condition_value) {
                continue;
            }

            BadgeUser::query()->firstOrCreate(
                ['badge_id' => $badge->id, 'user_id' => $user->id],
                ['awarded_at' => now()],
            );

            $awarded->push($badge);
        }

        if ($awarded->isNotEmpty()) {
            $this->celebrations->fire($user, 'badge.unlocked', $awarded->first());
        }

        return $awarded;
    }

    /**
     * كلّ الشارات مع حالة المستخدم فيها — الشبكة تظهر دائمًا:
     * المفتوح ملوّن، والمقفول رماديّ وشرطه مكتوب.
     *
     * @return Collection<int, array{badge:Badge,unlocked:bool,awarded_at:?Carbon,progress:float}>
     */
    public function board(User $user): Collection
    {
        $owned = BadgeUser::query()->where('user_id', $user->id)->get()->keyBy('badge_id');
        $metrics = $this->metrics($user);

        return Badge::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(function (Badge $badge) use ($owned, $metrics) {
                $target = (float) ($badge->condition_value ?: 0);
                $value = (float) ($metrics[$badge->condition_key] ?? 0);

                return [
                    'badge' => $badge,
                    'unlocked' => $owned->has($badge->id),
                    'awarded_at' => $owned->get($badge->id)?->awarded_at,
                    'progress' => $target > 0 ? min(100, round($value / $target * 100)) : 0,
                ];
            });
    }

    /**
     * ⭐ المقاييس المتاحة بأسمائها العربيّة — **القائمة المقفولة** التي يختار
     * منها الأدمن في فورم الشارة (7.4 · 2.13).
     *
     * لماذا قائمة لا نصّ حرّ؟ لأنّ الحقل الحرّ كان **مصنع الشارات الميتة**:
     * مفتاحٌ مكتوبٌ بخطأٍ حرف = شارةٌ لا تُمنَح أبدًا ولا يُنبَّه أحد، والمتدرّب
     * يرى «0% من الشرط» وهو مستوفيه. المفتاح الذي لا يقابله مقياسٌ هنا لا يجوز
     * أن يُحفَظ أصلًا.
     *
     * @return array<string, string>
     */
    public const CONDITIONS = [
        'xp.total' => 'إجمالي XP',
        'level.reached' => 'المستوى المبلوغ',
        'lesson.completed' => 'عدد الدروس المكتملة',
        'course.completed' => 'عدد التدريبات المكتملة',
        'certificate.issued' => 'عدد الشهادات السارية',
        'streak.days' => 'الستريك الحاليّ (أيّام)',
        'streak.best_days' => 'أطول ستريك (أيّام)',
        'streak.current_days' => 'الستريك الحاليّ (أيّام)',
        'club_5am.days' => 'أيّام نادي الخامسة',
        'five_am.count' => 'أيّام نادي الخامسة',
        'membership.count' => 'عدد البوزشنز التطوّعيّة',
        'referral.success' => 'الدعوات الناجحة',
        'challenges.finished' => 'الحروب المنتهية',
        'challenges.wins' => 'انتصارات الحروب',
        'focus.minutes' => 'دقائق حرب التركيز',
    ];

    /** @return array<string, string> */
    public function conditions(): array
    {
        return self::CONDITIONS;
    }

    /**
     * مقاييس الشروط المدعومة — مقفولة وصريحة (لا محرّك قواعد موازٍ).
     *
     * **كلّ مفتاح في `CONDITIONS` له قيمة هنا** — وإلّا كانت الشارة ميتة أبدًا.
     *
     * @return array<string, float>
     */
    public function metrics(User $user): array
    {
        $streak = Streak::query()->where('user_id', $user->id)->first();
        $streakDays = (float) ($streak?->current_days ?? 0);
        $clubDays = (float) ($streak?->club_5am_count ?? 0);

        $lessons = (float) LessonCompletion::query()->where('user_id', $user->id)->count();

        /*
         | ⭐ **XP من المصدر الواحد** (ن-2). كان هنا `$user->xp` — العمود وحده،
         | بينما اللوحة والرادار والليدر بورد تقرأ دفتر المحفظة. فشارةٌ شرطها
         | «5,000 XP» تُقيَّم برقمٍ غير الذي يراه صاحبها على الشاشة، فيستوفي
         | الشرط في عينه ولا تُمنَح — وهو نفس عطل «رقمين لمعنًى واحد».
         */
        $xp = $this->levels->xpFor($user);

        return [
            'xp.total' => (float) $xp,
            // ⭐ المستوى دالّةٌ واحدة في XP بصيغة 10.1 — لا عتبات جدولٍ ولا عمودٌ متأخّر
            'level.reached' => (float) $this->levels->levelFor($xp),
            // ⭐ مسار التعلّم كان غائبًا تمامًا عن الشارات — وهو قلب المنصّة (7 · 7.4)
            'lesson.completed' => $lessons,
            'course.completed' => (float) CourseCompletion::query()->where('user_id', $user->id)->count(),
            'certificate.issued' => (float) Certificate::query()
                ->where('user_id', $user->id)->where('status', 'valid')->count(),
            // مفتاحان لمعنًى واحد: القديم يبقى حيًّا كي لا تموت شارةٌ قائمة
            'streak.days' => $streakDays,
            'streak.current_days' => $streakDays,
            'streak.best_days' => (float) ($streak?->best_days ?? 0),
            'club_5am.days' => $clubDays,
            'five_am.count' => $clubDays,
            'membership.count' => (float) Membership::query()
                ->where('user_id', $user->id)->where('status', 'active')->count(),
            // الدعوة «ناجحة» حين صُرِفت مكافأتها فعلًا — لا بمجرّد الضغط على الرابط (7.6)
            'referral.success' => (float) Referral::query()
                ->where('referrer_id', $user->id)->where('referrer_ticket_granted', true)->count(),
            'challenges.finished' => (float) ChallengeParticipation::query()
                ->where('user_id', $user->id)->where('status', 'finished')->count(),
            'challenges.wins' => (float) ChallengeParticipation::query()
                ->where('user_id', $user->id)->where('result', 'win')->count(),
            // دقائق حرب التركيز — شارة 24 ساعة تراكميّة (15.3)
            'focus.minutes' => (float) WarUserStat::query()
                ->where('user_id', $user->id)->value('focus_minutes'),
        ];
    }
}
