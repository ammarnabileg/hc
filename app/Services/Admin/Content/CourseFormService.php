<?php

namespace App\Services\Admin\Content;

use App\Models\Course;
use App\Models\CourseLearningPath;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\LessonQuestion;
use App\Models\Section;
use App\Models\User;
use App\Support\Scope\ScopeFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * فورم التدريب متعدّد التابات (12.4-ب · 24.1): بيانات · تسعير · إتاحة · تقييم · محتوى.
 *
 * الحفظ بزرّين: **«حفظ واستمرار»** يحفظ مسودّةً ويكمّل التحرير، و**«حفظ»** يحفظ ويخرج،
 * وفوقهما **حفظ تلقائيّ كمسودّة** عند كلّ خطوة حتى لا يضيع أيّ عمل مهما حصل (2.17-ب).
 */
class CourseFormService
{
    public function __construct(private readonly ContentAudit $audit) {}

    /** الحالات الأربع المعتمَدة (12.4-هـ) */
    public const STATUSES = ['draft', 'scheduled', 'published', 'archived'];

    /** @param  array<string, mixed>  $filters */
    public function search(array $filters = []): LengthAwarePaginator
    {
        $query = Course::query()->latest('id');

        if (($q = trim((string) ($filters['q'] ?? ''))) !== '') {
            $query->where(fn ($inner) => $inner
                ->where('name_ar', 'like', '%'.$q.'%')
                ->orWhere('name_en', 'like', '%'.$q.'%')
                ->orWhere('cert_name_ar', 'like', '%'.$q.'%'));
        }

        if (($status = (string) ($filters['status'] ?? '')) !== '') {
            $query->where('status', $status);
        }

        if (($pathId = (int) ($filters['path'] ?? 0)) > 0) {
            $query->whereIn('id', CourseLearningPath::query()->where('learning_path_id', $pathId)->pluck('course_id'));
        }

        if (($pricing = (string) ($filters['pricing'] ?? '')) !== '') {
            $pricing === 'free'
                ? $query->where(fn ($i) => $i->where('is_free', true)->orWhere('price_coins', '<=', 0))
                : $query->where('is_free', false)->where('price_coins', '>', 0);
        }

        return $query->paginate((int) setting('courses.table.per_page', 15))->withQueryString();
    }

