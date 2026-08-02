<?php

namespace App\Services\Wallet;

use App\Models\Currency;
use App\Models\User;
use App\Models\WalletExchange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * تحويل العملة داخل محفظة صاحبها (19.3).
 *
 * **من:** دولار الأرباح / كوينز / تذاكر ⟵ **إلى:** تذاكر / XP.
 * **ورسوم 5% ثابتة لكلّ المسارات** — ونصّ الزرّ «تبدأ من 5%» صياغة عرضٍ فقط
 * لا قاعدةً ثانية، فالنسبة واحدة هنا مهما كان المسار.
 *
 * ⭐ ولا يصل من المتصفّح إلّا: من · إلى · الكمّيّة. أمّا السعر والرسوم والناتج
 * فمن الخادم وحده، ويُجمَّد السعر في السطر حتى لا يتبدّل معنى عمليّةٍ قديمة.
 */
class ExchangeService
{
    /** المصادر المسموحة (19.3) */
    public const FROM = ['usd', 'coins', 'tickets'];

    /** الوجهات المسموحة (19.3) */
    public const TO = ['tickets', 'xp'];

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ExchangeRates $rates,
    ) {}

    /** نسبة الرسوم — موحّدة لكلّ المسارات وقابلة للتعديل من مالك المنصّة */
    public function feePercent(): float
    {
        return (float) setting('finance.exchange.fee_percent', 5);
    }

    public function minAmount(string $fromCode): float
    {
        // حدّ أدنى بالدولار يُترجَم لكلّ عملةٍ بسعرها، فلا نكتب حدًّا لكلّ عملة على حدة
        $minUsd = (float) setting('finance.exchange.min_amount_usd', 1);
        $unit = $this->rates->unitInCoins($fromCode);

        return round($minUsd * $this->rates->usdToCoins() / $unit, 2);
    }

    /** مسارات التحويل المسموحة للعرض في البوب-أب */
    public function paths(): array
    {
        $pairs = [];

        foreach (self::FROM as $from) {
            foreach (self::TO as $to) {
                if ($from !== $to) {
                    $pairs[] = ['from' => $from, 'to' => $to];
                }
            }
        }

        return $pairs;
    }

    /**
     * الملخّص اللحظيّ: المُرسَل / الضريبة / يستلم.
     *
     * @return array{amount:float, fee_percent:float, fee:float, rate:float, credited:float}
     */
    public function quote(string $fromCode, string $toCode, float $amount): array
    {
        $this->assertPath($fromCode, $toCode);

        $amount = round(max($amount, 0), 2);
        $percent = $this->feePercent();
        $fee = round($amount * $percent / 100, 2);
        $rate = $this->rates->rate($fromCode, $toCode);
        $decimals = (int) (Currency::query()->where('code', $toCode)->value('decimals') ?? 0);

        // التقريب لأسفل في الناتج: لا تُخلَق وحدةٌ من العدم في أيّ مسار
        $factor = 10 ** $decimals;
        $credited = floor(max($amount - $fee, 0) * $rate * $factor) / $factor;

        return [
            'amount' => $amount,
            'fee_percent' => $percent,
            'fee' => $fee,
            'rate' => round($rate, 6),
            'credited' => round($credited, 2),
        ];
    }

    public function exchange(User $user, string $fromCode, string $toCode, float $amount): WalletExchange
    {
        $this->assertPath($fromCode, $toCode);

        $quote = $this->quote($fromCode, $toCode, $amount);
        $from = Currency::query()->where('code', $fromCode)->firstOrFail();
        $to = Currency::query()->where('code', $toCode)->firstOrFail();

        if ($quote['amount'] < $this->minAmount($fromCode)) {
            throw new WalletException(
                'أقلّ تحويل '.$this->number($this->minAmount($fromCode)).' '.$from->name_ar.' — زوّد القيمة شويّة.'
            );
        }

        if ($quote['credited'] <= 0) {
            throw new WalletException(
                'القيمة صغيرة أوي فالناتج بيطلع صفر '.$to->name_ar.' — زوّدها وشوف الملخّص قبل التأكيد.'
            );
        }

        return DB::transaction(function () use ($user, $from, $to, $fromCode, $toCode, $quote) {
            $exchange = WalletExchange::create([
                'number' => 'EXC-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'user_id' => $user->id,
                'from_currency_id' => $from->id,
                'to_currency_id' => $to->id,
                'amount' => $quote['amount'],
                'fee_percent' => $quote['fee_percent'],
                'fee_amount' => $quote['fee'],
                'rate' => $quote['rate'],
                'credited_amount' => $quote['credited'],
            ]);

            // `createdBy` = صاحب المحفظة: قرارٌ إنسانيّ موثَّق لا خصمٌ آليّ (13.4-ن)
            $debit = $this->ledger->debitOrFail(
                user: $user,
                currencyCode: $fromCode,
                amount: $quote['amount'],
                source: 'exchange',
                reference: $exchange,
                layer: 'training',
                reason: 'تحويل '.$from->name_ar.' ⟵ '.$to->name_ar,
                createdBy: $user->id,
            );

            $credit = $this->ledger->credit(
                user: $user,
                currencyCode: $toCode,
                amount: $quote['credited'],
                source: 'exchange',
                reference: $exchange,
                layer: 'training',
                reason: 'ناتج تحويل من '.$from->name_ar,
                createdBy: $user->id,
            );

            $exchange->update([
                'debit_transaction_id' => $debit->id,
                'credit_transaction_id' => $credit->id,
            ]);

            return $exchange->refresh();
        });
    }

    // ------------------------------------------------------------------ داخليّ

    private function assertPath(string $from, string $to): void
    {
        if (! in_array($from, self::FROM, true)) {
            throw new WalletException('التحويل بيبدأ من دولار الأرباح أو الكوينز أو التذاكر — اختر واحدة منهم.');
        }

        if (! in_array($to, self::TO, true)) {
            throw new WalletException('التحويل بيروح لتذاكر أو XP بس — اختر واحدة منهم.');
        }

        if ($from === $to) {
            throw new WalletException('العملة المصدر والهدف واحدة — غيّر واحدة منهم.');
        }
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
