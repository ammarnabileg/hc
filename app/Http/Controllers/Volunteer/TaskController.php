<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Arbitration;
use App\Models\Membership;
use App\Models\Task;
use App\Models\TaskBlock;
use App\Models\TaskContribution;
use App\Models\TaskSubmission;
use App\Models\TaskTodo;
use App\Models\TaskType;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\Volunteer\Contributions\ContributionService;
use App\Services\Volunteer\Org\AbsenceService;
use App\Services\Volunteer\Tasks\ActivityWindow;
use App\Services\Volunteer\Tasks\SubtaskBatch;
use App\Services\Volunteer\Tasks\TaskBlockService;
use App\Services\Volunteer\Tasks\TaskBoard;
use App\Services\Volunteer\Tasks\TaskLoadCap;
use App\Services\Volunteer\Tasks\TaskStatus;
use App\Services\Volunteer\Tasks\TaskWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * المهام (الدستور 24.4-2 · 23-3): «مهامّي» وصفحة المهمّة بتاباتها وأفعالها.
 *
 * قواعد حاكمة هنا:
 *  - سقف الانشغال يمنع الإنشاء والسحب — ويُشرَح لحظة كسره لا كتحذير ثابت (2.15-د).
 *  - قيد الديدلاين على دفعة الصب-تاسكات يُرفَض بوضوح (23-3.9-2).
 *  - الساعة تقف لحظة التسليم، والتقييم على وقت التسليم (23-3.7).
 */
class TaskController extends Controller
{
    public function __construct(
        private readonly TaskBoard $board,
        private readonly TaskLoadCap $cap,
        private readonly TaskWorkflow $workflow,
        private readonly TaskBlockService $blocks,
        private readonly SubtaskBatch $batch,
        private readonly ActivityWindow $window,
        private readonly ContributionService $contributions,
    ) {}

    /** مهامّي: عدّاد سقف الانشغال + [مهمّة جديدة] + تبديل (قائمة/كانبان) */
    public function index(Request $request)
    {
        $user = $request->user();
        $membership = $user->activeMembership();

        $filters = [
            'status' => $request->string('status')->toString() ?: null,
            'type' => $request->integer('type') ?: null,
            'work_item' => $request->integer('work_item') ?: null,
            'q' => $request->string('q')->toString() ?: null,
            'due_soon' => $request->boolean('due_soon') ?: null,
        ];

        $tasks = $this->board->mine($user, $membership, $filters)->get();
        $summary = $this->cap->summary($user, $membership);

        return view('volunteer.tasks.index', [
            'user' => $user,
            'membership' => $membership,
            'tasks' => $tasks,
            'counts' => $this->board->statusCounts($user, $membership),
            'summary' => $summary,
            // زرّ «مهمّة جديدة» لمن له فريق — ويُعطَّل عند بلوغ السقف برسالة تشرح
            'canCreate' => $this->hasTeam($user, $membership) && $user->allows('tasks.create'),
            'createBlockedReason' => $summary['reason'],
            'view' => $request->string('view')->toString() ?: 'list',
            'filters' => $filters,
            'types' => TaskType::query()->where('is_active', true)->get(),
            'workItems' => $this->workItemsFor($membership),
            'columns' => TaskStatus::boardColumns(),
        ]);
    }

    /** إنشاء مهمّة جديدة أوّل السلسلة — بربط إلزاميّ ببندٍ وبسقف انشغال محترَم */
    public function store(Request $request)
    {
        $user = $request->user();
        $membership = $user->activeMembership();

        if (! $this->hasTeam($user, $membership)) {
            throw ValidationException::withMessages([
                'title' => (string) setting('workflow.tasks.store_msg', 'إنشاء المهامّ لمن له فريق — تقدر تعمل صب-تاسك على مهمّتك أو تدعو مساهمًا.'),
            ]);
        }

        if ($reason = $this->cap->blockReason($user, $membership)) {
            throw ValidationException::withMessages(['title' => $reason]);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'task_type_id' => ['nullable', 'integer', 'exists:task_types,id'],
            'brief' => ['nullable', 'string'],
            'deliverable_spec' => ['required', 'string'],
            'deadline_at' => ['required', 'date'],
            'vxp_value' => ['nullable', 'numeric', 'min:0'],
            'work_item_id' => ['required', 'integer', 'exists:work_items,id'],
            'blocked_by_task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ], [], [
            'deliverable_spec' => (string) setting('workflow.tasks.store_msg_2', 'شكل المخرجات'),
            'work_item_id' => (string) setting('workflow.tasks.store_msg_3', 'البند التابع للمشروع'),
        ]);

