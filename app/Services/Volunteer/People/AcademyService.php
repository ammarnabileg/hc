<?php

namespace App\Services\Volunteer\People;

use App\Models\Course;
use App\Models\CourseCompletion;
use App\Models\LearningPath;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * الأكاديمية (13.4-ل · 24.4-9).
 *
 * القواعد التي تحكم هذا الملفّ:
 *  - **الكورسات هي نفس الكيانات الموجودة** — لا نسخ ولا جدول موازٍ.
 *  - **سجلّ إكمال واحد لكلّ (مستخدم، كورس)** — فما أتمّه في الأكاديمية محسوبٌ له في المسار الطبيعيّ
 *    **بنفس علامة الإكمال بالظبط بلا تمييز**.
 *  - **[احصل على الشهادة] بعد 100% فقط** لمسار مربوط؛ وقبلها **بار صامت بلا CTA**.
 *  - **المسار التعليميّ الصِرف: صفر إيحاء بشهادة ناقصة.**
 *  - **بلا أيّ شارة تسويقيّة على الكروت.**
 */
class AcademyService
{
    public function __construct(private readonly PeopleBridge $bridge) {}

    /** كيانات المتطوّع النشطة — الأكاديمية تُعرَض «حسب قسمي» */
    public function myEntityIds(User $user): array
    {
        return Membership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('entity_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * مسارات الأكاديمية المتاحة لقسمي — والمسار بلا ربط يعني «الكلّ».
     *
     * @return Collection<int, LearningPath>
     */
    public function paths(User $user): Collection
    {
        $entityIds = $this->myEntityIds($user);

        $linked = DB::table('academy_path_entity')
            ->when($entityIds !== [], fn ($q) => $q->whereIn('entity_id', $entityIds))
            ->pluck('learning_path_id')
            ->unique();

        $unlinked = DB::table('academy_path_entity')->distinct()->pluck('learning_path_id');

        return LearningPath::query()
            ->where('is_academy', true)
            ->where(fn ($q) => $q->whereIn('id', $linked)->orWhereNotIn('id', $unlinked->all() ?: [0]))
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * كورسات المسار مرتّبة — محطّات الـRoadmap الرأسيّ.
     *
     * @return Collection<int, Course>
     */
    public function courses(LearningPath $path): Collection
    {
        return Course::query()
            ->join('course_learning_path as clp', 'clp.course_id', '=', 'courses.id')
            ->where('clp.learning_path_id', $path->id)
            ->orderBy('clp.sort_order')
            ->select('courses.*')
            ->get();
    }

    /** معرّفات الكورسات المكتملة — **نفس سجلّ الإكمال** المستعمَل في المسار الطبيعيّ */
    public function completedCourseIds(User $user, Collection $courses): array
    {
        if ($courses->isEmpty()) {
            return [];
        }

        return CourseCompletion::query()
            ->where('user_id', $user->id)
            ->whereIn('course_id', $courses->pluck('id'))
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * حالة المسار للمستخدم: النسبة · هل اكتمل · هل هو مربوط بمسار شهادة.
     *
     * @return array{courses:Collection<int,Course>,done:array<int,int>,percent:int,complete:bool,
     *               target:LearningPath|null,coverage:array{covered:int,total:int}|null}
     */
    public function progress(User $user, LearningPath $path): array
    {
        $courses = $this->courses($path);
        $done = $this->completedCourseIds($user, $courses);
        $total = $courses->count();
        $percent = $total > 0 ? (int) round(count($done) / $total * 100) : 0;

        $target = $path->target_path_id ? LearningPath::query()->find($path->target_path_id) : null;

        return [
            'courses' => $courses,
            'done' => $done,
            'percent' => $percent,
            'complete' => $total > 0 && $percent >= 100,
            'target' => $target,
            'coverage' => $target ? $this->coverage($path, $target) : null,
        ];
    }

    /** «يغطّي 7 من 9 كورسات» — تقاطع كورسات المسارَين */
    public function coverage(LearningPath $academy, LearningPath $target): array
    {
        $academyIds = $this->courses($academy)->pluck('id');
        $targetIds = $this->courses($target)->pluck('id');

        return [
            'covered' => $targetIds->intersect($academyIds)->count(),
            'total' => $targetIds->count(),
        ];
    }

    /**
     * ⭐ هل يظهر زرّ «احصل على الشهادة»؟
     * شرطان معًا: مسارٌ **مربوط** + إكمال **100%**. وما دون ذلك: بار صامت بلا أيّ CTA.
     */
    public function showCertificateCta(array $progress): bool
    {
        return $progress['complete'] && $progress['target'] !== null;
    }

    /** رسالة الإتمام — وللتعليميّ الصِرف نصّ **بلا أيّ إيحاء بشهادة ناقصة** (13.4-ل) */
    public function completionMessage(array $progress): string
    {
        return $progress['target']
            ? (string) setting('academy.complete.linked_message', 'أنت جاهز للامتحان — كلّ المذاكرة خلصت.')
            : (string) setting('academy.complete.pure_message', 'أتممت المسار 🎉');
    }

    /** مكافأة إكمال المسار الأكاديميّ — قيمتها من جدول Rep لا من الكود (13.4-ن) */
    public function rewardCompletion(User $user, LearningPath $path): void
    {
        $this->bridge->credit($user, 'rep', rep_rule('academy.path_complete', 1.0), 'academy.path_complete', $path);
        $this->bridge->celebrate($user, 'course.completed', $path);
    }
}
