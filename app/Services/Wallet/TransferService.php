<?php

namespace App\Services\Wallet;

use App\Models\AppNotification;
use App\Models\Currency;
use App\Models\User;
use App\Models\WalletTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * إرسال حوالة لمستخدمٍ بالكود (19.3).
 *
 * **رسوم حسب العملة: كوينز 15% · XP 85% · تذاكر 0%.**
 * ورسوم الـXP المرتفعة **مقصودة**: تُثبّط تبادل الـXP حفاظًا على نزاهة الليدر بورد،
 * فليست خطأً في الرقم ولا مبالغة — هي أداة تصميم.
 *
 * ⭐ ولا تصل من المتصفّح إلّا ثلاثة حقول: كود المستلم · الكمّيّة · العملة.
 * أمّا النسبة والرسوم والصافي فتُحسَب هنا في الخادم، فلو زوّرها أحدٌ في الطلب أُهمِلت.
 */
class TransferService
{
    /** العملات التي تُرسَل حوالةً — وما عداها يُرفَض صراحةً */
    public const CURRENCIES = ['coins', 'xp', 'tickets'];

    public function __construct(private readonly LedgerService $ledger) {}

    /** نسبة الرسوم لهذه العملة — إعداد لا رقم محروق (2.13) */
    public function feePercent(string $currencyCode): float
    {
        return match ($currencyCode) {
            'coins' => (float) setting('finance.transfer.coins_fee_percent', 15),
            'xp' => (float) setting('finance.transfer.xp_fee_percent', 85),
            'tickets' => (float) setting('finance.transfer.tickets_fee_percent', 0),
            default => throw new WalletException(setting('wallet.transfer_service.fee_percent_1', 'العملة دي مابتتبعتش حوالة — اختر كوينز أو XP أو تذاكر.')),
        };
    }

    public function minAmount(): float
    {
        return (float) setting('finance.transfer.min_amount', 10);
    }

    /**
     * ⭐ الملخّص اللحظيّ: المُرسَل / الضريبة / يستلم — **يُقرَّب للأعلى (Ceil)**.
     * وهو نفسه الحساب الذي يُنفَّذ عند التأكيد، فلا يفاجأ أحدٌ برقمٍ مختلف.
     *
     * @return array{amount:float, fee_percent:float, fee:float, net:float}
     */
    public function quote(string $currencyCode, float $amount): array
    {
        $this->assertCurrency($currencyCode);

        $amount = round(max($amount, 0), 2);
        $percent = $this->feePercent($currencyCode);
        $net = $this->roundNet($amount * (100 - $percent) / 100);
        $net = min($net, $amount);

        return [
            'amount' => $amount,
            'fee_percent' => $percent,
            'fee' => round($amount - $net, 2),
            'net' => $net,
        ];
    }

    /** المستلِم بالكود — ويظهر اسمه للمُرسِل قبل التأكيد */
    public function findRecipient(User $sender, string $code): User
    {
        $code = trim($code);

        $recipient = User::query()->where('code', $code)->first();

        if (! $recipient) {
            throw new WalletException(strtr(setting('wallet.transfer_service.find_recipient_1', 'مافيش مستخدم بالكود «:p1» — راجع الكود مع صاحبه وجرّب تاني.'), [':p1' => (string) ($code)]));
        }

        if ($recipient->id === $sender->id) {
            throw new WalletException(setting('wallet.transfer_service.find_recipient_2', 'ماينفعش تبعت حوالة لنفسك — اكتب كود شخصٍ آخر.'));
        }

        if (method_exists($recipient, 'isActive') && ! $recipient->isActive()) {
            throw new WalletException(setting('wallet.transfer_service.find_recipient_3', 'حساب صاحب الكود ده لسّه مش مفعّل — استنّى لحدّ ما يتفعّل.'));
        }

        return $recipient;
    }

