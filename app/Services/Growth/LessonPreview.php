<?php

namespace App\Services\Growth;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Support\Collection;

/**
 * ⭐ «أوّل درس مجّانيّ كمعاينة» — منفَّذًا لا مكتوبًا (21.1-أ).
 *
 * كان الموجود **نصًّا فقط** («أوّل درس مجّانيّ») بلا أيّ مسار يفتحه الزائر،
 * فالوعد بلا باب. وهنا يصير للمعاينة **درسٌ يُفتَح فعلًا بلا تسجيل**،
 * **والحاجز في الخادم**: ما ليس ضمن المسموح لا يُفتَح ولو عرف الزائر رابطه.
 */
class LessonPreview
{
    public function enabled(): bool
    {
        return (bool) setting('growth.preview.enabled', true);
    }

    /** الحدّ الأعلى مهما قال العنصر — سقف واحد يحمي المحتوى كلّه */
    public function ceiling(): int
    {
        return (int) setting('growth.preview.max_lessons', 3);
    }

    /** عدد دروس المعاينة لهذا التدريب (21.1-هـ) */
    public function quota(Course $course): int
    {
        $requested = (int) ($course->free_preview_lessons ?? 0);

        return max(0, min($requested, $this->ceiling()));
    }

    /** هل التدريب نفسه معروض للعامّة أصلًا؟ */
    public function courseIsPublic(Course $course): bool
    {
        return $this->enabled()
            && $course->status === (string) setting('learning.course.published_status', 'published')
            && (bool) ($course->is_indexable ?? true);
    }

    /**
     * دروس المعاينة بترتيب المنهج: الأقسام ثمّ الدروس.
     * والدرس المعلَّم `is_free_preview` يدخل دائمًا ولو تجاوز العدّ.
     *
     * @return Collection<int,Lesson>
     */
    public function lessons(Course $course): Collection
    {
        if (! $this->courseIsPublic($course)) {
            return collect();
        }

        $ordered = Lesson::query()
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->where('sections.course_id', $course->id)
            ->orderBy('sections.sort_order')
            ->orderBy('sections.id')
            ->orderBy('lessons.sort_order')
            ->orderBy('lessons.id')
            ->select('lessons.*', 'sections.title_ar as section_title')
            ->get();

        $quota = $this->quota($course);

        return $ordered
            ->filter(fn (Lesson $lesson, int $index) => $index < $quota || (bool) $lesson->is_free_preview)
            ->take($this->ceiling())
            ->values();
    }

    /** كلّ دروس التدريب مع علامة «مفتوح/مقفول» — الحاجز يُرى ولا يُخدع به أحد (2.9) */
    public function outline(Course $course): Collection
    {
        $openIds = $this->lessons($course)->pluck('id')->all();

        return Lesson::query()
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->where('sections.course_id', $course->id)
            ->orderBy('sections.sort_order')
            ->orderBy('sections.id')
            ->orderBy('lessons.sort_order')
            ->orderBy('lessons.id')
            ->select('lessons.*', 'sections.title_ar as section_title')
            ->get()
            ->map(fn (Lesson $lesson) => [
                'lesson' => $lesson,
                'section' => $lesson->section_title,
                'open' => in_array($lesson->id, $openIds, true),
            ]);
    }

    /** ⭐ الحاجز نفسه: هل يُفتَح هذا الدرس للزائر؟ — يُسأل في الخادم قبل أيّ عرض */
    public function allows(Course $course, Lesson $lesson): bool
    {
        return $this->lessons($course)->contains(fn (Lesson $l) => (int) $l->id === (int) $lesson->id);
    }
}
