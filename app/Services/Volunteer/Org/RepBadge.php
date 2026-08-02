<?php

namespace App\Services\Volunteer\Org;

use App\Services\Volunteer\Goals\RepService;

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

    /**
     * الحالة: ذهبيّ للتميّز، وما دونه **من `RepService::state()` وحدها**.
     *
     * لماذا التفويض؟ لأنّ دالّتَي حالة متوازيتَين تختلفان حتمًا: كانت هذه تُرجِع
     * `danger` لكلّ ما دون **−5** بينما 13.4-هـ ينصّ أنّ **المؤشّر الأحمر يعني
     * تخطّي −8** (و−5 عتبة **الإنذار** — 13.4-و · 13.4-ن-ز · 13.4-م). وهذه الشارة
     * هي ما يُرسَم بجانب الاسم في كلّ مكان (الكانفاس · دليل الأعضاء · البروفايل ·
     * البطاقة)، فكان كلّ من بين −5 و−8 يُفضَح بالأحمر أمام فريقه بلا وجه حقّ.
     * والأسوأ: `0 >= -5` كان يعطي `warn`، فكلّ متطوّع مُسكَّن حديثًا يبدأ من الصفر
     * فيُعرَض بشارة تنبيه صفراء من يومه الأوّل.
     */
    public static function state(?float $score): string
    {
        if ($score === null) {
            return 'idle';
        }

        if (self::isClubMember($score)) {
            return 'honor';
        }

        return app(RepService::class)->state($score);
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
