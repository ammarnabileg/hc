<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskContribution;
use App\Services\Volunteer\Contributions\ContributionService;
use App\Services\Volunteer\Contributions\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «بانتظار مراجعتي» (الدستور 24.4 · 23 — 3.6/3.7).
 *
 * تنبيه دائم على الشاشة: **فوات النافذة يرفع الحالة لأبلاينك وعليه أثر التباطؤ** —
 * ومتوسّط زمن مراجعتي مؤشّر عليّ أنا، لأنّ «المراجِع مقيس» بلا استثناء.
 */
class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviews,
        private readonly ContributionService $contributions,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $queue = $this->reviews->queueFor($user);

        // ثلاثة فلاتر ظاهرة: النوع · الشخص · الأقرب لانتهاء النافذة (2.15-أ-4)
        $filters = [
            'kind' => $request->string('kind')->toString(),
            'person' => $request->integer('person'),
            'urgent' => $request->boolean('urgent'),
        ];

        $visible = $queue
            ->when($filters['kind'] !== '', fn ($rows) => $rows->where('kind', $filters['kind']))
            ->when($filters['person'], fn ($rows) => $rows->filter(fn ($row) => (int) ($row['user']->id ?? 0) === $filters['person']))
            ->when($filters['urgent'], fn ($rows) => $rows->whereIn('state', ['warn', 'danger']))
            ->values();

        return view('volunteer.reviews.index', [
            'rows' => $visible,
            'counters' => $this->reviews->counters($queue),
            'averageHours' => $this->reviews->averageReviewHours($user),
            'reasons' => $this->reviews->returnReasons(),
            'fixHours' => (float) setting('workflow.review.fix_hours', 24),
            'windowHours' => $this->reviews->windowHours(),
            'escalateAfter' => $this->reviews->escalateAfterReturns(),
            'kinds' => $this->kinds(),
            'people' => $queue->pluck('user')->filter()->unique('id')->values(),
            'filters' => $filters,
            'accessLevels' => $this->accessLevels(),
            'canLibrary' => $user->allows('internal_library.create'),
        ]);
    }

    /** اعتماد التسليم + سويتش «أضِف المخرج للمكتبة الداخليّة» بمستوى وصول */
    public function approveTask(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate([
            'add_to_library' => ['nullable', 'boolean'],
            'access_level' => ['nullable', 'in:entity,all_volunteers,restricted'],
            'library_type' => ['nullable', 'string', 'max:32'],
        ]);

        // مستوى الوصول يحدّده دايركتور الكيان — فلا إضافة لمن لا يملكها (23 — 3.3)
        if (! empty($data['add_to_library'])) {
            abort_unless($request->user()->allows('internal_library.create', $task), 403, 'إضافة المكتبة لدايركتور الكيان.');
        }

        $this->reviews->approveTask($task, $request->user(), $data);

        return back()->with('status', 'اتعمدت ✓');
    }

    /** إرجاع: فيدباك إجباريّ + سبب من العشرة + مهلة إصلاح مستقلّة */
    public function returnTask(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate([
            'review_feedback' => ['required', 'string'],
            'return_reason_code' => ['required', 'string'],
            'fix_hours' => ['nullable', 'numeric', 'min:1'],
        ]);

        $this->reviews->returnTask($task, $request->user(), $data);

        return back()->with('status', 'اترجّعت بمهلة إصلاح ✓');
    }

    public function approveContribution(Request $request, TaskContribution $contribution): RedirectResponse
    {
        abort_unless((int) $contribution->invited_by === (int) $request->user()->id, 403, 'الاعتماد للمالك وحده.');

        $this->contributions->approve($contribution, $request->user());

        return back()->with('status', 'اتعمد بند المساهمة وصُرِفت نقاطه ✓');
    }

    public function returnContribution(Request $request, TaskContribution $contribution): RedirectResponse
    {
        abort_unless((int) $contribution->invited_by === (int) $request->user()->id, 403, 'الإرجاع للمالك وحده.');

        $data = $request->validate([
            'review_feedback' => ['required', 'string'],
            'return_reason_code' => ['required', 'string'],
            'fix_hours' => ['nullable', 'numeric', 'min:1'],
        ]);

        $this->contributions->returnItem($contribution, $request->user(), $data);

        return back()->with('status', 'اترجّع البند بمهلة إصلاح ✓');
    }

    // -------------------------------------------------------------- شاشة الدفعة

    /** المهمّة الأمّ وصب-تاسكاتها معًا في شاشة واحدة */
    public function batch(Request $request, Task $task): View
    {
        return view('volunteer.reviews.batch', [
            'parent' => $task,
            'items' => $this->reviews->batchItems($task),
        ]);
    }

    public function approveBatch(Request $request, Task $task): RedirectResponse
    {
        Task::query()->where('parent_task_id', $task->id)->update([
            'batch_status' => 'approved',
            'updated_at' => now(),
        ]);

        $task->forceFill(['batch_status' => 'approved'])->save();

        return back()->with('status', 'اتعمدت الدفعة كلّها ✓');
    }

    public function editBatchItem(Request $request, Task $item): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'brief' => ['nullable', 'string'],
            'deadline_at' => ['nullable', 'date'],
            'vxp_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->reviews->editBatchItem($item, $request->user(), $data);

        return back()->with('status', 'اتحفظ ✓ — واتسجّل في سجلّ النسخ');
    }

    public function removeBatchItem(Request $request, Task $item): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string']]);

        $this->reviews->removeBatchItem($item, $request->user(), $data['note'] ?? null);

        return back()->with('status', 'البند رجع مسودّة لصاحبه ✓');
    }

    // -------------------------------------------------------------- داخليّ

    private function kinds(): array
    {
        return [
            'task_submission' => 'تسليم مهمّة',
            'contribution' => 'تسليم مساهم',
            'escalation' => 'حالة على المحرّك',
        ];
    }

    private function accessLevels(): array
    {
        return [
            'entity' => 'كياني فقط',
            'all_volunteers' => 'كلّ المتطوّعين',
            'restricted' => 'مقيَّد',
        ];
    }
}