        // «لا تُسنَد إليه مهامّ جديدة» طول غيابه المعذور (23-6)
        $target = ! empty($data['owner_id']) ? User::query()->find($data['owner_id']) : null;

        if ($target && app(AbsenceService::class)->isAbsent($target, $membership?->entity_id)) {
            $delegate = app(AbsenceService::class)->delegateFor($target, $membership?->entity_id);

            throw ValidationException::withMessages([
                'owner_id' => strtr((string) setting('workflow.tasks.store_msg_4', ':a1 في وضع «غائب» دلوقتي:a2. اختار حدًّا تاني.'), [
                    ':a1' => (string) $target->shortName(),
                    ':a2' => $delegate
                        ? strtr((string) setting('workflow.tasks.store_delegate', ' — البديل: :name'), [':name' => (string) $delegate->shortName()])
                        : '',
                ]),
            ]);
        }

        $task = Task::create([
            'title' => $data['title'],
            'task_type_id' => $data['task_type_id'] ?? null,
            'brief' => $data['brief'] ?? null,
            'deliverable_spec' => $data['deliverable_spec'],
            'deadline_at' => Carbon::parse($data['deadline_at']),
            'vxp_value' => $data['vxp_value'] ?? 0,
            'work_item_id' => $data['work_item_id'],
            'blocked_by_task_id' => $data['blocked_by_task_id'] ?? null,
            'entity_id' => $membership?->entity_id,
            'owner_id' => $data['owner_id'] ?? $user->id,
            'reviewer_id' => $user->id,
            'created_by' => $user->id,
            'status' => TaskStatus::IN_PROGRESS,
            'source' => 'assigned',
        ]);

