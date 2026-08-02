<?php

namespace App\Http\Controllers\Trainee\Concerns;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Support\Facades\DB;

/**
 * ملكيّة التدريب وانتماء الدرس — فحصٌ واحد تشترك فيه شاشات الدرس والتعليقات
 * والملاحظات والاختبار، حتى لا تختلف البوّابة من مسار لمسار (12.2.1).
 */
trait ChecksLessonAccess
{
    protected function enrollmentOrFail(int $userId, int $courseId): Enrollment
    {
        return Enrollment::query()
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->firstOr(fn () => abort(404));
    }

    protected function assertBelongs(Course $course, Lesson $lesson): void
    {
        $ownerId = DB::table('sections')->where('id', $lesson->section_id)->value('course_id');

        abort_unless((int) $ownerId === $course->id, 404);
    }
}
