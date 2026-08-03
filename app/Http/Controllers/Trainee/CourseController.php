<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\Engagement\SocialProof;
use App\Services\Learning\AvailabilityService;
use App\Services\Learning\CredentialService;
use App\Services\Learning\DeadlineService;
use App\Services\Learning\PathService;
use App\Services\Learning\ProgressService;
use App\Services\Learning\TimezoneDetector;
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
        private readonly TimezoneDetector $timezones,
        private readonly PathService $paths,
        private readonly SocialProof $socialProof,
    ) {}

    public function show(Request $request, Course $course): View
    {
        $user = $request->user();
        $enrollment = $this->enrollmentOrFail($user->id, $course->id);

        // مكان المستخدم يتبعه أوّلًا بأوّل، فالإتاحة تُحسَب بساعته هو (5)
        $this->timezones->sync($request, $user);

        $outline = $this->progress->outline($user, $course, $enrollment);
        $availability = $this->availability->forCourse($course, $enrollment, $user);

        return view('learning.course', [
            'course' => $course,
            'enrollment' => $enrollment,
            'outline' => $outline,
            'availability' => $availability,
            'deadline' => $this->deadlines->forEnrollment($enrollment),
            // الإتاحة تُمرَّر كما هي فلا تُحسَب مرّتين ولا يفترق نصّ البلوك عن نصّ البانر (5)
            'exam' => $this->credentials->courseExam($user, $course, $outline['percent'], $availability),
            'certificate' => $this->credentials->certificateBadge($user, $course),
            'next_xp' => $this->xp->previewXp($course, $enrollment),
            'report_types' => (array) setting('learning.report.types', []),
            // ⭐ «X بيتعلّموا الآن» و«انضم لـ N أكملوا» بأرقام حقيقيّة (3.4-45 · 3.4-49)
            'social' => $this->social($course),
        ]);
    }

    /**
     * ⭐ الدليل الاجتماعيّ الحيّ (3.4-45 · 3.4-49) بحدوده الدنيا (2.9-7).
     *
     * الأرقام تُقرأ من الجداول نفسها، والتأطير يقرّره `SocialProof` — فتحت الحدّ
     * نقول «كن أوّل من ينهي هذا التدريب» بدل رقمٍ صغير يُضعِف الدافع، وبلا أيّ
     * تجميل: العدّاد الظاهر هو العدّاد الحقيقيّ.
     *
     * @return array{learners:array, joined:array}
     */
    private function social(Course $course): array
    {
        $counts = $this->paths->socialProof(collect([$course]))[$course->id]
            ?? ['completed' => 0, 'learning_now' => 0];

        return [
            'learners' => $this->socialProof->frame('course_learners', $counts['learning_now']),
            'joined' => $this->socialProof->frame('course_completed', $counts['completed']),
        ];
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
                .(($validated['lesson_title'] ?? null) ? ' / '.$validated['lesson_title'] : ''),
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