        return redirect()
            ->route('volunteer.tasks.show', $task)
            ->with('status', (string) setting('workflow.tasks.store_ok', 'اتحفظت ✓ — المهمّة اتسجّلت وعدّادها شغّال.'));
    }

    /** صفحة المهمّة بتاباتها السبعة (24.4-2) */
    public function show(Request $request, Task $task)
    {
        $user = $request->user();

        abort_unless($this->board->canSee($user, $task), 403, (string) setting('workflow.tasks.show_msg', 'المهمّة دي خارج نطاقك.'));

        $task->load(['entity', 'task_type', 'work_item', 'owner', 'reviewer', 'parent_task', 'blocked_by_task']);

        $subtasks = Task::query()->where('parent_task_id', $task->id)->orderBy('deadline_at')->get();

        return view('volunteer.tasks.show', [
            'user' => $user,
            'task' => $task,
            'tab' => $request->string('tab')->toString() ?: 'details',
            /*
             | التودو **شخصيّ**: «لا يظهر لأحد إلا صاحبه — ويطّلع عليه الأبلاين
             | للقراءة فقط» (23-2.1). فالمالك يرى قائمته هو، والأبلاين يرى قائمة
             | المالك الحاليّ قراءةً — **ولا يرث المالك الجديد قائمة السابق**.
             */
            'todos' => TaskTodo::query()->where('task_id', $task->id)
                ->where('user_id', (int) $task->owner_id === (int) $user->id ? $user->id : $task->owner_id)
                ->orderBy('sort_order')->orderBy('id')->get(),
            'subtasks' => $subtasks,
            'contributions' => TaskContribution::query()->with('contributor')->where('task_id', $task->id)->get(),
            'submissions' => TaskSubmission::query()->with('user')->where('task_id', $task->id)->latest('version')->get(),
            'blocks' => TaskBlock::query()->where('task_id', $task->id)->latest()->get(),
            'arbitrations' => Schema::hasTable('arbitrations')
                ? Arbitration::query()->where('task_id', $task->id)->latest()->get()
                : collect(),
            'isOwner' => (int) $task->owner_id === (int) $user->id,
            'counterState' => $this->workflow->counterState($task),
            'isLate' => $this->workflow->isLate($task),
            'maxBlockDays' => $this->blocks->maxDays(),
            'blocksLeft' => max(0, $this->blocks->maxBlocks() - (int) $task->blocked_count),
            'nextBlockHalves' => $this->blocks->nextBlockHalvesReward($task),
            'rewardMultiplier' => $this->blocks->repRewardMultiplier($task),
            'repOnDelivery' => $this->workflow->deliveryRepValue($task),
            'childDeadlineLimit' => $this->batch->latestAllowedChildDeadline($task),
            'mergeWindowHours' => $this->batch->mergeWindowHours(),
            'canExtend' => (int) $task->extension_count < (int) setting('workflow.extension.max_per_task', 1)
                && $task->deadline_at && ! Carbon::parse($task->deadline_at)->isPast(),
            'window' => $this->window,
        ]);
    }

    /** تسليم — الساعة تقف هنا (23-3.7) */
    public function deliver(Request $request, Task $task)
    {
        $user = $request->user();
        $this->authorizeOwner($user, $task);

        $data = $request->validate([
            'body' => ['nullable', 'string'],
            'link' => ['nullable', 'url'],
            'note' => ['nullable', 'string'],
        ]);

        $this->workflow->deliver($task, $user, $data);

        return back()->with('status', (string) setting('workflow.tasks.deliver_ok', 'تمّ التسليم ✓ — العدّاد وقف دلوقتي، والتقييم على وقت تسليمك.'));
    }

    /** متعثّر: مدّة ≤ الحدّ بسبب إلزاميّ، أو Blocked By (23-3.4) */
    public function block(Request $request, Task $task)
    {
        $user = $request->user();
        $this->authorizeOwner($user, $task);

        $data = $request->validate([
            'type' => ['required', 'in:duration,dependency'],
            'reason' => ['required', 'string', 'min:3'],
            'days' => ['nullable', 'integer', 'min:1'],
            'blocking_task_id' => ['nullable', 'integer', 'exists:tasks,id'],
        ]);

        $blocking = $data['blocking_task_id'] ?? null ? Task::find($data['blocking_task_id']) : null;

        $this->blocks->block($task, $user, $data['type'], $data['reason'], $data['days'] ?? null, $blocking);

        $note = $this->blocks->repRewardMultiplier($task->refresh()) < 1
            ? (string) setting('workflow.tasks.block_msg', ' وتنبيه: دي الإعادة التانية فمكافأة الالتزام على المهمّة دي بقت النصف.')
            : '';

        return back()->with('status', strtr((string) setting('workflow.tasks.block_ok', 'اتسجّل التعثّر ✓ — مراجعك هيشوفه على محرّك التصعيد.:a1'), [':a1' => (string) ($note)]));
    }

    /** طلب تمديد: قبل الديدلاين ومرّة واحدة (23-3.5) */
    public function extension(Request $request, Task $task)
    {
        $user = $request->user();
        $this->authorizeOwner($user, $task);

        $data = $request->validate([
            'new_deadline' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:3'],
        ]);

        $this->workflow->requestExtension($task, $user, $data['new_deadline'], $data['reason']);

        return back()->with('status', (string) setting('workflow.tasks.extension_ok', 'اتبعت طلب التمديد ✓ — القرار عند مراجعك خلال نافذته.'));
    }

    /** اعتذار — يُعرَض على الأبلاين ويمشي على محرّك التصعيد */
    public function apology(Request $request, Task $task)
    {
        $user = $request->user();
        $this->authorizeOwner($user, $task);

        $data = $request->validate(['reason' => ['required', 'string', 'min:3']]);

        $this->workflow->apologize($task, $user, $data['reason']);

        return back()->with('status', (string) setting('workflow.tasks.apology_ok', 'اتسجّل الاعتذار ✓ — مراجعك هيقرّر فيه.'));
    }

    /** رفع علم «متأخّر بسبب [ابن]» (23-3.9-4) */
    public function flag(Request $request, Task $task)
    {
        $user = $request->user();
        $this->authorizeOwner($user, $task);

        $data = $request->validate(['child_task_id' => ['required', 'integer', 'exists:tasks,id']]);

        $this->workflow->flagLateDueToChild($task, Task::findOrFail($data['child_task_id']), $user);

        return back()->with('status', (string) setting('workflow.tasks.flag_ok', 'العلم اترفع ✓ — التأخير منسوب لصاحبه، ونافذتك محميّة.'));
    }

    /** Create Subtask دفعةً — بفحص قيد الديدلاين قبل الحفظ (23-2.3) */
    public function storeSubtasks(Request $request, Task $task)
    {
        $user = $request->user();

        abort_unless($this->board->canSee($user, $task), 403);

        $rows = $request->input('subtasks', []);

        $created = $this->batch->save($task, is_array($rows) ? $rows : [], $user);

        return back()->with('status', strtr((string) setting('workflow.tasks.store_subtasks_ok', 'اتحفظت الدفعة ✓ — :a1 صب-تاسك راحوا لمراجعة أبلاينك.'), [':a1' => (string) ($created->count())]));
    }

    /** التودو: شخصيّ بلا اعتماد وبلا أثر على أيّ درجة (23-2.1) */
    public function storeTodo(Request $request, Task $task)
    {
        $user = $request->user();
        $this->authorizeOwner($user, $task);

        $data = $request->validate(['body' => ['required', 'string', 'max:200']]);

        TaskTodo::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'body' => $data['body'],
            'sort_order' => (int) TaskTodo::where('task_id', $task->id)
                ->where('user_id', $user->id)->max('sort_order') + 1,
        ]);

        return back()->with('status', (string) setting('workflow.tasks.store_todo_ok', 'اتحفظ ✓'));
    }

    public function toggleTodo(Request $request, Task $task, TaskTodo $todo)
    {
        $this->authorizeTodo($request->user(), $task, $todo);

        $todo->forceFill(['is_done' => ! $todo->is_done])->save();

        return back()->with('status', (string) setting('workflow.tasks.toggle_todo_ok', 'اتحفظ ✓'));
    }

    public function destroyTodo(Request $request, Task $task, TaskTodo $todo)
    {
        $this->authorizeTodo($request->user(), $task, $todo);

        $todo->delete();

        return back()->with('status', (string) setting('workflow.tasks.destroy_todo_ok', 'اتشال ✓'));
    }

    // ------------------------------------------------------------------ داخليّ

    /** التودو لصاحبه وحده — والمالك الجديد لا يرث قائمة السابق (23-2.1) */
    private function authorizeTodo(User $user, Task $task, TaskTodo $todo): void
    {
        abort_unless((int) $todo->task_id === (int) $task->id, 404);
        abort_unless((int) $todo->user_id === (int) $user->id, 403, (string) setting('workflow.tasks.authorize_todo_msg', 'التودو ده شخصيّ لصاحبه.'));
    }

    private function authorizeOwner(User $user, Task $task): void
    {
        abort_unless((int) $task->owner_id === (int) $user->id, 403, (string) setting('workflow.tasks.authorize_owner_msg', 'الفعل ده لصاحب المهمّة.'));
    }

    /** هل تحته أفراد؟ — «الإنشاء لمن له فريق» (23-3.1) */
    private function hasTeam(User $user, $membership): bool
    {
        if (! $membership) {
            return false;
        }

        return Membership::query()
            ->where('upline_id', $membership->id)
            ->where('status', 'active')
            ->exists();
    }

    /** بنود الكيان — الربط إلزاميّ لكلّ مهمّة جديدة */
    private function workItemsFor($membership)
    {
        if (! $membership) {
            return collect();
        }

        // طول قائمة الاختيار إعداد لا رقم محروق (2.13)
        return WorkItem::query()->latest('id')->limit((int) setting('workflow.work_items.picker_limit', 50))->get();
    }
}
