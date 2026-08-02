<?php

namespace Tests\Feature\Learning;

use App\Models\LessonCompletion;
use App\Services\Learning\ProgressService;
use Illuminate\Support\Carbon;

/**
 * الترتيب الإجباريّ والإتاحة (الدستور 5 · 24.5).
 * القاعدة: المقفول **يظهر بسببه المكتوب** ولا يُفتَح — لا مخفيّ ولا قابل للتخطّي.
 */
class LockedLessonTest extends LearningTestCase
{
    public function test_locked_lesson_is_visible_with_its_reason_but_cannot_be_opened(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 3, forcedOrder: true);
        $this->enroll($user, $course);
        $lessons = $this->lessonsOf($course);

        // الدرس الثالث مقفول: سابقه لم يكتمل
        $this->actingAs($user)
            ->get(route('learning.course', $course))
            ->assertOk()
            ->assertSee($lessons[2]->title_ar)
            ->assertSee(setting('learning.lock.forced_order_reason'));

        $this->actingAs($user)
            ->get(route('learning.lesson', [$course, $lessons[2]]))
            ->assertRedirect(route('learning.course', $course));

        $this->assertSame(0, LessonCompletion::where('user_id', $user->id)->count());
    }

    public function test_completing_a_locked_lesson_is_refused_server_side(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 3, forcedOrder: true);
        $enrollment = $this->enroll($user, $course);
        $lessons = $this->lessonsOf($course);

        $result = app(ProgressService::class)->completeLesson($user, $course, $lessons[2], $enrollment);

        $this->assertFalse($result['ok']);
        $this->assertSame(setting('learning.lock.forced_order_reason'), $result['message']);
        $this->assertDatabaseMissing('lesson_completions', ['user_id' => $user->id, 'lesson_id' => $lessons[2]->id]);
    }

    public function test_next_lesson_unlocks_after_the_previous_one_is_completed(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 3, forcedOrder: true);
        $enrollment = $this->enroll($user, $course);
        $lessons = $this->lessonsOf($course);
        $progress = app(ProgressService::class);

        $progress->completeLesson($user, $course, $lessons[0], $enrollment);

        $this->assertTrue($progress->isUnlocked($user, $course, $lessons[1], $enrollment->refresh()));
        $this->actingAs($user)->get(route('learning.lesson', [$course, $lessons[1]]))->assertOk();
    }

    public function test_free_order_course_opens_every_lesson(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 3, forcedOrder: false);
        $this->enroll($user, $course);
        $lessons = $this->lessonsOf($course);

        $this->actingAs($user)->get(route('learning.lesson', [$course, $lessons[2]]))->assertOk();
    }

    public function test_expired_course_shows_its_state_and_reason_instead_of_hiding(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 2);
        $this->enroll($user, $course, Carbon::now()->subDays(30), Carbon::now()->subDay());

        // منتهي الإتاحة يظهر في القائمة بحالته وسبب قفله — لا يُخفى (24.5)
        $this->actingAs($user)
            ->get(route('learning.courses'))
            ->assertOk()
            ->assertSee($course->name_ar)
            ->assertSee(setting('learning.lock.expired_reason'));

        $this->actingAs($user)
            ->get(route('learning.lesson', [$course, $this->lessonsOf($course)->first()]))
            ->assertRedirect(route('learning.course', $course));
    }
}
