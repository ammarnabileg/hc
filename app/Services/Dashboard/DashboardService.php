<?php

namespace App\Services\Dashboard;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Currency;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Level;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * لوحة المتدرّب الرئيسيّة (الدستور 14 · 24.5):
 * «أين أقف في كلّ تدريباتي» — مصدر واحد للأرقام، وكلّ عتبة إعدادٌ لا رقم محروق (2.13).
 */
class DashboardService
{
    /** كاش لكلّ طلب: تقدّم التدريبات محسوبٌ مرّةً واحدة ويُستهلَك في أكثر من بلوك */
    private array $progressCache = [];

    // ------------------------------------------------------------ الكروت الأربعة

    /**
     * صفّ الـKPI: **أربعة بحدّ أقصى** (2.15-أ-3) — والباقي في تاب «تفاصيل».
     *
     * @return array<int, array<string, mixed>>
     */
    public function kpis(User $user): array
    {
        $xp = $this->xp($user);
        $level = $this->level($xp);
        $streak = $user->streak;
        $certificates = $this->certificatesCount($user);

        $cards = [
            [
                'label' => 'مستوى الحساب و XP',
                'value' => $xp,
                'icon' => '⭐',
                'hint' => 'المستوى '.($level['level'] ?? 1).' — '.($level['name'] ?? ''),
                'state' => null,
            ],
            [
                'label' => 'التذاكر',
                'value' => (int) $this->balance($user, (string) setting('wallet.currency.tickets_code', 'tickets')),
                'icon' => '🎟️',
                'hint' => 'رصيدك المتاح للصرف',
                'state' => null,
            ],
            [
                'label' => 'الستريك',
                'value' => (int) ($streak?->current_days ?? 0),
                'icon' => '🔥',
                'hint' => 'أطول ستريك: '.(int) ($streak?->best_days ?? 0).' يوم',
                'state' => ($streak?->current_days ?? 0) > 0 ? 'ok' : 'idle',
            ],
            [
                'label' => 'الشهادات',
                'value' => $certificates,
                'icon' => '🎓',
                'hint' => 'شهاداتك السارية',
                'state' => $certificates > 0 ? 'honor' : 'idle',
            ],
        ];

        return array_slice($cards, 0, (int) setting('ux.kpi.max_cards', 4));
    }

    /** الأرقام الثانويّة (تاب «تفاصيل») — خرجت من الصفّ الأوّل التزامًا بحدّ الأربعة */
    public function details(User $user): array
    {
        $rows = $this->progress($user);

        return [
            'completed' => $rows->where('is_completed', true)->count(),
            'active' => $rows->where('is_completed', false)->count(),
            'lessons_done' => $rows->sum('completed_lessons'),
            'lessons_total' => $rows->sum('total_lessons'),
            'rank' => $this->leaderboardRank($user),
            'peers' => $this->leaderboardSize(),
            'xp_from_courses' => (int) $rows->sum('xp_earned'),
        ];
    }

    // ------------------------------------------------------------ كروت التدريبات

    /** كروت التدريبات الجارية (14-ب) — بحدّ أقصى من الإعدادات */
    public function activeCourses(User $user): Collection
    {
        return $this->progress($user)
            ->where('is_completed', false)
            ->sortBy(fn (array $row) => $row['timer']->deadline?->timestamp ?? PHP_INT_MAX)
            ->take((int) setting('dashboard.courses.limit', 6))
            ->values();
    }

    /** بلوك «أقرب المواعيد» بعدّاداتها الملوّنة (14-د) */
    public function upcomingDeadlines(User $user): Collection
    {
        return $this->progress($user)
            ->where('is_completed', false)
            ->filter(fn (array $row) => $row['timer']->deadline !== null && $row['timer']->isUpcoming())
            ->sortBy(fn (array $row) => $row['timer']->deadline->timestamp)
            ->take((int) setting('dashboard.deadlines.limit', 4))
            ->values();
    }

    /**
     * الفعل الرئيسيّ الوحيد [أكمل آخر درس]: آخر درس غير مكتمل
     * في التدريب الذي كان يذاكره فعلًا — لا أوّل تدريب في القائمة.
     */
    public function nextLesson(User $user): ?array
    {
        $row = $this->progress($user)
            ->where('is_completed', false)
            ->filter(fn (array $r) => $r['next_lesson'] !== null)
            ->sortByDesc(fn (array $r) => $r['last_activity_at']?->timestamp ?? 0)
            ->first();

        return $row['next_lesson'] ?? null;
    }

