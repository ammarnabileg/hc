<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Services\Learning\BookmarkService;
use App\Services\Learning\CourseNoteService;
use App\Services\Learning\DeadlineService;
use App\Services\Learning\LessonQuestionService;
use App\Services\Learning\LessonQuizService;
use App\Services\Learning\ProgressService;
use App\Services\Learning\TimezoneDetector;
use App\Services\Learning\VideoCommentService;
use App\Services\Learning\VideoWatchService;
use App\Services\Learning\XpCalculator;
use Illuminate\Http\JsonResponse;
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
        private readonly BookmarkService $bookmarks,
        private readonly VideoWatchService $watch,
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
            // ⭐ حالة الحفظ (3.4-34) — تُقرأ من الخادم فلا يختلف الزرّ عن الحقيقة
            'bookmarked' => $this->bookmarks->has($user, $lesson),
            'next_xp' => $this->xp->previewXp($course, $enrollment),
            'embed_url' => $this->embedUrl($lesson),

            // تعليقات الفيديو (3.1): تحت المشغّل، وأوّل دفعة فقط ثمّ تحميل تدريجيّ
            'comments' => $isVideo && $this->commentsEnabled() ? $this->comments->paginate($lesson, $user) : null,
            'comments_count' => $isVideo && $this->commentsEnabled() ? $this->comments->countFor($lesson, $user) : 0,

            // ملاحظات التدريب (3.2): مساحة واحدة مشتركة يصلها من أيّ درس
            'notes_enabled' => (bool) setting('learning.notes.enabled', true),
            'note_body' => $this->notes->bodyFor($user, $course),
            'note_max_length' => $this->notes->maxLength(),
        ]);
    }

    /**
     * ⭐ تقرير موضع المشاهدة (4.1) — الشقّ الأوّل من تعريف «إنهاء الدرس».
     *
     * المتصفّح **يبلّغ** بموضعه في المشغّل والخادم وحده يقرّر متى صارت المشاهدة
     * كافية: النسبة إعدادٌ في لوحة الإدارة، والقفزة الواحدة محدودة فلا يُعلَن
     * الفيديو مشاهَدًا بنداءٍ ملفَّق.
     */
    public function watch(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        $this->enrollmentOrFail($user->id, $course->id);
        $this->assertBelongs($course, $lesson);

        // الدرس المقفول لا يُشاهَد ولا يُسجَّل له تقدّم — القرار في الخادم لا في الواجهة
        abort_unless($this->progress->isUnlocked($user, $course, $lesson), 403);

        $data = $request->validate([
            'position' => ['required', 'integer', 'min:0'],
            'duration' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json($this->watch->track(
            $user,
            $lesson,
            (int) $data['position'],
            (int) ($data['duration'] ?? 0),
        ));
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

        // ردّ فوريّ يقول ما كسبه بالضبط (2.17-أ): XP وتذاكر الدرس معًا (7 · 7.1)
        $message = $result['message'].($result['xp'] > 0
            ? ' — +'.$result['xp'].' '.setting('learning.xp.suffix')
            : '').(($result['tickets'] ?? 0) > 0
                ? ' · +'.$result['tickets'].' '.setting('learning.tickets.suffix', 'تذكرة')
                : '');

        /*
         | ⭐ الاحتفال يُحمَل في السيشن لمرّةٍ واحدة (2.14): الاستهلاك مسجَّل في
         | الخادم أصلًا فلا يتكرّر بإعادة التحميل، والفلاش يضمن أنّه يظهر على
         | **الصفحة التالية** حيث تقع عين المتدرّب بعد الإكمال (2.9-6).
         */
        $celebration = $result['celebration']
            // ⭐ «+XP بيطير» (3.4-18): الرقم المكتسب فعلًا يسافر مع الاحتفال
            ? $result['celebration'] + ['xp' => (int) $result['xp'], 'tickets' => (int) ($result['tickets'] ?? 0)]
            : null;

        $flash = fn ($redirect, string $status) => $celebration
            ? $redirect->with('status', $status)->with('celebration', $celebration)
            : $redirect->with('status', $status);

        if ($result['course_completed']) {
            return $flash(
                redirect()->route('learning.course', $course),
                (string) setting('learning.course.done_message'),
            );
        }

        $next = $this->progress->neighbours($course, $lesson)['next'];

        return $next
            ? $flash(redirect()->route('learning.lesson', [$course, $next]), $message)
            : $flash(redirect()->route('learning.course', $course), $message);
    }

    /**
     * ⭐ حفظ الدرس / إلغاء حفظه (3.4-34) — ردّ فوريّ لكلّ فعل (2.17-ب).
     * والملكيّة تُتحقَّق أوّلًا: مَن لا يملك التدريب لا يحفظ درسًا فيه.
     */
    public function bookmark(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        abort_unless((bool) setting('learning.ux.bookmark_enabled', true), 404);

        $user = $request->user();
        $this->enrollmentOrFail($user->id, $course->id);
        $this->assertBelongs($course, $lesson);

        $saved = $this->bookmarks->toggle($user, $lesson);

        return back()->with('status', setting(
            $saved ? 'learning.bookmark.saved_message' : 'learning.bookmark.removed_message',
        ));
    }

    // ------------------------------------------------------------ داخليّ

    private function commentsEnabled(): bool
    {
        return (bool) setting('learning.comments.enabled', true);
    }

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