    /**
     * حفظ التدريب من الفورم متعدّد التابات.
     *
     * @param  array<string, mixed>  $data
     * @param  bool  $continue  «حفظ واستمرار» ⟵ يبقى في التحرير، و«حفظ» ⟵ يخرج
     */
    public function save(?Course $course, array $data, bool $continue = false): Course
    {
        $isNew = $course === null;
        $before = $isNew ? [] : $course->only([
            'name_ar', 'name_en', 'cert_name_ar', 'price_coins', 'status', 'is_free',
        ]);

        /*
         | ⭐ «حفظ واستمرار» **يحفظ درافت ويكمّل التحرير** (12.4-ب) — ومعناه أن يبقى
         | العمل جاريًا، **لا أن يُسحَب تدريبٌ حيٌّ من تحت أقدام المتدرّبين** بضغطة
         | زرٍّ يظنّها المحرّر حفظًا مؤقّتًا. فالجديد يبدأ مسودّةً، أمّا القائم فحالته
         | تُقرأ من الفورم كما اختارها الأدمن صراحةً: المنشور يبقى منشورًا ما لم
         | يختر هو إنزاله. وبلا ذلك يفقد المسجَّلون وصولهم فورًا وبلا سؤال.
         */
        $status = (string) ($data['status'] ?? 'draft');
        $status = in_array($status, self::STATUSES, true) ? $status : 'draft';

        if ($continue && $isNew) {
            $status = 'draft';
        }

        $payload = [
            // ---------------- تاب البيانات
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'] ?? null,
            'cert_name_ar' => $data['cert_name_ar'] ?? null,
            'cert_name_en' => $data['cert_name_en'] ?? null,
            'description_ar' => $data['description_ar'] ?? null,
            'description_en' => $data['description_en'] ?? null,
            'cover_path' => $data['cover_path'] ?? ($course->cover_path ?? null),

            // ---------------- تاب التسعير
            'is_free' => (bool) ($data['is_free'] ?? false),
            'price_coins' => (float) ($data['price_coins'] ?? 0),
            'offer_price_coins' => ($data['offer_price_coins'] ?? null) !== null && $data['offer_price_coins'] !== ''
                ? (float) $data['offer_price_coins']
                : null,
            'offer_ends_at' => $data['offer_ends_at'] ?? null,
            'free_first_time' => (bool) ($data['free_first_time'] ?? false),
            'paywall_text_ar' => $data['paywall_text_ar'] ?? null,
            'paywall_text_en' => $data['paywall_text_en'] ?? null,

            // ---------------- تاب الإتاحة (فترات متعدّدة + أوقات تشغيل يوميّة + ديدلاين)
            'availability' => json_encode($this->cleanAvailability($data['availability'] ?? []), JSON_UNESCAPED_UNICODE),
            'deadline_days' => ($data['deadline_days'] ?? null) !== '' ? (int) ($data['deadline_days'] ?? 0) ?: null : null,

            // ---------------- تاب التقييم
            /*
             | ⭐ «أقصى XP للدرس» مصدرٌ واحد: `xp_max` — وهو ما تقرؤه الحاسبة (7).
             | كان الفورم يكتب في عمودٍ آخر لا يقرؤه أحد، فيعدّل الأدمن رقمًا بلا أثر.
             */
            'xp_max' => (int) ($data['xp_max'] ?? setting('courses.xp.max_per_lesson', 50)),
            /*
             | تذاكر الدرس حسب نصف الديدلاين (7 · 7.1): تذكرتان قبله وواحدة بعده.
             | و`null` هنا معناه «اتبع الإعداد العامّ»، أمّا الصفر فاختيارٌ صريح
             | بلا تذاكر — ولذلك الفراغ يُخزَّن NULL لا صفرًا.
             */
            'tickets_before_half' => $this->ticketOverride($data['tickets_before_half'] ?? null),
            'tickets_after_half' => $this->ticketOverride($data['tickets_after_half'] ?? null),
            'forced_order' => (bool) ($data['forced_order'] ?? true),

            // ---------------- الحالة والجدولة
            'status' => $status,
            'scheduled_at' => $status === 'scheduled' ? ($data['scheduled_at'] ?? null) : null,

            // الحفظ الصريح يسري ⟵ فمسوّدة التحرير الجانبيّة انتهى دورها
            'draft_payload' => null,
            'draft_saved_at' => null,
        ];

        if ($status === 'published' && ! ($course?->published_at)) {
            $payload['published_at'] = now();
        }

        $course = DB::transaction(function () use ($course, $isNew, $payload, $data) {
            if ($isNew) {
                $payload['slug'] = $this->uniqueSlug($payload['name_ar']);
                $payload['created_by'] = auth()->id();
                $course = Course::create($payload);
            } else {
                $course->update($payload);
            }

            // ⭐ التدريب يجوز أن يكون في أكثر من مسار (12.4-أ)
            if (array_key_exists('path_ids', $data)) {
                $this->syncPaths($course, array_map('intval', (array) $data['path_ids']));
            }

            $this->syncExam($course, $data);

            // ⭐ الإتاحة لها مخزنٌ معتمَد واحد (5): جدول الفترات وأعمدة النافذة
            // اليوميّة — وهو ما تقرؤه AvailabilityService. فحفظُ التاب هنا ينعكس
            // عليه فورًا، وإلّا صار للتدريب موعدان: واحد في الشاشة وآخر في المنطق.
            if (array_key_exists('availability', $data)) {
                $this->syncAvailability($course, $payload['availability']);
            }

            return $course;
        });

        $this->audit->record($course, $isNew ? 'course.created' : 'course.updated', $before, $payload);

        return $course->refresh();
    }

