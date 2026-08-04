<?php

namespace App\Services\Volunteer\Contributions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * نافذة النشاط اليوميّة (الدستور 23 — القسم 4).
 *
 * لماذا؟ لأنّ المتطوّع يعمل في وقت فراغه، فلو حُسبت «مهلة الساعتين» على مدار
 * الساعة لخُصِم من نائمٍ الثالثةَ فجرًا. لذلك كلّ مهلة قصيرة تُستهلَك **داخل**
 * النافذة فقط، وما يقع خارجها يُرحَّل لبداية النافذة التالية بلا احتساب تأخير.
 */
class ActivityWindow
{
    /** المنطقة الزمنيّة التي تُقاس بها النافذة — إعداد لا قيمة محروقة (2.13) */
    public static function timezone(): string
    {
        return (string) setting('workflow.activity_window.timezone', 'Africa/Cairo');
    }

    /** بداية النافذة بالدقائق من منتصف الليل (افتراضي 9:00 ص) */
    public static function startMinutes(): int
    {
        return self::toMinutes((string) setting('workflow.activity_window.start', '09:00'), 540);
    }

    /**
     * نهاية النافذة بالدقائق (افتراضي 12 منتصف الليل = 1440).
     * القيمة «00:00» تعني نهاية اليوم لا بدايته — وإلّا صارت النافذة صفرًا.
     */
    public static function endMinutes(): int
    {
        $end = self::toMinutes((string) setting('workflow.activity_window.end', '00:00'), 1440);

        return $end <= self::startMinutes() ? 1440 : $end;
    }

    public static function contains(CarbonInterface $at): bool
    {
        $local = CarbonImmutable::parse($at)->setTimezone(self::timezone());
        $minutes = $local->hour * 60 + $local->minute;

        return $minutes >= self::startMinutes() && $minutes < self::endMinutes();
    }

    /** أوّل لحظة صالحة للعمل ابتداءً من هذا الوقت */
    public static function shift(CarbonInterface $at): CarbonImmutable
    {
        $local = CarbonImmutable::parse($at)->setTimezone(self::timezone());
        $minutes = $local->hour * 60 + $local->minute;

        if ($minutes < self::startMinutes()) {
            return self::atMinute($local, self::startMinutes());
        }

        if ($minutes >= self::endMinutes()) {
            return self::atMinute($local->addDay(), self::startMinutes());
        }

        return $local;
    }

    /**
     * إضافة ساعات **محسوبة داخل النافذة وحدها** — قلب القاعدة.
     * ترجع الوقت بمنطقة التطبيق الزمنيّة ليُخزَّن كما تُخزَّن بقيّة التواريخ.
     */
    public static function addHours(CarbonInterface $from, float $hours): CarbonImmutable
    {
        $cursor = self::shift($from);
        $remaining = (int) round($hours * 60);

        // حارس: نافذة صفريّة أو مهلة صفريّة لا تدور إلى ما لا نهاية
        if ($remaining <= 0) {
            return $cursor->setTimezone(config('app.timezone'));
        }

        while ($remaining > 0) {
            $windowEnd = self::atMinute($cursor, self::endMinutes());
            $available = (int) $cursor->diffInMinutes($windowEnd, absolute: true);

            if ($available >= $remaining) {
                return $cursor->addMinutes($remaining)->setTimezone(config('app.timezone'));
            }

            $remaining -= $available;
            $cursor = self::atMinute($cursor->addDay(), self::startMinutes());
        }

        return $cursor->setTimezone(config('app.timezone'));
    }

    /** نصّ يشرح النافذة للمستخدم — يظهر تحت كلّ عدّاد قصير */
    public static function label(): string
    {
        return strtr(setting('workflow.activity_window.label_1', 'نافذة النشاط :p1 — :p2'), [':p1' => (string) (self::format(self::startMinutes())), ':p2' => (string) (self::format(self::endMinutes()))]);
    }

    // ------------------------------------------------------------------ داخليّ

    private static function atMinute(CarbonImmutable $day, int $minutes): CarbonImmutable
    {
        return $day->startOfDay()->addMinutes($minutes);
    }

    private static function toMinutes(string $value, int $fallback): int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', trim($value), $m)) {
            return $fallback;
        }

        $minutes = ((int) $m[1]) * 60 + (int) $m[2];

        return $minutes === 0 ? $fallback : $minutes;
    }

    private static function format(int $minutes): string
    {
        $minutes = min($minutes, 1440);

        return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
    }
}
