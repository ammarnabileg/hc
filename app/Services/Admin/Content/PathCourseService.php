<?php

namespace App\Services\Admin\Content;

use App\Models\Course;
use App\Models\CourseLearningPath;
use App\Models\Exam;
use App\Models\LearningPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * المسارات وعلاقتها بالتدريبات (12.4-أ · 24.1).
 *
 * ⭐ قاعدتان حاكمتان لا تُكسَران:
 *   1) **حذف المسار لا يحذف تدريباته** — التدريب كيان مستقلّ يعيش بعد مساره.
 *   2) **التدريب يجوز أن يكون في أكثر من مسار** — العلاقة متعدّدة لا واحدة.
 */
class PathCourseService
{
    public function __construct(private readonly ContentAudit $audit) {}

    /** @param  array<string, mixed>  $filters */
    public function list(array $filters = []): Collection
    {
        $query = LearningPath::query()->orderBy('sort_order')->orderBy('id');

        if (($q = trim((string) ($filters['q'] ?? ''))) !== '') {
            $query->where(fn ($inner) => $inner->where('name_ar', 'like', '%'.$q.'%')->orWhere('name_en', 'like', '%'.$q.'%'));
        }

        if (($status = (string) ($filters['status'] ?? '')) !== '') {
            $query->where('status', $status);
        }

        $paths = $query->get();

        $counts = CourseLearningPath::query()
            ->whereIn('learning_path_id', $paths->pluck('id'))
            ->selectRaw('learning_path_id, count(*) as total')
            ->groupBy('learning_path_id')
            ->pluck('total', 'learning_path_id');

        return $paths->each(fn (LearningPath $path) => $path->setAttribute('courses_count', (int) ($counts[$path->id] ?? 0)));
    }

    /** @param  array<string, mixed>  $data */
    public function save(?LearningPath $path, array $data): LearningPath
    {
        $isNew = $path === null;
        $before = $isNew ? [] : $path->only(array_keys($data));

        $payload = [
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'] ?? null,
            'description_ar' => $data['description_ar'] ?? null,
            'description_en' => $data['description_en'] ?? null,
            'cover_path' => $data['cover_path'] ?? ($path->cover_path ?? null),
            // ⭐ ترتيب المشاهدة: إجباريّ بالترتيب أم حرّ/عشوائيّ (12.4-أ)
            'forced_order' => (bool) ($data['forced_order'] ?? setting('paths.order.forced_default', false)),
            'sort_order' => (int) ($data['sort_order'] ?? $this->nextSortOrder()),
            'status' => $data['status'] ?? 'draft',
            // سعر امتحان شهادة المسار بالكوينز — لكلّ مسار على حدة فوق الافتراضيّ العامّ
            'exam_price_coins' => (float) ($data['exam_price_coins'] ?? setting('paths.exam.default_price_coins', 0)),
        ];

        if ($payload['status'] === 'published' && ! ($path?->published_at)) {
            $payload['published_at'] = now();
        }

        if ($isNew) {
            $payload['slug'] = $this->uniqueSlug($payload['name_ar']);
            $path = LearningPath::create($payload);
        } else {
            $path->update($payload);
        }

        $this->syncPathExam($path);
        $this->audit->record($path, $isNew ? 'path.created' : 'path.updated', $before, $payload);

        return $path->refresh();
    }

    /**
     * ⭐ حذف المسار **لا يحذف تدريباته** (12.4-أ): الحذف ناعم على المسار وحده،
     * والتدريبات تبقى كما هي ويمكن أن تكون في مسارات أخرى.
     */
    public function delete(LearningPath $path): void
    {
        $this->audit->record($path, 'path.deleted', ['name_ar' => $path->name_ar], []);
        $path->delete();
    }