    /** تنفيذ الحوالة: خصمٌ وإضافة وسجلّان في دفتر الأستاذ داخل معاملةٍ واحدة */
    public function send(User $sender, string $recipientCode, string $currencyCode, float $amount): WalletTransfer
    {
        $this->assertCurrency($currencyCode);

        $recipient = $this->findRecipient($sender, $recipientCode);
        $quote = $this->quote($currencyCode, $amount);

        if ($quote['amount'] < $this->minAmount()) {
            throw new WalletException(strtr(setting('wallet.transfer_service.send_1', 'أقلّ حوالة :p1 — زوّد القيمة شويّة.'), [':p1' => (string) ($this->number($this->minAmount()))]));
        }

        if ($quote['net'] <= 0) {
            throw new WalletException(setting('wallet.transfer_service.send_2', 'الرسوم هتاكل الحوالة كلّها — زوّد القيمة عشان يوصله حاجة.'));
        }

        $currency = Currency::query()->where('code', $currencyCode)->firstOrFail();

        return DB::transaction(function () use ($sender, $recipient, $currency, $currencyCode, $quote) {
            $transfer = WalletTransfer::create([
                'number' => 'TRF-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
                'currency_id' => $currency->id,
                'amount' => $quote['amount'],
                'fee_percent' => $quote['fee_percent'],
                'fee_amount' => $quote['fee'],
                'net_amount' => $quote['net'],
            ]);

            /*
             | `createdBy` هنا = المُرسِل نفسه، وهو **قرار إنسانٍ موثَّق** —
             | وهذا ما يسمح بخصم الـXP رغم أنّها لا تُخصَم آليًّا (13.4-ن).
             */
            $debit = $this->ledger->debitOrFail(
                user: $sender,
                currencyCode: $currencyCode,
                amount: $quote['amount'],
                source: 'transfer',
                reference: $transfer,
                layer: 'training',
                reason: strtr(setting('wallet.transfer_service.send_3', 'حوالة إلى :p1'), [':p1' => (string) ($recipient->code)]),
                createdBy: $sender->id,
            );

            $credit = $this->ledger->credit(
                user: $recipient,
                currencyCode: $currencyCode,
                amount: $quote['net'],
                source: 'transfer',
                reference: $transfer,
                layer: 'training',
                reason: strtr(setting('wallet.transfer_service.send_4', 'حوالة من :p1'), [':p1' => (string) ($sender->code)]),
                createdBy: $sender->id,
            );

            $transfer->update([
                'debit_transaction_id' => $debit->id,
                'credit_transaction_id' => $credit->id,
            ]);

            AppNotification::create([
                'user_id' => $recipient->id,
                'layer' => 'platform',
                'category' => 'wallet',
                'title' => setting('wallet.transfer_service.send_5', 'وصلتك حوالة ✓'),
                'body' => strtr(setting('wallet.transfer_service.send_6', 'استلمت :p1 :p2 من :p3.'), [':p1' => (string) ($this->number($quote['net'])), ':p2' => (string) ($currency->name_ar), ':p3' => (string) ($sender->name)]),
                'url' => route('wallet.index'),
                'reference_type' => $transfer->getMorphClass(),
                'reference_id' => $transfer->getKey(),
            ]);

            return $transfer->refresh();
        });
    }

    // ------------------------------------------------------------------ داخليّ

    private function assertCurrency(string $code): void
    {
        if (! in_array($code, self::CURRENCIES, true)) {
            throw new WalletException(setting('wallet.transfer_service.assert_currency_1', 'العملة دي مابتتبعتش حوالة — اختر كوينز أو XP أو تذاكر.'));
        }
    }

    /** سياسة التقريب للصافي: للأعلى (Ceil) كما ينصّ 19.3 — والسياسة إعداد */
    private function roundNet(float $value): float
    {
        return setting('finance.transfer.rounding', 'ceil') === 'ceil'
            ? (float) ceil($value)
            : round($value, 2);
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