    /** الحقول التي يحفظها الحفظ التلقائيّ — الكتابيّة منها وحدها. */
    private const AUTOSAVE_FIELDS = [
        'name_ar', 'name_en', 'cert_name_ar', 'cert_name_en', 'description_ar', 'description_en',
        'price_coins', 'offer_price_coins', 'paywall_text_ar', 'paywall_text_en',
        'deadline_days', 'xp_max',
    ];

    /**
     * ⭐ الحفظ التلقائيّ **كدرافت** (12.4-ب) — ولا يمسّ حالة النشر أبدًا.
     *
     * الدستور يقول «حفظ تلقائيّ **كدرافت** عند كلّ خطوة حتى لا يضيع أيّ عمل»،
     * وكان يكتب مباشرةً على صفّ التدريب: فيغيّر المحرّرُ اسمًا أو سعرًا وهو
     * «يجرّب» فيراه المتدرّبون على الشاشة في نفس اللحظة. فمن هنا:
     *
     *  • **المنشور والمجدول** ⟵ العمل يُحفَظ في **مسوّدة تحرير جانبيّة**، تُعاد
     *    إلى حقول الفورم عند العودة (فلا يضيع شيء) ولا تسري على الناس إلّا
     *    بضغطة «حفظ» الصريحة أو تُلغى بـ«تجاهل المسودّة».
     *  • **المسودّة** ⟵ لا أحد يراها، فالحفظ التلقائيّ يسري عليها مباشرةً.
     *
     * وفي الحالتين: `status` و`published_at` **ليسا من حقول الحفظ التلقائيّ**
     * أصلًا (`AUTOSAVE_FIELDS`)، فلا سبيل لهذا الطريق أن ينشر أو يسحب نشرًا —
     * وهذا هو ضمان «لا يُسحَب تدريبٌ حيّ من تحت أقدام المتدرّبين».
     *
     * @param  array<string, mixed>  $data
     */
    public function autosave(Course $course, array $data): Course
    {
        $payload = collect($data)->only(self::AUTOSAVE_FIELDS)->filter(fn ($v) => $v !== null && $v !== '')->all();

        if ($payload === []) {
            return $course;
        }

        if ($this->isLive($course)) {
            $course->forceFill([
                'draft_payload' => array_merge($this->pendingDraft($course), $payload),
                'draft_saved_at' => now(),
            ])->save();

            return $course;
        }

        $course->update($payload + ['draft_saved_at' => now()]);

        return $course;
    }

    /**
     * مسوّدة التحرير المعلّقة لتدريب حيّ — **تُعاد إلى حقول الفورم** كي لا يضيع
     * العمل، ولا تصير حقيقةً إلّا بحفظٍ صريح.
     *
     * @return array<string, mixed>
     */
    public function pendingDraft(Course $course): array
    {
        $raw = $course->draft_payload;
        $raw = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);

