<?php

namespace App\Services\Learning;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Support\Carbon;

/**
 * نقاط الخبرة بقيمتين حسب نصف المهلة (الدستور 7 · أعمدة xp_before_half/xp_after_half).
 *
 * لماذا في الخادم حصرًا؟ لأنّ القيمة الممنوحة قرارٌ ماليّ-تلعيبيّ لا يُؤتمَن عليه
 * المتصفّح: العميل يعرض فقط، والخادم يقرّر ويكتب.
 */
class XpCalculator
{
    /** نصف المهلة = منتصف المدّة بين بداية التدريب وديدلاينه (7) */
    public function halfPoint(Enrollment $enrollment): ?Carbon
    {
        if (! $enrollment->deadline_at instanceof Carbon) {
            return null;
        }

        $start = $enrollment->started_at ?? $enrollment->created_at;

        if (! $start instanceof Carbon) {
            return null;
        }

        $seconds = (int) round($start->diffInSeconds($enrollment->deadline_at, absolute: true) / 2);

        return $start->copy()->addSeconds($seconds);
    }

    /** هل ما زلنا قبل نصف المهلة؟ وبلا ديدلاين تُحتسَب القيمة الأعلى. */
    public function isBeforeHalf(Enrollment $enrollment, ?Carbon $at = null): bool
    {
        $half = $this->halfPoint($enrollment);

        if (! $half) {
            return true;
        }

        return ($at ?? Carbon::now())->lessThanOrEqualTo($half);
    }

    /** قيمة الدرس الواحد لحظة إكماله — القيمة تُجمَّد وقت الكتابة لا وقت العرض. */
    public function lessonXp(Course $course, Enrollment $enrollment, ?Carbon $at = null): int
    {
        return $this->isBeforeHalf($enrollment, $at)
            ? (int) $course->xp_before_half
            : (int) $course->xp_after_half;
    }

    /** ما سيكسبه الآن لو أنهى درسًا — للعرض التحفيزيّ على صفحة التدريب. */
    public function previewXp(Course $course, Enrollment $enrollment, ?Carbon $at = null): int
    {
        return $this->lessonXp($course, $enrollment, $at);
    }
}
