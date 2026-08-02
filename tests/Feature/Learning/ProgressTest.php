<?php

namespace Tests\Feature\Learning;

use App\Models\CourseCompletion;
use App\Models\LessonCompletion;
use App\Services\Learning\ProgressService;

/**
 * التقدّم والإكمال (الدستور 3 · 24.5).
 * والقاعدة النهائيّة تحت الاختبار: سجلّ واحد لكلّ (مستخدم، درس) و(مستخدم، كورس).
 */
class ProgressTest extends LearningTestCase
{
    public function test_progress_percent_is_computed_from_completed_lessons(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 4);
        $enrollment = $this->enroll($user, $course);
        $lessons = $this->lessonsOf($course);

        $progress = app(ProgressService::class);
        $this->assertSame(0, $progress->percent($user, $course));

        $progress->completeLesson($user, $course, $lessons[0], $enrollment);
        $this->assertSame(25, $progress->percent($user, $course));

        $progress->completeLesson($user, $course, $lessons[1], $enrollment->refresh());
        $this->assertSame(50, $progress->percent($user, $course));

        $this->assertSame(50, (int) $enrollment->refresh()->progress_percent);
    }

    public function test_completing_all_lessons_records_a_single_course_completion(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 2);
        $enrollment = $this->enroll($user, $course);
        $progress = app(ProgressService::class);

        foreach ($this->lessonsOf($course) as $lesson) {
            $progress->completeLesson($user, $course, $lesson, $enrollment->refresh());
        }

        $this->assertSame(100, (int) $enrollment->refresh()->progress_percent);
        $this->assertSame('completed', $enrollment->refresh()->status);

        // تكرار الاستدعاء لا يُنشئ سجلًّا ثانيًا — قاعدة نهائيّة (13.4-ل)
        $progress->recalculate($user, $course, $enrollment->refresh());

        $this->assertSame(1, CourseCompletion::where('user_id', $user->id)->where('course_id', $course->id)->count());
    }

    public function test_completing_the_same_lesson_twice_keeps_one_record_and_grants_xp_once(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 3);
        $enrollment = $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();
        $progress = app(ProgressService::class);

        $first = $progress->completeLesson($user, $course, $lesson, $enrollment);
        $xpAfterFirst = (int) $enrollment->refresh()->xp_earned;

        $second = $progress->completeLesson($user, $course, $lesson, $enrollment->refresh());

        $this->assertSame(40, $first['xp']);          // قبل نصف المهلة ⟵ القيمة الأعلى
        $this->assertSame(0, $second['xp']);
        $this->assertSame($xpAfterFirst, (int) $enrollment->refresh()->xp_earned);
        $this->assertSame(1, LessonCompletion::where('user_id', $user->id)->where('lesson_id', $lesson->id)->count());
    }

    public function test_xp_drops_to_the_after_half_value_past_the_midpoint(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 2);

        // بدأ من عشرة أيّام وديدلاينه بعد يومين ⟵ تجاوزنا نصف المهلة (7)
        $enrollment = $this->enroll(
            $user,
            $course,
            now()->subDays(10),
            now()->addDays(2),
        );

        $result = app(ProgressService::class)->completeLesson($user, $course, $this->lessonsOf($course)->first(), $enrollment);

        $this->assertSame(20, $result['xp']);
        $this->assertSame(20, (int) $enrollment->refresh()->xp_earned);
    }
}
