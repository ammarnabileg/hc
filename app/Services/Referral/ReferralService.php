<?php

namespace App\Services\Referral;

use App\Models\Referral;
use App\Models\User;
use App\Services\Events\LedgerBridge;
use App\Services\Events\Tracker;
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

    public function welcomeTickets(): int
    {
        return (int) setting('referral.welcome_tickets', 1);
    }

    /** رابط الدعوة العامّ بنمط `signup.php?offer=<code>` (7.6) */
    public function link(User $user): string
    {
        $path = (string) setting('referral.link.path', '/register');
        $param = (string) setting('referral.link.param', 'offer');

        return url($path).'?'.http_build_query([$param => $user->code]);
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

        $this->ledger->credit($user, 'tickets', $tickets, 'referral', 'تذكرة ترحيب بالدعوة', $referral);
        $this->tracker->record('referral_welcome_ticket', $referral, $user->id);

        return true;
    }

    /** عمولة الداعي على شحن المدعوّ — نقطة نداء لمجال المحفظة (19.3) */
    public function recordCommission(User $referred, float $amount): float
    {
        $referral = $this->referralOf($referred);

        if (! $referral || $amount <= 0) {
            return 0.0;
        }

        $commission = round($amount * ((float) $referral->commission_percent / 100), 2);

        $referral->forceFill([
            'commission_earned' => (float) $referral->commission_earned + $commission,
        ])->save();

        return $commission;
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

    /** حالة المدعوّ بقاموس 2.16 — لون ومعه رمز دائمًا */
    public function statusOf(Referral $referral): array
    {
        $user = $referral->referred;

        return match (true) {
            $user === null => ['state' => 'idle', 'label' => 'لم يكمل التسجيل'],
            $user->isActive() => ['state' => 'ok', 'label' => 'مكتمل'],
            default => ['state' => 'warn', 'label' => 'في انتظار الاعتماد'],
        };
    }
}