    public function hasEnrollments(User $user): bool
    {
        return $this->progress($user)->isNotEmpty();
    }

    // ------------------------------------------------------------ الأرقام الخام

    /** إجمالي XP: من المحفظة، وإن لم تُفتَح بعدُ فمن عدّاد الحساب */
    public function xp(User $user): int
    {
        $code = (string) setting('wallet.currency.xp_code', 'xp');
        $balance = $user->balances()
            ->whereHas('currency', fn ($q) => $q->where('code', $code))
            ->value('balance');

        return (int) ($balance ?? $user->xp ?? 0);
    }

    /** مستوى الحساب من جدول المستويات — لا عتبات محروقة في الكود */
    public function level(int $xp): array
    {
        $current = Level::query()->where('min_xp', '<=', $xp)->orderByDesc('min_xp')->first();
        $next = Level::query()->where('min_xp', '>', $xp)->orderBy('min_xp')->first();

        $from = (int) ($current->min_xp ?? 0);
        $to = (int) ($next->min_xp ?? 0);

        return [
            'level' => (int) ($current->level ?? 1),
            'name' => (string) ($current->name_ar ?? ''),
            'next_at' => $next?->min_xp,
            'percent' => $next && $to > $from ? (int) round((($xp - $from) / ($to - $from)) * 100) : 100,
        ];
    }

    public function balance(User $user, string $currencyCode): float
    {
        return (float) $user->balances()
            ->whereHas('currency', fn ($q) => $q->where('code', $currencyCode))
            ->value('balance');
    }

    public function certificatesCount(User $user): int
    {
        return Certificate::query()
            ->where('user_id', $user->id)
            ->where('status', 'valid')
            ->count();
    }

    /** ترتيب الليدر بورد (7.3) — بالمقارنة على نفس مصدر XP لكلّ المستخدمين */
    public function leaderboardRank(User $user): int
    {
        $xp = $this->xp($user);
        $currencyId = Currency::query()
            ->where('code', (string) setting('wallet.currency.xp_code', 'xp'))
            ->value('id');

        return 1 + DB::table('users')
            ->leftJoin('wallet_balances', function ($join) use ($currencyId) {
                $join->on('wallet_balances.user_id', '=', 'users.id')
                    ->where('wallet_balances.currency_id', '=', $currencyId);
            })
            ->where('users.status', 'active')
            ->whereNull('users.deleted_at')
            ->whereRaw('COALESCE(wallet_balances.balance, users.xp) > ?', [$xp])
            ->count();
    }

    public function leaderboardSize(): int
    {
        return DB::table('users')->where('status', 'active')->whereNull('deleted_at')->count();
    }

    // ------------------------------------------------------------ الحساب المركزيّ

