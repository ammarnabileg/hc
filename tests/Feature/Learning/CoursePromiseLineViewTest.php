<?php

namespace Tests\Feature\Learning;

/**
 * الوعد قبل التدريب (الفكرة #13): يظهر تحت عنوان التدريب للمتدرّب المسجَّل، ويغيب تمامًا
 * حين لا يكتبه محرّر المحتوى؛ لا وعد تلقائيّ.
 */
class CoursePromiseLineViewTest extends LearningTestCase
{
    public function test_the_promise_shows_under_the_title_only_when_it_is_written(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(2, true, ['outcome_ar' => 'تدير وقتك من غير قوائم لا تنتهي.']);
        $this->enroll($user, $course);

        $this->actingAs($user)
            ->get(route('learning.course', $course))
            ->assertOk()
            ->assertSee('data-course-outcome', false)
            ->assertSee(setting('learning.course.outcome_label', 'بعد التدريب هتقدر:'))
            ->assertSee('تدير وقتك من غير قوائم لا تنتهي.');

        $course->update(['outcome_ar' => null]);

        $this->actingAs($user)
            ->get(route('learning.course', $course))
            ->assertOk()
            ->assertDontSee('data-course-outcome', false);
    }
}
