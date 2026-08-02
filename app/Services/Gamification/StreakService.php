<?php

namespace App\Services\Gamification;

use App\Models\Streak;
use App\Models\StreakDay;
use App\Models\StreakReward;
use App\Models\User;
use App\Services\Learning\UserClock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * الستريكس ونادي الخامسة صباحًا (الدستور 7.2 · 7.1).
 *
 * المبدأ: الهدف بناء عادة لا معاقبة الانقطاع — فالانقطاع يُقابَل برسالة
 * محايدة تشجّع (2.17-ج)، والعدّ يبدأ من جديد بلا تجريح.
 *
 * ⭐ والقاعدة الحاكمة للنافذة: **بتوقيت كلّ مستخدم المحلّيّ حسب دولته** لا
 * بتوقيت الخادم (7.2 · 5) — فـ«4:50 ⟵ 5:20 ص» تعني فجر كلٍّ منهم هو.
 *
 * وثلاثة أشياء يقرّرها هذا الملفّ ولا تُترَك للواجهة:
 *  - **سلّم XP المتدرّج** حسب إجمالي أيّام الحضور (الأيّام ليست شرطًا متتابعة).
 *  - **تذكرة مكافأة السلسلة** عند اكتمال الدورة — مرّةً واحدة بسجلٍّ يمنع التكرار.
 *  - **درع التجميد**: تذكرة تحمي يومًا فايتًا فلا تنهار السلسلة.
 */
class StreakService
{
    public function __construct(
        private readonly CelebrationService $celebrations,
        private readonly WalletGateway $wallet,
        private readonly UserClock $clock,
    ) {}

    /**
     * ⭐ **حضور النادي — مسارٌ واحد لا غير: فعلٌ من المستخدم نفسه** (7.2).
     *
     * تسجيل اليوم في `streak_days` وتحديث `streaks`.
     * تكرار التسجيل في اليوم نفسه لا يزيد العدّاد ولا يكرّر الـXP.
     *
     * ⛔ **ممنوع استدعاء هذه الدالّة من أيّ مسارٍ آليّ** (تسوية مواجهة · وظيفة
     * مجدوَلة · ويب هوك …). الحضور يقع بالنافذة المحلّيّة وبضغطة المستخدم، وأيّ
     * استدعاءٍ تبعيّ يجعل حسابين يتواجهان فجرًا يحصدان XP وستريكًا **بلا حضور
     * حقيقيّ** — وهو سكٌّ لـXP من مسار حرب يخرق المحصّلة الصفريّة (15.2-6).
     * لتحديث لقطة النشاط بلا منح: استخدم {@see touchActivity()}.
     *
     * @return array{streak:Streak,club:bool,xp:int,message:string}
     */
    public function checkIn(User $user, ?CarbonInterface $at = null): array
    {
        $at = $this->clock->now($user, $at);
        $day = $at->toDateString();

        $inClubWindow = $this->inClubWindow($at);

        // المقارنة بـwhereDate لأنّ العمود يُخزَّن بصيغة تاريخ/وقت كاملة
        $streakDay = StreakDay::query()
            ->where('user_id', $user->id)
            ->whereDate('day', $day)
            ->first()
            ?? new StreakDay(['user_id' => $user->id, 'day' => $day]);

        // مرّة واحدة تكفي: من دخل نادي الخامسة اليوم يبقى فيه ولو سجّل ثانيةً بعدها
        $streakDay->club_5am = (bool) $streakDay->club_5am || $inClubWindow;
        $streakDay->is_freeze = false; // الحضور الحقيقيّ يعلو الدرع
        $streakDay->save();

        $xp = 0;

        // XP الحضور يُمنَح لدخول النافذة وحدها، ومرّةً واحدة لليوم (7.2)
        if ($streakDay->club_5am && (int) $streakDay->xp_awarded === 0) {
            $xp = $this->awardClubXp($user, $streakDay);
        }

        $streak = $this->recalculate($user, $at);

        // اكتمال الدورة = احتفال بمستوى الأدمن (2.14)
        if ($this->rewardIsDue($user, $streak)) {
            $this->celebrations->fire($user, 'streak.7days', $streak);
        }

        return [
            'streak' => $streak,
            'club' => (bool) $streakDay->club_5am,
            'xp' => $xp,
            'message' => $this->checkInMessage($streak, (bool) $streakDay->club_5am, $xp),
        ];
    }

