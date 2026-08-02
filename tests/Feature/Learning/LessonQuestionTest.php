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
    /**
     * 4.1: اختبار الدرس **بوّابة انتقال لا مصدر نقاط** — «ولا يؤثّر في الحساب».
     * وكان يمنح XP ثابتة لا تخضع للتناقص الخطّيّ حتى الديدلاين، فيفتح مسارًا
     * ثانيًا لكسب XP التعلّم يلتفّ على قاعدة الإنجاز المبكر (7).
     */
    public function test_answering_a_lesson_question_never_grants_xp(): void
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
        $this->assertSame(0, $first['xp'], 'اختبار الدرس بوّابة لا مصدر نقاط (4.1).');
        $this->assertTrue($second['already']);
        $this->assertSame(0, $second['xp']);
        $this->assertSame(0, (int) $enrollment->refresh()->xp_earned);
        $this->assertSame(0, (int) $user->refresh()->xp, 'ولا يمسّ رصيد الحساب.');
        $this->assertSame(1, LessonQuestionAnswer::where('user_id', $user->id)->where('lesson_question_id', $question->id)->count());
    }

    /** الخطأ لا يكلّف شيئًا، والصواب بعده يفتح البوّابة — وكلاهما بلا XP (4.1) */
    public function test_wrong_answer_costs_nothing_and_a_later_correct_answer_opens_the_gate(): void
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
        $this->assertSame(0, (int) $enrollment->refresh()->xp_earned);
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

        $this->actingAs($user)->get(route('learning.lesson.quiz', [$course, $lesson]))->assertOk();

        $this->actingAs($user)
            ->post(route('learning.lesson.quiz.submit', [$course, $lesson]), [
                'digits' => [$question->id => ['4', '5', '1']],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lesson_question_answers', [
            'user_id' => $user->id,
            'lesson_question_id' => $question->id,
            'is_correct' => true,
            'xp_awarded' => 0,
        ]);
        $this->assertSame(0, (int) $enrollment->refresh()->xp_earned, 'البوّابة لا تمنح XP (4.1).');
    }
}
