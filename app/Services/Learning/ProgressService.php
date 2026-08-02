<?php

namespace App\Services\Learning;

use App\Models\Course;
use App\Models\CourseCompletion;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * حساب التقدّم وفتح الدروس وتسجيل الإكمال (الدستور 3 · 4.1 · 7 · 24.5).
 *
 * لماذا خدمة مستقلّة؟ لأنّ «الترتيب الإجباريّ» و«التقدّم %» و«الإكمال» ثلاثة
 * قرارات يعتمد بعضها على بعض، ولو تفرّقت على الكنترولرز اختلفت النتيجة بين شاشة
 * وأخرى — والقاعدة النهائيّة: سجلّ إكمالٍ واحد لكلّ (مستخدم، درس) و(مستخدم، كورس).
 */
class ProgressService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly XpCalculator $xp,
        private readonly LessonQuestionService $questions,
    ) {}

    // ------------------------------------------------------------ قراءة

    /**
     * دروس التدريب بترتيبها المعتمَد: السيكشن ثمّ الدرس.
     *
     * @return Collection<int, object>
     */
    public function orderedLessons(Course $course): Collection
    {
        return DB::table('lessons')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->where('sections.course_id', $course->id)
            ->orderBy('sections.sort_order')
            ->orderBy('sections.id')
            ->orderBy('lessons.sort_order')
            ->orderBy('lessons.id')
            ->get([
                'lessons.id',
                'lessons.section_id',
                'lessons.title_ar',
                'lessons.type',
                'lessons.duration_minutes',
                'lessons.is_free_preview',
                'sections.title_ar as section_title',
                'sections.sort_order as section_order',
            ]);
    }

    /** @return array<int, int> معرّفات الدروس المكتملة */
    public function completedLessonIds(User $user, Course $course): array
    {
        return DB::table('lesson_completions')
            ->join('lessons', 'lessons.id', '=', 'lesson_completions.lesson_id')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->where('lesson_completions.user_id', $user->id)
            ->where('sections.course_id', $course->id)
            ->pluck('lesson_completions.lesson_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function percent(User $user, Course $course): int
    {
        $total = $this->orderedLessons($course)->count();

        if ($total === 0) {
            return 0;
        }

        return (int) round(count($this->completedLessonIds($user, $course)) / $total * 100);
    }

    /**
     * خريطة التدريب الكاملة: سيكشنز ودروس بحالاتها.
     * الدروس المقفولة **تظهر بقفل وسببٍ مكتوب** ولا تُخفى (24.5 — صفحة التدريب).
     *
     * @return array{sections:array<int,array>,total:int,completed:int,percent:int,current_id:?int,locked_reason:?string}
     */
    public function outline(User $user, Course $course, ?Enrollment $enrollment = null): array
    {
        $lessons = $this->orderedLessons($course);
        $done = $this->completedLessonIds($user, $course);
        $availability = $this->availability->forCourse($course, $enrollment);

        $forced = (bool) $course->forced_order;
        $previousDone = true;
        $currentId = null;
        $sections = [];

        foreach ($lessons as $row) {
            $isDone = in_array((int) $row->id, $done, true);

            [$unlocked, $reason] = match (true) {
                ! $availability['open'] => [false, $availability['reason']],
                $isDone || ! $forced || $previousDone => [true, null],
                default => [false, setting('learning.lock.forced_order_reason')],
            };

            // الدرس الحاليّ: أوّل درس مفتوح غير مكتمل — عليه يقع الفعل الرئيسيّ [أكمل]
            if ($currentId === null && $unlocked && ! $isDone) {
                $currentId = (int) $row->id;
            }

            $sections[$row->section_id] ??= [
                'id' => (int) $row->section_id,
                'title' => $row->section_title,
                'lessons' => [],
            ];

            $sections[$row->section_id]['lessons'][] = [
                'id' => (int) $row->id,
                'title' => $row->title_ar,
                'type' => $row->type,
                'icon' => $this->typeIcon($row->type),
                'duration' => (int) ($row->duration_minutes ?: setting('learning.lesson.default_duration_minutes', 5)),
                'completed' => $isDone,
                'unlocked' => $unlocked,
                'lock_reason' => $reason,
                'is_free_preview' => (bool) $row->is_free_preview,
            ];

            $previousDone = $isDone;
        }

        $total = $lessons->count();

        return [
            'sections' => array_values($sections),
            'total' => $total,
            'completed' => count($done),
            'percent' => $total > 0 ? (int) round(count($done) / $total * 100) : 0,
            'current_id' => $currentId,
            'locked_reason' => $availability['open'] ? null : $availability['reason'],
        ];
    }

    /** أيقونة نوع الدرس — 🎥 فيديو / 📄 مستند (24.5) */
    public function typeIcon(string $type): string
    {
        return $type === 'video'
            ? setting('learning.icon.video', '🎥')
            : setting('learning.icon.document', '📄');
    }

    /** هل هذا الدرس مفتوح لهذا المستخدم؟ — الفحص الحاسم يقع هنا لا في الواجهة. */
    public function isUnlocked(User $user, Course $course, Lesson $lesson, ?Enrollment $enrollment = null): bool
    {
        return (bool) ($this->lessonState($user, $course, $lesson, $enrollment)['unlocked'] ?? false);
    }

    /** @return array{unlocked:bool,reason:?string,completed:bool} */
    public function lessonState(User $user, Course $course, Lesson $lesson, ?Enrollment $enrollment = null): array
    {
        foreach ($this->outline($user, $course, $enrollment)['sections'] as $section) {
            foreach ($section['lessons'] as $row) {
                if ($row['id'] === $lesson->id) {
                    return [
                        'unlocked' => $row['unlocked'],
                        'reason' => $row['lock_reason'],
                        'completed' => $row['completed'],
                    ];
                }
            }
        }

        return ['unlocked' => false, 'reason' => setting('learning.lock.unpublished_reason'), 'completed' => false];
    }

    /** الدرس السابق والتالي في الترتيب المعتمَد — لأزرار [السابق]/[التالي]. */
    public function neighbours(Course $course, Lesson $lesson): array
    {
        $ids = $this->orderedLessons($course)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $index = array_search($lesson->id, $ids, true);

        if ($index === false) {
            return ['previous' => null, 'next' => null];
        }

        return [
            'previous' => $ids[$index - 1] ?? null,
            'next' => $ids[$index + 1] ?? null,
        ];
    }

    // ------------------------------------------------------------ كتابة

    /**
     * تسجيل إكمال درس — والقاعدة النهائيّة: **سجلّ واحد لكلّ (مستخدم، درس)**،
     * فإعادة الضغط لا تكرّر الـXP ولا تنشئ سجلًّا ثانيًا.
     *
     * @return array{ok:bool,message:string,xp:int,course_completed:bool}
     */
    public function completeLesson(User $user, Course $course, Lesson $lesson, Enrollment $enrollment): array
    {
        if (! $this->isUnlocked($user, $course, $lesson, $enrollment)) {
            return $this->refuse($this->lessonState($user, $course, $lesson, $enrollment)['reason']);
        }

        // «إنهاء الدرس» = المحتوى + اجتياز أسئلة الدرس معًا (4.1)
        if (! $this->questions->allAnsweredCorrectly($user, $lesson)) {
            return $this->refuse(setting('learning.lock.quiz_reason'));
        }

        $already = LessonCompletion::query()
            ->where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->exists();

        if ($already) {
            return [
                'ok' => true,
                'message' => setting('learning.lesson.already_done_message'),
                'xp' => 0,
                'course_completed' => $enrollment->fresh()->status === 'completed',
            ];
        }

        $xp = $this->xp->lessonXp($course, $enrollment);

        DB::transaction(function () use ($user, $lesson, $enrollment, $xp) {
            LessonCompletion::query()->firstOrCreate(
                ['user_id' => $user->id, 'lesson_id' => $lesson->id],
                ['completed_at' => Carbon::now()],
            );

            if ($xp > 0) {
                $enrollment->increment('xp_earned', $xp);
            }
        });

        $completed = $this->recalculate($user, $course, $enrollment->refresh());

        return [
            'ok' => true,
            'message' => setting('learning.lesson.done_message'),
            'xp' => $xp,
            'course_completed' => $completed,
        ];
    }

    /**
     * إعادة حساب نسبة التقدّم وتسجيل إكمال التدريب عند اكتمالها.
     * **سجلّ واحد لكلّ (مستخدم، كورس)** — قاعدة نهائيّة (13.4-ل).
     */
    public function recalculate(User $user, Course $course, Enrollment $enrollment): bool
    {
        $percent = $this->percent($user, $course);
        $target = (int) setting('learning.progress.complete_percent', 100);
        $isComplete = $percent >= $target;

        $enrollment->forceFill([
            'progress_percent' => $percent,
            'status' => $isComplete ? 'completed' : $enrollment->status,
        ])->save();

        if (! $isComplete) {
            return false;
        }

        CourseCompletion::query()->firstOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            ['completed_at' => Carbon::now(), 'xp_awarded' => (int) $enrollment->xp_earned],
        );

        return true;
    }

    // ------------------------------------------------------------ تجميع

    /**
     * عدّادات التقدّم لقائمة تسجيلات دفعةً واحدة — لتفادي استعلامٍ لكلّ كارت.
     *
     * @param  Collection<int, Enrollment>  $enrollments
     * @return array<int, array{total:int,completed:int,percent:int,current_title:?string,section_title:?string}>
     */
    public function summaries(User $user, Collection $enrollments): array
    {
        $courseIds = $enrollments->pluck('course_id')->unique()->values()->all();

        if ($courseIds === []) {
            return [];
        }

        $rows = DB::table('lessons')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->leftJoin('lesson_completions', function ($join) use ($user) {
                $join->on('lesson_completions.lesson_id', '=', 'lessons.id')
                    ->where('lesson_completions.user_id', '=', $user->id);
            })
            ->whereIn('sections.course_id', $courseIds)
            ->orderBy('sections.sort_order')
            ->orderBy('sections.id')
            ->orderBy('lessons.sort_order')
            ->orderBy('lessons.id')
            ->get([
                'sections.course_id',
                'lessons.id',
                'lessons.title_ar',
                'sections.title_ar as section_title',
                'lesson_completions.id as completion_id',
            ]);

        $out = [];

        foreach ($rows as $row) {
            $courseId = (int) $row->course_id;
            $out[$courseId] ??= ['total' => 0, 'completed' => 0, 'percent' => 0, 'current_title' => null, 'section_title' => null];
            $out[$courseId]['total']++;

            if ($row->completion_id !== null) {
                $out[$courseId]['completed']++;

                continue;
            }

            // القسم/الدرس الحاليّ = أوّل درس غير مكتمل بالترتيب المعتمَد
            if ($out[$courseId]['current_title'] === null) {
                $out[$courseId]['current_title'] = $row->title_ar;
                $out[$courseId]['section_title'] = $row->section_title;
            }
        }

        foreach ($out as $courseId => $data) {
            $out[$courseId]['percent'] = $data['total'] > 0
                ? (int) round($data['completed'] / $data['total'] * 100)
                : 0;
        }

        return $out;
    }

    /** @return array{ok:bool,message:string,xp:int,course_completed:bool} */
    private function refuse(?string $reason): array
    {
        return [
            'ok' => false,
            'message' => $reason ?? setting('learning.lock.unpublished_reason'),
            'xp' => 0,
            'course_completed' => false,
        ];
    }
}
