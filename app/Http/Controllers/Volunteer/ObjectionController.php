<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Objection;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Volunteer\Objections\ObjectionDesk;
use App\Services\Volunteer\Objections\ObjectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * اعتراضاتي (الدستور 13.4-ط · 24.4).
 *
 * تخطيط «قائمة + بانل» الموحَّد (2.15-ب): القائمة يمينًا والتفاصيل يسارًا،
 * والتبديل فوريّ بلا انتقال صفحة — وعلى الموبايل شاشة واحدة + Bottom Sheet.
 *
 * ولا أزرار ردّ/تصعيد هنا — تلك للمسؤول في صفحة التصعيدات.
 */
class ObjectionController extends Controller
{
    public function __construct(
        private readonly ObjectionService $service,
        private readonly ObjectionDesk $desk,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $filters = [
            'status' => $request->string('status')->toString(),
            'days' => (int) $request->integer('days', (int) setting('ux.lists.default_range_days', 30)),
            'q' => trim($request->string('q')->toString()),
        ];

        $all = Objection::query()
            ->with(['transaction.currency', 'current_handler', 'correction_transaction', 'messages.user', 'user'])
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(max(1, $filters['days'])))
            ->orderByDesc('created_at')
            ->get();

        $counts = collect(ObjectionService::STATUSES)
            ->mapWithKeys(fn ($s) => [$s => $all->where('status', $s)->count()])
            ->all();

        $rows = $all
            ->when($filters['status'] !== '', fn ($c) => $c->where('status', $filters['status']))
            ->when($filters['q'] !== '', fn ($c) => $c->filter(
                fn (Objection $o) => str_contains((string) $o->transaction_id, $filters['q'])
                    || str_contains($o->reason, $filters['q'])
            ))
            ->values();

        $selectedId = (int) $request->integer('objection');
        $selected = $rows->firstWhere('id', $selectedId) ?: $rows->first();

