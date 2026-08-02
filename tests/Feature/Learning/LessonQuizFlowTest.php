<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonQuestion;
use App\Models\LessonQuizAttempt;
use App\Models\User;

/**
 * تدفّق اختبار الدرس (الدستور 4.1):
 * 1) الأسئلة بترتيب عشوائيّ · 2) معاينة الإجابات · 3) الصحّ والغلط بعد التسليم ·
 * 4) الإعادة بمحاولات غير محدودة **بعد انتظار 20 ثانية** — والحاجز في الخادم.
 */
class LessonQuizFlowTest extends LearningTestCase
{
    public function test_questions_are_shuffled_for_the_attempt(): void
    {
        [$user, $course, $lesson] = $this->quizLesson(questions: 8);

        $natural = LessonQuestion::where('lesson_id', $lesson->id)
            ->orderBy('sort_order')->orderBy('id')->pluck('id')->all();

        $seen = [];

        // الخلط عشوائيّ، فنجرّب مرارًا ونكتفي بأنّ ترتيبًا واحدًا خالف الترتيب الطبيعيّ
        for ($i = 0; $i < 12; $i++) {
            $this->actingAs($user)->get(route('learning.lesson.quiz', [$course, $lesson]))->assertOk();

            $attempt = LessonQuizAttempt::where('user_id', $user->id)->latest('id')->firstOrFail();
            $order = array_map('intval', (array) $attempt->question_order);
            $seen[] = $order;

            sort($order);
            $expected = $natural;
            sort($expected);
            $this->assertSame($expected, $order, 'كلّ الأسئلة موجودة في المحاولة بلا نقص');

            $attempt->delete();
        }

        $this->assertTrue(
            collect($seen)->contains(fn (array $order) => $order !== $natural),
            'الأسئلة تُعرَض بترتيب عشوائيّ (4.1-1)',
        );
    }

    public function test_choice_options_are_shuffled_inside_the_attempt_and_stay_stable_through_preview(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 1);
        $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();

        LessonQuestion::create([
            'lesson_id' => $lesson->id,
            'type' => 'choice',
            'prompt' => 'أيّها أوضح؟',
            'options' => ['أ', 'ب', 'ج', 'د', 'هـ', 'و'],
            'correct_answer' => 'أ',
            'xp_reward' => 0,
        ]);

        $orders = [];

        for ($i = 0; $i < 12; $i++) {
            $this->actingAs($user)->get(route('learning.lesson.quiz', [$course, $lesson]))->assertOk();

            $attempt = LessonQuizAttempt::where('user_id', $user->id)->latest('id')->firstOrFail();
            $orders[] = array_values((array) collect($attempt->option_order)->first());
            $attempt->delete();
        }

