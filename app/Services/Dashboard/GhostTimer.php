<?php

namespace App\Services\Dashboard;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Ghost Timer مصغّر (14-ب · 24.5): الوقت المتبقّي للموعد النهائيّ
 * بلونه ورمزه من قاموس الحالة (2.16) — واللون وحده لا يحمل المعنى أبدًا.
 */
final class GhostTimer
{
    private function __construct(
        public readonly ?CarbonInterface $deadline,
        public readonly string $state,
        public readonly string $label,
        public readonly int $remainingSeconds,
    ) {}

    /** بناء العدّاد من موعدٍ نهائيّ — والعتبات إعدادات لا أرقام محروقة (2.13) */
    public static function make(?CarbonInterface $deadline): self
    {
        if (! $deadline) {
            return new self(null, 'idle', (string) setting('dashboard.deadline.none_label', 'بلا موعد نهائيّ'), 0);
        }

        $now = Carbon::now();
        $seconds = (int) round($now->diffInSeconds($deadline, false));

        // فات الموعد ⟵ أحمر (2.16): «فات · خطر»
        if ($seconds <= 0) {
            return new self($deadline, 'danger', 'فات الموعد من '.self::humanize(abs($seconds)), $seconds);
        }

        $dangerHours = (int) setting('dashboard.deadline.danger_hours', 24);
        $warnDays = (int) setting('dashboard.deadline.warn_days', 3);

        // اقترب ويحتاج إجراءً قريبًا ⟵ أصفر · وما دونه بساعات ⟵ أحمر
        $state = match (true) {
            $seconds <= $dangerHours * 3600 => 'danger',
            $seconds <= $warnDays * 86400 => 'warn',
            default => 'ok',
        };

        return new self($deadline, $state, 'باقي '.self::humanize($seconds), $seconds);
    }

    /** هل الموعد ضمن نافذة «أقرب المواعيد»؟ */
    public function isUpcoming(): bool
    {
        $windowDays = (int) setting('dashboard.deadlines.window_days', 30);

        return $this->deadline !== null && $this->remainingSeconds <= $windowDays * 86400;
    }

    /** صياغة عربيّة سليمة للمدّة: مفرد ومثنّى وجمع */
    private static function humanize(int $seconds): string
    {
        if ($seconds < 3600) {
            $minutes = intdiv($seconds, 60);

            return $minutes < 1 ? 'أقلّ من دقيقة' : self::plural($minutes, 'دقيقة', 'دقيقتين', 'دقائق', 'دقيقة');
        }

        if ($seconds < 86400) {
            return self::plural(intdiv($seconds, 3600), 'ساعة', 'ساعتين', 'ساعات', 'ساعة');
        }

        return self::plural(intdiv($seconds, 86400), 'يوم', 'يومين', 'أيّام', 'يومًا');
    }

    private static function plural(int $n, string $one, string $two, string $few, string $many): string
    {
        return match (true) {
            $n === 1 => $one,
            $n === 2 => $two,
            $n <= 10 => $n.' '.$few,
            default => $n.' '.$many,
        };
    }
}
