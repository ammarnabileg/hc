<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Objection;
use App\Models\Transaction;
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
    public function __construct(private readonly ObjectionService $service) {}

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
}
