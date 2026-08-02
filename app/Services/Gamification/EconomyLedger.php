<?php

namespace App\Services\Gamification;

use App\Models\Currency;
use App\Models\Enrollment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Wallet\LedgerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ نقطة المنح والخصم الموحّدة للاقتصاد والتلعيب (7 · 7.1 · 7.3 · 19).
 *
 * **العطل الذي تُصلحه:** كان XP التعلّم يُكتَب في `enrollments.xp_earned` وحده،
 * بينما **الليدر بورد والمستوى والشارات تقرأ `users.xp` ودفتر المحفظة** — فكان
 * التعلّم غير مرئيّ في لوحة الصدارة إطلاقًا. فمن اليوم: **كلّ XP يُضاف يمرّ من
 * هنا**، فيُكتَب في الثلاثة معًا **داخل معاملة واحدة**:
 *   1. دفتر الأستاذ (سطر معاملة + رصيد محفظة XP) — للسجلّ والفروق الزمنيّة،
 *   2. `users.xp` — وهو مفتاح ترتيب الليدر بورد وحساب المستوى،
 *   3. `enrollments.xp_earned` حين يكون المصدر درسًا — لعرض «XP هذا التدريب».
 *
 * والقرار كلّه **في الخادم حصرًا**: العميل يعرض ولا يقرّر.
 */
class EconomyLedger
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly EconomyRules $rules,
    ) {}

    /**
     * منح XP من المصدر الموحّد.
     *
     * @param  string  $source  دلو المصدر في دفتر الأستاذ (academy · challenge · …)
     * @param  ?string  $ruleKey  مفتاح صفّ الكسب في `xp_rules.earn` — يطبّق حدّه اليوميّ
     * @return int ما مُنِح فعلًا بعد الحدّ اليوميّ
     */
    public function awardXp(
        User $user,
        int $amount,
        string $source,
        ?Model $reference = null,
        ?string $reason = null,
        ?Enrollment $enrollment = null,
        ?string $ruleKey = null,
    ): int {
        if ($amount <= 0) {
            return 0;
        }

        $amount = $this->withinDailyCap($user, $amount, $source, $ruleKey);

        if ($amount <= 0) {
            return 0;
        }

        DB::transaction(function () use ($user, $amount, $source, $reference, $reason, $enrollment) {
            $this->ledger->credit(
                user: $user,
                currencyCode: $this->xpCode(),
                amount: $amount,
                source: $source,
                reference: $reference,
                layer: 'training',
                reason: $reason,
            );

            // عمود users.xp هو مفتاح ترتيب الليدر بورد (7.3) — فلا يُترَك خلف الدفتر
            $user->increment('xp', $amount);

            if ($enrollment) {
                $enrollment->increment('xp_earned', $amount);
            }
        });

        return $amount;
    }

    /** منح تذاكر (7.1) — عملة قابلة للصرف، والمنح يمرّ بالدفتر كأيّ حركة */
    public function awardTickets(
        User $user,
        float $amount,
        string $source,
        ?Model $reference = null,
        ?string $reason = null,
    ): float {
        if ($amount <= 0) {
            return 0.0;
        }

        $this->ledger->credit(
            user: $user,
            currencyCode: $this->ticketsCode(),
            amount: $amount,
            source: $source,
            reference: $reference,
            layer: 'training',
            reason: $reason,
        );

        return $amount;
    }

    /**
     * خصمٌ موجَّه بعد التأكّد من الرصيد — والرصيد لا ينزل تحت الصفر.
     *
     * @return bool هل تمّ الخصم فعلًا؟
     */
    public function charge(
        User $user,
        string $currencyCode,
        float $amount,
        string $source,
        ?Model $reference = null,
        ?string $reason = null,
    ): bool {
        if ($amount <= 0) {
            return true;
        }

        if ($this->balance($user, $currencyCode) < $amount) {
            return false;
        }

        $this->ledger->debit(
            user: $user,
            currencyCode: $currencyCode,
            amount: $amount,
            source: $source,
            reference: $reference,
            layer: 'training',
            reason: $reason,
        );

        return true;
    }

    public function balance(User $user, string $currencyCode): float
    {
        return $this->ledger->balance($user, $currencyCode);
    }

    /** اسم العملة بالعربيّة — رسائل الواجهة تقول «تذكرة» لا `tickets` */
    public function label(string $currencyCode): string
    {
        return Currency::query()->where('code', $currencyCode)->value('name_ar') ?? $currencyCode;
    }

    public function xpCode(): string
    {
        return (string) setting('wallet.currency.xp_code', 'xp');
    }

    public function ticketsCode(): string
    {
        return (string) setting('wallet.currency.tickets_code', 'tickets');
    }

    // ------------------------------------------------------------ داخليّ

    /** الحدّ اليوميّ لمصدر الكسب (12.10 — عمود «حدّ يوميّ»)؛ و0 يعني بلا حدّ */
    private function withinDailyCap(User $user, int $amount, string $source, ?string $ruleKey): int
    {
        $cap = $ruleKey ? $this->rules->dailyCap($ruleKey) : 0;

        if ($cap <= 0) {
            return $amount;
        }

        $today = (float) Transaction::query()
            ->where('user_id', $user->id)
            ->where('source', $source)
            ->whereHas('currency', fn ($q) => $q->where('code', $this->xpCode()))
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->selectRaw('COALESCE(SUM(COALESCE(applied_amount, amount)), 0) AS total')
            ->value('total');

        return (int) max(0, min($amount, $cap - $today));
    }
}
