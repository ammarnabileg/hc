<?php

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Services\Growth\LessonPreview;
use App\Services\Growth\UtmBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ⭐ «أوّل درس مجّانيّ كمعاينة» — **مسار عامّ بلا تسجيل** (21.1-أ).
 *
 * كان الوعد نصًّا في صفحة المنتج بلا بابٍ يُفتَح. وهنا الباب:
 *  · صفحة منهجٍ عامّة تُظهر **المفتوح والمقفول معًا** بصراحة (بلا Dark Patterns — 2.9)،
 *  · وصفحة درسٍ تفتح **المسموح وحده**، **والحاجز في الخادم**: أيّ درسٍ خارج
 *    المعاينة يُردّ بـ403 ومعه دعوةٌ صريحة للتسجيل — لا يُخدَع الزائر بواجهةٍ مقفولة.
 */
class PreviewController extends Controller
{
    public function __construct(
        private readonly LessonPreview $preview,
        private readonly UtmBuilder $utm,
    ) {}

    public function course(Request $request, string $slug): View
    {
        $course = Course::query()->where('slug', $slug)->firstOrFail();

        abort_unless($this->preview->courseIsPublic($course), 404);

        return view('growth.preview.course', [
            'course' => $course,
            'outline' => $this->preview->outline($course),
            'openCount' => $this->preview->lessons($course)->count(),
            'ogImage' => route('growth.og.course', $course->slug),
            'indexable' => (bool) setting('growth.seo.index_courses', true),
            'buyUrl' => $this->buyUrl($course),
            'registerUrl' => $this->registerUrl(),
        ]);
    }

    public function lesson(Request $request, string $slug, Lesson $lesson): View
    {
        $course = Course::query()->where('slug', $slug)->firstOrFail();

        abort_unless($this->preview->courseIsPublic($course), 404);

        // ⛔ الحاجز في الخادم: معرفة الرابط لا تفتح درسًا خارج المعاينة
        abort_unless(
            $this->preview->allows($course, $lesson),
            403,
            (string) setting('growth.preview.locked_message', 'الدرس ده مش ضمن المعاينة المجّانيّة — سجّل حسابك وافتح التدريب كامل.'),
        );

        $lessons = $this->preview->lessons($course);
        $position = $lessons->search(fn (Lesson $l) => (int) $l->id === (int) $lesson->id);

        return view('growth.preview.lesson', [
            'course' => $course,
            'lesson' => $lesson,
            'lessons' => $lessons,
            'next' => $position === false ? null : $lessons->get($position + 1),
            'ogImage' => route('growth.og.course', $course->slug),
            'indexable' => (bool) setting('growth.seo.index_courses', true),
            'buyUrl' => $this->buyUrl($course),
            'registerUrl' => $this->registerUrl(),
        ]);
    }

    private function buyUrl(Course $course): string
    {
        return $this->utm->tag(
            route('store.product', ['type' => 'course', 'slug' => $course->slug]),
            'preview',
            'free_preview',
            $course->slug,
        );
    }

    private function registerUrl(): string
    {
        return $this->utm->tag(route('register'), 'preview', 'free_preview');
    }
}
