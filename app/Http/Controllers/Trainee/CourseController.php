<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\Learning\AvailabilityService;
use App\Services\Learning\CredentialService;
use App\Services\Learning\DeadlineService;
use App\Services\Learning\ProgressService;
use App\Services\Learning\XpCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * تعلّمي ← صفحة التدريب (الدستور 24.5).
 * مساحة التعلّم: Roadmap رأسيّ بالسيكشنز ودروسها، والمقفول يظهر بسببه لا يُخفى.
 */
class CourseController extends Controller
{
    public function __construct(
        private readonly ProgressService $progress,
        private readonly DeadlineService $deadlines,
        private readonly AvailabilityService $availability,
        private readonly CredentialService $credentials,
        private readonly XpCalculator $xp,
    ) {}

    public function show(Request $request, Course $course): View
    {
        $user = $request->user();
        $enrollment = $this->enrollmentOrFail($user->id, $course->id);

        $outline = $this->progress->outline($user, $course, $enrollment);
        $availability = $this->availability->forCourse($course, $enrollment);

        return view('learning.course', [
            'course' => $course,
            'enrollment' => $enrollment,
            'outline' => $outline,
            'availability' => $availability,
            'deadline' => $this->deadlines->forEnrollment($enrollment),
            'exam' => $this->credentials->courseExam($user, $course, $outline['percent']),
            'certificate' => $this->credentials->certificateBadge($user, $course),
            'next_xp' => $this->xp->previewXp($course, $enrollment),
            'report_types' => (array) setting('learning.report.types', []),
        ]);
    }

    /** «الإبلاغ عن مشكلة في الدرس» ⟵ يفتح تذكرة دعم (24.5) */
    public function report(Request $request, Course $course): RedirectResponse
    {
        $user = $request->user();
        $this->enrollmentOrFail($user->id, $course->id);

        $validated = $request->validate([
            'problem_type' => ['required', 'string', 'max:64'],
            'body' => ['required', 'string', 'min:5', 'max:2000'],
            'lesson_title' => ['nullable', 'string', 'max:190'],
        ]);

        Complaint::query()->create([
            'number' => strtoupper(Str::random((int) setting('learning.report.number_length', 10))),
            'user_id' => $user->id,
            'type' => setting('learning.report.ticket_type', 'complaint'),
            'category' => setting('learning.report.category', 'lesson'),
            'title' => $validated['problem_type'].' — '.$course->name_ar
                .($validated['lesson_title'] ? ' / '.$validated['lesson_title'] : ''),
            'body' => $validated['body'],
        ]);

        // ردّ فوريّ لكلّ فعل، فلا يسأل المستخدم «هل تمّ؟» (2.17-ب)
        return back()->with('status', setting('learning.report.sent_message'));
    }

    /** مالك التدريب فقط — ومَن لا يملكه لا يرى الصفحة أصلًا (2.15-أ-7) */
    private function enrollmentOrFail(int $userId, int $courseId): Enrollment
    {
        return Enrollment::query()
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->firstOr(fn () => abort(404));
    }
}
