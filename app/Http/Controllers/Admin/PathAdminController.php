<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LearningPath;
use App\Services\Admin\Content\PathCourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * المسارات (12.4-أ · 24.1): جدول قابل لسحب الصفوف + فورم في بوب-أب.
 *
 * ⭐ قاعدتان لا تُكسَران: **حذف المسار لا يحذف تدريباته**،
 * و**التدريب يجوز أن يكون في أكثر من مسار**.
 */
class PathAdminController extends Controller
{
    public function __construct(private readonly PathCourseService $paths) {}

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim($request->string('q')->toString()),
            'status' => $request->string('status')->toString(),
        ];

        $paths = $this->paths->list($filters);

        return view('admin.courses.paths', [
            'paths' => $paths,
            'filters' => $filters,
            'statuses' => $this->statuses(),
            // ⭐ السعر من مصدره الواحد (صفّ الامتحان) لا من عمود المرآة (12.4-أ)
            'examPrices' => $this->paths->examPricesFor($paths),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $path = $this->paths->save(null, $this->validated($request));

        return redirect()
            ->route('admin.paths.index')
            ->with('status', strtr((string) setting('paths.admin.store_ok', 'اتحفظ المسار «:a1» ✓'), [':a1' => (string) ($path->name_ar)]));
    }

    public function update(Request $request, LearningPath $path): RedirectResponse
    {
        $this->paths->save($path, $this->validated($request));

        return back()->with('status', (string) setting('paths.admin.update_ok', 'اتحفظ ✓'));
    }

    /** ⭐ تكرار/نسخ (Duplicate) المسار (12.4-هـ) — نسخة مسودّة بتدريباتها منسوخة معها. */
    public function duplicate(LearningPath $path): RedirectResponse
    {
        $this->paths->duplicate($path, auth()->user());

        return redirect()
            ->route('admin.paths.index')
            ->with('status', (string) setting('paths.admin.duplicate_ok', 'اتعمل نسخة من المسار — عدّلها وانشرها ✓'));
    }

    /** الحذف بتأكيد — ولا يمسّ التدريبات (12.4-أ). */
    public function destroy(LearningPath $path): RedirectResponse
    {
        $this->paths->delete($path);

        return redirect()
            ->route('admin.paths.index')
            ->with('status', (string) setting('paths.delete.success_text', 'اتشال المسار — وتدريباته زيّ ما هي ✓'));
    }

    /** سحب الصفوف لترتيب ظهور المسارات — وفشل الترتيب يسترجع السابق في الواجهة. */
    public function reorder(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);

        $this->paths->reorder($data['ids']);

        return $this->respond($request, (string) setting('paths.admin.reorder_ok', 'اتظبط الترتيب ✓'));
    }

    /** شاشة «إدارة تدريبات المسار»: بحث/سحب-ترتيب/إضافة/حذف (12.4-أ). */
    public function courses(Request $request, LearningPath $path): View
    {
        $search = trim($request->string('q')->toString());

        return view('admin.courses.path-courses', [
            'path' => $path,
            'courses' => $this->paths->coursesOf($path, $search),
            'attachable' => $this->paths->attachableCourses($path, $search),
            'search' => $search,
        ]);
    }

    public function attach(Request $request, LearningPath $path): RedirectResponse
    {
        $data = $request->validate([
            'course_ids' => ['required', 'array'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
        ]);

        $added = $this->paths->attach($path, $data['course_ids']);

        return back()->with('status', $added > 0 ? strtr((string) setting('paths.admin.attach_ok', 'اتضاف :a1 تدريب للمسار ✓'), [':a1' => (string) ($added)]) : (string) setting('paths.admin.attach_msg', 'التدريبات دي موجودة في المسار خلاص.'));
    }

    /** ⭐ الإزالة من المسار فكّ ارتباط لا حذف — التدريب يبقى قائمًا. */
    public function detach(LearningPath $path, Course $course): RedirectResponse
    {
        $this->paths->detach($path, $course);

        return back()->with('status', (string) setting('paths.detach.success_text', 'اتشال من المسار — والتدريب زيّ ما هو ✓'));
    }

    public function reorderCourses(Request $request, LearningPath $path): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);

        $this->paths->reorderCourses($path, $data['ids']);

        return $this->respond($request, (string) setting('paths.admin.reorder_courses_ok', 'اتظبط الترتيب ✓'));
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['nullable', 'string', 'max:190'],
            'description_ar' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'cover_path' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_keys($this->statuses()))],
            'forced_order' => ['nullable', 'boolean'],
            // سعر امتحان شهادة المسار بالكوينز (12.4-أ)
            'exam_price_coins' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    /** @return array<string, string> */
    private function statuses(): array
    {
        $statuses = setting('paths.statuses', [
            'draft' => 'مسودّة', 'scheduled' => 'مجدول', 'published' => 'منشور', 'archived' => 'مؤرشف',
        ]);

        return is_array($statuses) ? $statuses : [];
    }

    private function respond(Request $request, string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('status', $message);
    }
}
