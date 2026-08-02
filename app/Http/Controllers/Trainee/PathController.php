<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningPath;
use App\Services\Learning\AvailabilityService;
use App\Services\Learning\CredentialService;
use App\Services\Learning\DeadlineService;
use App\Services\Learning\PathService;
use App\Services\Learning\ProgressService;
use App\Services\Learning\TimezoneDetector;
use App\Services\Learning\UserClock;
use App\Services\Learning\XpCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        private readonly DeadlineService $deadlines,
        private readonly XpCalculator $xp,
        private readonly TimezoneDetector $timezones,
        private readonly UserClock $clock,
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

        // نفس كشف صفحتَي التدريب: المنطقة تتبع مكانه الآن قبل أيّ حساب إتاحة (5)
        $this->timezones->sync($request, $user);

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

        // ⭐ عناصر 3.3 و3.4 دفعةً واحدة: المدّة · الدليل الاجتماعيّ · تقدّم المدعوّين
        $durations = $this->paths->durations($courses);
        $social = $this->paths->socialProof($courses);
        $friends = $this->paths->invitedProgress($user, $courses);

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
                /*
                 | ⭐ الإتاحة بساعة **صاحب الشاشة** لا بساعة الخادم (5).
                 | كانت تُنادى بلا `$user` فتُحسَب بتوقيت الخادم، فتقول صفحة المسار
                 | «مقفول — يفتح الاثنين» بينما صفحة التدريب نفسها مفتوحة ودروسها
                 | متاحة؛ وكلمة «بتوقيتك» في الرسالة تجعل الكذبة أشدّ ضررًا.
                 */
                'availability' => $this->availability->forCourse($course, $enrollment, $user),
                'deadline' => $enrollment ? $this->deadlines->forEnrollment($enrollment) : null,
                'certificate' => $this->credentials->certificateBadge($user, $course),
                'duration' => $durations[$course->id] ?? 0,
                'reward' => $enrollment ? $this->earlyReward($course, $enrollment) : null,
                'social' => $social[$course->id] ?? ['completed' => 0, 'learning_now' => 0],
                'friends' => $friends[$course->id] ?? [],
            ];
        }

        return view('learning.path', [
            'path' => $path,
            'rows' => $rows,
            'progress' => $this->paths->progress($user, $path),
            'exam' => $this->credentials->pathExam($path),
            'certificate' => $this->credentials->certificateBadge($user, $path),
            // ⭐ إجماليّ مدّة المسار = مجموع دقائق دروس تدريباته (3.3 — الهيدر)
            'totalMinutes' => array_sum($durations),
            // ⭐ لمحة ترتيبك على صفحة المسار (3.4-46)
            'glimpse' => $this->paths->rankGlimpse($user),
            'clockTimezone' => $this->clock->timezoneFor($user),
        ]);
    }

    /**
     * ⭐ العدّاد التنازليّ ومكافأة الإكمال المبكر إنلاين على الكارت (3.3).
     *
     * القيم من `XpCalculator` نفسه لا من حسابٍ ثانٍ هنا — فما يراه على الكارت
     * هو بالضبط ما سيُكتَب له لحظة الإكمال، ولا يوجد رقمان لنفس المعنى.
     *
     * @return array{xp:int, tickets:int, before_half:bool, half_at:?Carbon}
     */
    private function earlyReward(Course $course, Enrollment $enrollment): array
    {
        return [
            'xp' => $this->xp->previewXp($course, $enrollment),
            'tickets' => $this->xp->lessonTickets($course, $enrollment),
            'before_half' => $this->xp->isBeforeHalf($enrollment),
            'half_at' => $this->xp->halfPoint($enrollment),
        ];
    }
}
