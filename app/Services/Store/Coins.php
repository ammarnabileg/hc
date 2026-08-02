<?php

namespace App\Services\Store;

use App\Models\Currency;

/**
 * صياغة أرقام عملات المتجر في الواجهة (17 · 19.1).
 * الكسور العشريّة مسموحة صراحةً (2.15 — «منع الكسور العشريّة» مرفوض)،
 * لكنّها لا تُعرَض إن كانت أصفارًا حتى يبقى الرقم مقروءًا.
 *
 * والاسم `Coins` بقي لأنّ الكوينز هي العملة الافتراضيّة، لكنّ الصياغة صارت
 * **بعملةٍ صريحة** بعد أن صار المتجر يسعّر بـ Coins أو XP أو Tickets (17) —
 * فالرقم بلا عملته يضلّل، و«50» تذكرة ليست «50» كوين.
 */
final class Coins
{
    public static function fmt(float|string|null $value): string
    {
        $value = round((float) $value, 2);
        $text = number_format($value, 2, '.', ',');

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }

    /** الرقم ومعه اسم العملة — والاسم من جدول العملات لا محروقًا (2.13) */
    public static function label(float|string|null $value, ?string $currency = null): string
    {
        return self::fmt($value).' '.self::currencyLabel($currency);
    }

    /**
     * اسم العملة كما يُعرَض بجوار الرقم («100 **كوين**» · «50 **تذكرة**»).
     *
     * الأولويّة لخريطة `store.currency.labels` لأنّ صيغة العرض بجوار رقمٍ مفرد
     * تختلف عن اسم العملة في جدولها («كوينز» ⟵ «كوين»)، ثمّ اسم العملة من
     * جدول `currencies`، ثمّ الإعداد القديم — ولا نصّ محروق في أيّ منها (2.13).
     */
    public static function currencyLabel(?string $code = null): string
    {
        $code = $code ?: self::defaultCode();
        $labels = (array) setting('store.currency.labels', []);

        if (! empty($labels[$code])) {
            return (string) $labels[$code];
        }

        static $cache = [];

        if (! array_key_exists($code, $cache)) {
            $cache[$code] = Currency::query()->where('code', $code)->value('name_ar');
        }

        return (string) ($cache[$code] ?: setting('store.currency.label', 'كوين'));
    }

    /** نصّ زرّ الشراء بعملة العنصر — لكلّ عملة صياغتها العربيّة السليمة (2.13 · 17) */
    public static function buyLabel(?string $code = null): string
    {
        $code = $code ?: self::defaultCode();
        $labels = (array) setting('store.buy.labels', []);

        return (string) ($labels[$code] ?? setting('store.buy.label', 'إتمام الشراء'));
    }

    /** العملة الافتراضيّة للمتجر — كلّ الأسعار الحقيقيّة بالكوينز (16 · 19.1) */
    public static function defaultCode(): string
    {
        return (string) setting('store.currency.default', 'coins');
    }
}
