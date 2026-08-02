<?php

namespace App\Services\Store;

/**
 * صياغة أرقام الكوينز في الواجهة.
 * الكسور العشريّة مسموحة صراحةً (2.15 — «منع الكسور العشريّة» مرفوض)،
 * لكنّها لا تُعرَض إن كانت أصفارًا حتى يبقى الرقم مقروءًا.
 */
final class Coins
{
    public static function fmt(float|string|null $value): string
    {
        $value = round((float) $value, 2);
        $text = number_format($value, 2, '.', ',');

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }

    /** الرقم ومعه اسم العملة — واسمها من الإعدادات لا محروقًا (2.13) */
    public static function label(float|string|null $value): string
    {
        return self::fmt($value).' '.setting('store.currency.label', 'كوين');
    }
}
