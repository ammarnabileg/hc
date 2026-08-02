<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\ContributionCheckpoint;
use App\Models\Task;
use App\Models\TaskContribution;
use App\Models\User;
use App\Services\Volunteer\Contributions\ActivityWindow;
use App\Services\Volunteer\Contributions\ContributionService;
use App\Services\Volunteer\Escalation\CaseCatalog;
use App\Services\Volunteer\Escalation\EscalationEngine;
use App\Services\Volunteer\Escalation\FlowLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «مساهماتي» (الدستور 24.4 · 23 — القسم 4).
 *
 * سؤال واحد للشاشة: «إيه البنود اللي عليّ كمساهم، وإيه المستحقّ دلوقتي؟»
 * والفعل الرئيسيّ واحد: الردّ على الدعوة أو التسليم.
 */
class ContributionController extends Controller
{
    public function __construct(
        private readonly ContributionService $contributions,
        private readonly EscalationEngine $engine,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        // ثلاثة فلاتر ظاهرة فقط: الحالة · المالك · بحث (2.15-أ-4)
        $filters = [
            'status' => $request->string('status')->toString(),
            'owner' => $request->integer('owner'),
            'q' => trim($request->string('q')->toString()),
        ];

        $rows = TaskContribution::query()
            ->where('contributor_id', $user->id)
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['owner'], fn ($q) => $q->where('invited_by', $filters['owner']))
            ->when($filters['q'] !== '', fn ($q) => $q->where('item_title', 'like', '%'.$filters['q'].'%'))
            ->orderByRaw("CASE WHEN status = 'invited' THEN 0 ELSE 1 END")
            ->orderBy('internal_deadline_at')
            ->get();

        $tasks = Task::query()->whereIn('id', $rows->pluck('task_id')->unique())->get()->keyBy('id');
        $owners = User::query()->whereIn('id', $rows->pluck('invited_by')->unique())->get()->keyBy('id');

