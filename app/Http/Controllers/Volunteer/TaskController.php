<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Arbitration;
use App\Models\Task;
use App\Models\TaskBlock;
use App\Models\TaskContribution;
use App\Models\TaskSubmission;
use App\Models\TaskTodo;
use App\Models\TaskType;
use App\Models\User;
use App\Services\Volunteer\Contributions\ContributionService;
use App\Services\Volunteer\Tasks\ActivityWindow;
use App\Services\Volunteer\Tasks\SubtaskBatch;
use App\Services\Volunteer\Tasks\TaskBlockService;
use App\Services\Volunteer\Tasks\TaskBoard;
use App\Services\Volunteer\Tasks\TaskCreation;
use App\Services\Volunteer\Tasks\TaskLoadCap;
use App\Services\Volunteer\Tasks\TaskStatus;
use App\Services\Volunteer\Tasks\TaskWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

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
        private readonly TaskCreation $creation,
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
            'canCreate' => $this->creation->hasTeam($user, $membership) && $user->allows('tasks.create'),
            'createBlockedReason' => $summary['reason'],
            'view' => $request->string('view')->toString() ?: 'list',
            'filters' => $filters,
            'types' => TaskType::query()->where('is_active', true)->get(),
            'workItems' => $this->creation->workItemsFor($membership),
            // فريقه في سلكت بوكس عند الـAssign، وجنب كلّ واحد عدد مهامّه (23-3.1)
            'teamMembers' => $this->creation->teamMembersFor($user, $membership),
            'columns' => TaskStatus::boardColumns(),
        ]);
    }

    /** إنشاء مهمّة جديدة أوّل السلسلة — بربط إلزاميّ ببندٍ وبسقف انشغال محترَم */
    public function store(Request $request)
    {
        $user = $request->user();
        $membership = $user->activeMembership();

        // العقد الموحّد للإنشاء — ويمرّ به كذلك توليد المهمّة من بند المحضر (23-0.3)
        $this->creation->guard($user, $membership);

        $data = $request->validate($this->creation->rules(), [], $this->creation->attributes());

        $this->creation->guardAssignee($data, $membership);

        $task = $this->creation->create($data, $user, $membership);

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
}
