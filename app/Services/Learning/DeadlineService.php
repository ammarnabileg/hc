<?php

namespace App\Services\Learning;

use App\Models\Enrollment;
use Illuminate\Support\Carbon;

/**
 * الديدلاين والعدّاد التفاعليّ (الدستور 6 — Ghost Timer 👻).
 *
 * لماذا يُحسَب في الخادم؟ لأنّ اللون والرمز والنسبة تُبنى على وقت الخادم لا على
 * ساعة الجهاز، ولأنّ اللون وحده لا يحمل المعنى فلا بدّ من رمزٍ معه (2.16-ب).
 */
class DeadlineService
{
    public function __construct(private readonly XpCalculator $xp) {}

    /**
     * @return array{
     *   has_deadline:bool, passed:bool, state:string, icon:string, label:string,
     *   deadline_at:?Carbon, seconds_left:int, elapsed_percent:int, left_percent:int, half_at:?Carbon
     * }
     */
    public function forEnrollment(Enrollment $enrollment, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $deadline = $enrollment->deadline_at;

        if (! $deadline instanceof Carbon) {
            return [
                'has_deadline' => false,
                'passed' => false,
                'state' => 'idle',
                'icon' => state_color('idle')['icon'],
                'label' => setting('learning.deadline.none_label'),
                'deadline_at' => null,
                'seconds_left' => 0,
                'elapsed_percent' => 0,
                'left_percent' => 100,
                'half_at' => null,
            ];
        }

        $start = $enrollment->started_at ?? $enrollment->created_at ?? $now;
        $total = max(1, $start->diffInSeconds($deadline, absolute: true));
        $left = (int) $now->diffInSeconds($deadline, absolute: false);
        $left = $now->greaterThan($deadline) ? 0 : max(0, $left);

        $leftPercent = (int) round($left / $total * 100);
        $leftPercent = max(0, min(100, $leftPercent));
        $passed = $now->greaterThanOrEqualTo($deadline);

        $state = match (true) {
            $passed => 'danger',
            $leftPercent <= (int) setting('learning.deadline.danger_percent', 20) => 'danger',
            $leftPercent <= (int) setting('learning.deadline.warn_percent', 50) => 'warn',
            default => 'ok',
        };

        return [
            'has_deadline' => true,
            'passed' => $passed,
            'state' => $state,
            'icon' => state_color($state)['icon'],
            'label' => $passed ? setting('learning.deadline.passed_label') : $this->humanize($left),
            'deadline_at' => $deadline,
            'seconds_left' => $left,
            'elapsed_percent' => 100 - $leftPercent,
            'left_percent' => $leftPercent,
            /*
             | ⭐ نصف الديدلاين مصدرٌ واحد: `XpCalculator::halfPoint()` الذي يقرأ
             | `tickets.midpoint_percent` من لوحة الإدارة (7 · 7.1 · 2.13).
             | كان يُحسَب هنا بـ`/2` محروقًا، فلو غيّر الأدمن النسبة اختلف ما
             | يراه المتدرّب على العدّاد عمّا يمنحه النظام من تذاكر.
             */
            'half_at' => $this->xp->halfPoint($enrollment),
        ];
    }

    /** «تبقّى 3 أيّام» — تواريخ نسبيّة والتاريخ الكامل بالـtitle (2.15-د) */
    private function humanize(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = max(1, intdiv($seconds % 3600, 60));

        $unit = match (true) {
            $days > 0 => $this->plural($days, setting('learning.deadline_service.humanize_1', 'يوم'), setting('learning.deadline_service.humanize_2', 'يومان'), setting('learning.deadline_service.humanize_3', 'أيّام')),
            $hours > 0 => $this->plural($hours, setting('learning.deadline_service.humanize_4', 'ساعة'), setting('learning.deadline_service.humanize_5', 'ساعتان'), setting('learning.deadline_service.humanize_6', 'ساعات')),
            default => $this->plural($minutes, setting('learning.deadline_service.humanize_7', 'دقيقة'), setting('learning.deadline_service.humanize_8', 'دقيقتان'), setting('learning.deadline_service.humanize_9', 'دقائق')),
        };

        return setting('learning.deadline.left_prefix').' '.$unit;
    }

    /** المثنّى والجمع في العربيّة — نبرة واحدة سليمة بلا ركاكة (2.17-ج) */
    private function plural(int $count, string $one, string $two, string $many): string
    {
        return match (true) {
            $count === 1 => $one,
            $count === 2 => $two,
            default => $count.' '.$many,
        };
    }
}