        return view('volunteer.contributions.index', [
            'rows' => $rows,
            'tasks' => $tasks,
            'owners' => $owners,
            'checkpoints' => ContributionCheckpoint::query()
                ->whereIn('task_contribution_id', $rows->pluck('id'))
                ->orderBy('sequence')
                ->get()
                ->groupBy('task_contribution_id'),
            'counters' => $this->contributions->counters($user),
            'filters' => $filters,
            'statuses' => $this->statuses(),
            'engine' => $this->engine,
            'service' => $this->contributions,
            'ownerOptions' => $owners,
            'activityWindow' => ActivityWindow::label(),
            // تنبيه «تفتيش مستحقّ خلال ساعة» — يُحسَب داخل نافذة النشاط
            'dueSoonCheckpoint' => $this->dueSoonCheckpoint($rows->pluck('id')->all()),
        ]);
    }

    /** تفاصيل البند في بانل/بوب-أب — لا صفحة جديدة (2.15-أ-6) */
    public function show(Request $request, TaskContribution $contribution): JsonResponse|View
    {
        $this->authorizeParty($request->user(), $contribution);

        $payload = [
            'contribution' => $contribution,
            'task' => Task::query()->find($contribution->task_id),
            'owner' => User::query()->find($contribution->invited_by),
            'checkpoints' => $this->contributions->checkpointsOf($contribution),
            'penalty' => rep_rule('task.contribution_no_delivery'),
            'activityWindow' => ActivityWindow::label(),
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return view('volunteer.contributions.show', $payload);
    }

    /**
     * معاينة قبل إرسال الدعوة: مهامّ المدعوّ المفتوحة · مسلَّماته · مساهماته ·
     * **ورصيدي بعد الخصم** — ويُمنَع الإرسال إن لم يكفِ (23 — القسم 4).
     */
    public function preview(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'vxp_value' => ['nullable', 'numeric', 'min:0'],
            'vxp_source' => ['nullable', 'in:task_pool,owner_balance'],
        ]);

        $invitee = User::query()->where('code', $data['code'])->firstOrFail();

        $preview = $this->contributions->invitePreview(
            $invitee,
            $request->user(),
            (float) ($data['vxp_value'] ?? 0),
            $data['vxp_source'] ?? 'task_pool',
        );

        return response()->json([
            'invitee' => ['id' => $invitee->id, 'name' => $invitee->name, 'code' => $invitee->code],
            'open_tasks' => $preview['open_tasks'],
            'delivered_tasks' => $preview['delivered_tasks'],
            'contributions' => $preview['contributions_done'].' / '.$preview['contributions_total'],
            'balance' => $preview['balance'],
            'balance_after' => $preview['balance_after'],
            'sufficient' => $preview['sufficient'],
            'latest_internal_deadline' => $this->contributions->latestInternalDeadline($task)?->toDateTimeString(),
            'message' => $preview['sufficient']
                ? 'تمام — رصيدك بعد الخصم: '.$preview['balance_after'].' VXP.'
                : 'رصيدك مايكفّيش — قلّل القيمة أو خدها من وعاء المهمّة.',
        ]);
    }

    /** إرسال الدعوة بعد فحص القيود كلّها في الخدمة */
    public function store(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'item_title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'deliverable_spec' => ['required', 'string'],
            'internal_deadline_at' => ['required', 'date'],
            'vxp_value' => ['required', 'numeric', 'min:0'],
            'vxp_source' => ['required', 'in:task_pool,owner_balance'],
            'checkpoints' => ['nullable', 'array', 'max:'.$this->contributions->maxCheckpoints()],
            'checkpoints.*' => ['nullable', 'date'],
        ]);

        $invitee = User::query()->where('code', $data['code'])->firstOrFail();

        $this->contributions->invite($task, $request->user(), $invitee, $data);

        return back()->with('status', 'اتبعتت الدعوة ✓');
    }

    /** القبول يفتح البند، والرفض يعيده بندًا شخصيًّا للمالك */
    public function respond(Request $request, TaskContribution $contribution): RedirectResponse
    {
        abort_unless((int) $contribution->contributor_id === (int) $request->user()->id, 403, 'الدعوة ليست لك.');

        $data = $request->validate(['decision' => ['required', 'in:accept,reject']]);

        $this->contributions->respond($contribution, $data['decision'] === 'accept');

        return back()->with('status', $data['decision'] === 'accept' ? 'اتفتح البند — يلّا بينا 💪' : 'اتسجّل اعتذارك ✓');
    }

    /** ردّ نقطة تفتيش — نمط تذاكر، ومهلته ساعتان داخل نافذة النشاط */
    public function checkpoint(Request $request, ContributionCheckpoint $checkpoint): RedirectResponse
    {
        $contribution = TaskContribution::query()->findOrFail($checkpoint->task_contribution_id);

        $this->authorizeParty($request->user(), $contribution);

        $data = $request->validate(['body' => ['required', 'string']]);

        $this->contributions->respondToCheckpoint($checkpoint, $request->user(), $data['body']);

        return back()->with('status', 'اتسجّل ردّك ✓');
    }

    /** تسليم نهائيّ ⟵ مهلة المالك ثمّ اعتماد تلقائيّ بنقاطك كاملة */
    public function deliver(Request $request, TaskContribution $contribution): RedirectResponse
    {
        abort_unless((int) $contribution->contributor_id === (int) $request->user()->id, 403, 'البند ليس لك.');

        $data = $request->validate([
            'body' => ['nullable', 'string'],
            'link' => ['nullable', 'url'],
            'note' => ['nullable', 'string'],
        ]);

        $this->contributions->deliver($contribution, $data);

        return back()->with('status', 'اتسلّم البند ✓ — مهلة المالك '.$this->contributions->ownerReviewHours().' ساعة، وبعدها اعتماد تلقائيّ.');
    }

    /** طلب سحب مساهم ⟵ الحالة 6 على محرّك التصعيد (بلا أثر على درجة أيّ طرف) */
    public function withdraw(Request $request, TaskContribution $contribution): RedirectResponse
    {
        abort_unless((int) $contribution->invited_by === (int) $request->user()->id, 403, 'الطلب للمالك وحده.');

        $data = $request->validate(['reason' => ['required', 'string']]);

        // بلا نقاط تفتيش: السحب مش متاح قبل فوات الديدلاين الداخليّ (23 — القسم 4)
        $hasCheckpoints = $this->contributions->checkpointsOf($contribution)->isNotEmpty();
        $deadlinePassed = $contribution->internal_deadline_at?->isPast() ?? false;

        abort_if(
            ! $hasCheckpoints && ! $deadlinePassed,
            422,
            'مافيش نقاط تفتيش على البند ده — السحب مايتاحش قبل فوات الديدلاين الداخليّ.',
        );

        $this->engine->open(CaseCatalog::CONTRIBUTOR_WITHDRAW, $contribution, $request->user(), [
            'reason' => $data['reason'],
        ]);

        return back()->with('status', 'اترفع طلب السحب للمراجِع ✓');
    }

    // ------------------------------------------------------------------ داخليّ

    private function statuses(): array
    {
        return [
            'invited' => 'مدعوّ',
            'accepted' => 'مقبول/مفتوح',
            'delivered' => 'تسليم نهائيّ',
            'approved' => 'معتمد',
            'returned' => 'مُرجَع',
            'withdrawn' => 'مسحوب',
            'rejected' => 'معتذَر عنه',
            'expired' => 'عدم تسليم',
        ];
    }

    private function dueSoonCheckpoint(array $contributionIds): ?ContributionCheckpoint
    {
        if ($contributionIds === []) {
            return null;
        }

        return ContributionCheckpoint::query()
            ->whereIn('task_contribution_id', $contributionIds)
            ->where('status', 'pending')
            ->whereNotNull('response_due_at')
            ->where('response_due_at', '>', now())
            ->where('response_due_at', '<=', now()->addHour())
            ->orderBy('response_due_at')
            ->first();
    }

    /** الطرفان وأبلاين المالك وحدهم — والمساهمة عابرة للأقسام فلا تكفي العضويّة */
    private function authorizeParty(User $user, TaskContribution $contribution): void
    {
        $allowed = in_array((int) $user->id, [
            (int) $contribution->contributor_id,
            (int) $contribution->invited_by,
        ], true);

        abort_unless($allowed || $user->allows('contributions.view', $contribution), 403, 'البند ده مش في نطاقك.');
    }

    /** رصيد المالك — يُعرَض في بوب-أب الدعوة قبل الإرسال */
    public function balanceOf(User $user): float
    {
        return FlowLedger::balance($user);
    }
}
