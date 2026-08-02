<?php

namespace App\Http\Controllers\AdminScreens;

use App\Http\Controllers\Controller;
use App\Models\ReportSchedule;
use App\Models\Role;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\AdminScreens\ReportScheduler;
use App\Services\AdminScreens\ScreenSettings;
use App\Support\Scope\ScopeFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * التقارير المجدولة (24.3-خامسًا).
 *
 * 🔒 القاعدة الحاكمة هنا: التقرير الماليّ **لا يُنشَأ ولا يُعدَّل ولا يُشغَّل ولا
 * يُقرأ** إلّا من مالك المنصّة — والحارس في الكنترولر لا في الواجهة، لأنّ
 * إخفاء الخيار من القائمة وحده لا يمنع طلبًا مكتوبًا بيد.
 */
class ReportScheduleController extends Controller
{
    public function __construct(private readonly ReportScheduler $scheduler) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $reports = $this->scheduler->reportsFor($user);

        $filters = [
            'q' => $request->string('q')->toString(),
            'tab' => $request->string('report')->toString(),
            'frequency' => $request->string('frequency')->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $schedules = ReportSchedule::query()
            // النطاق إلزاميّ مع كلّ صلاحيّة (12.2.1-ب) — جداول مَن هم في نطاقه
            ->tap(fn ($q) => app(ScopeFilter::class)->apply($q, $user, 'report_schedules.list', 'created_by'))
            ->with('creator:id,name')
            // 🔒 الجدولة الماليّة تختفي تمامًا لغير مالك المنصّة
            ->when(! $user->isPlatformOwner(), fn ($q) => $q->where('is_financial', false))
            ->when($filters['q'] !== '', fn ($q) => $q->where('name', 'like', '%'.$filters['q'].'%'))
            ->when($filters['tab'] !== '', fn ($q) => $q->where('report_tab', $filters['tab']))
            ->when($filters['frequency'] !== '', fn ($q) => $q->where('frequency', $filters['frequency']))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('id')
            ->paginate((int) setting('report_schedules.per_page', 20))
            ->withQueryString();

        return view('admin.report-schedules.index', [
            'schedules' => $schedules,
            'filters' => $filters,
            'reports' => $reports,
            'frequencies' => $this->scheduler->frequencies(),
            'formats' => $this->scheduler->formats(),
            'roles' => Role::query()->orderBy('name_ar')->get(['id', 'name_ar']),
            'stats' => [
                'total' => $schedules->total(),
                'active' => ReportSchedule::query()->where('status', 'active')->count(),
                'failed' => ReportSchedule::query()->where('last_result', 'failed')->count(),
                'sent_today' => ReportSchedule::query()->where('last_result', 'sent')->whereDate('last_run_at', today())->count(),
            ],
            'settings' => ScreenSettings::rows(ScreenSettings::SCREEN_REPORTS, $user),
            'scheduler' => $this->scheduler,
        ]);
    }

