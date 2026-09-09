<?php

namespace App\Services\Referral;

use App\Http\Controllers\Onboarding\OnboardingController;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Events\LedgerBridge;
use App\Services\Events\Tracker;
use App\Services\Growth\UtmBuilder;
use App\Services\Wallet\ReferralCommissionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * نظام الدعوات (7.6) + مكافأة الطرفين (21.1-ج):
 * الداعي عمولة `referral.commission_percent`% مدى الحياة،
 * **والمدعوّ تذكرة ترحيب** تُمنَح **عند تفعيل حسابه ومرّة واحدة**.
 */
class ReferralService
{
    public function __construct(
        private readonly DeepLink $deepLink,
        private readonly LedgerBridge $ledger,
        private readonly Tracker $tracker,
    ) {}

    /** نسبة العمولة — إعداد لا رقم محروق (2.13) */
    public function commissionPercent(): float
    {
        return (float) setting('referral.commission_percent', 7);
    }

    /**
     * ⭐ حفظ الداعي في **السيشن** (7.6) — لا في الـQuery وحدها.
     *
     * كان الكود يُمرَّر في `?offer=` فقط، فأيّ تشتّت (تبويب جديد · رجوع للخلف ·
     * تحقّق OTP في نافذة أخرى · فتح `/register` مباشرةً) يُسقِط الدعوة، فيخسر
     * **الطرفان** التذكرة، ويخسر الداعي **عمولة مدى الحياة** — وهي أثمن ما في
     * النظام. والسيشن يعبر كلّ ذلك لأنّه ملتصق بالمتصفّح لا بالرابط.
     *
     * ولا يُدهَس داعٍ محفوظ بآخر: **أوّل مَن دعا يفوز**، فلا تُسرَق الدعوة برابطٍ
     * لاحق يفتحه الزائر بالصدفة.
     */
    public function rememberReferrerCode(?string $code): bool
    {
        $code = trim((string) $code);

        if ($code === '' || session()->has(OnboardingController::SESSION_CODE)) {
            return false;
        }

        if (! User::query()->where('code', $code)->exists()) {
            return false;
        }

        /*
         | مفتاحٌ واحد لا اثنان: نكتب في نفس مفتاح رحلة الأونبوردنج الذي يقرؤه
         | التسجيل — فمفتاحان لنفس المعنى يعنيان دعوةً تنجو في مسارٍ وتضيع في آخر.
         | و`ANSWERED` معه لأنّ 7.6 صريح: **مَن دخل عبر رابط دعوة لا تظهر له شاشة
         | «هل دعاك شخص ما؟»**، والمكافأة تُحتسَب عادي.
         */
        session()->put([
            OnboardingController::SESSION_CODE => $code,
            OnboardingController::SESSION_ANSWERED => true,
        ]);

        return true;
    }

    /**
     * ⭐ كود الداعي الوارد: من الرابط أوّلًا (وبأيّ من الصيغتين)، ثمّ من السيشن.
     *
     * **حسم تعارض داخل الدستور:** 7.6 يكتب الرابط `?offer=<user_id>` و7.6.2 يكتبه
     * `/join?ref=CODE`. المعتمَد صيغة **7.6.2** لأنّها لا تكشف المعرّفات الرقميّة
     * (عدّ تسلسليّ يُفشي حجم القاعدة ويسمح بالتخمين)، مع إبقاء `?offer=` **مقبولًا
     * للتوافق** فلا تموت روابط قديمة بين يدي الناس.
     */
    public function incomingCode(?string $fromQuery = null, ?string $legacy = null): ?string
    {
        foreach ([$fromQuery, $legacy] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                $this->rememberReferrerCode($candidate);

                return trim($candidate);
            }
        }

        $stored = session(OnboardingController::SESSION_CODE);

