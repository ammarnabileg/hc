<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskType;
use App\Models\WorkItem;
use App\Services\Volunteer\Tasks\LedgerBridge;
use App\Services\Volunteer\Tasks\TaskBoard;
use App\Services\Volunteer\Tasks\TaskLoadCap;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * لوحة المهام العامّة (الدستور 24.4-2 · 23-3.1).
 *
 * ⛔ **لا زرّ إضافة هنا** — إضافة المهامّ العامّة للأدمن ومشرف عام التطوّع حصرًا،
 *    وللقادة زرّ **«رشّح بندًا ليصير عامًّا»** يُرفَع لمشرف عام التطوّع.
 * ⭐ وبلوغ السقف يترك الكروت **مقروءة** وزرّ السحب معطَّلًا بسطر يشرح.
 */
class PublicBoardController extends Controller
{
    public function __construct(
        private readonly TaskBoard $board,
        private readonly TaskLoadCap $cap,
        private readonly LedgerBridge $bridge,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $membership = $user->activeMembership();

        $filters = [
            'type' => $request->integer('type') ?: null,
            'entity' => $request->integer('entity') ?: null,
            'q' => $request->string('q')->toString() ?: null,
            'due_soon' => $request->boolean('due_soon') ?: null,
        ];

        return view('volunteer.tasks.board', [
            'user' => $user,
            'membership' => $membership,
            'tasks' => $this->board->publicBoard($filters)->get(),
            'summary' => $this->cap->summary($user, $membership),
            'filters' => $filters,
            'types' => TaskType::query()->where('is_active', true)->get(),
            // الترشيح للقادة فقط — ومَن لا يملكه لا يراه أصلًا (2.15-أ-7)
            'canNominate' => $user->allows('public_board.create')
                || $user->allows('work_packages.edit')
                || $user->allows('tasks.assign'),
            'nominatable' => $user->allows('work_packages.edit') || $user->allows('tasks.assign')
                ? WorkItem::query()->where('is_public_board_candidate', false)->latest('id')->limit(50)->get()
                : collect(),
        ]);
    }

    /** سحب مهمّة — ممنوع عند بلوغ سقف الانشغال، والرسالة تشرح ما العمل */
    public function claim(Request $request, Task $task)
    {
        $user = $request->user();
        $membership = $user->activeMembership();

        if ($task->source !== 'public_board' || $task->owner_id !== null) {
            throw ValidationException::withMessages([
                'claim' => 'المهمّة دي اتسحبت خلاص — شوف باقي اللوحة.',
            ]);
        }

        if ($reason = $this->cap->blockReason($user, $membership)) {
            throw ValidationException::withMessages(['claim' => $reason]);
        }

        $task->forceFill([
            'owner_id' => $user->id,
            'entity_id' => $task->entity_id ?? $membership?->entity_id,
        ])->save();

        $summary = $this->cap->summary($user, $membership);

        return redirect()
            ->route('volunteer.tasks.show', $task)
            ->with('status', 'المهمّة بقت عليك ✓ — سقف انشغالك دلوقتي '.$summary['display'].'.');
    }

    /** ترشيح بندٍ ليصير عامًّا — يُرفَع لمشرف عام التطوّع ولا يصير عامًّا بنفسه */
    public function nominate(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'work_item_id' => ['required', 'integer', 'exists:work_items,id'],
            'reason' => ['required', 'string', 'min:3'],
            'suggested_vxp' => ['nullable', 'numeric', 'min:0'],
        ]);

        $item = WorkItem::findOrFail($data['work_item_id']);

        $item->forceFill(['is_public_board_candidate' => true])->save();

        $this->bridge->notify(
            $user,
            'public_board_nomination',
            'اترفع ترشيحك: '.$item->name,
            'السبب: '.$data['reason'],
            route('volunteer.tasks.board'),
            $item,
        );

        return back()->with('status', 'اترفع الترشيح ✓ — مشرف عام التطوّع هو اللي يقرّر يخلّيه عامًّا.');
    }
}
