<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLearningPath;
use App\Models\LearningPath;
use App\Services\Admin\Content\ContentAudit;
use App\Services\Admin\Content\CourseFormService;
use App\Services\Admin\Content\LessonBuilder;
use App\Services\Admin\Content\MediaLibrary;
use App\Services\Admin\Content\PathCourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * التدريبات (12.4-ب · 24.1): جدول بفلاتر + فورم متعدّد التابات.
 *
 * الحفظ بزرّين — **«حفظ واستمرار»** (درافت ويكمّل) و**«حفظ»** (ويخرج) — وفوقهما
 * **حفظ تلقائيّ** كي لا يضيع عمل الأدمن مهما حصل (12.4-ب · 2.17-ب).
 */
class CourseAdminController extends Controller
{
    public function __construct(
        private readonly CourseFormService $courses,
        private readonly PathCourseService $paths,
        private readonly LessonBuilder $lessons,
        private readonly ContentAudit $audit,
    ) {}

    public function index(Request $request): View
    {
        // ثلاثة فلاتر ظاهرة + بحث، والباقي مطويّ (2.15-أ-4)
        $filters = [
            'q' => trim($request->string('q')->toString()),
            'path' => $request->integer('path'),
            'status' => $request->string('status')->toString(),
            'pricing' => $request->string('pricing')->toString(),
        ];

        $courses = $this->courses->search($filters);
        $counts = $this->courses->countsFor($courses->getCollection()->pluck('id'));

        return view('admin.courses.index', [
            'courses' => $courses,
            'counts' => $counts,
            'pathNames' => $this->paths->pathNamesFor($courses->getCollection()->pluck('id')),
            'paths' => LearningPath::query()->orderBy('sort_order')->get(),
            'filters' => $filters,
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Course(['status' => 'draft']));
    }

    public function edit(Course $course): View
    {
        return $this->form($course);
    }

    public function store(Request $request): RedirectResponse
    {
        $course = $this->courses->save(null, $this->validated($request), $request->boolean('continue'));

        // «حفظ واستمرار» يبقيك في التحرير · «حفظ» يخرج (12.4-ب)
        return $request->boolean('continue')
            ? redirect()->route('admin.courses.edit', $course)->with('status', 'اتحفظ كمسودّة ✓')
            : redirect()->route('admin.courses.index')->with('status', 'اتحفظ التدريب ✓');
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $this->courses->save($course, $this->validated($request), $request->boolean('continue'));

        return $request->boolean('continue')
            ? back()->with('status', 'اتحفظ كمسودّة ✓')
            : redirect()->route('admin.courses.index')->with('status', 'اتحفظ التدريب ✓');
    }

    /** الحفظ التلقائيّ — يردّ «اتحفظ ✓» فورًا ولا يغيّر حالة النشر أبدًا. */
    public function autosave(Request $request, Course $course): JsonResponse
    {
        $this->courses->autosave($course, $request->all());

        return response()->json([
            'message' => (string) setting('courses.autosave.label', 'اتحفظ ✓'),
            'at' => now()->format('H:i'),
        ]);
    }

    public function destroy(Course $course): RedirectResponse
    {
        $this->audit->record($course, 'course.deleted', ['name_ar' => $course->name_ar], []);
        $course->delete();

        return redirect()->route('admin.courses.index')->with('status', 'اتشال التدريب ✓');
    }

    public function duplicate(Course $course): RedirectResponse
    {
        $copy = $this->courses->duplicate($course);

        return redirect()->route('admin.courses.edit', $copy)->with('status', 'اتعمل نسخة — عدّلها وانشرها ✓');
    }

    /** إجراءات جماعيّة — الزرّ يظهر عند الاختيار فقط (2.15-ب). */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'in:publish,hide,archive,move_path,price'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'path_id' => ['nullable', 'integer', 'exists:learning_paths,id'],
            'price_coins' => ['nullable', 'numeric', 'min:0'],
        ]);

        $affected = $this->courses->bulk($data['action'], $data['ids'], $data);

        return back()->with('status', 'اتنفّذ على '.$affected.' تدريب ✓');
    }

    /** الضغط على «عدد المسجّلين» ⟵ مَن هم (12.4-ب). */
    public function enrollees(Request $request, Course $course): View
    {
        return view('admin.courses.enrollees', [
            'course' => $course,
            'enrollments' => $this->courses->enrollees($course, $request->user()),
        ]);
    }

    public function stats(Course $course): View
    {
        return view('admin.courses.stats', [
            'course' => $course,
            'stats' => $this->courses->stats($course),
            'indicator' => $this->courses->generalQuestionsIndicator($course),
        ]);
    }

    /** معاينة كطالب قبل النشر (12.4-هـ). */
    public function preview(Course $course): View
    {
        return view('admin.courses.preview', [
            'course' => $course,
            'sections' => $this->lessons->tree($course),
        ]);
    }

    /** سجلّ التدقيق: مَن عدّل ماذا ومتى (12.4-هـ). */
    public function audit(Course $course): View
    {
        return view('admin.courses.audit', [
            'course' => $course,
            'entries' => $this->audit->forSubject($course),
        ]);
    }

    // ------------------------------------------------------------------ داخليّ

    private function form(Course $course): View
    {
        $media = app(MediaLibrary::class);

        return view('admin.courses.form', [
            'course' => $course,
            'paths' => LearningPath::query()->orderBy('sort_order')->get(),
            'selectedPaths' => $course->exists
                ? CourseLearningPath::query()->where('course_id', $course->id)->pluck('learning_path_id')->all()
                : [],
            'sections' => $course->exists ? $this->lessons->tree($course) : collect(),
            'exam' => $course->exists ? $this->courses->exam($course) : null,
            'availability' => $course->exists ? $this->courses->availabilityOf($course) : [],
            'indicator' => $course->exists ? $this->courses->generalQuestionsIndicator($course) : null,
            'statuses' => $this->statuses(),
            'mediaItems' => $media->search(['kind' => 'image'])->take((int) setting('media.picker.limit', 12)),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            // تاب البيانات
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['nullable', 'string', 'max:190'],
            'cert_name_ar' => ['nullable', 'string', 'max:190'],
            'cert_name_en' => ['nullable', 'string', 'max:190'],
            'description_ar' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'cover_path' => ['nullable', 'string', 'max:255'],
            'path_ids' => ['nullable', 'array'],
            'path_ids.*' => ['integer', 'exists:learning_paths,id'],

            // تاب التسعير
            'is_free' => ['nullable', 'boolean'],
            'price_coins' => ['nullable', 'numeric', 'min:0'],
            'offer_price_coins' => ['nullable', 'numeric', 'min:0'],
            'offer_ends_at' => ['nullable', 'date'],
            'free_first_time' => ['nullable', 'boolean'],
            'paywall_text_ar' => ['nullable', 'string'],
            'paywall_text_en' => ['nullable', 'string'],

            // تاب الإتاحة
            'availability' => ['nullable', 'array'],
            'deadline_days' => ['nullable', 'integer', 'min:0'],

            // تاب التقييم
            'max_lesson_xp' => ['nullable', 'integer', 'min:0'],
            'exam_pass_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'exam_questions_count' => ['nullable', 'integer', 'min:0'],
            'forced_order' => ['nullable', 'boolean'],

            // الحالة
            'status' => ['nullable', 'string', 'in:'.implode(',', array_keys($this->statuses()))],
            'scheduled_at' => ['nullable', 'date'],
        ]);
    }

    /** @return array<string, string> */
    private function statuses(): array
    {
        $statuses = setting('courses.statuses', [
            'draft' => 'مسودّة', 'scheduled' => 'مجدول', 'published' => 'منشور', 'archived' => 'مؤرشف',
        ]);

        return is_array($statuses) ? $statuses : [];
    }
}
