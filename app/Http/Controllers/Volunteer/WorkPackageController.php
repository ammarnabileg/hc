<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\WorkItem;
use App\Models\WorkPackage;
use App\Services\Volunteer\Goals\BuildAccess;
use App\Services\Volunteer\Goals\EntityScope;
use App\Services\Volunteer\Goals\GoalBuildService;
use App\Services\Volunteer\Goals\RollupService;
use App\Services\Volunteer\Goals\VxpDistributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * حزم العمل وبنودها (الدستور 24.4 · 23 — 1.3 · 3.9).
 *
 * سؤال الشاشة الواحد: «إيه بنود حزمتي وفين وصلت؟»
 * والجدول 6 أعمدة: البند · عدد مهامّه · وعاء VXP والمنصرف · النسبة · أقرب ديدلاين (2.15-أ-5).
 */
class WorkPackageController extends Controller
{
    public function __construct(
        private readonly RollupService $rollup,
        private readonly VxpDistributionService $vxp,
        private readonly EntityScope $scope,
        private readonly BuildAccess $access,
        private readonly GoalBuildService $build,
    ) {}

    // ============================================================================
    //  ⭐ 1.3 — ملء الحزم (الدايركتور · نطاق ENTITY)
    // ============================================================================

    /**
     * «يفتح فيرى الهدف والمَعلَم و**حزم العمل الخاصّة به**» (23 — 1.3).
     *
     * والحصر هنا حارسٌ لا ترتيبُ عرض: `packagesFor` تُرجِع حزم الكيانات التي
     * يقودها هو وحده، فحزمة كيانٍ آخر داخل المَعلَم نفسه لا تصل الشاشة أصلًا.
     * ومَن ليس في الطبقات الثلاث — كوردنيتور أو تيم ليدر داخل الكيان — يُردّ
     * بـ403 ولا يعرف حتى اسم الهدف: «لا يرى أحد من الداونلاينز شيئًا».
     */
    public function fill(Request $request, Goal $goal): View
    {
        $user = $request->user();

        abort_unless($this->access->isBuilding($goal), 409, (string) setting('workflow.packages.fill_msg', 'الهدف اتبعت للتنفيذ خلاص.'));
        abort_unless($this->access->canSeeBuild($user, $goal), 403);

        $packages = $this->access->packagesFor($user, $goal);

        abort_if($packages->isEmpty(), 403);

        $tasks = Task::query()
            ->whereIn('work_item_id', WorkItem::query()->whereIn('work_package_id', $packages->pluck('id'))->select('id'))
            ->with('work_item')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Task $task) => (int) ($task->work_item?->work_package_id ?? 0));

        return view('volunteer.goals.build.fill', [
            'goal' => $goal,
            'packages' => $packages,
            'tasks' => $tasks,
            'icons' => $packages->mapWithKeys(fn (WorkPackage $p) => [$p->id => $this->access->entityIcon($p->entity)])->all(),
            'canWrite' => $user->allows('wp_items.create'),
        ]);
    }

    /** مهمّة «لنفسه» داخل حزمة كيانه — **بلا حدّ أقصى** (23 — 1.3) */
    public function storeTask(Request $request, WorkPackage $workPackage): RedirectResponse
    {
        $goal = $this->buildGoalOf($workPackage);

        abort_unless($this->access->isBuilding($goal), 409, (string) setting('workflow.packages.store_task_msg', 'الهدف اتبعت للتنفيذ خلاص.'));
        abort_unless($this->access->isDirectorOf($request->user(), (int) $workPackage->entity_id), 403);
        abort_if($workPackage->build_status === 'submitted', 409, (string) setting('workflow.packages.store_task_msg_2', 'الحزمة دي اترفعت للمراجعة — مبقاش عندك تعديل عليها.'));

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'brief' => ['nullable', 'string', 'max:2000'],
            'deliverable_spec' => ['required', 'string', 'max:2000'],
            'deadline_at' => ['nullable', 'date'],
        ], [], [
            'title' => (string) setting('workflow.packages.store_task_msg_3', 'اسم المهمّة'),
            'deliverable_spec' => (string) setting('workflow.packages.store_task_msg_4', 'شكل المخرجات'),
        ]);

        $this->build->addDirectorTask($workPackage, $data, $request->user());

        return back()->with('status', (string) setting('workflow.packages.store_task_ok', 'اتضافت المهمّة ✓ — زوّد اللي إنت عايزه، مافيش حدّ أقصى.'));
    }

    /** «رفع للمراجعة» ⟵ يجمعها مشرف المسار (23 — 1.4) */
    public function submitForReview(Request $request, WorkPackage $workPackage): RedirectResponse
    {
        $goal = $this->buildGoalOf($workPackage);

        abort_unless($this->access->isBuilding($goal), 409, (string) setting('workflow.packages.submit_for_review_msg', 'الهدف اتبعت للتنفيذ خلاص.'));
        abort_unless($this->access->isDirectorOf($request->user(), (int) $workPackage->entity_id), 403);

        $hasTasks = Task::query()
            ->whereIn('work_item_id', WorkItem::query()->where('work_package_id', $workPackage->id)->select('id'))
            ->exists();

        abort_unless($hasTasks, 422, (string) setting('workflow.packages.submit_for_review_msg_2', 'الحزمة لسّه فاضية — ضيف مهمّة واحدة على الأقلّ قبل الرفع.'));

        $this->build->submitPackage($workPackage, $request->user());

        return back()->with('status', (string) setting('workflow.packages.submit_for_review_ok', 'اترفعت للمراجعة ✓ — مشرف مسارك هيجمّعها ويسعّرها.'));
    }

    private function buildGoalOf(WorkPackage $package): Goal
    {
        $goalId = Milestone::query()->whereKey($package->milestone_id)->value('goal_id');

        return Goal::query()->findOrFail($goalId);
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $entityIds = $this->scope->visibleEntityIds($user, 'work_packages.view');

        $filters = [
            'entity' => $request->integer('entity') ?: null,
            'q' => trim($request->string('q')->toString()),
        ];

        $packages = WorkPackage::query()
            ->whereNotNull('milestone_id')
            ->when($entityIds !== null, fn ($q) => $q->whereIn('entity_id', $entityIds ?: [0]))
            ->when($filters['entity'], fn ($q, $entity) => $q->where('entity_id', $entity))
            ->when($filters['q'] !== '', fn ($q) => $q->where('name', 'like', '%'.$filters['q'].'%'))
            ->with('entity', 'milestone')
            ->orderByDesc('id')
            ->get();

        return view('volunteer.goals.packages', [
            'packages' => $packages,
            'counts' => $packages->mapWithKeys(fn (WorkPackage $p) => [$p->id => $this->rollup->packageTaskCounts($p)]),
            'filters' => $filters,
            'memberships' => $this->scope->memberships($user),
        ]);
    }

    public function show(Request $request, WorkPackage $workPackage): View
    {
        $this->authorizeEntity($request, $workPackage);

        $filters = [
            'status' => $request->string('status')->toString(),
            'q' => trim($request->string('q')->toString()),
        ];

        $items = WorkItem::query()
            ->where('work_package_id', $workPackage->id)
            ->when($filters['q'] !== '', fn ($q) => $q->where('name', 'like', '%'.$filters['q'].'%'))
            ->orderBy('id')
            ->get();

        $counts = $this->rollup->countsForPackage($workPackage);

        $tasks = Task::query()
            ->whereIn('work_item_id', $items->pluck('id'))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->with('owner')
            ->orderBy('deadline_at')
            ->get()
            ->groupBy('work_item_id');

        $rows = $items->map(function (WorkItem $item) use ($counts, $tasks) {
            $deadline = $this->rollup->nearestDeadline($item);

            return [
                'item' => $item,
                'counts' => $counts->get($item->id, ['active' => 0, 'done' => 0, 'closed' => 0, 'denominator' => 0]),
                'percent' => (float) $item->progress_percent,
                'deadline' => $deadline,
                'deadline_state' => $this->rollup->deadlineState($deadline),
                'tasks' => $tasks->get($item->id, collect()),
            ];
        });

        // مهامّ الأب القابلة للتوزيع: التي لها أبناء أو يملكها المستخدم
        $distributable = $tasks->flatten()
            ->filter(fn (Task $task) => $task->owner_id === $request->user()->id || Task::query()->where('parent_task_id', $task->id)->exists())
            ->values();

        return view('volunteer.goals.package-show', [
            'package' => $workPackage->load('entity', 'milestone'),
            'rows' => $rows,
            'filters' => $filters,
            'statuses' => $this->statusLabels(),
            'distributable' => $distributable->map(fn (Task $task) => [
                'task' => $task,
                'children' => $this->vxp->children($task),
                'summary' => $this->vxp->summary($task),
            ]),
            'minShare' => $this->vxp->parentMinSharePercent(),
            'versionDiff' => $this->versionDiff($workPackage),
            'canObject' => $this->objectionOpen($workPackage) && $request->user()->allows('wp_items.edit'),
        ]);
    }

    /**
     * بوب-أب توزيع VXP — القيدان يُفحَصان عند الحفظ ويُرفَض التجاوز
     * إلّا بموافقة صريحة على الخصم من الرصيد الشخصيّ (23 — 3.9-٥).
     */
    public function distributeVxp(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate([
            'shares' => ['required', 'array'],
            'shares.*' => ['numeric', 'min:0'],
            'consent_personal' => ['nullable', 'boolean'],
        ]);

        abort_unless($task->owner_id === $request->user()->id || $request->user()->allows('tasks.assign', $task), 403);

        try {
            $result = $this->vxp->distribute(
                parent: $task,
                shares: $data['shares'],
                consentPersonal: (bool) ($data['consent_personal'] ?? false),
                payer: $request->user(),
            );
        } catch (ValidationException $e) {
            // القيود تُشرَح لحظة كسرها فقط، والمُدخَل يبقى كما هو (2.15-د · 2.17-ب)
            return back()->withInput()->withErrors($e->errors());
        }

        $message = $result['overflow'] > 0
            ? strtr((string) setting('workflow.packages.distribute_vxp_ok', 'اتحفظ ✓ — واتخصم :a1 من رصيدك الشخصيّ بموافقتك.'), [':a1' => (string) ($result['overflow'])])
            : (string) setting('workflow.packages.distribute_vxp_ok_2', 'اتحفظ ✓');

        return back()->with('status', $message);
    }

    /**
     * الاعتراض على نسخة الاعتماد خلال 24 ساعة — والسكوت قبول (23 — 1.6).
     */
    public function object(Request $request, WorkPackage $workPackage): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $this->authorizeEntity($request, $workPackage);

        abort_unless($this->objectionOpen($workPackage), 409, (string) setting('workflow.packages.object_msg', 'انتهت مهلة الاعتراض — والسكوت قبول.'));

        $workPackage->forceFill([
            'objection_status' => 'raised',
            'objection_note' => $data['note'],
            'objection_at' => now(),
        ])->save();

        return back()->with('status', (string) setting('workflow.packages.object_ok', 'اترفع اعتراضك ✓ — هيتصعّد للطبقة الأعلى.'));
    }

    // ------------------------------------------------------------------ داخليّ

    private function authorizeEntity(Request $request, WorkPackage $package): void
    {
        $entityIds = $this->scope->visibleEntityIds($request->user(), 'work_packages.view');

        if ($entityIds === null) {
            return;
        }

        abort_unless(in_array((int) $package->entity_id, $entityIds, true), 403);
    }

    /** نافذة الاعتراض مفتوحة ما دامت لم تفت ولم يُرفَع اعتراض بعد */
    private function objectionOpen(WorkPackage $package): bool
    {
        if ($package->objection_status === 'raised') {
            return false;
        }

        if (! $package->objection_due_at) {
            return false;
        }

        return Carbon::parse($package->objection_due_at)->isFuture();
    }

    /**
     * فرق النسخة: ما رُفِع ↔ ما اعتُمد — يُعرَض للدايركتور جنب زرّ الاعتراض.
     *
     * @return array<int,array{field:string,submitted:mixed,approved:mixed}>
     */
    private function versionDiff(WorkPackage $package): array
    {
        $submitted = $this->decode($package->getAttribute('submitted_snapshot'));
        $approved = $this->decode($package->getAttribute('approved_snapshot'));

        if ($submitted === [] && $approved === []) {
            return [];
        }

        $diff = [];

        foreach (array_unique(array_merge(array_keys($submitted), array_keys($approved))) as $field) {
            $before = $submitted[$field] ?? null;
            $after = $approved[$field] ?? null;

            if ($before !== $after) {
                $diff[] = ['field' => $field, 'submitted' => $before, 'approved' => $after];
            }
        }

        return $diff;
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return is_string($value) && $value !== '' ? (json_decode($value, true) ?: []) : [];
    }

    private function statusLabels(): array
    {
        return [
            'in_progress' => (string) setting('workflow.packages.status_labels_msg', 'قيد التنفيذ'),
            'blocked' => (string) setting('workflow.packages.status_labels_msg_2', 'متعثّرة'),
            'in_review' => (string) setting('workflow.packages.status_labels_msg_3', 'قيد المراجعة'),
            'approved' => (string) setting('workflow.packages.status_labels_msg_4', 'معتمدة'),
            'returned' => (string) setting('workflow.packages.status_labels_msg_5', 'مُرجَعة'),
            'no_delivery' => (string) setting('workflow.packages.status_labels_msg_6', 'عدم تسليم'),
            'closed' => (string) setting('workflow.packages.status_labels_msg_7', 'مُغلَقة'),
        ];
    }
}
