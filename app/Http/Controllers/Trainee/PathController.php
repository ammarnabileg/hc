<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\LearningPath;
use App\Services\Learning\AvailabilityService;
use App\Services\Learning\CredentialService;
use App\Services\Learning\PathService;
use App\Services\Learning\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * تعلّمي ← المسارات وصفحة المسار (الدستور 3.3 · 24.5).
 *
 * القاعدة الحاسمة في العرض: زرّ امتحان شهادة المسار **لا يظهر قبل 100%**،
 * وقبلها بارٌ صامت بلا CTA — لا نلوّح بما لم يُستحقّ بعد.
 */
class PathController extends Controller
{
    public function __construct(
        private readonly PathService $paths,
        private readonly ProgressService $progress,
        private readonly AvailabilityService $availability,
        private readonly CredentialService $credentials,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $cards = [];
        $counts = ['active' => 0, 'completed' => 0];

        foreach ($this->paths->pathsOf($user) as $path) {
            if ($search !== '' && ! str_contains($path->name_ar, $search)) {
                continue;
            }

            $progress = $this->paths->progress($user, $path);
            $isDone = $progress['unlocked_exam'];
            $counts[$isDone ? 'completed' : 'active']++;

            if ($status === 'completed' && ! $isDone) {
                continue;
            }

            if ($status === 'active' && $isDone) {
                continue;
            }

            $cards[] = [
                'path' => $path,
                'progress' => $progress,
                'exam' => $this->credentials->pathExam($path),
                'certificate' => $this->credentials->certificateBadge($user, $path),
            ];
        }

        return view('learning.paths', [
            'cards' => $cards,
            'counts' => $counts,
            'filters' => ['status' => $status, 'q' => $search],
        ]);
    }

    public function show(Request $request, LearningPath $path): View
    {
        $user = $request->user();

        $enrollments = Enrollment::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('course_id');

        $completed = DB::table('course_completions')
            ->where('user_id', $user->id)
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $courses = $this->paths->coursesOf($path);
        $summaries = $this->progress->summaries($user, $enrollments->values());

        $rows = [];

        foreach ($courses as $index => $course) {
            $enrollment = $enrollments->get($course->id);
            $summary = $summaries[$course->id] ?? ['total' => 0, 'completed' => 0, 'percent' => 0, 'current_title' => null, 'section_title' => null];

            $rows[] = [
                'order' => $index + 1,
                'course' => $course,
                'owned' => (bool) $enrollment,
                'enrollment' => $enrollment,
                'summary' => $summary,
                'completed' => in_array($course->id, $completed, true),
                'availability' => $this->availability->forCourse($course, $enrollment),
            ];
        }

        return view('learning.path', [
            'path' => $path,
            'rows' => $rows,
            'progress' => $this->paths->progress($user, $path),
            'exam' => $this->credentials->pathExam($path),
            'certificate' => $this->credentials->certificateBadge($user, $path),
        ]);
    }
}
