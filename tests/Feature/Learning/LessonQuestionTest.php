<?php

namespace Tests\Feature\Learning;

use App\Models\LessonQuestion;
use App\Models\LessonQuestionAnswer;
use App\Services\Learning\LessonQuestionService;

/**
 * أسئلة الدرس (الدستور 4 · 4.1): التحقّق Server-side، والـXP مرّة واحدة لكلّ سؤال
 * بقيدٍ فريد في قاعدة البيانات لا بشرطٍ في الواجهة.
 */
class LessonQuestionTest extends LearningTestCase
{
    public function test_xp_is_granted_once_per_question_however_many_times_it_is_answered(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 2);
        $enrollment = $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();

        $question = LessonQuestion::create([
            'lesson_id' => $lesson->id,
            'type' => 'otp',
            'prompt' => 'كم عدد أركان الرسالة؟',
            'correct_answer' => '3',
            'xp_reward' => 15,
        ]);

        $service = app(LessonQuestionService::class);

        $first = $service->answer($user, $question, '3', $enrollment);
        $second = $service->answer($user, $question, '3', $enrollment->refresh());

        $this->assertTrue($first['correct']);
        $this->assertSame(15, $first['xp']);
        $this->assertTrue($second['already']);
        $this->assertSame(0, $second['xp']);
        $this->assertSame(15, (int) $enrollment->refresh()->xp_earned);
        $this->assertSame(1, LessonQuestionAnswer::where('user_id', $user->id)->where('lesson_question_id', $question->id)->count());
    }

    public function test_wrong_answer_costs_nothing_and_a_later_correct_answer_still_pays(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 2);
        $enrollment = $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();

        $question = LessonQuestion::create([
            'lesson_id' => $lesson->id,
            'type' => 'otp',
            'prompt' => 'كم دقيقة يستغرق الدرس؟',
            'correct_answer' => '12',
            'xp_reward' => 10,
        ]);

        $service = app(LessonQuestionService::class);

        $wrong = $service->answer($user, $question, '11', $enrollment);
        $this->assertFalse($wrong['correct']);
        $this->assertSame(0, (int) $enrollment->refresh()->xp_earned);

        $right = $service->answer($user, $question, '12', $enrollment->refresh());
        $this->assertTrue($right['correct']);
        $this->assertSame(10, (int) $enrollment->refresh()->xp_earned);
        $this->assertSame(1, LessonQuestionAnswer::where('user_id', $user->id)->count());
    }

    public function test_arabic_indic_digits_are_accepted_as_the_same_answer(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 1);
        $enrollment = $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();

        $question = LessonQuestion::create([
            'lesson_id' => $lesson->id,
            'type' => 'otp',
            'prompt' => 'اكتب الرقم',
            'correct_answer' => '20',
            'xp_reward' => 5,
        ]);

        $result = app(LessonQuestionService::class)->answer($user, $question, '٢٠', $enrollment);

        $this->assertTrue($result['correct']);
    }

    public function test_lesson_with_unanswered_questions_cannot_be_completed(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 2);
        $enrollment = $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();

        LessonQuestion::create([
            'lesson_id' => $lesson->id,
            'type' => 'otp',
            'prompt' => 'سؤال البوّابة',
            'correct_answer' => '7',
            'xp_reward' => 5,
        ]);

        $this->actingAs($user)
            ->post(route('learning.lesson.complete', [$course, $lesson]))
            ->assertRedirect();

        $this->assertDatabaseMissing('lesson_completions', ['user_id' => $user->id, 'lesson_id' => $lesson->id]);
    }

    public function test_answering_through_the_otp_boxes_is_validated_on_the_server(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 2);
        $enrollment = $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();

        $question = LessonQuestion::create([
            'lesson_id' => $lesson->id,
            'type' => 'otp',
            'prompt' => 'اكتب الكود',
            'correct_answer' => '451',
            'xp_reward' => 12,
        ]);

        $this->actingAs($user)
            ->post(route('learning.lesson.answer', [$course, $lesson, $question]), ['digits' => ['4', '5', '1']])
            ->assertRedirect();

        $this->assertDatabaseHas('lesson_question_answers', [
            'user_id' => $user->id,
            'lesson_question_id' => $question->id,
            'is_correct' => true,
            'xp_awarded' => 12,
        ]);
        $this->assertSame(12, (int) $enrollment->refresh()->xp_earned);
    }
}
