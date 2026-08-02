<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Trainee\Concerns\ChecksLessonAccess;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonQuizAttempt;
use App\Models\User;
use App\Services\Learning\LessonQuestionService;
use App\Services\Learning\LessonQuizService;
use App\Services\Learning\ProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * اختبار الدرس (الدستور 4.1) — بوّابة الانتقال:
 * ترتيب عشوائيّ ⟵ معاينة الإجابات ⟵ تسليم نهائيّ ⟵ الصحّ والغلط ⟵ إعادة بعد انتظار 20 ثانية.
 *
 * **الحاجز في الخادم**: كلّ مسارٍ يبدأ محاولةً أو يسلّمها يسأل الخدمة عن الانتظار أوّلًا،
 * فلا ينفع إعادة تحميل الصفحة ولا تعطيل الجافاسكربت.
 */
class LessonQuizController extends Controller
{
    use ChecksLessonAccess;

    public function __construct(
        private readonly LessonQuizService $quiz,
        private readonly LessonQuestionService $questions,
        private readonly ProgressService $progress,
    ) {}

    /** شاشة الحلّ — أو شاشة الانتظار لو حاجز الـ20 ثانية لسّه شغّال */
    public function show(Request $request, Course $course, Lesson $lesson): View|RedirectResponse
    {
        [$user] = $this->context($request, $course, $lesson);

        $questions = $this->quiz->questionsOf($lesson);

        if ($questions->isEmpty()) {
            return redirect()
                ->route('learning.lesson', [$course, $lesson])
                ->with('status', setting('learning.quiz.no_questions_message'));
        }

        // اجتازه من قبل: نعرض نتيجته بدل بدء محاولة بلا داعٍ
        if ($this->questions->allAnsweredCorrectly($user, $lesson)) {
            $last = $this->quiz->lastSubmittedAttempt($user, $lesson);

            return $last
                ? redirect()->route('learning.lesson.quiz.result', [$course, $lesson, $last])
                : redirect()->route('learning.lesson', [$course, $lesson])
                    ->with('status', setting('learning.quiz.already_passed_message'));
        }

        $attempt = $this->quiz->startOrResume($user, $lesson);

        if (! $attempt) {
            $last = $this->quiz->lastSubmittedAttempt($user, $lesson);

            return redirect()->route('learning.lesson.quiz.result', [$course, $lesson, $last]);
        }

        return $this->view($course, $lesson, 'answer', $attempt);
    }

    /** معاينة الإجابات قبل الإرسال (4.1-2) — تُحفَظ أوّلًا ثمّ تُعرَض للمراجعة */
    public function preview(Request $request, Course $course, Lesson $lesson): View|RedirectResponse
    {
        [$user] = $this->context($request, $course, $lesson);

        $attempt = $this->quiz->runningAttempt($user, $lesson);

        if (! $attempt) {
            return redirect()->route('learning.lesson.quiz', [$course, $lesson]);
        }

        $this->quiz->saveAnswers($attempt, $lesson, $request->all());

        return $this->view($course, $lesson, 'preview', $attempt->refresh());
    }

    /** التسليم النهائيّ — التصحيح في الخادم، وحاجز الإعادة يُضبَط هنا لا في المتصفّح */
    public function submit(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        [$user, $enrollment] = $this->context($request, $course, $lesson);

        $attempt = $this->quiz->runningAttempt($user, $lesson);

        if (! $attempt) {
            return redirect()->route('learning.lesson.quiz', [$course, $lesson]);
        }

        $this->quiz->saveAnswers($attempt, $lesson, $request->all());
        $outcome = $this->quiz->submit($user, $lesson, $attempt, $enrollment);

        return redirect()
            ->route('learning.lesson.quiz.result', [$course, $lesson, $outcome['attempt']])
            ->with('status', $outcome['passed']
                ? setting('learning.quiz.passed_message')
                : setting('learning.quiz.failed_message'));
    }

    /** كشف الصحّ والغلط (4.1-3) ومعه عدّاد الانتظار قبل السماح بالإعادة */
    public function result(Request $request, Course $course, Lesson $lesson, LessonQuizAttempt $attempt): View|RedirectResponse
    {
        [$user] = $this->context($request, $course, $lesson);

        abort_unless($attempt->user_id === $user->id && $attempt->lesson_id === $lesson->id, 404);

        if ($attempt->status !== 'submitted') {
            return redirect()->route('learning.lesson.quiz', [$course, $lesson]);
        }

        return $this->view(
            $course,
            $lesson,
            'result',
            $attempt,
            $this->quiz->resultsOf($attempt, $lesson),
        );
    }

    // ------------------------------------------------------------ داخليّ

    /** @return array{0: User, 1: Enrollment} */
    private function context(Request $request, Course $course, Lesson $lesson): array
    {
        $user = $request->user();
        $enrollment = $this->enrollmentOrFail($user->id, $course->id);
        $this->assertBelongs($course, $lesson);

        // الدرس المقفول لا يُحلّ اختباره — نفس بوّابة صفحة الدرس بالضبط
        abort_unless($this->progress->isUnlocked($user, $course, $lesson, $enrollment), 403);

        return [$user, $enrollment];
    }

    private function view(
        Course $course,
        Lesson $lesson,
        string $stage,
        ?LessonQuizAttempt $attempt,
        array $results = [],
    ): View {
        $user = request()->user();
        $questions = $attempt
            ? $this->quiz->orderedQuestions($attempt, $lesson)
            : $this->quiz->questionsOf($lesson);

        return view('learning.quiz', [
            'course' => $course,
            'lesson' => $lesson,
            'stage' => $stage,
            'attempt' => $attempt,
            'questions' => $questions,
            'answers' => (array) ($attempt?->answers ?? []),
            'results' => $results,
            'options' => $attempt
                ? $questions->mapWithKeys(fn ($q) => [$q->id => $this->quiz->optionsOf($attempt, $q)])->all()
                : [],
            'otp_lengths' => $questions->mapWithKeys(fn ($q) => [$q->id => $this->questions->otpLength($q)])->all(),
            'wait_seconds' => $this->quiz->waitSecondsLeft($user, $lesson),
            'quiz_passed' => $this->questions->allAnsweredCorrectly($user, $lesson),
        ]);
    }
}
