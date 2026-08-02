<?php

namespace App\Services\Volunteer\Org;

/**
 * شارة Rep بقاموس الحالة (2.16): لون **ومعه رمز دائمًا**، والعتبات كلّها
 * من جدول Rep الموحَّد (13.4-ن) لا أرقامًا محروقة في الكود.
 */
final class RepBadge
{
    /** عتبة نادي التميّز — ومنها يأتي الإطار الذهبيّ (13.4-م · 13.4-ي) */
    public static function clubThreshold(): float
    {
        return rep_rule('limit.club_threshold', 9.5);
    }

    public static function isClubMember(?float $score): bool
    {
        return $score !== null && $score >= self::clubThreshold();
    }

    /** الحالة: ذهبيّ للتميّز · أخضر موجب · أصفر حتى عتبة الإنذار · أحمر تحتها */
    public static function state(?float $score): string
    {
        if ($score === null) {
            return 'idle';
        }

        if (self::isClubMember($score)) {
            return 'honor';
        }

        if ($score > 0) {
            return 'ok';
        }

        return $score >= rep_rule('limit.warning_threshold', -5.0) ? 'warn' : 'danger';
    }

    /** نصّ مختصر للشارة — الكسور العشريّة مسموحة في الواجهة (2.15) */
    public static function label(?float $score): string
    {
        return $score === null ? 'Rep —' : 'Rep '.rtrim(rtrim(number_format($score, 2, '.', ''), '0'), '.');
    }

    /**
     * نسبة الإشغال بألوان هادئة (13.4-ف-د) — والعتبات إعدادات.
     */
    public static function occupancyState(?int $percent): string
    {
        if ($percent === null) {
            return 'idle';
        }

        if ($percent >= (int) setting('volunteer.org.occupancy_danger_percent', 100)) {
            return 'danger';
        }

        return $percent >= (int) setting('volunteer.org.occupancy_warn_percent', 80) ? 'warn' : 'ok';
    }
}
