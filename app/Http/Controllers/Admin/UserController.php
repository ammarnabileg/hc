<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdAudience;
use App\Models\Country;
use App\Models\Referral;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\WalletWithdrawal;
use App\Services\Admin\AccountApproval;
use App\Services\Admin\AudienceSegments;
use App\Services\Admin\AuditTrail;
use App\Services\Admin\UserDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * إدارة المستخدمين (الدستور 12.13 · 12.1 · 24.1 · 2.5-د).
 *
 * ثلاث شاشات: القائمة · طلبات الاعتماد · شرائح الجمهور — ورابعة تفصيليّة لحساب واحد.
 * والقاعدة الحاكمة في الاعتماد: **التفعيل مجّانيّ باعتماد إداريّ** (2.5-د).
 */
class UserController extends Controller
{
    public function __construct(
        private readonly UserDirectory $directory,
        private readonly AccountApproval $approval,
        private readonly AudienceSegments $segments,
        private readonly AuditTrail $audit,
    ) {}

    // ---------------------------------------------------------- قائمة المستخدمين

    public function index(Request $request): View
    {
        return view('admin.users.index', [
            'users' => $this->directory->query($request),
            'columns' => $this->directory->visibleColumns($request->user()),
            'allColumns' => UserDirectory::COLUMNS,
            'statuses' => UserDirectory::STATUSES,
            'roles' => $this->directory->roleOptions(),
            'directory' => $this->directory,
        ]);
    }

    /** اختيار الأعمدة يُحفَظ لكلّ مستخدم (2.15-د-⭐) */
    public function columns(Request $request): RedirectResponse
    {
        $this->directory->saveColumns($request->user(), (array) $request->input('columns', []));

        return back()->with('status', 'اتحفظت أعمدتك ✓');
    }

    // ------------------------------------------------------- صفحة حساب المستخدم