    /**
     * تقدّم كلّ تدريب أخذه المستخدم — يُحسَب مرّةً واحدة لكلّ طلب،
     * فالكروت والمواعيد والفعل الرئيسيّ والإحصائيّات كلّها من مصدرٍ واحد.
     */
    public function progress(User $user): Collection
    {
        if (isset($this->progressCache[$user->id])) {
            return $this->progressCache[$user->id];
        }

        $enrollments = Enrollment::query()
            ->with('course')
            ->where('user_id', $user->id)
            ->get()
            ->filter(fn (Enrollment $e) => $e->course !== null);

        if ($enrollments->isEmpty()) {
            return $this->progressCache[$user->id] = collect();
        }

        $courseIds = $enrollments->pluck('course_id')->all();

        $lessons = DB::table('lessons')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->whereIn('sections.course_id', $courseIds)
            ->orderBy('sections.sort_order')
            ->orderBy('sections.id')
            ->orderBy('lessons.sort_order')
            ->orderBy('lessons.id')
            ->get(['lessons.id', 'lessons.title_ar', 'sections.course_id', 'sections.title_ar as section_title'])
            ->groupBy('course_id');

        $completions = DB::table('lesson_completions')
            ->where('user_id', $user->id)
            ->pluck('completed_at', 'lesson_id');

        $finished = DB::table('course_completions')
            ->where('user_id', $user->id)
            ->pluck('completed_at', 'course_id');

        $exams = Exam::query()
            ->where('examable_type', Course::class)
            ->whereIn('examable_id', $courseIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('examable_id');

        $passedExams = ExamAttempt::query()
            ->where('user_id', $user->id)
            ->whereIn('exam_id', $exams->pluck('id')->all())
            ->where('passed', true)
            ->pluck('exam_id')
            ->all();

        $certificates = Certificate::query()
            ->where('user_id', $user->id)
            ->where('subject_type', Course::class)
            ->whereIn('subject_id', $courseIds)
            ->where('status', 'valid')
            ->pluck('subject_id')
            ->all();

        $rows = $enrollments->map(function (Enrollment $enrollment) use ($lessons, $completions, $finished, $exams, $passedExams, $certificates) {
            $courseLessons = $lessons->get($enrollment->course_id, collect());
            $total = $courseLessons->count();

            $done = $courseLessons->filter(fn ($lesson) => isset($completions[$lesson->id]));
            $next = $courseLessons->first(fn ($lesson) => ! isset($completions[$lesson->id]));

            // لو التدريب بلا دروس بعد، نعتمد النسبة المسجّلة في التسجيل نفسه
            $percent = $total > 0 ? (int) round(($done->count() / $total) * 100) : (int) $enrollment->progress_percent;

            $lastActivity = $done
                ->map(fn ($lesson) => $completions[$lesson->id])
                ->map(fn ($at) => Carbon::parse($at))
                ->sortDesc()
                ->first();

            $exam = $exams->get($enrollment->course_id);
            $hasCertificate = in_array($enrollment->course_id, $certificates, true);
            $isCompleted = isset($finished[$enrollment->course_id]) || $percent >= 100;

            return [
                'enrollment' => $enrollment,
                'course' => $enrollment->course,
                'percent' => min(100, max(0, $percent)),
                'total_lessons' => $total,
                'completed_lessons' => $done->count(),
                'section_title' => $next->section_title ?? null,
                'lesson_title' => $next->title_ar ?? null,
                'next_lesson' => $next ? [
                    'course_id' => $enrollment->course_id,
                    'course_name' => $enrollment->course->name_ar,
                    'lesson_id' => $next->id,
                    'lesson_title' => $next->title_ar,
                    'section_title' => $next->section_title,
                    'url' => $this->lessonUrl($enrollment->course, (int) $next->id),
                ] : null,
                'course_url' => $this->courseUrl($enrollment->course),
                'timer' => GhostTimer::make($enrollment->deadline_at),
                'xp_earned' => (int) $enrollment->xp_earned,
                'exam' => $this->examState($exam, $exam && in_array($exam->id, $passedExams, true), $percent),
                'certificate' => $hasCertificate
                    ? ['state' => 'honor', 'label' => 'الشهادة صادرة']
                    : ['state' => 'idle', 'label' => 'الشهادة مقفولة'],
                'is_completed' => $isCompleted,
                'last_activity_at' => $lastActivity ?? $enrollment->started_at,
            ];
        });

        return $this->progressCache[$user->id] = $rows->values();
    }

    // ------------------------------------------------------------ داخليّ

    private function examState(?Exam $exam, bool $passed, int $percent): array
    {
        if (! $exam) {
            return ['state' => 'idle', 'label' => 'بلا امتحان'];
        }

        if ($passed) {
            return ['state' => 'ok', 'label' => 'اجتزت الامتحان'];
        }

        return $percent >= 100
            ? ['state' => 'warn', 'label' => 'الامتحان مفتوح']
            : ['state' => 'idle', 'label' => 'الامتحان مقفول'];
    }

    /**
     * روابط مجال «تعلّمي» تُبنى بأسماء مساراته إن كانت منشورة،
     * وإلّا فالزرّ يقود لقائمة التدريبات — فلا تنكسر الصفحة أثناء البناء التدريجيّ.
     */
    private function lessonUrl(Course $course, int $lessonId): string
    {
        foreach (['learning.lesson', 'learning.lessons.show'] as $name) {
            if (Route::has($name)) {
                return route($name, ['course' => $course->id, 'lesson' => $lessonId]);
            }
        }

        return $this->courseUrl($course);
    }

    private function courseUrl(Course $course): string
    {
        foreach (['learning.course', 'learning.courses.show'] as $name) {
            if (Route::has($name)) {
                return route($name, ['course' => $course->id]);
            }
        }

        return Route::has('learning.courses') ? route('learning.courses') : '#';
    }
}
