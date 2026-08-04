<?php

namespace App\Services\Wallet;

use App\Models\AppNotification;
use App\Models\Currency;
use App\Models\Referral;
use App\Models\ReferralCommission;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ عمولة الريفيرال 7% عند **نجاح الشحن** (19.3 · 7.6).
 *
 * ثلاث قواعد تحكم هذا الصنف:
 *
 *  1) **الحدث هو نجاح الشحن نفسه** — لا زرّ ولا مهمّة مجدولة. ولذلك تُلتقَط
 *     العمولة من حركة الشحن في دفتر الأستاذ، فتسري على الطريقتين معًا
 *     (اليدويّة بعد اعتماد الأدمن · والبوّابة من الويب هوك) **بلا لمس أيٍّ منهما**.
 *
 *  2) **مرّة واحدة لكلّ حركة** — `source_transaction_id` عمود فريد، وهو كلّ
 *     الحارس: نداءٌ مكرَّر لا يُنتج سطرًا ثانيًا ولا دولارًا ثانيًا، بنفس منطق
 *     منع التكرار في فواتير البوّابة (19.5-ج-2).
 *
 *  3) **العمولة بالدولار وتدخل رصيدًا قابلًا للسحب فعلًا** — لا رقمًا معروضًا
 *     في صفحة الدعوات فقط. تُضاف إلى محفظة «دولار الأرباح» فتظهر في
 *     «جاهزة للسحب» ويقدر صاحبها يسحبها أو يحوّلها.
 */
class ReferralCommissionService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ExchangeRates $rates,
    ) {}

    /** نسبة العمولة الافتراضيّة — إعداد لا رقم محروق (2.13) */
    public function defaultPercent(): float
    {
        return (float) setting('finance.referral.commission_percent', 7);
    }

    /**
     * التقاط حركة شحن ناجحة وتحويلها إلى عمولة.
     * ترجع `null` بلا ضجيج إن لم تكن الحركة مؤهَّلة أو كانت مسجَّلة من قبل.
     */
    public function recordForTopup(Transaction $transaction): ?ReferralCommission
    {
        if (! $this->qualifies($transaction)) {
            return null;
        }

        $referred = $transaction->user()->first();

        if (! $referred) {
            return null;
        }

        $referral = Referral::query()->where('referred_id', $referred->id)->first();

        if (! $referral) {
            return null; // مستخدم جاء من غير دعوة — لا عمولة ولا خطأ
        }

        return $this->record($referral, $referred, $transaction);
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * الحركة المؤهَّلة: شحنٌ **ناجح وموجب وغير تصحيحيّ**.
     * والسالب أو التصحيح (عكس فاتورة مستردّة — 19.4) لا يولّد عمولة أبدًا.
     */
    private function qualifies(Transaction $transaction): bool
    {
        $applied = (float) ($transaction->applied_amount ?? $transaction->amount);

        return $transaction->source === 'topup'
            && $applied > 0
            && ! $transaction->is_correction;
    }

    private function record(Referral $referral, User $referred, Transaction $transaction): ?ReferralCommission
    {
        $referrer = $referral->referrer()->first();

        if (! $referrer || $referrer->id === $referred->id) {
            return null;
        }

        $percent = (float) $referral->commission_percent > 0
            ? (float) $referral->commission_percent
            : $this->defaultPercent();

        $currency = Currency::query()->find($transaction->currency_id);
        $code = (string) ($currency?->code ?? 'coins');
        $base = (float) ($transaction->applied_amount ?? $transaction->amount);
        $baseUsd = $this->rates->toUsd($code, $base);
        $amountUsd = round($baseUsd * $percent / 100, 2);

        if ($amountUsd <= 0) {
            return null; // شحنة أصغر من سنتٍ من العمولة — لا نسجّل صفرًا
        }

        return DB::transaction(function () use ($referral, $referrer, $referred, $transaction, $percent, $code, $base, $baseUsd, $amountUsd) {
            /*
             | ⭐ منع التكرار: الإدراج نفسه هو القفل. لو كان السطر موجودًا
             | يرجع الإدراج بصفرٍ فنخرج بلا أيّ أثر ماليّ — وهذا هو المطلوب
             | تمامًا عند إعادة إرسال الويب هوك أو تكرار الاعتماد.
             */
            $inserted = DB::table('referral_commissions')->insertOrIgnore([
                'referral_id' => $referral->id,
                'referrer_id' => $referrer->id,
                'referred_id' => $referred->id,
                'source_transaction_id' => $transaction->id,
                'base_amount' => $base,
                'base_currency' => $code,
                'base_usd' => $baseUsd,
                'percent' => $percent,
                'amount_usd' => $amountUsd,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted !== 1) {
                return null;
            }

            $commission = ReferralCommission::query()
                ->where('source_transaction_id', $transaction->id)
                ->firstOrFail();

            // ⭐ رصيد قابل للسحب فعلًا — لا رقم معروض فقط
            $credit = $this->ledger->credit(
                user: $referrer,
                currencyCode: WithdrawService::CURRENCY,
                amount: $amountUsd,
                source: 'referral',
                reference: $commission,
                layer: 'training',
                reason: strtr(setting('wallet.referral_commission_service.record_1', 'عمولة :p1% على شحن :p2'), [':p1' => (string) ($this->number($percent)), ':p2' => (string) ($referred->code)]),
            );

            $commission->update(['credit_transaction_id' => $credit->id]);

            // العدّاد المعروض في صفحة الدعوات يبقى متّسقًا مع دفتر الأستاذ
            DB::table('referrals')->where('id', $referral->id)->update([
                'commission_earned' => DB::raw('commission_earned + '.$amountUsd),
                'updated_at' => now(),
            ]);

            AppNotification::create([
                'user_id' => $referrer->id,
                'layer' => 'platform',
                'category' => 'wallet',
                'title' => setting('wallet.referral_commission_service.record_2', 'نزلت لك عمولة دعوة ✓'),
                'body' => strtr(setting('wallet.referral_commission_service.record_3', 'اتضاف لأرباحك $:p1 من شحن حد دعوته — جاهزة للسحب.'), [':p1' => (string) ($this->number($amountUsd))]),
                'url' => route('wallet.withdrawals'),
                'reference_type' => $commission->getMorphClass(),
                'reference_id' => $commission->getKey(),
            ]);

            return $commission->refresh();
        });
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