        return collect($raw)->only(self::AUTOSAVE_FIELDS)->all();
    }

    /**
     * «تجاهل المسودّة» — المحرّر يقرّر أنّ تجربته لا تستحقّ النشر.
     *
     * وبدونها كان الطريق الوحيد للتخلّص من مسوّدةٍ معلّقة هو حفظها على الناس،
     * وهذا عكس الغرض منها. والتجاهل **لا يمسّ المنشور ولا حالته** — يمسح
     * الجانبيّة وحدها، ويُسجَّل في التدقيق لأنّه إتلاف عملٍ محفوظ (12.4-هـ).
     */
    public function discardDraft(Course $course): Course
    {
        $before = $this->pendingDraft($course);

        if ($before === []) {
            return $course;
        }

        $course->forceFill(['draft_payload' => null, 'draft_saved_at' => null])->save();

        $this->audit->record($course, 'course.draft.discarded', $before, []);

        return $course->refresh();
    }

    /** المنشور والمجدول يراهما الناس — فمسوّدة التحرير تُفصَل عنهما (12.4-ب). */
    private function isLive(?Course $course): bool
    {
        return $course !== null
            && $course->exists
            && in_array((string) $course->status, ['published', 'scheduled'], true);
    }

    /** تكرار التدريب كقالب جاهز — بسيكشنزه ودروسه وأسئلته (12.4-هـ). */
    public function duplicate(Course $course): Course
    {
        return DB::transaction(function () use ($course) {
            $copy = $course->replicate(['slug', 'published_at', 'created_at', 'updated_at']);
            $suffix = (string) setting('courses.duplicate.suffix', ' — نسخة');
            $copy->name_ar = $course->name_ar.$suffix;
            $copy->slug = $this->uniqueSlug($copy->name_ar);
            $copy->status = 'draft';
            $copy->published_at = null;
            $copy->created_by = auth()->id();
            $copy->save();

            foreach (CourseLearningPath::query()->where('course_id', $course->id)->get() as $pivot) {
                CourseLearningPath::create([
                    'course_id' => $copy->id,
                    'learning_path_id' => $pivot->learning_path_id,
                    'sort_order' => $pivot->sort_order,
                ]);
            }

            foreach (Section::query()->where('course_id', $course->id)->orderBy('sort_order')->get() as $section) {
                $newSection = Section::create([
                    'course_id' => $copy->id,
                    'title_ar' => $section->title_ar,
                    'title_en' => $section->title_en,
                    'sort_order' => $section->sort_order,
                ]);

                foreach (Lesson::query()->where('section_id', $section->id)->orderBy('sort_order')->get() as $lesson) {
                    $newLesson = $lesson->replicate(['created_at', 'updated_at']);
                    $newLesson->section_id = $newSection->id;
                    $newLesson->save();

                    foreach (LessonQuestion::query()->where('lesson_id', $lesson->id)->get() as $question) {
                        $newQuestion = $question->replicate(['created_at', 'updated_at']);
                        $newQuestion->lesson_id = $newLesson->id;
                        $newQuestion->save();
                    }
                }
            }

            $this->audit->record($copy, 'course.duplicated', ['from' => $course->id], []);

            return $copy;
        });
    }

    /**
     * إجراءات جماعيّة على التدريبات (12.4-هـ): نشر · إخفاء · نقل لمسار · تسعير دفعة.
     *
     * @param  array<int, int>  $ids
     * @param  array<string, mixed>  $payload
     */
    public function bulk(string $action, array $ids, array $payload = []): int
    {
        $courses = Course::query()->whereIn('id', $ids)->get();

        foreach ($courses as $course) {
            match ($action) {
                'publish' => $course->update(['status' => 'published', 'published_at' => $course->published_at ?? now()]),
                'hide' => $course->update(['status' => 'draft']),
                'archive' => $course->update(['status' => 'archived']),
                'move_path' => $this->moveToPath($course, (int) ($payload['path_id'] ?? 0)),
                'price' => $course->update([
                    'price_coins' => (float) ($payload['price_coins'] ?? 0),
                    'is_free' => (float) ($payload['price_coins'] ?? 0) <= 0,
                ]),
                default => null,
            };

            $this->audit->record($course, 'course.bulk.'.$action, [], $payload);
        }

        return $courses->count();
    }

    /** صفحة إحصائيّات التدريب: مسجّلون · إكمال · متوسّط التقدّم · الإيراد (12.4-هـ). */
    public function stats(Course $course): array
    {
        $enrollments = Enrollment::query()->where('course_id', $course->id);
        $total = (clone $enrollments)->count();
        $completed = (clone $enrollments)->where('status', 'completed')->count();

        return [
            'enrolled' => $total,
            'completed' => $completed,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100) : 0,
            'avg_progress' => (int) round((float) (clone $enrollments)->avg('progress_percent')),
            'revenue_coins' => round((float) $course->price_coins * $total, 2),
            'sections' => Section::query()->where('course_id', $course->id)->count(),
            'lessons' => $this->lessonsCount($course),
            'general_questions' => $this->generalQuestionsCount($course),
        ];
    }

    /** المسجّلون في التدريب — «الضغط على العدد ⟵ مَن هم» (12.4-ب). */
    /** المسجّلون في تدريب — ومع `$viewer` يُحصَرون بنطاقه (12.2.1-ب) */
    public function enrollees(Course $course, ?User $viewer = null): LengthAwarePaginator
    {
        return Enrollment::query()
            ->when($viewer !== null, fn ($q) => app(ScopeFilter::class)->apply($q, $viewer, 'courses.view'))
            ->with('user')
            ->where('course_id', $course->id)
            ->latest('id')
            ->paginate((int) setting('courses.enrollees.per_page', 20))
            ->withQueryString();
    }

    /** عدد الدروس لكلّ تدريب دفعةً واحدة — لعمود «السيكشنز/الدروس». */
    public function countsFor(Collection $courseIds): array
    {
        $ids = $courseIds->isEmpty() ? [0] : $courseIds->all();

        $sections = Section::query()->whereIn('course_id', $ids)
            ->selectRaw('course_id, count(*) as total')->groupBy('course_id')->pluck('total', 'course_id');

        $lessons = DB::table('lessons')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->whereIn('sections.course_id', $ids)
            ->selectRaw('sections.course_id as course_id, count(*) as total')
            ->groupBy('sections.course_id')
            ->pluck('total', 'course_id');

        $enrollments = Enrollment::query()->whereIn('course_id', $ids)
            ->selectRaw('course_id, count(*) as total')->groupBy('course_id')->pluck('total', 'course_id');

        return ['sections' => $sections, 'lessons' => $lessons, 'enrollments' => $enrollments];
    }

    /**
     * ⭐ مؤشّر الأسئلة العامّة مقابل حدّ الامتحان (12.4-هـ):
     * لو العامّة أقلّ من المطلوب فالامتحان النهائيّ لا يُبنى — والتحذير يظهر مبكرًا.
     */
    public function generalQuestionsIndicator(Course $course): array
    {
        $available = $this->generalQuestionsCount($course);
        $required = (int) ($this->exam($course)?->questions_count ?: setting('exams.questions.default_count', 20));

        return [
            'available' => $available,
            'required' => $required,
            'short' => max(0, $required - $available),
            'state' => $available >= $required ? 'ok' : 'warn',
        ];
    }

    public function exam(Course $course): ?Exam
    {
        return Exam::query()
            ->where('examable_type', $course->getMorphClass())
            ->where('examable_id', $course->id)
            ->first();
    }

    /** @return array<string, mixed> */
    public function availabilityOf(Course $course): array
    {
        $raw = $course->availability;

        return is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
    }

    /**
     * مزامنة تاب الإتاحة مع مخزنه المعتمَد (5): الفترات في جدولها والنافذة
     * اليوميّة في عموديها. والفترات تُستبدَل بالكامل لأنّ الفورم يرسل الصورة
     * النهائيّة لها، فما اختفى منها اختفى بقصد الأدمن.
     */
    private function syncAvailability(Course $course, string $availabilityJson): void
    {
        $raw = json_decode($availabilityJson, true) ?: [];

        DB::table('course_availability_periods')->where('course_id', $course->id)->delete();

        foreach ((array) ($raw['windows'] ?? []) as $window) {
            $from = $window['from'] ?? null;
            $to = $window['to'] ?? null;

            if (! $from || ! $to) {
                continue;
            }

            DB::table('course_availability_periods')->insert([
                'course_id' => $course->id,
                'starts_on' => $from,
                'ends_on' => $to,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $course->forceFill([
            'daily_open_at' => $raw['daily_from'] ?: null,
            'daily_close_at' => ($raw['daily_from'] ?? null) ? ($raw['daily_to'] ?: null) : null,
        ])->save();
    }

    // ------------------------------------------------------------------ داخليّ

    private function moveToPath(Course $course, int $pathId): void
    {
        if ($pathId <= 0) {
            return;
        }

        $exists = CourseLearningPath::query()
            ->where('course_id', $course->id)
            ->where('learning_path_id', $pathId)
            ->exists();

        if (! $exists) {
            CourseLearningPath::create([
                'course_id' => $course->id,
                'learning_path_id' => $pathId,
                'sort_order' => ((int) CourseLearningPath::query()->where('learning_path_id', $pathId)->max('sort_order')) + 1,
            ]);
        }
    }

    /** @param  array<int, int>  $pathIds */
    private function syncPaths(Course $course, array $pathIds): void
    {
        $pathIds = array_values(array_filter(array_unique($pathIds)));

        CourseLearningPath::query()
            ->where('course_id', $course->id)
            ->when($pathIds !== [], fn ($q) => $q->whereNotIn('learning_path_id', $pathIds))
            ->delete();

        foreach ($pathIds as $index => $pathId) {
            CourseLearningPath::firstOrCreate(
                ['course_id' => $course->id, 'learning_path_id' => $pathId],
                ['sort_order' => $index + 1],
            );
        }
    }

    /** @param  array<string, mixed>  $data */
    private function syncExam(Course $course, array $data): void
    {
        if (! array_key_exists('exam_pass_score', $data) && ! array_key_exists('exam_questions_count', $data)) {
            return;
        }

        $payload = [
            'pass_score' => (int) ($data['exam_pass_score'] ?? setting('exams.pass_score.default', 70)),
            'questions_count' => (int) ($data['exam_questions_count'] ?? setting('exams.questions.default_count', 20)),
        ];

        $exam = $this->exam($course);

        if ($exam) {
            $exam->update($payload);

            return;
        }

        Exam::create($payload + [
            'examable_type' => $course->getMorphClass(),
            'examable_id' => $course->id,
            'title_ar' => strtr(setting('admin_content.course_form_service.sync_exam_1', 'امتحان :p1'), [':p1' => (string) ($course->name_ar)]),
            'duration_minutes' => (int) setting('exams.duration.default_minutes', 30),
        ]);
    }

    private function lessonsCount(Course $course): int
    {
        return DB::table('lessons')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->where('sections.course_id', $course->id)
            ->count();
    }

    private function generalQuestionsCount(Course $course): int
    {
        return DB::table('lesson_questions')
            ->join('lessons', 'lessons.id', '=', 'lesson_questions.lesson_id')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->where('sections.course_id', $course->id)
            ->where('lesson_questions.is_general', true)
            ->count();
    }

    /**
     * تجاوُز تذاكر التدريب: الفراغ = «اتبع الإعداد العامّ» (NULL)،
     * والصفر اختيارٌ صريح بلا تذاكر — والفرق بينهما مقصود (7.1).
     */
    private function ticketOverride(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : max(0, (int) $value);
    }

    /**
     * الإتاحة: فترات متعدّدة + أوقات تشغيل يوميّة — تُنظَّف قبل الحفظ.
     *
     * @return array<string, mixed>
     */
    private function cleanAvailability(mixed $raw): array
    {
        $raw = is_string($raw) ? (json_decode($raw, true) ?: []) : (array) $raw;

        $windows = collect($raw['windows'] ?? [])
            ->filter(fn ($w) => ! empty($w['from']) || ! empty($w['to']))
            ->map(fn ($w) => ['from' => $w['from'] ?? null, 'to' => $w['to'] ?? null])
            ->values()
            ->all();

        return [
            'windows' => $windows,
            'daily_from' => $raw['daily_from'] ?? null,
            'daily_to' => $raw['daily_to'] ?? null,
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'course';
        $slug = $base;
        $i = 1;

        while (Course::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
