<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\AdminDashboard;
use App\Services\Admin\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * لوحة القيادة (الدستور 12.3 · 24.1).
 *
 * سؤال واحد للشاشة: «إيه حالة المنصّة وإيه المستنّي قرارك؟» —
 * فصفّ الكروت **أربعة بحدّ أقصى** والباقي في تاب «تفاصيل» بتحميل كسول (2.15).
 *
 * والفلتر «من/إلى» **نفسه المستعمَل في 12.8** — مفهومٌ واحد فسلوكٌ واحد.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboard $dashboard,
        private readonly AuditTrail $audit,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $period = $this->period($request);
        $tab = $request->query('tab') === 'details' ? 'details' : 'overview';

        $data = [
            'tab' => $tab,
            'period' => $period,
            'quickRanges' => $this->dashboard->quickRanges($period),
            'compare' => $period['compare'],
            'tabs' => $this->tabs($tab, $period),
            'alerts' => $this->dashboard->alerts(),
            'quickActions' => $this->quickActions($user),
            // «آخر تحديث HH:MM» + التحديث التلقائيّ (12.3-5) — كلاهما من الإعدادات
            'lastUpdated' => now()->format('H:i'),
            'autoRefresh' => (bool) setting('admin.dashboard.auto_refresh', true),
            'refreshSeconds' => max(15, (int) setting('admin.dashboard.refresh_seconds', 120)),
            'canCustomize' => $user->allows('settings_general.edit'),
            'cardCatalog' => $this->dashboard->cardCatalog(),
            'layout' => $this->dashboard->layoutFor($user),
            'roleKey' => $this->roleKey($user),
        ];

        // تحميل كسول للتابات: لا يُحسَب إلّا ما يُعرَض فعلًا (2.15-ب)
        $data += $tab === 'details'
            ? ['details' => $this->dashboard->details($period, $user)]
            : [
                'kpis' => $this->dashboard->kpis($period, $user),
                'series' => $this->dashboard->series($period),
                'pendingAccounts' => $this->dashboard->pendingAccounts(),
                'pendingAccountsCount' => $this->dashboard->pendingAccountsCount(),
                'pendingWork' => $this->dashboard->pendingWork(),
                'pendingWorkCounts' => $this->dashboard->pendingWorkCounts(),
                'activity' => $this->activity($request, $period),
                'activityActors' => $this->activityActors($request),
                'canSeeActivity' => $user->allows('audit_logs.view'),
                'canExportActivity' => $user->allows('audit_logs.export'),
            ];

        return view('admin.dashboard.index', $data);
    }

    /**
     * تصدير سجلّ النشاطات (12.3-20) — بنفس فلاتر الشاشة وبصلاحيّته المستقلّة.
     * وBOM في أوّل الملفّ حتى تفتح العربيّة سليمةً في إكسل بلا خطوة إضافيّة.
     */
    public function exportActivity(Request $request): StreamedResponse
    {
        $period = $this->period($request);

        $rows = $this->audit->feed(
            $request->integer('actor') ?: null,
            $request->query('action') ?: null,
            max(1, (int) setting('admin.dashboard.activity_export_rows', 5000)),
            $period['from'],
            $period['to'],
        );

        $filename = 'activity-log-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [(string) setting('admin_dashboard.screen.export_activity_msg', 'الوقت'), (string) setting('admin_dashboard.screen.export_activity_msg_2', 'الموظّف'), (string) setting('admin_dashboard.screen.export_activity_msg_3', 'الإجراء'), (string) setting('admin_dashboard.screen.export_activity_msg_4', 'الكيان'), 'IP']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->created_at?->format('Y-m-d H:i'),
                    $row->user?->name ?? '—',
                    AuditTrail::label($row->action),
                    class_basename((string) $row->auditable_type).'#'.$row->auditable_id,
                    $row->ip ?? '—',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * حفظ تخصيص اللوحة **لهذا الدور** (12.3-3): الترتيب والمخفيّ.
     * ويعيش في جدول الإعدادات الواحد — فلا عمود جديد ولا مفتاح مبعثر (2.13).
     */
    public function saveLayout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'max:64'],
            'order' => ['array'],
            'order.*' => ['string', 'max:64'],
            'hidden' => ['array'],
            'hidden.*' => ['string', 'max:64'],
        ]);

        $catalog = array_keys($this->dashboard->cardCatalog());

        $layouts = $this->dashboard->saveLayout(
            $validated['role'],
            array_values(array_intersect($validated['order'] ?? [], $catalog)),
            array_values(array_intersect($validated['hidden'] ?? [], $catalog)),
        );

        Setting::updateOrCreate(['key' => 'admin.dashboard.role_layouts'], [
            'group' => 'admin_dashboard',
            'label_ar' => (string) setting('admin_dashboard.screen.save_layout_msg', 'تخصيص كروت لوحة القيادة لكلّ دور'),
            'type' => 'json',
            'value' => json_encode($layouts, JSON_UNESCAPED_UNICODE),
        ]);

        Cache::forget('settings');

        return back()->with('status', (string) setting('admin.dashboard.layout_saved_text', 'اتحفظ ✓ — ترتيب اللوحة للدور ده اتسجّل.'));
    }

    // ------------------------------------------------------------------ داخليّ

    /** فلتر الفترة الموحَّد مع 12.8 — والاختصار `days` يملأ التاريخين لا يستبدلهما */
    private function period(Request $request): array
    {
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;

        if ($from === null && $to === null && ($days = $request->integer('days')) > 0) {
            $to = now()->toDateString();
            $from = now()->subDays($days - 1)->toDateString();
        }

        return $this->dashboard->period(
            $from,
            $to,
            $request->boolean('compare', (bool) setting('admin.dashboard.compare_previous', true)),
        );
    }

    /** سجلّ النشاطات مفلتَر بالموظّف والنوع والفترة (12.3-20) — ولمن له صلاحيّته وحده */
    private function activity(Request $request, array $period)
    {
        if (! $request->user()->allows('audit_logs.view')) {
            return collect();
        }

        return $this->audit->feed(
            $request->integer('actor') ?: null,
            $request->query('action') ?: null,
            (int) setting('admin.dashboard.activity_rows', 10),
            $period['from'],
            $period['to'],
        );
    }

    /** موظّفو المنصّة الذين لهم أثر في السجلّ — لملء فلتر «الموظّف» */
    private function activityActors(Request $request)
    {
        if (! $request->user()->allows('audit_logs.view')) {
            return collect();
        }

        return User::query()
            ->whereIn('id', AuditLog::query()->distinct()->pluck('user_id')->filter())
            ->get(['id', 'name']);
    }

    /** الدور الذي يُحفَظ له التخصيص — أوّل دور للمستخدم، وإلّا فالافتراضيّ */
    private function roleKey(User $user): string
    {
        return (string) ($user->roles()->pluck('key')->first()
            ?? setting('admin.dashboard.default_layout_role', 'platform_owner'));
    }

    /** @return array<int, array<string, string>> */
    private function tabs(string $current, array $period): array
    {
        $query = [
            'from' => $period['from']->toDateString(),
            'to' => $period['to']->toDateString(),
            'compare' => $period['compare'] ? 1 : null,
        ];

        return [
            ['key' => 'overview', 'label' => (string) setting('admin_dashboard.screen.tabs_msg', 'نظرة عامّة'), 'url' => route('admin.dashboard', array_filter($query))],
            ['key' => 'details', 'label' => (string) setting('admin_dashboard.screen.tabs_msg_2', 'تفاصيل'), 'url' => route('admin.dashboard', array_filter($query + ['tab' => 'details']))],
        ];
    }

    /**
     * اختصارات سريعة (12.3-19) — تشير لمجالات أخرى تُبنى بالتوازي،
     * فلا يظهر منها إلّا المنشور فعلًا ولمن يملك صلاحيّته (2.15-أ-7).
     */
    private function quickActions(User $user): array
    {
        $candidates = [
            [(string) setting('admin_dashboard.screen.quick_actions_msg', '+ تدريب'), 'admin.courses.index', 'courses.create'],
            [(string) setting('admin_dashboard.screen.quick_actions_msg_2', '+ منشور تعليمات'), 'admin.guidance.index', 'announcements.create'],
            [(string) setting('admin_dashboard.screen.quick_actions_msg_3', 'منح مكافأة'), 'admin.rewards.index', 'manual_rewards.create'],
            [(string) setting('admin_dashboard.screen.quick_actions_msg_4', 'إرسال إشعار'), 'admin.guidance.index', 'notifications.create'],
            [(string) setting('admin_dashboard.screen.quick_actions_msg_5', 'طلبات الاعتماد'), 'admin.users.approvals', 'user_approvals.list'],
        ];

        $actions = [];

        foreach ($candidates as [$label, $route, $permission]) {
            if (Route::has($route) && $user->allows($permission)) {
                $actions[] = ['label' => $label, 'url' => route($route)];
            }
        }

        return $actions;
    }
}
