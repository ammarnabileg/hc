<?php

namespace App\Services\Wallet;

use App\Models\AppNotification;
use App\Models\User;
use App\Models\WalletWithdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * سحب الأرباح (19.2 · 19.3).
 *
 * **رسوم 1% بحدّ أدنى $0.50** — والحدّ الأدنى هو ما يجعل سحب السنت الواحد
 * غير مجدٍ، فلا تتحوّل الأرباح إلى آلاف الطلبات الصغيرة على المراجع.
 *
 * ⭐ والمبلغ يُخصَم لحظة الطلب لا لحظة الصرف: «جاهزة للسحب» تنقص فورًا
 * فلا يُسحَب نفس الدولار مرّتين، ويظهر المخصوم في «قيد التحويل».
 */
class WithdrawService
{
    /** عملة الأرباح — الدولار (19.3) */
    public const CURRENCY = 'usd';

    /** طرق التحويل المعتمَدة (19.3) — مفاتيح داخليّة لا نصّ (2.13-ب) */
    public const METHOD_KEYS = ['wallet', 'bank', 'instapay', 'other'];

    /**
     * عناوين طرق التحويل — من `setting()` لا محروقة (2.13).
     *
     * @return array<string, string>
     */
    public static function methods(): array
    {
        return [
            'wallet' => (string) setting('finance.withdraw.method.wallet', 'محفظة موبايل'),
            'bank' => (string) setting('finance.withdraw.method.bank', 'حساب بنكيّ'),
            'instapay' => (string) setting('finance.withdraw.method.instapay', 'إنستا باي'),
            'other' => (string) setting('finance.withdraw.method.other', 'أخرى'),
        ];
    }

    public function __construct(private readonly LedgerService $ledger) {}

    public function feePercent(): float
    {
        return (float) setting('finance.withdraw.fee_percent', 1);
    }

    public function minFee(): float
    {
        return (float) setting('finance.withdraw.min_fee_usd', 0.5);
    }

    public function minAmount(): float
    {
        return (float) setting('finance.withdraw.min_amount_usd', 5);
    }

    /** الرصيد المتاح للسحب الآن */
    public function available(User $user): float
    {
        return $this->ledger->balance($user, self::CURRENCY);
    }

    /**
     * ⭐ الملخّص اللحظيّ: الرسوم = النسبة أو الحدّ الأدنى — **أيّهما أكبر**.
     *
     * @return array{amount:float, fee_percent:float, fee:float, net:float}
     */
    public function quote(float $amount): array
    {
        $amount = round(max($amount, 0), 2);
        $percent = $this->feePercent();
        $fee = round(max($amount * $percent / 100, $this->minFee()), 2);

        return [
            'amount' => $amount,
            'fee_percent' => $percent,
            'fee' => $fee,
            'net' => round(max($amount - $fee, 0), 2),
        ];
    }

    /**
     * ⭐ كروت الأرباح الأربعة (19.2): جاهزة للسحب / قيد التحويل / مستلمة / إجماليّة.
     *
     * @return array{ready:float, in_transit:float, received:float, total:float}
     */
    public function earnings(User $user): array
    {
        $ready = $this->available($user);

        $inTransit = (float) WalletWithdrawal::query()
            ->where('user_id', $user->id)
            ->whereIn('status', WalletWithdrawal::IN_TRANSIT)
            ->sum('amount');

        $received = (float) WalletWithdrawal::query()
            ->where('user_id', $user->id)
            ->where('status', WalletWithdrawal::PAID)
            ->sum('net_amount');

        return [
            'ready' => round($ready, 2),
            'in_transit' => round($inTransit, 2),
            'received' => round($received, 2),
            // الإجماليّة = ما في اليد + ما في الطريق + ما وصل فعلًا
            'total' => round($ready + $inTransit + $received, 2),
        ];
    }

    /** طلب سحب: يُخصَم فورًا ويُسجَّل في دفتر الأستاذ ثمّ ينتظر المراجعة */
    public function request(User $user, float $amount, string $method, string $account, ?string $accountName = null): WalletWithdrawal
    {
        if (! in_array($method, self::METHOD_KEYS, true)) {
            throw new WalletException(setting('wallet.withdraw_service.request_1', 'اختر طريقة تحويل من القائمة: محفظة موبايل أو بنكيّ أو إنستا باي أو أخرى.'));
        }

        $quote = $this->quote($amount);

        if ($quote['amount'] < $this->minAmount()) {
            throw new WalletException(
                strtr(setting('wallet.withdraw_service.request_2', 'أقلّ سحب $:p1 — كمّل أرباحك شويّة وارجع لنا.'), [':p1' => (string) ($this->number($this->minAmount()))])
            );
        }

        if ($quote['net'] <= 0) {
            throw new WalletException(
                strtr(setting('wallet.withdraw_service.request_3', 'الرسوم ($:p1) بتاكل المبلغ كلّه — زوّد قيمة السحب.'), [':p1' => (string) ($this->number($quote['fee']))])
            );
        }

        if ($this->pendingFor($user)) {
            throw new WalletException(setting('wallet.withdraw_service.request_4', 'عندك طلب سحب لسّه تحت المراجعة — استنّى نتيجته قبل ما تبعت طلبًا جديدًا.'));
        }

        return DB::transaction(function () use ($user, $quote, $method, $account, $accountName) {
            $withdrawal = WalletWithdrawal::create([
                'number' => 'WD-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'user_id' => $user->id,
                'amount' => $quote['amount'],
                'fee_percent' => $quote['fee_percent'],
                'fee_amount' => $quote['fee'],
                'net_amount' => $quote['net'],
                'method' => $method,
                'account_number' => $account,
                'account_name' => $accountName,
                'status' => WalletWithdrawal::PENDING,
            ]);

            $debit = $this->ledger->debitOrFail(
                user: $user,
                currencyCode: self::CURRENCY,
                amount: $quote['amount'],
                source: 'withdraw',
                reference: $withdrawal,
                layer: 'training',
                reason: strtr(setting('wallet.withdraw_service.request_5', 'طلب سحب أرباح :p1'), [':p1' => (string) ($withdrawal->number)]),
                createdBy: $user->id,
            );

            $withdrawal->update(['debit_transaction_id' => $debit->id]);

            AppNotification::create([
                'user_id' => $user->id,
                'layer' => 'platform',
                'category' => 'wallet',
                'title' => setting('wallet.withdraw_service.request_6', 'استلمنا طلب السحب'),
                'body' => strtr(setting('wallet.withdraw_service.request_7', 'طلبك رقم :p1 اتسجّل، وهنبلّغك بأيّ تغيير في حالته.'), [':p1' => (string) ($withdrawal->number)]),
                'url' => route('wallet.withdrawals'),
                'reference_type' => $withdrawal->getMorphClass(),
                'reference_id' => $withdrawal->getKey(),
            ]);

            return $withdrawal->refresh();
        });
    }

    /** طلبٌ معلَّق للمستخدم — قفل: واحدٌ في المرّة كنمط طلبات الشحن (19.5-ب-5) */
    public function pendingFor(User $user): ?WalletWithdrawal
    {
        return WalletWithdrawal::query()
            ->where('user_id', $user->id)
            ->whereIn('status', WalletWithdrawal::IN_TRANSIT)
            ->latest('id')
            ->first();
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