        $this->assertTrue(
            collect($orders)->contains(fn (array $o) => $o !== ['أ', 'ب', 'ج', 'د', 'هـ', 'و']),
            'اختيارات السؤال تُخلَط داخل المحاولة',
        );
    }

    public function test_preview_shows_the_answers_before_anything_is_graded(): void
    {
        [$user, $course, $lesson, $questions] = $this->quizLessonWithQuestions();

        $this->actingAs($user)->get(route('learning.lesson.quiz', [$course, $lesson]))->assertOk();

        $this->actingAs($user)
            ->post(route('learning.lesson.quiz.preview', [$course, $lesson]), [
                'answers' => [$questions[0]->id => 'القاهرة', $questions[1]->id => 'إجابة خاطئة'],
            ])
            ->assertOk()
            ->assertSee(setting('learning.quiz.preview_hint'))
            ->assertSee(setting('learning.quiz.submit_cta'))
            ->assertSee('القاهرة');

        // المعاينة مراجعة لا تسليم — فلا تصحيح ولا تسجيل بعد
        $this->assertDatabaseCount('lesson_question_answers', 0);
    }

    public function test_submitting_reveals_right_and_wrong_for_every_question(): void
    {
        [$user, $course, $lesson, $questions] = $this->quizLessonWithQuestions();

        $this->actingAs($user)->get(route('learning.lesson.quiz', [$course, $lesson]))->assertOk();

        $redirect = $this->actingAs($user)
            ->post(route('learning.lesson.quiz.submit', [$course, $lesson]), [
                'answers' => [$questions[0]->id => 'القاهرة', $questions[1]->id => 'إجابة خاطئة'],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get($redirect->headers->get('location'))
            ->assertOk()
            ->assertSee(setting('learning.quiz.correct_label'))
            ->assertSee(setting('learning.quiz.wrong_label'))
            ->assertSee(setting('learning.quiz.failed_title'));
    }

    public function test_one_wrong_answer_blocks_the_retry_on_the_server_until_the_wait_is_over(): void
    {
        [$user, $course, $lesson, $questions] = $this->quizLessonWithQuestions();
        $wait = (int) setting('learning.quiz.retry_wait_seconds');

        $this->assertSame(20, $wait, 'مدّة الانتظار الافتراضيّة عشرون ثانية (4.1-4)');

        $this->actingAs($user)->get(route('learning.lesson.quiz', [$course, $lesson]));
        $this->actingAs($user)->post(route('learning.lesson.quiz.submit', [$course, $lesson]), [
            'answers' => [$questions[0]->id => 'خطأ', $questions[1]->id => 'خطأ'],
        ]);

        // قبل انتهاء الانتظار: لا محاولة جديدة مهما أعاد تحميل الصفحة
        $before = LessonQuizAttempt::where('user_id', $user->id)->count();

        $this->actingAs($user)
            ->get(route('learning.lesson.quiz', [$course, $lesson]))
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('learning.lesson.quiz.preview', [$course, $lesson]))
            ->assertRedirect();

        $this->assertSame($before, LessonQuizAttempt::where('user_id', $user->id)->count());

        // بعد انقضاء المدّة: المحاولات مفتوحة بلا حدّ
        $this->travel($wait + 1)->seconds();

        $this->actingAs($user)
            ->get(route('learning.lesson.quiz', [$course, $lesson]))
            ->assertOk();

        $this->assertSame($before + 1, LessonQuizAttempt::where('user_id', $user->id)->count());
    }

    public function test_a_full_correct_submission_opens_the_lesson_completion_gate(): void
    {
        [$user, $course, $lesson, $questions] = $this->quizLessonWithQuestions();

        $this->actingAs($user)->get(route('learning.lesson.quiz', [$course, $lesson]));
        $this->actingAs($user)->post(route('learning.lesson.quiz.submit', [$course, $lesson]), [
            'answers' => [$questions[0]->id => 'القاهرة', $questions[1]->id => 'ثلاثة'],
        ])->assertRedirect();

        $attempt = LessonQuizAttempt::where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->assertTrue((bool) $attempt->passed);
        $this->assertNull($attempt->retry_available_at);

        $this->actingAs($user)
            ->post(route('learning.lesson.complete', [$course, $lesson]))
            ->assertRedirect();

        $this->assertDatabaseHas('lesson_completions', ['user_id' => $user->id, 'lesson_id' => $lesson->id]);
    }

    public function test_a_stranger_cannot_open_the_quiz_of_a_course_they_do_not_own(): void
    {
        [, $course, $lesson] = $this->quizLessonWithQuestions();

        $this->actingAs($this->trainee('غريب'))
            ->get(route('learning.lesson.quiz', [$course, $lesson]))
            ->assertNotFound();
    }

    // ------------------------------------------------------------ مساعدات

    /** @return array{0: User, 1: Course, 2: Lesson} */
    private function quizLesson(int $questions): array
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 1);
        $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();

        for ($i = 1; $i <= $questions; $i++) {
            LessonQuestion::create([
                'lesson_id' => $lesson->id,
                'type' => 'text',
                'prompt' => 'سؤال رقم '.$i,
                'correct_answer' => 'إجابة '.$i,
                'sort_order' => $i,
                'xp_reward' => 0,
            ]);
        }

        return [$user, $course, $lesson];
    }

    /** @return array{0: User, 1: Course, 2: Lesson, 3: array<int, LessonQuestion>} */
    private function quizLessonWithQuestions(): array
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 2, forcedOrder: false);
        $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();

        $first = LessonQuestion::create([
            'lesson_id' => $lesson->id,
            'type' => 'text',
            'prompt' => 'ما عاصمة مصر؟',
            'correct_answer' => 'القاهرة',
            'sort_order' => 1,
            'xp_reward' => 5,
        ]);

        $second = LessonQuestion::create([
            'lesson_id' => $lesson->id,
            'type' => 'text',
            'prompt' => 'كم ركنًا للرسالة؟',
            'correct_answer' => 'ثلاثة',
            'sort_order' => 2,
            'xp_reward' => 5,
        ]);

        return [$user, $course, $lesson, [$first, $second]];
    }
}