        return view('volunteer.transactions.objections', [
            'rows' => $rows,
            'selected' => $selected,
            'counts' => $counts,
            'overdue' => $rows->filter(fn (Objection $o) => $this->service->isOverdue($o))->count(),
            'filters' => $filters,
            'service' => $this->service,
            'ladders' => $rows->mapWithKeys(fn (Objection $o) => [$o->id => $this->service->ladder($o)]),
        ]);
    }

    /** فتح اعتراض من بوب-أب صفحة المعاملات — اعتراض واحد لكلّ معاملة */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:transactions,id'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:8192'],
        ], [], ['reason' => 'سبب الاعتراض']);

        $transaction = Transaction::query()->findOrFail($data['transaction_id']);

        $result = $this->service->file(
            $transaction,
            $request->user(),
            $data['reason'],
            $request->hasFile('attachment')
                ? $request->file('attachment')->store('objections', 'public')
                : null,
        );

        if (! $result['ok']) {
            return back()->with('status', $result['message']);
        }

        return redirect()
            ->route('volunteer.objections', ['objection' => $result['objection']->id])
            ->with('status', $result['message']);
    }

    /** «إضافة تفاصيل» — متاحة أيّ وقت ما دام الاعتراض ساريًا */
    public function addMessage(Request $request, Objection $objection): RedirectResponse
    {
        abort_unless((int) $objection->user_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:8192'],
        ], [], ['body' => 'التفاصيل']);

        $result = $this->service->addMessage(
            $objection,
            $request->user(),
            $data['body'],
            $request->hasFile('attachment')
                ? $request->file('attachment')->store('objections', 'public')
                : null,
        );

        return redirect()
            ->route('volunteer.objections', ['objection' => $objection->id])
            ->with('status', $result['message']);
    }

    // ================================================================
    // ⬆️ الاعتراضات المصعَّدة إليّ (24.4-8) — مكتب المسؤول لا صفحة المعترِض
    // ================================================================

    /**
     * الشاشة: نصّان — يمين القائمة ويسار التفاصيل، والتبديل فوريّ.
     * ولا يظهر فيها إلّا ما هو على مكتب صاحبها فعلًا (`ObjectionDesk`).
     */
    public function desk(Request $request): View
    {
        $user = $request->user();

        $days = max(1, (int) $request->integer('days', (int) setting('workflow.objection_desk.range_days', 30)));

        $filters = [
            'status' => $request->string('status')->toString(),
            'entity' => (int) $request->integer('entity'),
            'days' => $days,
            'q' => trim($request->string('q')->toString()),
        ];

        $all = $this->desk->query($user)
            ->with(['transaction.currency', 'user.memberships.entity', 'current_handler', 'correction_transaction', 'messages.user'])
            ->where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('created_at')
            ->get();

        // أيقونة الكيان ومبدّله: كيان المعترِض في عضويّته النشطة
        $entityOf = fn (Objection $o) => $o->user?->memberships->firstWhere('status', 'active')?->entity;

        $entities = $all->map($entityOf)->filter()->unique('id')->values();

        $counts = collect(ObjectionService::STATUSES)
            ->mapWithKeys(fn (string $s) => [$s => $all->where('status', $s)->count()])
            ->all();

        $rows = $all
            ->when($filters['status'] !== '', fn ($c) => $c->where('status', $filters['status']))
            ->when($filters['entity'] > 0, fn ($c) => $c->filter(
                fn (Objection $o) => (int) ($entityOf($o)?->id ?? 0) === $filters['entity']
            ))
            ->when($filters['q'] !== '', fn ($c) => $c->filter(
                fn (Objection $o) => str_contains((string) $o->transaction_id, $filters['q'])
                    || str_contains((string) $o->user?->name, $filters['q'])
                    || str_contains((string) $o->user?->code, $filters['q'])
            ))
            ->values();

        $selectedId = (int) $request->integer('objection');
        $selected = $rows->firstWhere('id', $selectedId) ?: $rows->first();

        return view('volunteer.escalations.objections', [
            'rows' => $rows,
            'selected' => $selected,
            'counts' => $counts,
            'overdue' => $rows->filter(fn (Objection $o) => $this->service->isOverdue($o))->count(),
            'entities' => $entities,
            'entityOf' => $entityOf,
            'filters' => $filters,
            'service' => $this->service,
            'desk' => $this->desk,
            'ladders' => $rows->mapWithKeys(fn (Objection $o) => [$o->id => $this->service->ladder($o)]),
            // Audit: مَن قرّر — بلا اسمٍ يبقى القرار مجهول الصاحب
            'deciders' => User::query()
                ->whereIn('id', $rows->pluck('decided_by')->filter()->unique())
                ->get()->keyBy('id'),
            'notice' => (string) setting(
                'workflow.objection_desk.notice',
                'لا أحد يعدّل المعاملة الأصليّة — التصحيح بمعاملة عكسيّة موثّقة.',
            ),
            'emptyMessage' => (string) setting('workflow.objection_desk.empty', 'مفيش اعتراضات عندك'),
        ]);
    }

    /** ردّ المسؤول (نصّ + مرفق) — لا يُغلِق الاعتراض */
    public function reply(Request $request, Objection $objection): RedirectResponse
    {
        $this->desk->authorizeDecision($request->user(), $objection);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:8192'],
        ], [], ['body' => 'الردّ']);

        $result = $this->service->reply(
            $objection,
            $request->user(),
            $data['body'],
            $request->hasFile('attachment')
                ? $request->file('attachment')->store('objections', 'public')
                : null,
        );

        return $this->backToDesk($objection, $result['message']);
    }

    /** تصعيد لمن فوقي بسبب مكتوب — على المسار المستقلّ لا على `escalations` */
    public function escalate(Request $request, Objection $objection): RedirectResponse
    {
        $this->desk->authorizeDecision($request->user(), $objection);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], ['reason' => 'سبب التصعيد']);

        $result = $this->service->escalate($objection, $request->user(), $data['reason']);

        return $this->backToDesk($objection, $result['message']);
    }

    /** [للمخوَّل] تعديل/عكس ⟵ معاملة تصحيحيّة شفّافة + إشعار للعضو */
    public function accept(Request $request, Objection $objection): RedirectResponse
    {
        $this->desk->authorizeDecision($request->user(), $objection);

        $data = $request->validate([
            'note' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], ['note' => 'مبرّر القبول']);

        $result = $this->service->accept($objection, $request->user(), $data['note']);

        return $this->backToDesk($objection, $result['message']);
    }

    /** رفض بسبب مكتوب — والسبب إجباريّ فلا يُغلَق اعتراض بلا تعليل */
    public function reject(Request $request, Objection $objection): RedirectResponse
    {
        $this->desk->authorizeDecision($request->user(), $objection);

        $data = $request->validate([
            'note' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], ['note' => 'سبب الرفض']);

        $result = $this->service->reject($objection, $request->user(), $data['note']);

        return $this->backToDesk($objection, $result['message']);
    }

    private function backToDesk(Objection $objection, string $message): RedirectResponse
    {
        return redirect()
            ->route('volunteer.escalations.objections', ['objection' => $objection->id])
            ->with('status', $message);
    }
}
