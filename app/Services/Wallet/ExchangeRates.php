<?php

namespace App\Services\Wallet;

use RuntimeException;

/**
 * أسعار الصرف المبنيّة على الدولار (19.1).
 *
 * الدستور ينصّ على ثلاثة أسعار فقط: **1$ = 50 كوين** · **1 تذكرة = 10 كوينز** ·
 * **1 تذكرة = 300 XP**. وكلّ سعرٍ آخر يُشتَقّ منها — فلا يوجد في المنصّة رقم صرفٍ
 * ثانٍ يمكن أن يتناقض مع الأوّل.
 *
 * ⭐ ولا رقم منها محروق: كلّها إعدادات يملكها **مالك المنصّة وحده**
 * (`exchange_rates.edit` موسومة `is_owner_only`)، والافتراضيّ هنا هو نصّ الدستور.
 */
class ExchangeRates
{
    /** العملة المحوريّة التي تُقاس بها كلّ القيم داخليًّا */
    public const BASE = 'coins';

    /** مفاتيح الأسعار الثلاثة كما تظهر في شاشة الإدارة */
    public const KEYS = [
        'finance.rates.usd_to_coins' => ['label' => '1$ = كام كوين', 'default' => '50'],
        'finance.rates.ticket_to_coins' => ['label' => '1 تذكرة = كام كوين', 'default' => '10'],
        'finance.rates.ticket_to_xp' => ['label' => '1 تذكرة = كام XP', 'default' => '300'],
    ];

    /** كم كوينًا يساوي الدولار الواحد */
    public function usdToCoins(): float
    {
        return $this->positive('finance.rates.usd_to_coins', 50);
    }

    /** كم كوينًا تساوي التذكرة الواحدة */
    public function ticketToCoins(): float
    {
        return $this->positive('finance.rates.ticket_to_coins', 10);
    }

    /** كم نقطة خبرة تساوي التذكرة الواحدة */
    public function ticketToXp(): float
    {
        return $this->positive('finance.rates.ticket_to_xp', 300);
    }

    /**
     * قيمة وحدةٍ واحدة من العملة مقوَّمةً بالكوينز.
     * هي جسر كلّ التحويلات: من ⟵ كوينز ⟵ إلى.
     */
    public function unitInCoins(string $code): float
    {
        return match ($code) {
            'coins' => 1.0,
            'usd' => $this->usdToCoins(),
            'tickets' => $this->ticketToCoins(),
            // 1 تذكرة = 10 كوينز = 300 XP ⟵ فالـXP الواحدة = 10/300 كوين
            'xp' => $this->ticketToCoins() / $this->ticketToXp(),
            default => throw new RuntimeException('العملة «'.$code.'» مالهاش سعر صرف معتمَد.'),
        };
    }

    /** سعر التحويل: كم وحدةً من `$to` تساوي وحدةً واحدة من `$from` */
    public function rate(string $from, string $to): float
    {
        return $this->unitInCoins($from) / $this->unitInCoins($to);
    }

    /** قيمة مبلغٍ بالدولار — تُستعمَل في احتساب عمولة الريفيرال (19.3) */
    public function toUsd(string $code, float $amount): float
    {
        return round($amount * $this->unitInCoins($code) / $this->usdToCoins(), 2);
    }

    /**
     * جدول الأسعار للعرض في المحفظة وشاشة الإدارة.
     *
     * @return array<int, array{label:string, value:string}>
     */
    public function table(): array
    {
        return [
            ['label' => 'الدولار', 'value' => '1$ = '.$this->number($this->usdToCoins()).' كوين'],
            ['label' => 'التذكرة بالكوينز', 'value' => '1 تذكرة = '.$this->number($this->ticketToCoins()).' كوينز'],
            ['label' => 'التذكرة بالـXP', 'value' => '1 تذكرة = '.$this->number($this->ticketToXp()).' XP'],
        ];
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /**
     * سعرٌ صفريّ أو سالب يعني قسمةً على صفر في كلّ المسارات،
     * فنرفضه صراحةً بدل أن ينهار الحساب بصمت.
     */
    private function positive(string $key, float $fallback): float
    {
        $value = (float) setting($key, $fallback);

        if ($value <= 0) {
            throw new RuntimeException('سعر الصرف «'.$key.'» لازم يكون أكبر من صفر — صلّحه من شاشة أسعار الصرف.');
        }

        return $value;
    }
}
