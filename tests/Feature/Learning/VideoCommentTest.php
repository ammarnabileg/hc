<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Models\VideoComment;

/**
 * تعليقات الفيديو (الدستور 3.1): قسم تحت المشغّل · لايك وردّ ·
 * تحميل تدريجيّ ستّة كلّ مرّة · وإشراف يخفي أو يحذف بصلاحيّة.
 */
class VideoCommentTest extends LearningTestCase
{
    public function test_video_lesson_shows_the_comments_section_with_an_encouraging_empty_state(): void
    {
        [$user, $course, $lesson] = $this->videoLesson();

        $this->actingAs($user)
            ->get(route('learning.lesson', [$course, $lesson]))
            ->assertOk()
            ->assertSee(setting('learning.comments.title'))
            ->assertSee(setting('learning.comments.empty'));
    }

    public function test_a_trainee_can_post_a_comment_and_see_it_under_the_video(): void
    {
        [$user, $course, $lesson] = $this->videoLesson();

        $this->actingAs($user)
            ->post(route('learning.lesson.comments.store', [$course, $lesson]), [
                'body' => 'الدقيقة الرابعة وضّحت لي الفكرة كلّها.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('video_comments', [
            'lesson_id' => $lesson->id,
            'user_id' => $user->id,
            'parent_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('learning.lesson', [$course, $lesson]))
            ->assertSee('الدقيقة الرابعة وضّحت لي الفكرة كلّها.');
    }

    public function test_an_empty_comment_is_refused_with_a_message_that_says_what_to_do(): void
    {
        [$user, $course, $lesson] = $this->videoLesson();

        $this->actingAs($user)
            ->post(route('learning.lesson.comments.store', [$course, $lesson]), ['body' => ''])
            ->assertSessionHasErrors(['body' => setting('learning.comments.empty_error')]);
    }

    public function test_comments_load_six_at_a_time_and_the_next_batch_comes_from_its_own_route(): void
    {
        [$user, $course, $lesson] = $this->videoLesson();
        $perPage = (int) setting('learning.comments.per_page');

        for ($i = 1; $i <= $perPage + 1; $i++) {
            VideoComment::create([
                'lesson_id' => $lesson->id,
                'user_id' => $user->id,
                'body' => 'تعليق رقم '.$i,
            ]);
        }

        // الدفعة الأولى: ستّة فقط + رابط الدفعة الأقدم
        $first = $this->actingAs($user)->get(route('learning.lesson', [$course, $lesson]))->assertOk();
        $first->assertSee('تعليق رقم '.($perPage + 1));
        $first->assertDontSee('تعليق رقم 1');   // الأقدم خارج الدفعة الأولى
        $first->assertSee(setting('learning.comments.load_more'));

        // الدفعة الثانية: أقدم تعليق يظهر وحده
        $this->actingAs($user)
            ->get(route('learning.lesson.comments.index', [$course, $lesson, 'page' => 2]))
            ->assertOk()
            ->assertSee('تعليق رقم 1');
    }

    public function test_a_reply_hangs_under_its_parent_comment(): void
    {
        [$user, $course, $lesson] = $this->videoLesson();

        $parent = VideoComment::create([
            'lesson_id' => $lesson->id,
            'user_id' => $user->id,
            'body' => 'سؤال عن المثال الأخير',
        ]);

        $this->actingAs($user)
            ->post(route('learning.lesson.comments.store', [$course, $lesson]), [
                'body' => 'المثال الأخير مشروح في المرفق الثاني.',
                'parent_id' => $parent->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('video_comments', [
            'parent_id' => $parent->id,
            'body' => 'المثال الأخير مشروح في المرفق الثاني.',
        ]);
    }

    public function test_a_like_is_counted_once_and_pressing_again_takes_it_back(): void
    {
        [$user, $course, $lesson] = $this->videoLesson();
        $comment = VideoComment::create(['lesson_id' => $lesson->id, 'user_id' => $user->id, 'body' => 'ملاحظة مفيدة']);

        $this->actingAs($user)
            ->postJson(route('learning.lesson.comments.like', [$course, $lesson, $comment]))
            ->assertOk()
            ->assertJson(['liked' => true, 'count' => 1]);

        $this->actingAs($user)
            ->postJson(route('learning.lesson.comments.like', [$course, $lesson, $comment]))
            ->assertOk()
            ->assertJson(['liked' => false, 'count' => 0]);

        $this->assertSame(0, (int) $comment->refresh()->likes_count);
    }

    public function test_moderation_hides_a_comment_from_everyone_but_the_moderator_and_its_writer(): void
    {
        [$writer, $course, $lesson] = $this->videoLesson();
        $reader = $this->trainee('قارئ');
        $this->enroll($reader, $course);

        $moderator = $this->moderator();
        $this->enroll($moderator, $course);

        $comment = VideoComment::create([
            'lesson_id' => $lesson->id,
            'user_id' => $writer->id,
            'body' => 'تعليق مخالف',
        ]);

        $this->actingAs($moderator)
            ->post(route('learning.lesson.comments.hide', [$course, $lesson, $comment]))
            ->assertRedirect();

        $this->assertTrue((bool) $comment->refresh()->is_hidden);

        $this->actingAs($reader)->get(route('learning.lesson', [$course, $lesson]))->assertDontSee('تعليق مخالف');
        $this->actingAs($moderator)->get(route('learning.lesson', [$course, $lesson]))->assertSee('تعليق مخالف');

        $this->actingAs($moderator)
            ->post(route('learning.lesson.comments.unhide', [$course, $lesson, $comment]))
            ->assertRedirect();

        $this->assertFalse((bool) $comment->refresh()->is_hidden);
    }

    public function test_a_trainee_cannot_hide_or_delete_someone_elses_comment(): void
    {
        [$writer, $course, $lesson] = $this->videoLesson();
        $other = $this->trainee('غيره');
        $this->enroll($other, $course);

        $comment = VideoComment::create(['lesson_id' => $lesson->id, 'user_id' => $writer->id, 'body' => 'تعليق غيره']);

        $this->actingAs($other)
            ->post(route('learning.lesson.comments.hide', [$course, $lesson, $comment]))
            ->assertForbidden();

        $this->actingAs($other)
            ->delete(route('learning.lesson.comments.destroy', [$course, $lesson, $comment]))
            ->assertForbidden();
    }

    public function test_the_writer_deletes_their_own_comment_and_its_replies_go_with_it(): void
    {
        [$user, $course, $lesson] = $this->videoLesson();

        $comment = VideoComment::create(['lesson_id' => $lesson->id, 'user_id' => $user->id, 'body' => 'تعليقي']);
        $reply = VideoComment::create([
            'lesson_id' => $lesson->id,
            'user_id' => $user->id,
            'parent_id' => $comment->id,
            'body' => 'ردّي',
        ]);

        $this->actingAs($user)
            ->delete(route('learning.lesson.comments.destroy', [$course, $lesson, $comment]))
            ->assertRedirect();

        $this->assertSoftDeleted('video_comments', ['id' => $comment->id]);
        $this->assertSoftDeleted('video_comments', ['id' => $reply->id]);
    }

    public function test_a_user_who_does_not_own_the_course_cannot_comment_on_its_lessons(): void
    {
        [, $course, $lesson] = $this->videoLesson();

        $stranger = $this->trainee('غريب');

        $this->actingAs($stranger)
            ->post(route('learning.lesson.comments.store', [$course, $lesson]), ['body' => 'تعليق'])
            ->assertNotFound();
    }

    /** @return array{0: User, 1: Course, 2: Lesson} */
    private function videoLesson(): array
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 2, forcedOrder: false);
        $this->enroll($user, $course);

        $lesson = $this->lessonsOf($course)->first();
        $lesson->update(['type' => 'video', 'video_id' => 'dQw4w9WgXcQ']);

        return [$user, $course, $lesson->refresh()];
    }
}
