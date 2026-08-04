<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdAudience;
use App\Models\Country;
use App\Models\Course;
use App\Models\Governorate;
use App\Models\LearningPath;
use App\Models\Referral;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\WalletWithdrawal;
use App\Services\Admin\AccountApproval;
use App\Services\Admin\AudienceSegments;
use App\Services\Admin\AuditTrail;
use App\Services\Admin\UserDirectory;
use App\Support\Scope\ScopeFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        return back()->with('status', (string) setting('admin_users.screen.columns_ok', 'اتحفظت أعمدتك ✓'));
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
            // التعديل اليدويّ يحتاج محافظات دولته وحدها — لا كلّ محافظات العالم
            default => [
                'governorates' => Governorate::query()
                    ->when($user->country_id, fn ($q, $country) => $q->where('country_id', $country))
                    ->orderBy('name_ar')->get(),
            ],
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
            // النطاق إلزاميّ مع كلّ صلاحيّة (12.2.1-ب) — القائمة تُحصَر بما يملكه فعلًا
            ->tap(fn ($q) => app(ScopeFilter::class)->applyToUsers($q, $request->user(), 'user_approvals.list'))
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
            return back()->with('problem', (string) setting('admin_users.screen.approve_msg', 'ماحدّدتش أيّ حساب — اختر حساب أو أكتر وبعدين اعتمد.'));
        }

        $actor = $request->user();
        $done = 0;

        foreach (User::whereIn('id', $ids)->get() as $account) {
            $done += $this->approval->approve($actor, $account) ? 1 : 0;
        }

        return back()->with('status', $done > 0
            ? strtr((string) setting('admin_users.screen.approve_ok', 'اتعمد :count حساب ✓ — التفعيل مجّانيّ وبقرارك الإداريّ'), [':count' => (string) $done])
            : (string) setting('admin_users.screen.approve_empty', 'مافيش حساب اتغيّر — يمكن يكونوا اتعمدوا قبل كده.'));
    }

    public function reject(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [], ['reason' => (string) setting('admin_users.screen.reject_msg', 'سبب الرفض')]);

        $ids = $this->pickedIds($request);

        if ($ids === []) {
            return back()->with('problem', (string) setting('admin_users.screen.reject_msg_2', 'ماحدّدتش أيّ حساب — اختر حساب أو أكتر وبعدين ارفض.'));
        }

        $actor = $request->user();
        $done = 0;

        foreach (User::whereIn('id', $ids)->get() as $account) {
            $done += $this->approval->reject($actor, $account, $validated['reason']) ? 1 : 0;
        }

        return back()->with('status', strtr((string) setting('admin_users.screen.reject_ok', 'اترفض :count حساب — واتبعت للمستخدم سبب واضح.'), [':count' => (string) $done]));
    }

    // ---------------------------------------------------------- شرائح الجمهور

    /**
     * شاشة شرائح الجمهور (12.13): القائمة + باني المعايير + المعاينة اللحظيّة.
     *
     * والمعاينة والعدّ يجريان **داخل نطاق صاحب الشاشة** (12.2.1-ب): مَن يرى فريقه
     * وحده لا يبني شريحةً بالمنصّة كلّها ثمّ يخاطبها.
     */
    public function segments(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => (string) $request->query('type', ''),
            'state' => (string) $request->query('state', ''),
            'used' => (string) $request->query('used', ''),
        ];

        $editing = $request->query('edit')
            ? AdAudience::query()->where('kind', AudienceSegments::KIND)->find((int) $request->query('edit'))
            : null;

        return view('admin.users.segments', $this->segmentsViewData(
            request: $request,
            filters: $filters,
            editing: $editing,
            rule: $editing ? (array) $editing->rule : [],
            previewed: false,
        ));
    }

    /** معاينة لحظيّة بلا حفظ: العدد + عيّنة أعضاء (عددها إعداد) — 12.13. */
    public function previewSegment(Request $request): View
    {
        $editing = $request->input('segment_id')
            ? AdAudience::query()->where('kind', AudienceSegments::KIND)->find((int) $request->input('segment_id'))
            : null;

        return view('admin.users.segments', $this->segmentsViewData(
            request: $request,
            filters: ['q' => '', 'type' => '', 'state' => '', 'used' => ''],
            editing: $editing,
            rule: $this->segmentRule($request),
            previewed: true,
        ));
    }

    public function storeSegment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'segment_type' => ['nullable', 'string', Rule::in(array_keys(AudienceSegments::types()))],
            'segment_id' => ['nullable', 'integer'],
        ], [
            'name.required' => (string) setting('admin_users.screen.store_segment_msg', 'سمّ الشريحة عشان تلاقيها بعدين.'),
        ], ['name' => (string) setting('admin_users.screen.store_segment_msg_2', 'اسم الشريحة')]);

        $existing = ($validated['segment_id'] ?? null)
            ? AdAudience::query()->where('kind', AudienceSegments::KIND)->find((int) $validated['segment_id'])
            : null;

        $segment = $this->segments->save(
            name: $validated['name'],
            rule: $this->segmentRule($request),
            type: (string) ($validated['segment_type'] ?? AudienceSegments::TYPE_DYNAMIC),
            actor: $request->user(),
            segment: $existing,
            description: $validated['description'] ?? null,
        );

        $this->audit->record(
            $request->user(),
            $existing ? 'segment.updated' : 'segment.created',
            $segment,
            [],
            ['rule' => $segment->rule, 'type' => $segment->segment_type],
        );

        return redirect()->route('admin.users.segments')
            ->with('status', strtr((string) setting('admin_users.screen.store_segment_ok', 'اتحفظت شريحة «:name» بـ:size عضو ✓'), [
                ':name' => (string) $segment->name,
                ':size' => (string) $segment->size,
            ]));
    }

    /** تكرار الشريحة كنسخة مستقلّة (12.13). */
    public function duplicateSegment(Request $request, AdAudience $audience): RedirectResponse
    {
        $copy = $this->segments->duplicate($audience, $request->user());

        $this->audit->record($request->user(), 'segment.duplicated', $copy, [], ['source' => $audience->id]);

        return back()->with('status', strtr((string) setting('admin_users.screen.duplicate_segment_ok', 'اتعملت نسخة «:name» ✓'), [':name' => (string) $copy->name]));
    }

    /** الأرشفة بدل الحذف — Toggle في الاتّجاهين (12.13). */
    public function archiveSegment(Request $request, AdAudience $audience): RedirectResponse
    {
        $segment = $this->segments->toggleArchive($audience);

        $this->audit->record($request->user(), 'segment.archived', $segment, [], ['archived' => (bool) $segment->archived_at]);

        return back()->with('status', $segment->archived_at ? (string) setting('admin_users.screen.archive_segment_ok', 'اتأرشفت الشريحة ✓') : (string) setting('admin_users.screen.archive_segment_ok_2', 'رجعت الشريحة للخدمة ✓'));
    }

    /** أعضاء الشريحة في بوب-أب لا صفحة جديدة (2.15-ج). */
    public function segmentMembers(Request $request, AdAudience $audience): View
    {
        return view('admin.users.segment-members', [
            'segment' => $audience,
            'members' => $this->segments->members($audience, (int) setting('admin.segments.members_rows', 50)),
            'count' => $this->segments->memberCount($audience),
            'service' => $this->segments,
            'types' => AudienceSegments::types(),
        ]);
    }

    /**
     * الحذف — **بتحذير إن كانت مستخدَمة** (12.13). والرفض هنا على الخادم لا في
     * الواجهة: شريحةٌ يخاطبها منشورٌ حيّ لا تُمحى فيتحوّل جمهوره إلى فراغ.
     */
    public function destroySegment(Request $request, AdAudience $audience): RedirectResponse
    {
        $usage = $this->segments->usage($audience);

        if ($usage->isNotEmpty() && setting('admin.segments.block_delete_when_used', true)) {
            return back()->with('problem', str_replace(
                ':count',
                (string) $usage->count(),
                (string) setting('admin.segments.delete_warning', 'الشريحة دي مستخدَمة في :count مكان — أرشفها بدل ما تمسحها.'),
            ));
        }

        $this->audit->record($request->user(), 'segment.deleted', $audience, ['name' => $audience->name], []);
        $audience->delete();

        return back()->with('status', (string) setting('admin_users.screen.destroy_segment_ok', 'اتمسحت الشريحة ✓'));
    }

    /** تصدير قائمة الشرائح (12.13) — الملخّص والعدد والاستخدام، بلا بيانات أعضاء. */
    public function exportSegments(Request $request): StreamedResponse
    {
        $rows = [[(string) setting('admin_users.screen.export_segments_msg', 'الاسم'), (string) setting('admin_users.screen.export_segments_msg_2', 'النوع'), (string) setting('admin_users.screen.export_segments_msg_3', 'المعايير'), (string) setting('admin_users.screen.export_segments_msg_4', 'عدد الأعضاء'), (string) setting('admin_users.screen.export_segments_msg_5', 'مستخدَمة في'), (string) setting('admin_users.screen.export_segments_msg_6', 'الحالة'), (string) setting('admin_users.screen.export_segments_msg_7', 'آخر تحديث')]];
        $types = AudienceSegments::types();

        foreach ($this->segments->all(['state' => 'all']) as $segment) {
            $rows[] = [
                (string) $segment->name,
                (string) ($types[$segment->segment_type] ?? $segment->segment_type),
                $this->segments->summary((array) $segment->rule),
                (string) $this->segments->memberCount($segment),
                (string) $this->segments->usage($segment)->count(),
                $segment->archived_at ? (string) setting('admin_users.screen.export_segments_msg_8', 'مؤرشفة') : (string) setting('admin_users.screen.export_segments_msg_9', 'نشطة'),
                (string) ($segment->last_built_at?->format('Y-m-d H:i') ?? ''),
            ];
        }

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            // BOM ليفتح إكسل العربيّة سليمةً بلا خطوة يدويّة
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 'segments-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * بيانات شاشة الشرائح — واحدةٌ للعرض وللمعاينة، فلا تفترق الشاشتان.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    private function segmentsViewData(Request $request, array $filters, ?AdAudience $editing, array $rule, bool $previewed): array
    {
        $segments = $this->segments->all($filters);
        $viewer = $request->user();

        return [
            'segments' => $segments,
            'service' => $this->segments,
            'filters' => $filters,
            'editing' => $editing,
            'rule' => $this->segments->normalize($rule),
            'previewed' => $previewed,
            // المعاينة والعدّ داخل نطاق صاحب الشاشة (12.2.1-ب)
            'previewCount' => $previewed ? $this->segments->count($rule, $viewer) : null,
            'preview' => $previewed ? $this->segments->preview($rule, $viewer) : collect(),
            // «مستخدَمة في X مكان» بروابط — محسوبة من الاستخدام الفعليّ (12.13)
            'usage' => $segments->mapWithKeys(fn (AdAudience $s) => [$s->id => $this->segments->usage($s)]),
            'counts' => $segments->mapWithKeys(fn (AdAudience $s) => [$s->id => $this->segments->memberCount($s)]),
            'criteria' => AudienceSegments::criteria(),
            'types' => AudienceSegments::types(),
            'matchModes' => AudienceSegments::matchModes(),
            'groupCount' => max(1, (int) setting('admin.segments.max_groups', 2)),
            'options' => $this->segmentOptions(),
        ];
    }

    /**
     * خيارات باني المعايير — قوائم مقفولة محدودة بسقوفها من الإعدادات (2.13).
     *
     * @return array<string, array<int|string, string>>
     */
    private function segmentOptions(): array
    {
        $limit = (int) setting('admin.segments.option_rows', 50);

        return [
            'status' => UserDirectory::STATUSES,
            'role' => $this->directory->roleOptions(),
            'country' => Country::query()->orderBy('name_ar')->limit($limit)->pluck('name_ar', 'id')->all(),
            // المحافظات المستعملة فعلًا وحدها — قائمةٌ بلا معنى أسوأ من غيابها
            'governorate' => Governorate::query()
                ->whereIn('id', User::query()->whereNotNull('governorate_id')->distinct()->pluck('governorate_id'))
                ->orderBy('name_ar')
                ->limit($limit)
                ->pluck('name_ar', 'id')
                ->all(),
            'course' => Course::query()->orderByDesc('id')->limit($limit)->pluck('name_ar', 'id')->all(),
            'path' => LearningPath::query()->orderBy('sort_order')->limit($limit)->pluck('name_ar', 'id')->all(),
        ];
    }

    /**
     * شرط الشريحة كما يصل من باني المعايير: مجموعات بمنطق AND/OR (12.13).
     *
     * والتنظيف والقصّ في `AudienceSegments::normalize` — قائمة المعايير مقفولة
     * هناك، فلا يمرّ حقلٌ حرّ من الفورم إلى الاستعلام.
     *
     * @return array<string, mixed>
     */
    private function segmentRule(Request $request): array
    {
        return [
            'match' => (string) $request->input('match', 'all'),
            'groups' => array_values((array) $request->input('groups', [])),
        ];
    }

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
