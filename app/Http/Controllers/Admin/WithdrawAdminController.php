<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletWithdrawal;
use App\Services\Admin\System\WithdrawReviewService;
use App\Support\Scope\ScopeFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * إدارة طلبات سحب الأرباح (19.2 · 19.3).
 *
 * 🔒 الاعتماد والرفض لمالك المنصّة وحده — بحارسَي صلاحيّة ودور (`is_owner_only`
 * في المصفوفة **وفحصٌ صريح هنا**)، على غرار `FinanceController`: الحسّاس يُقفَل
 * مرّتين لا مرّة. أمّا الاستعراض (`withdraw.list`) فأوسع — يفتح لغير المالك أيضًا.
 */
class WithdrawAdminController extends Controller
{
    public function __construct(private readonly WithdrawReviewService $review) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() ?: WalletWithdrawal::PENDING;

        $rows = WalletWithdrawal::query()
            // النطاق إلزاميّ مع كلّ صلاحيّة (12.2.1-ب)
            ->tap(fn ($q) => app(ScopeFilter::class)->apply($q, $request->user(), 'withdraw.list'))
            ->with('user')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate((int) setting('finance.withdraw.admin.per_page', 20))
            ->withQueryString();

        return view('admin.store.withdrawals.index', [
            'rows' => $rows,
            'status' => $status,
            'statuses' => $this->statuses(),
            'pendingCount' => $this->review->pendingCount(),
        ]);
    }

    public function show(Request $request, WalletWithdrawal $withdrawal): View
    {
        $withdrawal->load(['user', 'processed_by']);

        return view('admin.store.withdrawals.show', [
            'withdrawal' => $withdrawal,
            'statuses' => $this->statuses(),
            'history' => WalletWithdrawal::query()
                ->where('user_id', $withdrawal->user_id)
                ->whereKeyNot($withdrawal->getKey())
                ->latest('id')->limit((int) setting('finance.withdraw.admin.user_history_rows', 5))->get(),
        ]);
    }

    public function approve(Request $request, WalletWithdrawal $withdrawal): RedirectResponse
    {
        $this->assertOwner($request);

        $data = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $this->review->approve($withdrawal, $request->user(), $data['note']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['note' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.withdrawals.index')->with('status', (string) setting('finance.withdraw.admin.approve_ok', 'الطلب اتصرف ✓'));
    }

    public function reject(Request $request, WalletWithdrawal $withdrawal): RedirectResponse
    {
        $this->assertOwner($request);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $this->review->reject($withdrawal, $request->user(), $data['reason']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['reason' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.withdrawals.index')->with('status', (string) setting('finance.withdraw.admin.reject_ok', 'الطلب اترفض والمبلغ رجع لصاحبه ✓'));
    }

    /** 🔒 حزامٌ وحمّالة: حتى لو أُسنِدت الصلاحيّة بالخطأ لغير المالك (24.3 · 12.2.1) */
    private function assertOwner(Request $request): void
    {
        abort_unless($request->user()->isPlatformOwner(), 403, (string) setting('finance.withdraw.admin.owner_only', 'اعتماد السحب ورفضه لمالك المنصّة وحده.'));
    }

    /** @return array<string,string> */
    private function statuses(): array
    {
        return [
            WalletWithdrawal::PENDING => (string) setting('finance.withdraw.admin.status_pending', '🟡 قيد المراجعة'),
            WalletWithdrawal::PROCESSING => (string) setting('finance.withdraw.admin.status_processing', '🔵 قيد التحويل'),
            WalletWithdrawal::PAID => (string) setting('finance.withdraw.admin.status_paid', '🟢 مستلمة'),
            WalletWithdrawal::REJECTED => (string) setting('finance.withdraw.admin.status_rejected', '🔴 مرفوضة'),
        ];
    }
}
