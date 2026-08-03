<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Services\Learning\AvailabilityService;
use App\Services\Learning\CredentialService;
use App\Services\Learning\DeadlineService;
use App\Services\Learning\ProgressService;
use App\Services\Learning\TimezoneDetector;
use App\Services\Learning\UserClock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * تعلّمي ← تدريباتي (الدستور 24.5).
 * الغرض: «كلّ التدريبات التي أملكها وحالتها» — سؤال واحد للشاشة (2.15-أ-1).
 */
class LearningController extends Controller
{
    public function __construct(
        private readonly ProgressService $progress,
        private readonly DeadlineService $deadlines,
        private readonly AvailabilityService $availability,
        private readonly CredentialService $credentials,
        private readonly TimezoneDetector $timezones,
        private readonly UserClock $clock,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        // الكشف التلقائيّ ديناميكيّ: يتبع مكان المستخدم الآن لا دولته عند التسجيل (5)
        $this->timezones->sync($request, $user);

        $query = Enrollment::query()
            ->with('course')
            ->where('user_id', $user->id);

        // فلتر المسار: ثالث الفلاتر الظاهرة (24.5)
        if ($pathId = (int) $request->integer('path')) {
            $courseIds = DB::table('course_learning_path')
                ->where('learning_path_id', $pathId)
                ->pluck('course_id');

            $query->whereIn('course_id', $courseIds);
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->whereIn('course_id', DB::table('courses')
                ->where('name_ar', 'like', '%'.$search.'%')
                ->orWhere('name_en', 'like', '%'.$search.'%')
                ->pluck('id'));
        }

        $sort = $request->query('sort') === 'name' ? 'name' : 'recent';

        $enrollments = $query
            ->orderByDesc($sort === 'recent' ? 'updated_at' : 'id')
            ->get();

        $summaries = $this->progress->summaries($user, $enrollments);
        $cards = [];
        $counts = ['not_started' => 0, 'active' => 0, 'completed' => 0];

        foreach ($enrollments as $enrollment) {
            $course = $enrollment->course;

            if (! $course) {
                continue;
            }

            $summary = $summaries[$course->id] ?? ['total' => 0, 'completed' => 0, 'percent' => 0, 'current_title' => null, 'section_title' => null];
            $availability = $this->availability->forCourse($course, $enrollment, $user);

            $status = match (true) {
                $summary['percent'] >= (int) setting('learning.progress.complete_percent', 100) => 'completed',
                $summary['completed'] > 0 => 'active',
                default => 'not_started',
            };

            $counts[$status]++;

            $cards[] = [
                'enrollment' => $enrollment,
                'course' => $course,
                'status' => $status,
                'summary' => $summary,
                'availability' => $availability,
                'deadline' => $this->deadlines->forEnrollment($enrollment),
                'exam' => $this->credentials->courseExam($user, $course, $summary['percent'], $availability),
                'certificate' => $this->credentials->certificateBadge($user, $course),
            ];
        }

        // الفلترة بالحالة بعد الحساب — لأنّ الحالة مشتقّة من الإكمال لا مخزّنة
        if (in_array($request->query('status'), ['not_started', 'active', 'completed'], true)) {
            $cards = array_values(array_filter($cards, fn ($c) => $c['status'] === $request->query('status')));
        }

        if ($sort === 'name') {
            usort($cards, fn ($a, $b) => strcmp($a['course']->name_ar, $b['course']->name_ar));
        }

        return view('learning.courses', [
            'cards' => $cards,
            'counts' => $counts,
            'paths' => DB::table('learning_paths')->orderBy('sort_order')->get(['id', 'name_ar']),
            'filters' => [
                'status' => (string) $request->query('status', ''),
                'path' => (string) $request->query('path', ''),
                'q' => $search,
                'sort' => $sort,
            ],
            'resume' => $this->resumeTarget($cards),
            // شريحة «توقيتك» فوق القائمة: يفهم منها لماذا فُتح تدريب وأُغلق آخر (5)
            'clock' => [
                'timezone' => $this->clock->timezoneFor($user),
                'offset' => $this->clock->offsetLabel($user),
                'source' => $this->clock->sourceFor($user),
                'now' => $this->clock->now($user),
            ],
            'timezones' => $this->clock->options(),
        ]);
    }

    /**
     * [أكمل آخر درس] — الفعل الرئيسيّ الوحيد في الهيدر (2.15-أ-2).
     * ويختار أوّل تدريب جارٍ ومتاح؛ فإن لم يوجد فلا زرّ أصلًا بدل زرٍّ لا يعمل.
     */
    private function resumeTarget(array $cards): ?array
    {
        foreach ($cards as $card) {
            if ($card['availability']['open'] && $card['status'] === 'active') {
                return ['course' => $card['course'], 'title' => $card['summary']['current_title']];
            }
        }

        foreach ($cards as $card) {
            if ($card['availability']['open'] && $card['status'] === 'not_started') {
                return ['course' => $card['course'], 'title' => $card['summary']['current_title']];
            }
        }

        return null;
    }
}
