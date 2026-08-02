<?php

namespace App\Services\Learning;

use App\Models\Course;
use App\Models\LearningPath;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * المسارات وتقدّمها (الدستور 3 · 3.3 · 24.5 — المسارات).
 *
 * قاعدة العرض الحاسمة: **[امتحان شهادة المسار] لا يظهر إلّا بعد 100%**،
 * وقبلها بارٌ صامت بلا CTA — لا تلميح ولا إغراء (منعًا لأنماط الضغط).
 */
class PathService
{
    /** المسارات التي يسير فيها المستخدم — تُشتقّ من تسجيلاته لا من قائمة منفصلة. */
    public function pathsOf(User $user): Collection
    {
        $courseIds = DB::table('enrollments')->where('user_id', $user->id)->pluck('course_id');

        if ($courseIds->isEmpty()) {
            return collect();
        }

        $pathIds = DB::table('course_learning_path')
            ->whereIn('course_id', $courseIds)
            ->pluck('learning_path_id')
            ->unique();

        return LearningPath::query()
            ->whereIn('id', $pathIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** تدريبات المسار بترتيبها المعتمَد (3.3 — Roadmap رأسيّ مرقّم). */
    public function coursesOf(LearningPath $path): Collection
    {
        return Course::query()
            ->join('course_learning_path as pivot', 'pivot.course_id', '=', 'courses.id')
            ->where('pivot.learning_path_id', $path->id)
            ->orderBy('pivot.sort_order')
            ->orderBy('pivot.id')
            ->select('courses.*')
            ->get();
    }

    /**
     * تقدّم المستخدم في المسار = التدريبات المكتملة ÷ تدريبات المسار.
     *
     * @return array{total:int,completed:int,percent:int,unlocked_exam:bool}
     */
    public function progress(User $user, LearningPath $path): array
    {
        $courseIds = DB::table('course_learning_path')
            ->where('learning_path_id', $path->id)
            ->pluck('course_id');

        $total = $courseIds->count();

        $completed = $total === 0 ? 0 : DB::table('course_completions')
            ->where('user_id', $user->id)
            ->whereIn('course_id', $courseIds)
            ->count();

        $percent = $total > 0 ? (int) round($completed / $total * 100) : 0;

        return [
            'total' => $total,
            'completed' => $completed,
            'percent' => $percent,
            'unlocked_exam' => $percent >= (int) setting('learning.path.exam_unlock_percent', 100),
        ];
    }

    /**
     * ⭐ مدّة كلّ تدريب بالدقائق (3.3 — «إجماليّ مدّة المسار» = مجموعها).
     * استعلامٌ واحد للمسار كلّه بدل استعلامٍ لكلّ كارت.
     *
     * @param  Collection<int, Course>  $courses
     * @return array<int, int>
     */
    public function durations(Collection $courses): array
    {
        $ids = $courses->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $fallback = (int) setting('learning.lesson.default_duration_minutes', 5);

        return DB::table('lessons')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->whereIn('sections.course_id', $ids)
            ->groupBy('sections.course_id')
            ->get([
                'sections.course_id as course_id',
                // الدرس بلا مدّة يأخذ الافتراضيّ من الإعدادات لا صفرًا (2.13)
                DB::raw('SUM(COALESCE(NULLIF(lessons.duration_minutes, 0), '.$fallback.')) as minutes'),
            ])
            ->mapWithKeys(fn ($row) => [(int) $row->course_id => (int) $row->minutes])
            ->all();
    }

    /**
     * ⭐ الدليل الاجتماعيّ الحيّ (3.4-45 · 3.4-49) — **أرقام حقيقيّة لا مجمَّلة**
     * (2.9-7، والحارس الأخلاقيّ يمنع الأرقام الوهميّة صراحةً).
     *
     *  - `completed`: كم متدرّبًا أتمّ هذا التدريب فعلًا (من `course_completions`).
     *  - `learning_now`: كم متدرّبًا نشِطًا في التدريب داخل نافذة قصيرة الآن.
     *
     * @param  Collection<int, Course>  $courses
     * @return array<int, array{completed:int, learning_now:int}>
     */
    public function socialProof(Collection $courses): array
    {
        $ids = $courses->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $completed = DB::table('course_completions')
            ->whereIn('course_id', $ids)
            ->groupBy('course_id')
            ->get(['course_id', DB::raw('COUNT(DISTINCT user_id) as total')])
            ->mapWithKeys(fn ($row) => [(int) $row->course_id => (int) $row->total]);

        // «بيتعلّموا الآن» = تسجيلات جارية تحرّكت داخل النافذة — لا تخمين ولا تضخيم
        $window = max(1, (int) setting('learning.social.active_window_minutes', 30));

        $now = DB::table('enrollments')
            ->whereIn('course_id', $ids)
            ->where('status', 'active')
            ->where('updated_at', '>=', now()->subMinutes($window))
            ->groupBy('course_id')
            ->get(['course_id', DB::raw('COUNT(DISTINCT user_id) as total')])
            ->mapWithKeys(fn ($row) => [(int) $row->course_id => (int) $row->total]);

        $out = [];

        foreach ($ids as $id) {
            $out[(int) $id] = [
                'completed' => (int) ($completed[(int) $id] ?? 0),
                'learning_now' => (int) ($now[(int) $id] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * ⭐ تقدّم المدعوّين على المسار (3.4-48): مَن دعاهم المستخدم ويسيرون معه.
     * ملكيّة وتقدّم مُهدى (2.9-2 · 2.9-8) — وبلا أيّ رقم مُختلَق.
     *
     * @param  Collection<int, Course>  $courses
     * @return array<int, array<int, array{name:string, percent:int}>>
     */
    public function invitedProgress(User $user, Collection $courses): array
    {
        $ids = $courses->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $invitedIds = DB::table('referrals')
            ->where('referrer_id', $user->id)
            ->whereNotNull('referred_id')
            ->pluck('referred_id');

        if ($invitedIds->isEmpty()) {
            return [];
        }

        $rows = DB::table('enrollments')
            ->join('users', 'users.id', '=', 'enrollments.user_id')
            ->whereIn('enrollments.course_id', $ids)
            ->whereIn('enrollments.user_id', $invitedIds)
            ->whereNull('users.deleted_at')
            ->orderByDesc('enrollments.progress_percent')
            ->limit((int) setting('learning.paths.friends_limit', 12))
            ->get(['enrollments.course_id', 'users.name', 'enrollments.progress_percent']);

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row->course_id][] = [
                'name' => (string) $row->name,
                'percent' => (int) $row->progress_percent,
            ];
        }

        return $out;
    }

    /**
     * ⭐ لمحة ترتيبي (3.4-46): رقمي في لوحة XP وكم يفصلني عن الذي أمامي مباشرةً
     * — مقارنة اجتماعيّة **قريبة** لا عامّة (2.9-5).
     *
     * @return array{rank:int, total:int, gap:int, ahead:?string}|null
     */
    public function rankGlimpse(User $user): ?array
    {
        $xp = (int) $user->xp;

        $ahead = DB::table('users')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->where('xp', '>', $xp)->orWhere(fn ($i) => $i->where('xp', $xp)->where('id', '<', $user->id)))
            ->orderBy('xp')
            ->orderByDesc('id')
            ->first(['name', 'xp']);

        $total = DB::table('users')->where('status', 'active')->whereNull('deleted_at')->count();

        if ($total <= 0) {
            return null;
        }

        $rank = DB::table('users')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->where('xp', '>', $xp)->orWhere(fn ($i) => $i->where('xp', $xp)->where('id', '<', $user->id)))
            ->count() + 1;

        return [
            'rank' => $rank,
            'total' => $total,
            'gap' => $ahead ? max(0, (int) $ahead->xp - $xp) : 0,
            'ahead' => $ahead?->name,
        ];
    }
}
