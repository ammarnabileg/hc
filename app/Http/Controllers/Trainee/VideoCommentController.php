<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Trainee\Concerns\ChecksLessonAccess;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Models\VideoComment;
use App\Services\Learning\ProgressService;
use App\Services\Learning\VideoCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * تعليقات الفيديو (الدستور 3.1): قسم تحت المشغّل · لايك وردّ · تحميل تدريجيّ 6 كلّ مرّة ·
 * وإشراف يخفي أو يحذف بصلاحيّة.
 *
 * كلّ مسار هنا محروسٌ بصلاحيّته، **والعنصر الذي لا يملكه المستخدم يُخفى من الواجهة أصلًا**
 * ولا يُعطَّل (2.15-أ-7) — والحارس هو خطّ الدفاع الثاني لا الأوّل.
 */
class VideoCommentController extends Controller
{
    use ChecksLessonAccess;

    public function __construct(
        private readonly VideoCommentService $comments,
        private readonly ProgressService $progress,
    ) {}

    /** دفعة تعليقات إضافيّة للتحميل التدريجيّ — تُعاد كجزء HTML جاهز للإلحاق */
    public function index(Request $request, Course $course, Lesson $lesson): View
    {
        abort_unless($this->enabled(), 404);

        [$user] = $this->context($request, $course, $lesson);

        $page = max(1, (int) $request->query('page', 1));
        $comments = $this->comments->paginate($lesson, $user, $page);

        return view('learning.partials.comment-page', [
            'course' => $course,
            'lesson' => $lesson,
            'comments' => $comments,
            'next_page' => $comments->hasMorePages() ? $comments->currentPage() + 1 : null,
        ]);
    }

    public function store(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        abort_unless($this->enabled(), 404);

        [$user] = $this->context($request, $course, $lesson);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:'.$this->maxLength()],
            'parent_id' => $this->likeReplyEnabled() ? ['nullable', 'integer'] : ['prohibited'],
        ], [
            // رسالة الخطأ = ماذا حدث + ماذا تفعل (2.17-ب)
            'body.required' => setting('learning.comments.empty_error'),
            'body.max' => setting('learning.comments.too_long_error'),
        ]);

        $parent = $data['parent_id'] ?? null
            ? VideoComment::query()->where('lesson_id', $lesson->id)->find($data['parent_id'])
            : null;

        $comment = $this->comments->create($lesson, $user, $data['body'], $parent);

        return redirect()
            ->to($this->backTo($course, $lesson, $comment->id))
            ->with('status', setting('learning.comments.sent_message'));
    }

    /** تبديل اللايك — يردّ JSON للردّ الفوريّ، ويعمل كفورم عاديّ بلا جافاسكربت (2.17-أ) */
    public function like(Request $request, Course $course, Lesson $lesson, VideoComment $comment): JsonResponse|RedirectResponse
    {
        abort_unless($this->enabled() && $this->likeReplyEnabled(), 404);

        [$user] = $this->context($request, $course, $lesson);
        $this->assertOnLesson($lesson, $comment);

        $result = $this->comments->toggleLike($comment, $user);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return redirect()->to($this->backTo($course, $lesson, $comment->id));
    }

    public function hide(Request $request, Course $course, Lesson $lesson, VideoComment $comment): RedirectResponse
    {
        [$user] = $this->context($request, $course, $lesson);
        $this->assertOnLesson($lesson, $comment);

        $this->comments->hide($comment, $user);

        return redirect()
            ->to($this->backTo($course, $lesson, $comment->id))
            ->with('status', setting('learning.comments.hidden_message'));
    }

    public function unhide(Request $request, Course $course, Lesson $lesson, VideoComment $comment): RedirectResponse
    {
        $this->context($request, $course, $lesson);
        $this->assertOnLesson($lesson, $comment);

        $this->comments->unhide($comment);

        return redirect()
            ->to($this->backTo($course, $lesson, $comment->id))
            ->with('status', setting('learning.comments.unhidden_message'));
    }

    public function destroy(Request $request, Course $course, Lesson $lesson, VideoComment $comment): RedirectResponse
    {
        $user = $request->user();
        $this->context($request, $course, $lesson);
        $this->assertOnLesson($lesson, $comment);

        // صاحب التعليق أو المشرف فقط — والفحص على العنصر نفسه لا على المسار وحده
        abort_unless($user->can('video_comments.delete', $comment), 403);

        $this->comments->delete($comment);

        return redirect()
            ->route('learning.lesson', [$course, $lesson])
            ->with('status', setting('learning.comments.deleted_message'));
    }

    // ------------------------------------------------------------ داخليّ

    private function enabled(): bool
    {
        return (bool) setting('learning.comments.enabled', true);
    }

    private function likeReplyEnabled(): bool
    {
        return (bool) setting('learning.comments.like_reply_enabled', true);
    }

    private function maxLength(): int
    {
        return max(1, (int) setting('learning.comments.max_length', 1000));
    }

    /** @return array{0: User, 1: Enrollment} */
    private function context(Request $request, Course $course, Lesson $lesson): array
    {
        $user = $request->user();
        $enrollment = $this->enrollmentOrFail($user->id, $course->id);
        $this->assertBelongs($course, $lesson);

        abort_unless($this->progress->isUnlocked($user, $course, $lesson, $enrollment), 403);

        return [$user, $enrollment];
    }

    private function assertOnLesson(Lesson $lesson, VideoComment $comment): void
    {
        abort_unless($comment->lesson_id === $lesson->id, 404);
    }

    /** العودة لمكان التعليق نفسه بدل أعلى الصفحة — ردّ فوريّ محسوس (2.17-أ) */
    private function backTo(Course $course, Lesson $lesson, int $commentId): string
    {
        return route('learning.lesson', [$course, $lesson]).'#comment-'.$commentId;
    }
}
