<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Course;

/**
 * الوعد قبل التدريب (الفكرة #13 في ملف الهويّة): سطر نتيجة عمليّة يكتبه محرّر المحتوى
 * من فورم التدريب، يظهر تحت عنوان التدريب للمتدرّب، ويغيب تمامًا حين يكون فارغًا.
 */
class CoursePromiseLineTest extends AdminContentTestCase
{
    public function test_the_editor_saves_the_promise_from_the_course_form(): void
    {
        $course = Course::query()->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.courses.update', $course), [
                'name_ar' => $course->name_ar,
                'status' => $course->status,
                'outcome_ar' => 'تكتب رسالة واضحة في سطرين.',
            ])
            ->assertRedirect();

        $this->assertSame('تكتب رسالة واضحة في سطرين.', $course->refresh()->outcome_ar);
    }

    public function test_the_promise_is_capped_to_one_short_line(): void
    {
        $course = Course::query()->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.courses.update', $course), [
                'name_ar' => $course->name_ar,
                'status' => $course->status,
                'outcome_ar' => str_repeat('و', 191),
            ])
            ->assertSessionHasErrors('outcome_ar');
    }
}