        return is_string($stored) && $stored !== '' ? $stored : null;
    }

    /** ينسى الدعوة بعد استهلاكها — فلا تُربَط بحسابٍ ثانٍ في نفس المتصفّح */
    public function forgetReferrerCode(): void
    {
        session()->forget([OnboardingController::SESSION_CODE, OnboardingController::SESSION_ANSWERED]);
    }

    public function welcomeTickets(): int
    {
        return (int) setting('referral.welcome_tickets', 1);
    }

    /** ⭐ تذكرة الداعي (7.6): «يحصل **كلٌ من الداعي والمدعو** على تذكرة» */
    public function referrerTickets(): int
    {
        return (int) setting('referral.referrer_tickets', 1);
    }

    /**
     * ⭐ رابط الدعوة بصيغة **7.6.2**: `[نطاق-المنصّة]/join?ref=CODE`.
     *
     * المسار والمعامل إعدادان (2.13)، والكود لا المعرّف الرقميّ — فلا يُفشي
     * الرابطُ حجمَ القاعدة ولا يُخمَّن بالعدّ. والصيغة القديمة `?offer=` تبقى
     * **مقبولةً عند الاستقبال** للتوافق، لكنّ ما نُولّده اليوم هو `ref`.
     *
     * وموسومٌ بـUTM كباقي ما تولّده المنصّة، وإلّا لم تعرف لوحة مصادر الاكتساب
     * عائد قناة الدعوات أصلًا (21.2-ح).
     */
    public function link(User $user): string
    {
        $path = (string) setting('referral.join.path', '/join');
        $param = (string) setting('referral.join.param', 'ref');
        $url = url($path).'?'.http_build_query([$param => $user->code]);

        return app(UtmBuilder::class)->tag($url, 'invite', 'referral_link', (string) $user->code);
    }

    /** ⭐ رابط دعوة لهذا المحتوى تحديدًا — يمرّ ببوّابتنا لتُخزَّن وجهته */
    public function deepLinkFor(User $user, string $type, int|string $id): string
    {
        return url('/i/'.$user->code).'?'.http_build_query(['type' => $type, 'id' => $id]);
    }

    /**
     * تسجيل نيّة الدعوة لحظة فتح الرابط: تُحفَظ الوجهة في السطر نفسه (landing_type/landing_id)
     * فتكون جاهزة لحظة اكتمال التسجيل.
     */
    public function rememberLanding(User $referrer, ?string $type, int|string|null $id): ?Referral
    {
        if (! $this->deepLink->isSupported($type) || $id === null) {
            return null;
        }

        return Referral::create([
            'referrer_id' => $referrer->id,
            'code' => $referrer->code,
            'landing_type' => $type,
            'landing_id' => (int) $id,
            'commission_percent' => $this->commissionPercent(),
        ]);
    }

    /**
     * ربط الدعوة المعلَّقة بالمستخدم بعد تسجيله، ونقل وجهتها إليه
     * حتّى «يُفتَح على نفس الصفحة» التي دُعي إليها.
     */
    public function claimLanding(User $user, ?int $pendingId, ?string $type = null, int|string|null $id = null): ?Referral
    {
        $referral = $this->referralOf($user);

        if (! $referral) {
            return null;
        }

        if ($referral->landing_type) {
            return $referral;
        }

        $pending = $pendingId
            ? Referral::query()->whereKey($pendingId)->whereNull('referred_id')->first()
            : null;

        $landingType = $pending?->landing_type ?? ($this->deepLink->isSupported($type) ? $type : null);
        $landingId = $pending?->landing_id ?? ($landingType ? $id : null);

        if (! $landingType || ! $landingId) {
            return $referral;
        }

        $referral->forceFill([
            'landing_type' => $landingType,
            'landing_id' => (int) $landingId,
        ])->save();

        $pending?->delete();

        return $referral;
    }

    /** الصفحة التي دُعي إليها المستخدم — تُفتَح له بعد التسجيل */
    public function landingUrlFor(User $user): ?string
    {
        $referral = $this->referralOf($user);

        return $referral
            ? $this->deepLink->url($referral->landing_type, $referral->landing_id)
            : null;
    }

    public function landingLabelFor(User $user): ?string
    {
        $referral = $this->referralOf($user);

        return $referral
            ? $this->deepLink->label($referral->landing_type, $referral->landing_id)
            : null;
    }

    /**
     * ⭐ تذكرة الترحيب للمدعوّ: **عند تفعيل حسابه ومرّة واحدة**
     * (`referrals.welcome_ticket_granted` هو الحارس).
     */
    public function grantWelcomeTicket(User $user): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        $referral = $this->referralOf($user);
        $tickets = $this->welcomeTickets();

        if (! $referral || $referral->welcome_ticket_granted || $tickets <= 0) {
            return false;
        }

        // تحديث مشروط: أوّل نداء فقط ينجح، فلا تتكرّر التذكرة مهما تكرّر النداء
        $claimed = DB::table('referrals')
            ->where('id', $referral->id)
            ->where('welcome_ticket_granted', false)
            ->update(['welcome_ticket_granted' => true, 'updated_at' => now()]);

        if ($claimed !== 1) {
            return false;
        }

        $this->ledger->credit($user, 'tickets', $tickets, 'referral', setting('growth.referral_service.grant_welcome_ticket_1', 'تذكرة ترحيب بالدعوة'), $referral);
        $this->tracker->record('referral_welcome_ticket', $referral, $user->id);

        return true;
    }

    /**
     * ⭐ تذكرة **الداعي** — كانت مفقودة تمامًا (7.6).
     *
     * نصّ الدستور: «عند نجاح الدعوة يحصل **كلٌ من الداعي والمدعو** على تذكرة»،
     * **وشرط الصرف**: بعد استكمال المدعوّ لبياناته + موافقة الأدمن — وهما معًا
     * تعني عندنا: حساب المدعوّ صار `active`.
     *
     * والحارس عمود مستقلّ (`referrals.referrer_ticket_granted`) لا حارس المدعوّ،
     * حتى لا يُسقِط منحُ أحدهما منحَ الآخر مهما اختلف ترتيب النداءات.
     */
    public function grantReferrerTicket(User $invited): bool
    {
        if (! $invited->isActive()) {
            return false;
        }

        $referral = $this->referralOf($invited);
        $tickets = $this->referrerTickets();

        if (! $referral || $referral->referrer_ticket_granted || $tickets <= 0) {
            return false;
        }

        $referrer = $referral->referrer;

        if (! $referrer) {
            return false;
        }

        // تحديث مشروط: أوّل نداء وحده ينجح، فلا تتكرّر التذكرة مهما تكرّر النداء
        $claimed = DB::table('referrals')
            ->where('id', $referral->id)
            ->where('referrer_ticket_granted', false)
            ->update(['referrer_ticket_granted' => true, 'updated_at' => now()]);

        if ($claimed !== 1) {
            return false;
        }

        $this->ledger->credit($referrer, 'tickets', $tickets, 'referral', setting('growth.referral_service.grant_referrer_ticket_1', 'تذكرة دعوة ناجحة'), $referral);
        $this->tracker->record('referral_referrer_ticket', $referral, $referrer->id);

        // ⭐ مكافأة مفاجئة متغيّرة أحيانًا بجانب التذكرة (7.6.1) — والاحتمال حقيقيّ لا موجَّه (2.9)
        $this->grantVariableReward($referrer, $referral);

        return true;
    }

    /**
     * ⭐ مكافأة السفراء المفاجئة المتغيّرة (7.6.1: «Variable Reward — أحيانًا
     * بجانب تذكرة الدعوة الناجحة»). تُقرَع مرّةً واحدة فقط — داخل نفس القفل
     * الذري الذي يمنح تذكرة الداعي، فلا تتكرّر مهما تكرّر النداء.
     */
    private function grantVariableReward(User $referrer, Referral $referral): bool
    {
        $chance = max(0, min(100, (int) setting('referral.variable_reward.chance_percent', 20)));
        $amount = (int) setting('referral.variable_reward.tickets', 3);

        if ($chance <= 0 || $amount <= 0 || random_int(1, 100) > $chance) {
            return false;
        }

        $this->ledger->credit($referrer, 'tickets', $amount, 'referral_bonus', setting('growth.referral_service.variable_reward_1', 'مكافأة مفاجئة — دعوة ناجحة 🎉'), $referral);
        $this->tracker->record('referral_variable_reward', $referral, $referrer->id);

        return true;
    }

    /**
     * تسوية مكافأتَي الدعوة معًا — نداءٌ واحد آمن للتكرار يُستدعى من أيّ نقطة
     * تكتشف أنّ المدعوّ صار مفعَّلًا (اعتماد الأدمن · فتح صفحة الدعوات).
     *
     * @return array{invited:bool,referrer:bool}
     */
    public function settleRewards(User $invited): array
    {
        return [
            'invited' => $this->grantWelcomeTicket($invited),
            'referrer' => $this->grantReferrerTicket($invited),
        ];
    }

    /** دعوات هذا الداعي التي استحقّت تذكرته ولم تُصرَف بعد — تُسوّى عند فتح صفحته */
    public function settlePendingFor(User $referrer): int
    {
        $settled = 0;

        $pending = Referral::query()
            ->where('referrer_id', $referrer->id)
            ->where('referrer_ticket_granted', false)
            ->whereNotNull('referred_id')
            ->with('referred')
            ->get();

        foreach ($pending as $referral) {
            if ($referral->referred && $this->grantReferrerTicket($referral->referred)) {
                $settled++;
            }
        }

        return $settled;
    }

    /**
     * ⭐ عمولة الداعي على شحن المدعوّ (19.3) — موصولة بلحظة **نجاح الشحن**.
     *
     * لا تحسب هنا شيئًا بنفسها: كلّ المنطق الماليّ في `ReferralCommissionService`
     * وهو المصدر الوحيد الذي **يُضيف رصيدًا قابلًا للسحب** ويمنع التكرار بقيدٍ فريد
     * على حركة الشحن. وهذه الدالّة تبقى مدخلًا باسمها لمن يستدعيها من خارج المحفظة.
     *
     * @param  Transaction  $topup  حركة الشحن الناجحة في الجدول الموحّد
     * @return float العمولة المسجَّلة بالدولار — وصفرٌ إن كانت مسجَّلة من قبل
     */
    public function recordCommission(User $referred, Transaction $topup): float
    {
        $commission = app(ReferralCommissionService::class)->recordForTopup($topup);

        return $commission && (int) $commission->referred_id === $referred->id
            ? (float) $commission->amount_usd
            : 0.0;
    }

    /** سطر الدعوة الذي جاء منه هذا المستخدم */
    public function referralOf(User $user): ?Referral
    {
        return Referral::query()->where('referred_id', $user->id)->first();
    }

    /**
     * @return Collection<int,Referral>
     */
    public function invitedBy(User $user, ?int $days = null): Collection
    {
        return Referral::query()
            ->where('referrer_id', $user->id)
            ->whereNotNull('referred_id')
            ->when($days, fn ($q) => $q->where('created_at', '>=', now()->subDays($days)))
            ->with('referred')
            ->latest()
            ->get();
    }

    /**
     * @param  Collection<int,Referral>  $invited
     * @return array{invited: int, completed: int, pending: int, commission: float, percent: float}
     */
    public function stats(Collection $invited): array
    {
        $completed = $invited->filter(fn (Referral $r) => $r->referred?->isActive())->count();

        return [
            'invited' => $invited->count(),
            'completed' => $completed,
            'pending' => $invited->count() - $completed,
            'commission' => (float) $invited->sum(fn (Referral $r) => (float) $r->commission_earned),
            'percent' => $this->commissionPercent(),
        ];
    }

    /**
     * ⭐ فلاتر قائمة المدعوّين (7.6.2): الكلّ / مكتمل / في الانتظار.
     *
     * الفلترة هنا لا في الواجهة، فيبقى تعريف «مكتمل» واحدًا مع `statusOf()`
     * ولا يختلف ما تعدّه الشاشة عمّا تُظهره الشارة.
     *
     * @param  Collection<int,Referral>  $invited
     * @return Collection<int,Referral>
     */
    public function filterByStatus(Collection $invited, string $status): Collection
    {
        return match ($status) {
            'completed' => $invited->filter(fn (Referral $r) => $r->referred?->isActive())->values(),
            'pending' => $invited->reject(fn (Referral $r) => $r->referred?->isActive())->values(),
            default => $invited,
        };
    }

    /**
     * ⭐ إعدادات الآلة الحاسبة التفاعليّة (7.6.2 — «قلب التفاعل»).
     *
     * كلّ حدٍّ وكلّ قيمة ابتدائيّة **إعداد** لا رقم محروق (2.13): المدى 0→500
     * مدعوّ، و1$→50$ متوسّط شحن، و1→24 شهرًا — وسعر الصرف كذلك، فلو تغيّر
     * لم يُلاحَق في الكود.
     *
     * والمعادلات توضيحيّة صراحةً: الشهريّة = المدعوّون × الشحن × النسبة،
     * والكليّة = الشهريّة × الأشهر. **تقديرات واجهة لا التزام ماليّ** — والنصّ
     * يقولها بصراحة، فلا وعدَ ضمنيّ (2.9، بلا Dark Patterns).
     *
     * @return array<string, mixed>
     */
    public function calculator(): array
    {
        return [
            'percent' => $this->commissionPercent(),
            'invites' => [
                'min' => (int) setting('referral.calc.invites_min', 0),
                'max' => (int) setting('referral.calc.invites_max', 500),
                'value' => (int) setting('referral.calc.invites_default', 249),
            ],
            'topup' => [
                'min' => (int) setting('referral.calc.topup_min', 1),
                'max' => (int) setting('referral.calc.topup_max', 50),
                'value' => (int) setting('referral.calc.topup_default', 8),
            ],
            'months' => [
                'min' => (int) setting('referral.calc.months_min', 1),
                'max' => (int) setting('referral.calc.months_max', 24),
                'value' => (int) setting('referral.calc.months_default', 7),
            ],
            'egp_rate' => (float) setting('referral.calc.egp_rate', 50),
        ];
    }

    /** حالة المدعوّ بقاموس 2.16 — لون ومعه رمز دائمًا */
    public function statusOf(Referral $referral): array
    {
        $user = $referral->referred;

        return match (true) {
            $user === null => ['state' => 'idle', 'label' => setting('growth.referral_service.status_of_1', 'لم يكمل التسجيل')],
            $user->isActive() => ['state' => 'ok', 'label' => setting('growth.referral_service.status_of_2', 'مكتمل')],
            default => ['state' => 'warn', 'label' => setting('growth.referral_service.status_of_3', 'في انتظار الاعتماد')],
        };
    }
}
