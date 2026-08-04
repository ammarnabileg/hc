<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Arbitration;
use App\Models\ArbitrationMessage;
use App\Models\Escalation;
use App\Models\Task;
use App\Models\TaskContribution;
use App\Models\User;
use App\Services\Volunteer\Escalation\ArbitrationService;
use App\Services\Volunteer\Escalation\CaseCatalog;
use App\Services\Volunteer\Escalation\EscalationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «التحكيمات» (الدستور 24.4 · 23 — القسم 5).
 *
 * نمط الشاشة: يمين قائمة القضايا بعدّاد ملوّن — يسار ملفّ الحالة.
 * وثلاثة التزامات: **Masking** · **تخطّي تنازع المصالح بسببٍ معروض** ·
 * **قرار نهائيّ لا يُعاد بمبرّر إجباريّ**.
 */
class ArbitrationController extends Controller
{
    public function __construct(
        private readonly ArbitrationService $arbitrations,
        private readonly EscalationEngine $engine,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $filters = [
            'status' => $request->string('status')->toString(),
            'party' => $request->integer('party'),
            'q' => trim($request->string('q')->toString()),
        ];

        $cases = Arbitration::query()
            ->where(fn ($q) => $q->where('arbiter_id', $user->id)->orWhere('opened_by', $user->id))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['q'] !== '', fn ($q) => $q->where('claim', 'like', '%'.$filters['q'].'%'))
            ->orderByRaw("CASE WHEN status = 'decided' THEN 1 ELSE 0 END")
            ->orderBy('window_due_at')
            ->get();

        $selected = $request->integer('case')
            ? $cases->firstWhere('id', $request->integer('case'))
            : $cases->first();