    /** سجلّ الإرسال — ماذا حدث فعلًا ومتى ولماذا فشل */
    public function log(Request $request, ReportSchedule $schedule): View
    {
        $this->guardFinancial($request, $schedule->report_tab);

        return view('admin.report-schedules.log', [
            'schedule' => $schedule,
            'runs' => $schedule->runs()->with('triggeredBy:id,name')->latest('ran_at')->paginate((int) setting('report_schedules.runs_per_page', 30)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $schedule = ReportSchedule::create([
            ...$data,
            'created_by' => $request->user()?->id,
        ]);

        $schedule->forceFill(['next_run_at' => $this->scheduler->nextRunAt($schedule)])->save();

        AuditTrail::log($request->user(), 'report_schedules.create', $schedule, [], $data);

        return back()->with('status', 'اتحفظت الجدولة ✓ — أوّل إرسال '.$schedule->next_run_at?->format('Y-m-d H:i'));
    }

    public function update(Request $request, ReportSchedule $schedule): RedirectResponse
    {
        $this->guardFinancial($request, $schedule->report_tab);

        $data = $this->validated($request);
        $old = $schedule->only(array_keys($data));

        $schedule->update($data);
        $schedule->forceFill(['next_run_at' => $this->scheduler->nextRunAt($schedule)])->save();

        AuditTrail::log($request->user(), 'report_schedules.edit', $schedule, $old, $data);

        return back()->with('status', 'اتحفظ التعديل ✓');
    }

    public function toggle(Request $request, ReportSchedule $schedule): RedirectResponse
    {
        $this->guardFinancial($request, $schedule->report_tab);

        $schedule->update(['status' => $schedule->isActive() ? 'paused' : 'active']);

        AuditTrail::log($request->user(), 'report_schedules.edit', $schedule, [], ['status' => $schedule->status]);

        return back()->with('status', $schedule->isActive() ? 'اترجّعت للجدول ✓' : 'اتوقفت ✓');
    }

    /** «شغّل الآن» — نفس مسار المهمّة المجدولة تمامًا، فما تراه هو ما سيُرسَل */
    public function run(Request $request, ReportSchedule $schedule): RedirectResponse
    {
        $this->guardFinancial($request, $schedule->report_tab);

        $result = $this->scheduler->run($schedule, $request->user(), manual: true);

        return $result['result'] === 'sent'
            ? back()->with('status', $result['message'])
            : back()->with('problem', $result['message']);
    }

    public function destroy(Request $request, ReportSchedule $schedule): RedirectResponse
    {
        $this->guardFinancial($request, $schedule->report_tab);

        AuditTrail::log($request->user(), 'report_schedules.delete', $schedule, $schedule->only(['name', 'report_tab']), []);

        $schedule->delete();

        return back()->with('status', 'اتحذفت الجدولة ✓');
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['settings' => ['required', 'array']]);

        ScreenSettings::putMany(ScreenSettings::SCREEN_REPORTS, $data['settings'], $request->user());

        return back()->with('status', 'اتحفظ ✓');
    }

    public function resetSettings(Request $request): RedirectResponse
    {
        $count = ScreenSettings::resetScreen(ScreenSettings::SCREEN_REPORTS, $request->user());

        return back()->with('status', 'رجعت '.$count.' قيمة للافتراضيّ ✓');
    }

    /** 🔒 حارس المجموعة المحميّة — يُنادى قبل أيّ فعل على جدولة ماليّة */
    private function guardFinancial(Request $request, string $tab): void
    {
        if ($this->scheduler->isFinancial($tab)) {
            abort_unless((bool) $request->user()?->isPlatformOwner(), 403, 'التقرير ده ماليّ — لمالك المنصّة وحده.');
        }
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'report_tab' => ['required', 'string', 'max:32'],
            'format' => ['required', 'string', 'max:8'],
            'frequency' => ['required', 'string', 'max:16'],
            'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:28'],
            'hour' => ['required', 'integer', 'min:0', 'max:23'],
            'timezone' => ['required', 'string', 'max:64'],
            'period_days' => ['required', 'integer', 'min:1', 'max:365'],
            'include_comparison' => ['nullable', 'boolean'],
            'skip_when_empty' => ['nullable', 'boolean'],
            'emails' => ['nullable', 'string', 'max:2000'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ], [], [
            'name' => 'اسم التقرير',
            'report_tab' => 'التقرير المصدر',
            'hour' => 'ساعة الإرسال',
            'timezone' => 'المنطقة الزمنيّة',
        ]);

        $reports = $this->scheduler->reportsFor($request->user());
        abort_unless(array_key_exists($data['report_tab'], $reports), 403, 'التقرير ده مش متاح ليك.');
        abort_unless(array_key_exists($data['format'], $this->scheduler->formats()), 422);
        abort_unless(array_key_exists($data['frequency'], $this->scheduler->frequencies()), 422);

        $emails = collect(preg_split('/[\s,;]+/', (string) ($data['emails'] ?? '')) ?: [])
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();

        return [
            'name' => trim($data['name']),
            'report_tab' => $data['report_tab'],
            'is_financial' => $this->scheduler->isFinancial($data['report_tab']),
            'format' => $data['format'],
            'frequency' => $data['frequency'],
            'day_of_week' => $data['frequency'] === 'weekly' ? (int) ($data['day_of_week'] ?? 0) : null,
            'day_of_month' => $data['frequency'] === 'monthly' ? (int) ($data['day_of_month'] ?? 1) : null,
            'hour' => (int) $data['hour'],
            'timezone' => $data['timezone'],
            'period_days' => (int) $data['period_days'],
            'include_comparison' => (bool) ($data['include_comparison'] ?? false),
            'skip_when_empty' => (bool) ($data['skip_when_empty'] ?? false),
            'recipient_emails' => $emails,
            'recipient_role_ids' => array_map('intval', $data['role_ids'] ?? []),
            'recipient_user_ids' => array_map('intval', $data['user_ids'] ?? []),
        ];
    }
}
