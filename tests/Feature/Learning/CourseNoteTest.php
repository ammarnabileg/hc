<?php

namespace Tests\Feature\Learning;

use App\Models\CourseNote;

/**
 * ملاحظات التدريب (الدستور 3.2): **مساحة واحدة مشتركة لكلّ دروس التدريب**،
 * يكتبها من أيّ درس فيجدها في غيره، وتُحفَظ تلقائيًّا بـ«اتحفظ ✓».
 */
class CourseNoteTest extends LearningTestCase
{
    public function test_the_note_written_in_one_lesson_shows_up_in_every_other_lesson_of_the_course(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 3, forcedOrder: false);
        $this->enroll($user, $course);
        $lessons = $this->lessonsOf($course);

        $this->actingAs($user)
            ->post(route('learning.course.notes.save', $course), ['body' => 'الخلاصة: ابدأ بالجملة الواحدة.'])
            ->assertRedirect();

        foreach ([$lessons[0], $lessons[2]] as $lesson) {
            $this->actingAs($user)
                ->get(route('learning.lesson', [$course, $lesson]))
                ->assertOk()
                ->assertSee(setting('learning.notes.title'))
                ->assertSee('الخلاصة: ابدأ بالجملة الواحدة.');
        }

        $this->assertSame(1, CourseNote::where('user_id', $user->id)->where('course_id', $course->id)->count());
    }

    public function test_autosave_answers_with_the_saved_confirmation(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 1);
        $this->enroll($user, $course);

        $this->actingAs($user)
            ->postJson(route('learning.course.notes.save', $course), ['body' => 'ملاحظة سريعة'])
            ->assertOk()
            ->assertJson(['saved' => true, 'message' => setting('learning.notes.saved')]);
    }

    public function test_a_note_longer_than_the_limit_is_refused_with_a_message_that_says_what_to_do(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 1);
        $this->enroll($user, $course);

        $tooLong = str_repeat('ا', (int) setting('learning.notes.max_length') + 1);

        $this->actingAs($user)
            ->postJson(route('learning.course.notes.save', $course), ['body' => $tooLong])
            ->assertStatus(422)
            ->assertJson(['saved' => false, 'message' => setting('learning.notes.too_long_error')]);
    }

    public function test_clearing_empties_the_shared_space_without_losing_it(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 1);
        $this->enroll($user, $course);

        $this->actingAs($user)->post(route('learning.course.notes.save', $course), ['body' => 'نصّ']);
        $this->actingAs($user)->delete(route('learning.course.notes.clear', $course))->assertRedirect();

        $this->assertDatabaseHas('course_notes', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'body' => '',
        ]);
    }

    public function test_notes_can_be_downloaded_as_a_text_file(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 1);
        $this->enroll($user, $course);

        $this->actingAs($user)->post(route('learning.course.notes.save', $course), ['body' => 'ملاحظة للتصدير']);

        $response = $this->actingAs($user)
            ->get(route('learning.course.notes.export', $course))
            ->assertOk();

        $this->assertStringContainsString('ملاحظة للتصدير', $response->streamedContent());
        $this->assertStringContainsString('.txt', (string) $response->headers->get('content-disposition'));
    }

    public function test_a_users_notes_are_never_visible_to_another_user(): void
    {
        $owner = $this->trainee('المالك');
        $other = $this->trainee('غيره');
        $course = $this->makeCourse(lessons: 1);
        $this->enroll($owner, $course);
        $this->enroll($other, $course);

        $this->actingAs($owner)->post(route('learning.course.notes.save', $course), ['body' => 'ملاحظة خاصّة جدًّا']);

        $this->actingAs($other)
            ->get(route('learning.lesson', [$course, $this->lessonsOf($course)->first()]))
            ->assertOk()
            ->assertDontSee('ملاحظة خاصّة جدًّا');
    }

    public function test_a_user_who_does_not_own_the_course_cannot_write_notes_on_it(): void
    {
        $stranger = $this->trainee('غريب');
        $course = $this->makeCourse(lessons: 1);

        $this->actingAs($stranger)
            ->post(route('learning.course.notes.save', $course), ['body' => 'محاولة'])
            ->assertNotFound();
    }
}
