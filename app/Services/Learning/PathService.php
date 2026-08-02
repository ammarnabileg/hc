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

    /** سعر امتحان شهادة المسار بالكوينز — من الإعدادات لا من الكود (2.13). */
    public function examPriceCoins(): int
    {
        return (int) setting('learning.path.exam_price_coins', 0);
    }
}