        return view('volunteer.escalations.arbitrations.index', [
            'cases' => $cases,
            'selected' => $selected,
            'file' => $selected ? $this->fileOf($user, $selected) : null,
            'engine' => $this->engine,
            'filters' => $filters,
            'statuses' => ['open' => (string) setting('workflow.arbitration.index_msg', 'مفتوحة'), 'messages_locked' => (string) setting('workflow.arbitration.index_msg_2', 'مقفولة الرسائل'), 'decided' => (string) setting('workflow.arbitration.index_msg_3', 'محسومة')],
            'slowdown' => rep_rule('task.slowdown'),
            'lateMessages' => $cases->filter(fn ($c) => $c->status !== 'decided' && $c->window_due_at?->isPast())->count(),
        ]);
    }

    public function show(Request $request, Arbitration $arbitration): JsonResponse|View
    {
        $file = $this->fileOf($request->user(), $arbitration);

        if ($request->expectsJson()) {
            return response()->json($file);
        }

        return view('volunteer.escalations.arbitrations.show', ['file' => $file, 'selected' => $arbitration]);
    }

    /** فتح التحكيم — حقّ الطرفين معًا (المالك والمدعوّ) */
    public function store(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate([
            'claim' => ['required', 'string'],
            'attachment_path' => ['nullable', 'string'],
            'contribution_id' => ['nullable', 'integer', 'exists:task_contributions,id'],
        ]);

        $contribution = $data['contribution_id']
            ? TaskContribution::query()->find($data['contribution_id'])
            : null;

        $this->arbitrations->open($task, $contribution, $request->user(), $data);

        return back()->with('status', (string) setting('workflow.arbitration.store_ok', 'اتفتحت القضيّة ووصلت للمحكّم ✓'));
    }

    public function message(Request $request, Arbitration $arbitration): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string'],
            'attachment_path' => ['nullable', 'string'],
        ]);

        $this->arbitrations->message($arbitration, $request->user(), $data['body'], $data['attachment_path'] ?? null);

        return back()->with('status', (string) setting('workflow.arbitration.message_ok', 'اتبعتت رسالتك ✓'));
    }

    /** قفل الرسائل — للمحكّم الحاليّ وحده */
    public function lock(Request $request, Arbitration $arbitration): RedirectResponse
    {
        $this->arbitrations->lockMessages($arbitration, $request->user());

        return back()->with('status', (string) setting('workflow.arbitration.lock_ok', 'اتقفلت الرسائل ✓'));
    }

    /**
     * تواصل مع طرف بلا كشف الرقم — ويُكشَف فقط لمن بينه وبين الطرف
     * علاقة أبلاين/داونلاين (حقّه النظاميّ — 13.4-م).
     */
    public function contact(Request $request, Arbitration $arbitration, User $party): JsonResponse
    {
        $parties = $this->arbitrations->parties($arbitration);

        abort_unless($parties->contains('id', $party->id), 404, (string) setting('workflow.arbitration.contact_denied', 'الطرف ده مش في القضيّة.'));

        return response()->json($this->arbitrations->contactFor($request->user(), $party));
    }

    /**
     * قرار المحكّم: (+)/(−) VXP لطرف أو للطرفين أو قيمة وسط أو «حفظ القضيّة» —
     * **مبرّر إجباريّ في كلّ الحالات** والقرار **نهائيّ ولا يُعاد**.
     */
    public function decide(Request $request, Arbitration $arbitration): RedirectResponse
    {
        abort_unless((int) $arbitration->arbiter_id === (int) $request->user()->id, 403, (string) setting('workflow.arbitration.decide_msg', 'القرار للمحكّم الحاليّ وحده.'));
        abort_if($arbitration->status === 'decided', 422, (string) setting('workflow.arbitration.decide_msg_2', 'القرار نهائيّ ولا يُعاد.'));

        $data = $request->validate([
            'decision_type' => ['required', 'in:award,deduct,split,shelved'],
            'owner_amount' => ['nullable', 'numeric'],
            'contributor_amount' => ['nullable', 'numeric'],
            'decision_justification' => ['required', 'string'],
            'confirm_final' => ['accepted'],
        ]);

        // القضيّة حالة على المحرّك — فالقرار يمرّ منه ليُغلَق المساران معًا
        $escalation = Escalation::query()
            ->where('subject_type', $arbitration->getMorphClass())
            ->where('subject_id', $arbitration->id)
            ->where('case_type', CaseCatalog::ARBITRATION)
            ->where('status', 'open')
            ->first();

        if ($escalation) {
            $this->engine->decide($escalation, $request->user(), $data['decision_type'], $data['decision_justification'], [
                'owner_amount' => $data['owner_amount'] ?? 0,
                'contributor_amount' => $data['contributor_amount'] ?? 0,
                'justification' => $data['decision_justification'],
            ]);
        } else {
            $this->arbitrations->applyDecision($arbitration, $request->user(), $data['decision_type'], [
                'owner_amount' => $data['owner_amount'] ?? 0,
                'contributor_amount' => $data['contributor_amount'] ?? 0,
                'justification' => $data['decision_justification'],
            ]);
        }

        return back()->with('status', (string) setting('workflow.arbitration.decide_ok', 'اتسجّل القرار — نهائيّ ولا يُعاد ✓'));
    }

    // ------------------------------------------------------------------ داخليّ

    /** ملفّ الحالة كاملًا كما يراه هذا المستخدم (بالـMasking الخاصّ به) */
    private function fileOf(User $viewer, Arbitration $arbitration): array
    {
        $task = Task::query()->find($arbitration->task_id);
        $contribution = $arbitration->task_contribution_id
            ? TaskContribution::query()->find($arbitration->task_contribution_id)
            : null;

        $parties = $this->arbitrations->parties($arbitration, $task);

        return [
            'arbitration' => $arbitration,
            'task' => $task,
            'contribution' => $contribution,
            // شكل المخرجات: أوّل ما يقرؤه المحكّم — منه يبدأ الخلاف وعنده ينتهي
            'spec' => $contribution?->deliverable_spec ?: $task?->deliverable_spec,
            'claim' => $arbitration->claim,
            'messages' => ArbitrationMessage::query()
                ->where('arbitration_id', $arbitration->id)
                ->orderBy('created_at')
                ->get(),
            'senders' => User::query()
                ->whereIn('id', ArbitrationMessage::query()->where('arbitration_id', $arbitration->id)->pluck('user_id')->unique())
                ->get()->keyBy('id'),
            'parties' => $parties->map(fn (User $party) => [
                'user' => $party,
                'contact' => $this->arbitrations->contactFor($viewer, $party),
            ]),
            'skipReason' => $this->arbitrations->skipReason($arbitration),
            'settlementShare' => $this->arbitrations->settlementShare($arbitration),
            'isArbiter' => (int) $arbitration->arbiter_id === (int) $viewer->id,
            'state' => $this->engine->windowState($arbitration->window_due_at),
        ];
    }
}