    public function show(Request $request, User $user): View
    {
        $tabs = $this->directory->tabsFor($request->user(), $user);
        $tab = array_key_exists((string) $request->query('tab'), $tabs) ? (string) $request->query('tab') : 'profile';

        $data = [
            'user' => $user->load('roles', 'country', 'governorate'),
            'tabs' => collect($tabs)->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'url' => route('admin.users.show', ['user' => $user, 'tab' => $key]),
            ])->values()->all(),
            'tab' => $tab,
            'directory' => $this->directory,
        ];

        // طول جدول التاب إعداد لا رقم محروق (2.13)
        $rows = (int) setting('admin.users.tab_rows', 25);

        // تحميل كسول لكلّ تاب — لا يُستعلَم إلّا عمّا يُفتَح فعلًا (2.15-ب)
        $data += match ($tab) {
            // ⭐ تاب الجداول: الثلاثة **بفلتر من فترة لفترة** (12.1-الجداول)
            'tables' => $this->tablesTab($request, $user, $rows),
            'wallet' => ['balances' => $user->balances()->with('currency')->get()],
            'learning' => ['enrollments' => $user->enrollments()->with('course')->latest('id')->limit($rows)->get()],
            'certificates' => ['certificates' => $user->certificates()->latest('id')->limit($rows)->get()],
            'security' => [
                // الجلسات النشطة على الحساب — عرضها شرط قبل زرّ «إنهاء كلّ الجلسات»
                'devices' => UserDevice::where('user_id', $user->id)->latest('last_active_at')->limit($rows)->get(),
            ],
            'admin' => [
                'roleOptions' => $this->directory->roleOptions(),
                'rejectReasons' => $this->approval->rejectReasons(),
            ],
            'advanced' => [
                'countries' => Country::query()->where('is_active', true)->orderBy('name_ar')->get(),
                'lastChange' => $this->audit->lastChange($user),
            ],
            default => [],
        };

        return view('admin.users.show', $data);
    }

    /**
     * جداول 12.1 الثلاثة بفلتر الفترة: المعاملات · **السحوبات** · الدعوات.
     *
     * وجدول الدعوات يقول صراحةً **هل أخذوا هديتهم**، والهديّة لا تُصرَف إلّا
     * **بعد قبول الحساب** — فحالة «لسّه» على حساب تحت المراجعة ليست تأخيرًا بل قاعدة.
     */
    private function tablesTab(Request $request, User $user, int $rows): array
    {
        $period = $this->directory->period($request);

        return [
            'period' => $period,
            'transactions' => $user->transactions()
                ->with('currency')
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->latest('id')->limit($rows)->get(),
            'withdrawals' => WalletWithdrawal::where('user_id', $user->id)
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->latest('id')->limit($rows)->get(),
            'referrals' => Referral::where('referrer_id', $user->id)
                ->with('referred')
                ->whereBetween('created_at', [$period['from'], $period['to']])
                ->latest('id')->limit($rows)->get(),
        ];
    }

    // ---------------------------------------------------------- طلبات الاعتماد

    public function approvals(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $pending = User::query()
            ->where('status', $request->query('state', 'pending'))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(fn ($inner) => $inner
                    ->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('email', 'like', $like));
            })
            ->when($request->query('age') === 'late', fn ($q) => $q->where('created_at', '<=', now()->subDays((int) setting('admin.dashboard.approval_late_days', 2))))
            ->when($request->query('source') === 'referral', fn ($q) => $q->whereIn('id', Referral::whereNotNull('referred_id')->pluck('referred_id')))
            ->orderBy('created_at')
            ->paginate((int) setting('admin.users.per_page', 25))
            ->withQueryString();

        $referrals = Referral::whereIn('referred_id', $pending->pluck('id'))
            ->with('referrer')
            ->get()
            ->keyBy('referred_id');

        return view('admin.users.approvals', [
            'pending' => $pending,
            'referrals' => $referrals,
            'reasons' => $this->approval->rejectReasons(),
            'bulkLimit' => $this->approval->bulkLimit(),
            'lateDays' => (int) setting('admin.dashboard.approval_late_days', 2),
            'directory' => $this->directory,
        ]);
    }

    public function approve(Request $request): RedirectResponse
    {
        $ids = $this->pickedIds($request);

        if ($ids === []) {
            return back()->with('problem', 'ماحدّدتش أيّ حساب — اختر حساب أو أكتر وبعدين اعتمد.');
        }

        $actor = $request->user();
        $done = 0;

        foreach (User::whereIn('id', $ids)->get() as $account) {
            $done += $this->approval->approve($actor, $account) ? 1 : 0;
        }

        return back()->with('status', $done > 0
            ? "اتعمد {$done} حساب ✓ — التفعيل مجّانيّ وبقرارك الإداريّ"
            : 'مافيش حساب اتغيّر — يمكن يكونوا اتعمدوا قبل كده.');
    }

    public function reject(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [], ['reason' => 'سبب الرفض']);

        $ids = $this->pickedIds($request);

        if ($ids === []) {
            return back()->with('problem', 'ماحدّدتش أيّ حساب — اختر حساب أو أكتر وبعدين ارفض.');
        }

        $actor = $request->user();
        $done = 0;

        foreach (User::whereIn('id', $ids)->get() as $account) {
            $done += $this->approval->reject($actor, $account, $validated['reason']) ? 1 : 0;
        }

        return back()->with('status', "اترفض {$done} حساب — واتبعت للمستخدم سبب واضح.");
    }

    // ---------------------------------------------------------- شرائح الجمهور

    public function segments(Request $request): View
    {
        $rule = array_filter([
            'status' => $request->query('status'),
            'role' => $request->query('role'),
            'min_xp' => $request->query('min_xp'),
            'registered_days' => $request->query('registered_days'),
        ], fn ($value) => $value !== null && $value !== '');

        return view('admin.users.segments', [
            'segments' => $this->segments->all(),
            'service' => $this->segments,
            'rule' => $rule,
            'previewCount' => $rule === [] ? null : $this->segments->count($rule),
            'preview' => $rule === [] ? collect() : $this->segments->preview($rule),
            'statuses' => UserDirectory::STATUSES,
            'roles' => $this->directory->roleOptions(),
            'criteria' => AudienceSegments::CRITERIA,
        ]);
    }

    public function storeSegment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ], [], ['name' => 'اسم الشريحة']);

        $segment = $this->segments->save($validated['name'], [
            'status' => $request->input('status'),
            'role' => $request->input('role'),
            'min_xp' => $request->input('min_xp'),
            'registered_days' => $request->input('registered_days'),
        ]);

        $this->audit->record($request->user(), 'segment.created', $segment, [], ['rule' => $segment->rule]);

        return redirect()->route('admin.users.segments')
            ->with('status', "اتحفظت شريحة «{$segment->name}» بـ{$segment->size} عضو ✓");
    }

    public function destroySegment(Request $request, AdAudience $audience): RedirectResponse
    {
        $this->audit->record($request->user(), 'segment.deleted', $audience, ['name' => $audience->name], []);
        $audience->delete();

        return back()->with('status', 'اتمسحت الشريحة ✓');
    }

    // ------------------------------------------------------------------ داخليّ

    /** الإجراء الجماعيّ محدود بسقفه من الإعدادات (24.1) */
    private function pickedIds(Request $request): array
    {
        return collect((array) $request->input('users', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->take($this->approval->bulkLimit())
            ->values()
            ->all();
    }
}