    /** @param  array<int, int>  $orderedIds */
    public function reorder(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds) {
            foreach (array_values($orderedIds) as $index => $id) {
                LearningPath::query()->whereKey($id)->update(['sort_order' => $index + 1]);
            }
        });
    }

    /** تدريبات المسار مرتّبةً — شاشة «إدارة تدريبات المسار». */
    public function coursesOf(LearningPath $path, string $search = ''): Collection
    {
        return Course::query()
            ->join('course_learning_path as pivot', 'pivot.course_id', '=', 'courses.id')
            ->where('pivot.learning_path_id', $path->id)
            ->when($search !== '', fn ($q) => $q->where('courses.name_ar', 'like', '%'.$search.'%'))
            ->orderBy('pivot.sort_order')
            ->select('courses.*', 'pivot.sort_order as pivot_sort_order')
            ->get();
    }

    /** التدريبات المرشَّحة للإضافة — وقد تكون مضمومةً لمسارات أخرى بالفعل. */
    public function attachableCourses(LearningPath $path, string $search = ''): Collection
    {
        $attached = CourseLearningPath::query()->where('learning_path_id', $path->id)->pluck('course_id');

        return Course::query()
            ->whereNotIn('id', $attached->isEmpty() ? [0] : $attached->all())
            ->when($search !== '', fn ($q) => $q->where('name_ar', 'like', '%'.$search.'%'))
            ->orderByDesc('id')
            ->limit((int) setting('paths.courses.picker_limit', 20))
            ->get();
    }

    /**
     * ⭐ ضمّ تدريب لمسار — ولا يمنع أن يكون التدريب مضمومًا لمسارات أخرى (12.4-أ).
     *
     * @param  array<int, int>  $courseIds
     */
    public function attach(LearningPath $path, array $courseIds): int
    {
        $next = (int) CourseLearningPath::query()->where('learning_path_id', $path->id)->max('sort_order');
        $added = 0;

        foreach (array_unique($courseIds) as $courseId) {
            $exists = CourseLearningPath::query()
                ->where('learning_path_id', $path->id)
                ->where('course_id', $courseId)
                ->exists();

            if ($exists) {
                continue;
            }

            CourseLearningPath::create([
                'learning_path_id' => $path->id,
                'course_id' => $courseId,
                'sort_order' => ++$next,
            ]);
            $added++;
        }

        if ($added > 0) {
            $this->audit->record($path, 'path.courses_attached', [], ['count' => $added]);
        }

        return $added;
    }

    /** ⭐ الإزالة من المسار **فكّ ارتباط لا حذف** — التدريب يبقى قائمًا (12.4-أ). */
    public function detach(LearningPath $path, Course $course): void
    {
        CourseLearningPath::query()
            ->where('learning_path_id', $path->id)
            ->where('course_id', $course->id)
            ->delete();

        $this->audit->record($path, 'path.course_detached', [], ['course_id' => $course->id]);
    }

    /** @param  array<int, int>  $orderedCourseIds */
    public function reorderCourses(LearningPath $path, array $orderedCourseIds): void
    {
        DB::transaction(function () use ($path, $orderedCourseIds) {
            foreach (array_values($orderedCourseIds) as $index => $courseId) {
                CourseLearningPath::query()
                    ->where('learning_path_id', $path->id)
                    ->where('course_id', $courseId)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }

    /** أسماء مسارات كلّ تدريب — لعمود «المسار(ات)» في جدول التدريبات. */
    public function pathNamesFor(Collection $courseIds): Collection
    {
        return DB::table('course_learning_path as pivot')
            ->join('learning_paths as p', 'p.id', '=', 'pivot.learning_path_id')
            ->whereIn('pivot.course_id', $courseIds->isEmpty() ? [0] : $courseIds->all())
            ->whereNull('p.deleted_at')
            ->select('pivot.course_id', 'p.name_ar')
            ->get()
            ->groupBy('course_id')
            ->map(fn ($rows) => $rows->pluck('name_ar')->all());
    }

    /**
     * امتحان شهادة المسار مدفوع بالكوينز (12.4-أ) — يُحفَظ سعره في جدول الامتحانات
     * كي يقرأه المتدرّب من نفس المصدر الذي يقرأ منه امتحان التدريب.
     */
    private function syncPathExam(LearningPath $path): void
    {
        $exam = Exam::query()
            ->where('examable_type', $path->getMorphClass())
            ->where('examable_id', $path->id)
            ->first();

        $payload = ['price_coins' => $path->exam_price_coins];

        if ($exam) {
            $exam->update($payload);

            return;
        }

        Exam::create($payload + [
            'examable_type' => $path->getMorphClass(),
            'examable_id' => $path->id,
            'title_ar' => 'امتحان شهادة '.$path->name_ar,
            'pass_score' => (int) setting('exams.pass_score.default', 70),
            'questions_count' => (int) setting('exams.questions.default_count', 20),
            'duration_minutes' => (int) setting('exams.duration.default_minutes', 30),
        ]);
    }

    private function nextSortOrder(): int
    {
        return ((int) LearningPath::query()->max('sort_order')) + 1;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'path';
        $slug = $base;
        $i = 1;

        while (LearningPath::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
