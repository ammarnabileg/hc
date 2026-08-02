<?php

namespace App\Services\Learning;

use App\Models\Course;
use App\Models\Enrollment;
use App\Services\Gamification\EconomyRules;
use Illuminate\Support\Carbon;

/**
 * نقاط الخبرة والتذاكر (الدستور — القسم 7).
 *
 * ⭐ قاعدة نهائيّة: **أُلغي مفهوم «نصف المهلة» للـXP**. القيمة تتناقص **خطّيًّا
 * وباستمرار** من القيمة القصوى التي يحدّدها الأدمن عند بداية التدريب حتى
 * **صفر عند الديدلاين** — فكلّما أنهى الدرس أبكر أخذ أكثر.
 *
 * ونصف الديدلاين يبقى **للتذاكر وحدها**: تذكرتان قبله وواحدة بعده.
 *
 * لماذا في الخادم حصرًا؟ لأنّ القيمة الممنوحة قرارٌ تلعيبيّ-ماليّ لا يُؤتمَن
 * عليه المتصفّح: العميل يعرض فقط، والخادم يقرّر ويكتب.
 */
class XpCalculator
{
    public function __construct(private readonly EconomyRules $rules) {}

    /**
     * نصف الديدلاين = نقطة المنتصف بين بداية التدريب وديدلاينه — للتذاكر فقط.
     * والنسبة إعدادٌ في لوحة الإدارة (`tickets.midpoint_percent` = 50% افتراضًا).
     */
    public function halfPoint(Enrollment $enrollment): ?Carbon
    {
        $start = $this->startPoint($enrollment);

        if (! $start || ! $enrollment->deadline_at instanceof Carbon) {
            return null;
        }

        $percent = max(1, min(99, (int) setting('tickets.midpoint_percent', 50)));
        $total = $start->diffInSeconds($enrollment->deadline_at, absolute: true);

        return $start->copy()->addSeconds((int) round($total * $percent / 100));
    }

    /** هل ما زلنا قبل نصف الديدلاين؟ وبلا ديدلاين تُحتسَب القيمة الأعلى. */
    public function isBeforeHalf(Enrollment $enrollment, ?Carbon $at = null): bool
    {
        $half = $this->halfPoint($enrollment);

        if (! $half) {
            return true;
        }

        return ($at ?? Carbon::now())->lessThanOrEqualTo($half);
    }

    /**
     * ⭐ نسبة ما تبقّى من قيمة الدرس: 1.0 عند البداية ⟵ 0.0 عند الديدلاين (تناقص خطّيّ).
     * وبلا ديدلاين لا تناقص — القيمة كاملة.
     */
    public function remainingRatio(Enrollment $enrollment, ?Carbon $at = null): float
    {
        $start = $this->startPoint($enrollment);
        $deadline = $enrollment->deadline_at;

        if (! $start || ! $deadline instanceof Carbon) {
            return 1.0;
        }

        $at ??= Carbon::now();
        $total = $start->diffInSeconds($deadline, absolute: true);

        if ($total <= 0) {
            return 0.0;
        }

        $elapsed = $start->diffInSeconds($at, absolute: false);

        return max(0.0, min(1.0, 1 - ($elapsed / $total)));
    }

    /**
     * قيمة الدرس الواحد لحظة إكماله — والقيمة **تُجمَّد وقت الكتابة لا وقت العرض**،
     * فلا تتغيّر بأثر رجعيّ على مَن أنجز مبكّرًا.
     */
    public function lessonXp(Course $course, Enrollment $enrollment, ?Carbon $at = null): int
    {
        // القيمة القصوى للتدريب أوّلًا، وإلّا فقيمة صفّ «إكمال درس» في جدول الكسب (12.10)
        $max = (int) ($course->xp_max ?: $course->xp_before_half)
            ?: $this->rules->earnValue('lesson.completed');

        $value = (int) floor($max * $this->remainingRatio($enrollment, $at));

        // حدّ أدنى اختياريّ بعد التناقص — إعدادٌ في تاب «XP والتذاكر»
        return max($value, min($max, (int) setting('xp_rules.decay_min', 0)));
    }

    /** ما سيكسبه الآن لو أنهى درسًا — للعرض التحفيزيّ (البار «يدوب» مع الوقت — 2.9-4) */
    public function previewXp(Course $course, Enrollment $enrollment, ?Carbon $at = null): int
    {
        return $this->lessonXp($course, $enrollment, $at);
    }

    /** تذاكر الدرس حسب نصف الديدلاين (7): تذكرتان قبله وواحدة بعده — والقيم إعدادات */
    public function lessonTickets(Course $course, Enrollment $enrollment, ?Carbon $at = null): int
    {
        /*
         | قيمة التدريب تغلب حين تُضبَط، وإلّا فالافتراضيّ العامّ من تاب
         | «XP والتذاكر» في لوحة الإدارة (12.10) لا رقمٌ محروق (2.13).
         | و`??` لا `?:` عمدًا: **صفرٌ اختيارٌ صريح بلا تذاكر**، أمّا NULL وحده
         | فمعناه «اتبع الإعداد العامّ».
         */
        return $this->isBeforeHalf($enrollment, $at)
            ? (int) ($course->tickets_before_half ?? setting('tickets.before_half_deadline', 2))
            : (int) ($course->tickets_after_half ?? setting('tickets.after_half_deadline', 1));
    }

    private function startPoint(Enrollment $enrollment): ?Carbon
    {
        $start = $enrollment->started_at ?? $enrollment->created_at;

        return $start instanceof Carbon ? $start : null;
    }
}
