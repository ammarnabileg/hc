<?php

namespace App\Services\Volunteer\Tasks;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * نافذة النشاط (9ص–12م افتراضيًّا — إعداد 2.13).
 *
 * لماذا؟ لأنّ المهل تُحسب داخل النشاط لا خارجه: ما يقع خارج النافذة
 * «لا يُحتسَب تأخيرًا» (24.4 — تقويم نشاطي · 23-4 مهلة نقاط التفتيش).
 */
class ActivityWindow
{
    /** بداية النافذة كنصّ HH:MM من الإعدادات */
    public function start(): string
    {
        return (string) setting('workflow.activity_window.start', '09:00');
    }

    /** نهاية النافذة كنصّ HH:MM — و«00:00» تعني منتصف الليل (نهاية اليوم) */
    public function end(): string
    {
        return (string) setting('workflow.activity_window.end', '00:00');
    }

    /** بداية النافذة بالدقائق من أوّل اليوم */
    public function startMinutes(): int
    {
        return $this->toMinutes($this->start(), 0);
    }

    /** نهاية النافذة بالدقائق — ومنتصف الليل يساوي آخر دقيقة في اليوم */
    public function endMinutes(): int
    {
        $end = $this->toMinutes($this->end(), 1440);

        return $end <= $this->startMinutes() ? 1440 : $end;
    }

    /** هل هذا الوقت داخل نافذة النشاط؟ */
    public function contains(?CarbonInterface $at): bool
    {
        if (! $at) {
            return false;
        }

        $minutes = $at->hour * 60 + $at->minute;

        return $minutes >= $this->startMinutes() && $minutes < $this->endMinutes();
    }

    /** هل هذه الساعة (0–23) داخل النافذة؟ — للتظليل في التقويم */
    public function containsHour(int $hour): bool
    {
        $minutes = $hour * 60;

        return $minutes >= $this->startMinutes() && $minutes < $this->endMinutes();
    }

    /** وصف النافذة للعرض: «9ص–12م» */
    public function label(): string
    {
        return $this->humanTime($this->startMinutes()).'–'.$this->humanTime($this->endMinutes());
    }

    /** Tooltip موحَّد لما هو خارج النافذة (24.4) */
    public function outsideHint(): string
    {
        return 'خارج نافذة النشاط — لا يُحتسَب تأخيرًا';
    }

    /** أوّل لحظة داخل النافذة ابتداءً من وقتٍ ما — تُستعمَل في حساب المهل */
    public function nextOpening(CarbonInterface $from): Carbon
    {
        $at = Carbon::instance($from->toDateTime());

        if ($this->contains($at)) {
            return $at;
        }

        $minutes = $at->hour * 60 + $at->minute;
        $opening = $at->copy()->startOfDay()->addMinutes($this->startMinutes());

        return $minutes < $this->startMinutes() ? $opening : $opening->addDay();
    }

    private function toMinutes(string $value, int $fallback): int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', trim($value), $m)) {
            return $fallback;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    /** تنسيق عربيّ بسيط: 540 ⟵ «9ص» و1440 ⟵ «12م» */
    private function humanTime(int $minutes): string
    {
        $hour = intdiv($minutes, 60) % 24;
        $suffix = $hour < 12 ? 'ص' : 'م';
        $display = $hour % 12 === 0 ? 12 : $hour % 12;

        return $display.$suffix;
    }
}
