<?php

namespace App\Services\Dashboard;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Currency;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Services\Gamification\LevelResolver;
use App\Services\Gamification\TicketsAccount;
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

    public function __construct(
        private readonly LevelResolver $levels,
        private readonly TicketsAccount $tickets,
    ) {}

    // ------------------------------------------------------------ الكروت الأربعة

    /**
     * صفّ الـKPI: **الستّة التي نصّ عليها 14-أ مبنيّةً كاملةً**، ويقصّها
     * `ux.kpi.max_cards` عند العرض (2.15-أ-3 افتراضيًّا أربعة).
     *
     * لماذا نبنيها كلّها ثمّ نقصّ؟ لأنّ الإعداد كان يوهم بمرونة غير موجودة:
     * كارتا «التدريبات» و«ترتيب الليدر بورد» لم تكونا مبنيّتين أصلًا، فرفعُ الحدّ
     * من اللوحة لا يُظهر شيئًا. **الحدّ يقصّ لا يحذف** — فما زاد ينزل لتاب «تفاصيل».
     *
     * @return array<int, array<string, mixed>>
     */
    public function kpis(User $user): array
    {
        $xp = $this->xp($user);
        $level = $this->level($xp);
        $streak = $user->streak;
        $certificates = $this->certificatesCount($user);
        $rows = $this->progress($user);
        $completed = $rows->where('is_completed', true)->count();
        $active = $rows->where('is_completed', false)->count();
        $rank = $this->leaderboardRank($user);

        $cards = [
            [
                'label' => (string) setting('dashboard.kpi.level_label', 'مستوى الحساب و XP'),
                'value' => $xp,
                'icon' => 'xp',
                'hint' => 'المستوى '.($level['level'] ?? 1).' — '.($level['name'] ?? ''),
                'state' => null,
            ],
            /*
             | ⭐ **«رصيد التذاكر»** بالاسم كما ينصّ 10.0-أ — لا «التذاكر» مجرّدةً.
             | فعلى اللوحة نفسها رقمٌ آخر للتذاكر في الرادار معناه **المكتسب**
             | (10)، وثالثٌ في البارات معناه **حركة المدى** (24.5). ثلاثة معانٍ
             | لثلاثة أرقام — وبلا تسميةٍ تفرّقها يقرؤها المستخدم تناقضًا.
             */
            [
                'label' => (string) setting('dashboard.kpi.tickets_label', 'رصيد التذاكر'),
                'value' => $this->tickets->balance($user),
                'icon' => 'ticket',
                'hint' => (string) setting('dashboard.kpi.tickets_hint', 'رصيدك المتاح للصرف'),
                'state' => null,
            ],
            [
                'label' => 'الستريك',
                'value' => (int) ($streak?->current_days ?? 0),
                'icon' => 'streak',
                'hint' => 'أطول ستريك: '.(int) ($streak?->best_days ?? 0).' يوم',
                'state' => ($streak?->current_days ?? 0) > 0 ? 'ok' : 'idle',
            ],
            [
                'label' => 'الشهادات',
                'value' => $certificates,
                'icon' => 'certificate',
                'hint' => 'شهاداتك السارية',
                'state' => $certificates > 0 ? 'honor' : 'idle',
            ],
            // «التدريبات (مكتملة / جارية)» — كارتٌ منصوصٌ عليه في 14-أ
            [
                'label' => (string) setting('dashboard.kpi.courses_label', 'التدريبات (مكتملة / جارية)'),
                'value' => $completed.' / '.$active,
                'icon' => 'library',
                'hint' => str_replace(
                    ':total',
                    (string) ($completed + $active),
                    (string) setting('dashboard.kpi.courses_hint', 'إجماليّ تدريباتك: :total'),
                ),
                'state' => $active > 0 ? 'ok' : 'idle',
            ],
            // «ترتيب الليدر بورد #» — بمقياس XP (7.3)
            [
                'label' => (string) setting('dashboard.kpi.rank_label', 'ترتيب الليدر بورد'),
                'value' => '#'.$rank,
                'icon' => 'trophy',
                'hint' => str_replace(
                    ':peers',
                    (string) $this->leaderboardSize(),
                    (string) setting('dashboard.kpi.rank_hint', 'من بين :peers متدرّبًا'),
                ),
                'state' => $rank === 1 ? 'honor' : 'idle',
            ],
        ];

        return array_slice($cards, 0, max((int) setting('ux.kpi.max_cards', 4), 1));
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

    /** إجمالي XP — **من المصدر الواحد** `LevelResolver::xpFor()` لا بحسابٍ موازٍ */
    public function xp(User $user): int
    {
        return $this->levels->xpFor($user);
    }

    /**
     * مستوى الحساب — **من المصدر الواحد** `LevelResolver` بصيغة 10.1.
     *
     * كان هنا حسابٌ ثانٍ يقرأ عتبات `levels.min_xp`، فيقول كارت الـKPI «المستوى 3»
     * بينما رادار الإنجازات في **الصفحة نفسها** يقول «مستوى 4» لنفس المستخدم
     * ونفس اللحظة (ن-2). فحُذِف الحساب الثاني ولم يُوفَّق بينهما — التوفيق يُبقي
     * مصدرين، والحذف يُبقي واحدًا.
     */
    public function level(int $xp): array
    {
        return $this->levels->forXp($xp);
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
        // نمرّر الموديل نفسه ليستخدم Laravel مفتاح المسار الصحيح (slug أو id)
        foreach (['learning.lesson', 'learning.lessons.show'] as $name) {
            if (Route::has($name)) {
                return route($name, ['course' => $course, 'lesson' => $lessonId]);
            }
        }

        return $this->courseUrl($course);
    }

    private function courseUrl(Course $course): string
    {
        foreach (['learning.course', 'learning.courses.show'] as $name) {
            if (Route::has($name)) {
                return route($name, ['course' => $course]);
            }
        }

        return Route::has('learning.courses') ? route('learning.courses') : '#';
    }
}