    /**
     * **تحديث النشاط** — لقطة الستريك وحدها، بلا أيّ منح (7.2).
     *
     * لماذا تُقابِل `checkIn()` ولا تشبهها؟ لأنّ المسارات الآليّة تحتاج أن تعيد
     * حساب لقطة الستريك بعد حدث (تسوية مواجهة مثلًا) كي تعرضها الشاشة محدَّثة،
     * وهذا **لا يعني حضورًا**. فهذه الدالّة تُعيد الحساب من `streak_days`
     * المسجَّلة فعلًا: **لا تُنشئ يومًا · لا تمنح XP · لا تفتح تذكرة مكافأة**.
     */
    public function touchActivity(User $user, ?CarbonInterface $at = null): Streak
    {
        return $this->recalculate($user, $at);
    }

    /** ستريك المستخدم (يُنشأ فارغًا إن لم يوجد — فالشاشة تعرض دائمًا) */
    public function forUser(User $user): Streak
    {
        return Streak::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['current_days' => 0, 'best_days' => 0, 'club_5am_count' => 0],
        );
    }

    /** هل انقطع الستريك؟ (آخر يوم نشط أقدم من أمس) */
    public function isBroken(Streak $streak): bool
    {
        if ($streak->last_active_date === null) {
            return false;
        }

        $today = $this->clock->now($streak->user)->startOfDay();

        return $streak->last_active_date->lt($today->subDay());
    }

    public function recordedToday(User $user): bool
    {
        return StreakDay::query()
            ->where('user_id', $user->id)
            ->whereDate('day', $this->clock->now($user)->toDateString())
            ->where('is_freeze', false)
            ->exists();
    }

    // ------------------------------------------------------------------ سلّم XP (7.2)

    /**
     * سلّم XP الحضور المتدرّج حسب **إجمالي** أيّام النادي — والأيّام ليست شرطًا
     * أن تكون متتابعة، فالهدف بناء العادة لا معاقبة الانقطاع.
     * (100 لأوّل 10 · 150 للـ15 التالية · 200 · 250 · 300 · ثمّ 350 — والقيم إعداد.)
     */
    public function xpForClubDay(int $totalClubDays): int
    {
        $ladder = (array) setting('streaks.xp_ladder', []);
        $openEnded = 0;

        foreach ($ladder as $row) {
            $from = (int) ($row['from'] ?? 0);
            $to = (int) ($row['to'] ?? 0);
            $xp = (int) ($row['xp'] ?? 0);

            // to = 0 تعني «فما فوق» — آخر درجة في السلّم
            if ($to === 0) {
                $openEnded = $xp;

                if ($totalClubDays >= $from) {
                    return $xp;
                }

                continue;
            }

            if ($totalClubDays >= $from && $totalClubDays <= $to) {
                return $xp;
            }
        }

        return $openEnded;
    }

    /** الدرجة القادمة في السلّم — هدفٌ قريب يُعرَض للمستخدم (2.9) */
    public function nextLadderStep(int $totalClubDays): ?array
    {
        foreach ((array) setting('streaks.xp_ladder', []) as $row) {
            $from = (int) ($row['from'] ?? 0);

            if ($from > $totalClubDays) {
                return [
                    'at_day' => $from,
                    'xp' => (int) ($row['xp'] ?? 0),
                    'days_left' => $from - $totalClubDays,
                ];
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ تذكرة المكافأة (7.2)

    /** كم يومًا متواصلًا تُصرَف عندها المكافأة */
    public function rewardEveryDays(): int
    {
        return max(1, (int) setting('streaks.reward_days', 7));
    }

    /** هل استحقّ زرّ المكافأة الآن؟ (دورة مكتملة ولم تُصرَف بعد) */
    public function rewardIsDue(User $user, ?Streak $streak = null): bool
    {
        $streak = $streak ?? $this->forUser($user);
        $days = (int) $streak->current_days;

        if ($days <= 0 || $days % $this->rewardEveryDays() !== 0) {
            return false;
        }

        return ! StreakReward::query()
            ->where('user_id', $user->id)
            ->whereDate('day', $this->rewardDay($user, $streak))
            ->exists();
    }

    /**
     * صرف تذكرة المكافأة — والسجلّ يُكتَب داخل معاملة ذرّيّة قبل الإيداع،
     * فالضغط المزدوج لا يصرف تذكرتين (7.1-2).
     *
     * @return array{ok:bool,tickets:float,message:string}
     */
    public function claimReward(User $user): array
    {
        $streak = $this->forUser($user);

        if (! $this->rewardIsDue($user, $streak)) {
            return ['ok' => false, 'tickets' => 0.0, 'message' => (string) setting('streaks.reward.not_due_message')];
        }

        $tickets = (float) setting('streaks.reward_tickets', 1);
        $day = $this->rewardDay($user, $streak);

        $created = DB::transaction(function () use ($user, $streak, $day, $tickets) {
            $exists = StreakReward::query()
                ->where('user_id', $user->id)
                ->whereDate('day', $day)
                ->exists();

            if ($exists) {
                return null;
            }

            return StreakReward::create([
                'user_id' => $user->id,
                'day' => $day,
                'streak_days_count' => (int) $streak->current_days,
                'tickets' => $tickets,
            ]);
        });

        if (! $created) {
            return ['ok' => false, 'tickets' => 0.0, 'message' => (string) setting('streaks.reward.not_due_message')];
        }

        $this->wallet->credit($user, 'tickets', $tickets, (string) setting('streaks.reward.reason'), $created);

        return [
            'ok' => true,
            'tickets' => $tickets,
            'message' => str_replace(':tickets', (string) (int) $tickets, (string) setting('streaks.reward.claimed_message')),
        ];
    }

    // ------------------------------------------------------------------ درع التجميد (7.2 · 7.1-4)

    /**
     * اليوم الفايت الذي يستحقّ الحماية الآن — أو null.
     *
     * الدرع يحمي **يومًا فايتًا يصل ما انقطع**، لا أيّ يوم فارغ في التقويم:
     * فلا بدّ أن يكون قبل بداية سلسلتك الحيّة مباشرةً، وأن يوجد قبله يوم حضور
     * حقيقيّ يُوصَل به، وألّا يكون أقدم من الحدّ الذي يضبطه الأدمن.
     */
    public function freezableDay(User $user): ?CarbonImmutable
    {
        $today = $this->clock->now($user)->startOfDay();
        $maxAge = max(1, (int) setting('streaks.freeze_max_age_days', 2));
        $days = $this->recordedDays($user);

        $chainStart = $this->chainStart($days, $today);

        // بلا سلسلة حيّة لا شيء يُنقَذ اليوم — سجّل حضورك أوّلًا
        if ($chainStart === null) {
            return null;
        }

        $candidate = CarbonImmutable::parse($chainStart)->subDay();

        if (abs($today->diffInDays($candidate)) > $maxAge) {
            return null;
        }

        if (in_array($candidate->toDateString(), $days, true)) {
            return null;
        }

        // لا بدّ من حضورٍ أقدم يتّصل به الدرع، وإلّا فليس هناك سلسلة تُوصَل
        $hasEarlier = collect($days)->contains(fn (string $d) => $d < $candidate->toDateString());

        return $hasEarlier ? $candidate : null;
    }

    /** كم درعًا استُعمل هذا الشهر — السقف إعداد لا رقم محروق */
    /**
     * تكلفة درع التجميد (7.2 · 2.13): المصدر الحاكم صفّ «أوجه الصرف»
     * (`xp_rules.spend` ⟵ `streak.freeze`) — وكان الصفّ يُحرَّر بلا مستهلك
     * بينما تُقرأ القيمة من إعداد الستريك وحده، فمصدران لقيمة واحدة.
     * وإعداد `streaks.freeze_cost_tickets` بقي الافتراضيّ حين لا صفّ أصلًا.
     */
    public function freezeCost(): float
    {
        return app(EconomyRules::class)->spendCost('streak.freeze', (float) setting('streaks.freeze_cost_tickets', 1));
    }

    public function freezesUsedThisMonth(User $user): int
    {
        $now = $this->clock->now($user);

        return StreakDay::query()
            ->where('user_id', $user->id)
            ->where('is_freeze', true)
            ->whereDate('day', '>=', $now->startOfMonth()->toDateString())
            ->whereDate('day', '<=', $now->endOfMonth()->toDateString())
            ->count();
    }

    /**
     * شراء درع يحمي يومًا فايتًا بتذكرة (7.1-4).
     *
     * @return array{ok:bool,message:string}
     */
    public function buyFreeze(User $user): array
    {
        $day = $this->freezableDay($user);

        if (! $day) {
            return ['ok' => false, 'message' => (string) setting('streaks.freeze.nothing_message')];
        }

        $cap = (int) setting('streaks.max_freezes_per_month', 2);

        if ($this->freezesUsedThisMonth($user) >= $cap) {
            return ['ok' => false, 'message' => str_replace(':cap', (string) $cap, (string) setting('streaks.freeze.cap_message'))];
        }

        $cost = $this->freezeCost();

        if ($this->wallet->balance($user, 'tickets') < $cost) {
            return ['ok' => false, 'message' => (string) setting('streaks.freeze.no_tickets_message')];
        }

        $streakDay = StreakDay::query()->firstOrCreate(
            ['user_id' => $user->id, 'day' => $day->toDateString()],
            ['club_5am' => false, 'xp_awarded' => 0, 'is_freeze' => true],
        );

        // الخصم بعد ضمان وجود اليوم — فلا تُخصَم تذكرة بلا حماية
        $this->wallet->debit($user, 'tickets', $cost, (string) setting('streaks.freeze.reason'), $streakDay);

        $this->recalculate($user);

        return [
            'ok' => true,
            'message' => str_replace(
                ':day',
                $day->translatedFormat((string) setting('streaks.freeze.day_format', 'j F')),
                (string) setting('streaks.freeze.done_message'),
            ),
        ];
    }

    // ------------------------------------------------------------------ عرض

    /**
     * أيّام الفترة للخريطة الحراريّة: التاريخ ⟵ ['active','club','freeze'].
     *
     * @return array<string, array{active:bool,club:bool,freeze:bool}>
     */
    public function heatmap(User $user, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = StreakDay::query()
            ->where('user_id', $user->id)
            ->whereDate('day', '>=', $from->format('Y-m-d'))
            ->whereDate('day', '<=', $to->format('Y-m-d'))
            ->get()
            ->keyBy(fn (StreakDay $d) => $d->day->toDateString());

        $map = [];
        $cursor = CarbonImmutable::parse($from->format('Y-m-d'));
        $end = CarbonImmutable::parse($to->format('Y-m-d'));

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $row = $rows->get($key);

            $map[$key] = [
                'active' => $row !== null,
                'club' => (bool) $row?->club_5am,
                'freeze' => (bool) $row?->is_freeze,
            ];

            $cursor = $cursor->addDay();
        }

        return $map;
    }

    /** آخر أيّام النادي — لعرضها في الشرح */
    public function clubDays(User $user, int $limit = 5): Collection
    {
        return StreakDay::query()
            ->where('user_id', $user->id)
            ->where('club_5am', true)
            ->orderByDesc('day')
            ->limit($limit)
            ->get();
    }

    public function clubDaysCount(User $user): int
    {
        return StreakDay::query()->where('user_id', $user->id)->where('club_5am', true)->count();
    }

    /** نافذة نادي الخامسة كما ضبطها الأدمن — نصًّا للعرض */
    public function clubWindow(): array
    {
        return [
            'start' => (string) setting('streaks.club5am.window_start', '04:50'),
            'end' => (string) setting('streaks.club5am.window_end', '05:20'),
        ];
    }

    /** هل النافذة مفتوحة الآن **بتوقيت هذا المستخدم**؟ — شرط الشريط العلويّ (7.2) */
    public function windowIsOpenFor(User $user, ?CarbonInterface $at = null): bool
    {
        return $this->inClubWindow($this->clock->now($user, $at));
    }

    /** المنطقة الزمنيّة التي تُقاس بها نافذة هذا المستخدم — تُعرَض معها دائمًا */
    public function timezoneFor(User $user): string
    {
        return $this->clock->timezoneFor($user);
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * إعادة حساب السلسلة من الأيّام المسجَّلة — لا بالزيادة خطوةً خطوة.
     * لماذا؟ لأنّ درع التجميد يُدخِل يومًا **في الماضي**، والحساب التراكميّ
     * لا يراه؛ فالعدّ من السجلّ هو الوحيد الذي يعطي نفس النتيجة دائمًا.
     */
    public function recalculate(User $user, ?CarbonInterface $at = null): Streak
    {
        $now = $this->clock->now($user, $at);
        $days = $this->recordedDays($user);
        $chainStart = $this->chainStart($days, $now->startOfDay());

        $current = $chainStart === null
            ? 0
            : (int) abs(CarbonImmutable::parse($days[0])->diffInDays(CarbonImmutable::parse($chainStart))) + 1;

        $streak = $this->forUser($user);

        $streak->forceFill([
            'current_days' => $current,
            'best_days' => max((int) $streak->best_days, $current),
            'last_active_date' => $days[0] ?? null,
            'club_5am_count' => $this->clubDaysCount($user),
        ])->save();

        return $streak->refresh();
    }

    /**
     * أيّام الحضور المسجَّلة نزولًا (الأحدث أوّلًا) كسلاسل تواريخ.
     *
     * @return array<int, string>
     */
    private function recordedDays(User $user): array
    {
        return StreakDay::query()
            ->where('user_id', $user->id)
            ->orderByDesc('day')
            ->pluck('day')
            ->map(fn ($d) => CarbonImmutable::parse($d)->toDateString())
            ->values()
            ->all();
    }

    /**
     * أوّل يوم في السلسلة الحيّة — أو null إن كانت منقطعة.
     * السلسلة حيّة إن كان آخر يوم مسجَّل هو اليوم أو أمس.
     *
     * @param  array<int, string>  $days  نزولًا
     */
    private function chainStart(array $days, CarbonImmutable $today): ?string
    {
        $expected = null;
        $start = null;

        foreach ($days as $day) {
            if ($expected === null) {
                if ($day !== $today->toDateString() && $day !== $today->subDay()->toDateString()) {
                    return null;
                }

                $expected = $day;
            }

            if ($day !== $expected) {
                break;
            }

            $start = $day;
            $expected = CarbonImmutable::parse($expected)->subDay()->toDateString();
        }

        return $start;
    }

    /** يوم الدورة المكتملة الذي تُنسَب إليه المكافأة */
    private function rewardDay(User $user, Streak $streak): string
    {
        return $streak->last_active_date?->toDateString() ?? $this->clock->now($user)->toDateString();
    }

    /** منح XP الحضور وتسجيله على اليوم — فلا يتكرّر مهما أُعيد الضغط */
    private function awardClubXp(User $user, StreakDay $streakDay): int
    {
        $total = $this->clubDaysCount($user);
        $xp = $this->xpForClubDay($total);

        if ($xp <= 0) {
            return 0;
        }

        $streakDay->forceFill(['xp_awarded' => $xp])->save();

        $this->wallet->credit($user, 'xp', $xp, (string) setting('streaks.club5am.xp_reason'), $streakDay);
        // عمود users.xp هو مصدر الترتيب في الليدر بورد (7.3) فيُحدَّث معه
        $user->increment('xp', $xp);

        return $xp;
    }

    private function checkInMessage(Streak $streak, bool $club, int $xp): string
    {
        $template = $club
            ? (string) setting('streaks.checkin.club_message')
            : (string) setting('streaks.checkin.message');

        return str_replace(
            [':days', ':xp'],
            [(string) (int) $streak->current_days, (string) $xp],
            $template,
        );
    }

    /**
     * هل نحن داخل النافذة؟ — والنافذة التي تعبر منتصف الليل (23:50 ⟵ 00:20)
     * لا تُسقِط أحدًا من النادي.
     */
    private function inClubWindow(CarbonInterface $at): bool
    {
        $window = $this->clubWindow();
        $minutes = ((int) $at->format('H') * 60) + (int) $at->format('i');
        $start = $this->toMinutes($window['start']);
        $end = $this->toMinutes($window['end']);

        return $end >= $start
            ? ($minutes >= $start && $minutes <= $end)
            : ($minutes >= $start || $minutes <= $end);
    }

    private function toMinutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return ((int) $h * 60) + (int) $m;
    }
}
