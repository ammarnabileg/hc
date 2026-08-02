<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Services\Learning\CourseNoteService;
use App\Services\Learning\DeadlineService;
use App\Services\Learning\LessonQuestionService;
use App\Services\Learning\LessonQuizService;
use App\Services\Learning\ProgressService;
use App\Services\Learning\TimezoneDetector;
use App\Services\Learning\VideoCommentService;
use App\Services\Learning\XpCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * تعلّمي ← صفحة الدرس (الدستور 24.5 · 4.1).
 * كلّ ما يمنح قيمةً (فتح الدرس · تصحيح السؤال · إكمال الدرس) يُقرَّر هنا في الخادم.
 */
class LessonController extends Controller
{
    public function __construct(
        private readonly ProgressService $progress,
        private readonly LessonQuestionService $questions,
        private readonly DeadlineService $deadlines,
        private readonly XpCalculator $xp,
        private readonly TimezoneDetector $timezones,
        private readonly VideoCommentService $comments,
        private readonly CourseNoteService $notes,
        private readonly LessonQuizService $quiz,
    ) {}

    public function show(Request $request, Course $course, Lesson $lesson): View|RedirectResponse
    {
        $user = $request->user();
        $enrollment = $this->enrollmentOrFail($user->id, $course->id);
        $this->assertBelongs($course, $lesson);
        $this->timezones->sync($request, $user);

        $state = $this->progress->lessonState($user, $course, $lesson, $enrollment);

        // الدرس المقفول لا يُفتَح — يُردّ لصفحة التدريب حيث يقرأ سبب القفل مكتوبًا
        if (! $state['unlocked']) {
            return redirect()
                ->route('learning.course', $course)
                ->with('status', $state['reason'] ?? setting('learning.lock.forced_order_reason'));
        }

        $questions = $this->questions->forLesson($lesson);
        $isVideo = $lesson->type === 'video';

        return view('learning.lesson', [
            'course' => $course,
            'lesson' => $lesson,
            'enrollment' => $enrollment,
            'outline' => $this->progress->outline($user, $course, $enrollment),
            'neighbours' => $this->progress->neighbours($course, $lesson),
            'deadline' => $this->deadlines->forEnrollment($enrollment),
            'attachments' => $this->attachments($lesson),
            'questions' => $questions,
            'quiz_passed' => $this->questions->allAnsweredCorrectly($user, $lesson),
            'quiz_wait_seconds' => $this->quiz->waitSecondsLeft($user, $lesson),
            'completed' => $state['completed'],
            'next_xp' => $this->xp->previewXp($course, $enrollment),
            'embed_url' => $this->embedUrl($lesson),

            // تعليقات الفيديو (3.1): تحت المشغّل، وأوّل دفعة فقط ثمّ تحميل تدريجيّ
            'comments' => $isVideo ? $this->comments->paginate($lesson, $user) : null,
            'comments_count' => $isVideo ? $this->comments->countFor($lesson, $user) : 0,

            // ملاحظات التدريب (3.2): مساحة واحدة مشتركة يصلها من أيّ درس
            'note_body' => $this->notes->bodyFor($user, $course),
            'note_max_length' => $this->notes->maxLength(),
        ]);
    }

    /** إكمال الدرس — سجلّ واحد لكلّ (مستخدم، درس)، وXP بقيمة نصف المهلة. */
    public function complete(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        $user = $request->user();
        $enrollment = $this->enrollmentOrFail($user->id, $course->id);
        $this->assertBelongs($course, $lesson);

        $result = $this->progress->completeLesson($user, $course, $lesson, $enrollment);

        if (! $result['ok']) {
            return back()->with('status', $result['message']);
        }

        $message = $result['message'].($result['xp'] > 0
            ? ' — +'.$result['xp'].' '.setting('learning.xp.suffix')
            : '');

        if ($result['course_completed']) {
            return redirect()
                ->route('learning.course', $course)
                ->with('status', setting('learning.course.done_message'));
        }

        $next = $this->progress->neighbours($course, $lesson)['next'];

        return $next
            ? redirect()->route('learning.lesson', [$course, $next])->with('status', $message)
            : redirect()->route('learning.course', $course)->with('status', $message);
    }

    // ------------------------------------------------------------ داخليّ

    private function enrollmentOrFail(int $userId, int $courseId): Enrollment
    {
        return Enrollment::query()
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->firstOr(fn () => abort(404));
    }

    private function assertBelongs(Course $course, Lesson $lesson): void
    {
        $ownerId = DB::table('sections')->where('id', $lesson->section_id)->value('course_id');

        abort_unless((int) $ownerId === $course->id, 404);
    }

    /** @return Collection<int, object> */
    private function attachments(Lesson $lesson)
    {
        return DB::table('lesson_attachments')
            ->join('media_items', 'media_items.id', '=', 'lesson_attachments.media_item_id')
            ->where('lesson_attachments.lesson_id', $lesson->id)
            ->orderBy('lesson_attachments.sort_order')
            ->get(['media_items.name', 'media_items.path', 'media_items.mime', 'media_items.size', 'media_items.disk']);
    }

    /**
     * مشغّل يوتيوب بـiframe فقط — بلا SDK خارجيّ (شرط أداء وخصوصيّة).
     * ولذلك نبني الرابط من الـVideo ID المخزَّن لا من رابطٍ خام يُحقَن كما هو.
     */
    private function embedUrl(Lesson $lesson): ?string
    {
        if ($lesson->type !== 'video' || ! $lesson->video_id) {
            return null;
        }

        $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $lesson->video_id);

        if ($id === '') {
            return null;
        }

        return rtrim((string) setting('learning.video.embed_base'), '/').'/'.$id
            .'?'.http_build_query((array) setting('learning.video.embed_params', []));
    }
}
