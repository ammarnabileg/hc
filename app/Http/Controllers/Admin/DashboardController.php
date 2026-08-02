<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Admin\AdminDashboard;
use App\Services\Admin\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * لوحة القيادة (الدستور 12.3 · 24.1).
 *
 * سؤال واحد للشاشة: «إيه حالة المنصّة وإيه المستنّي قرارك؟» —
 * فصفّ الكروت **أربعة بحدّ أقصى** والباقي في تاب «تفاصيل» بتحميل كسول (2.15).
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
        $days = $this->dashboard->resolveDays($request->integer('days') ?: null);
        $tab = $request->query('tab') === 'details' ? 'details' : 'overview';

        $data = [
            'tab' => $tab,
            'days' => $days,
            'rangeOptions' => $this->dashboard->rangeOptions(),
            'compare' => $request->boolean('compare', (bool) setting('admin.dashboard.compare_previous', true)),
            'tabs' => $this->tabs($tab, $days),
            'alerts' => $this->dashboard->alerts(),
            'quickActions' => $this->quickActions($user),
        ];

        // تحميل كسول للتابات: لا يُحسَب إلّا ما يُعرَض فعلًا (2.15-ب)
        $data += $tab === 'details'
            ? ['details' => $this->dashboard->details($days)]
            : [
                'kpis' => $this->dashboard->kpis($days),
                'series' => $this->dashboard->series($days),
                'pendingAccounts' => $this->dashboard->pendingAccounts(),
                'pendingAccountsCount' => $this->dashboard->pendingAccountsCount(),
                'pendingWork' => $this->dashboard->pendingWork(),
                'pendingWorkCounts' => $this->dashboard->pendingWorkCounts(),
                'activity' => $this->activity($request),
                'activityActors' => $this->activityActors($request),
                'canSeeActivity' => $user->allows('audit_logs.view'),
            ];

        return view('admin.dashboard.index', $data);
    }

    /** سجلّ النشاطات مفلتَر بالموظّف والنوع (12.3-20) — ولمن له صلاحيّته وحده */
    private function activity(Request $request)
    {
        if (! $request->user()->allows('audit_logs.view')) {
            return collect();
        }

        return $this->audit->feed(
            $request->integer('actor') ?: null,
            $request->query('action') ?: null,
            (int) setting('admin.dashboard.activity_rows', 10),
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

    /** @return array<int, array<string, string>> */
    private function tabs(string $current, int $days): array
    {
        return [
            ['key' => 'overview', 'label' => 'نظرة عامّة', 'url' => route('admin.dashboard', ['days' => $days])],
            ['key' => 'details', 'label' => 'تفاصيل', 'url' => route('admin.dashboard', ['tab' => 'details', 'days' => $days])],
        ];
    }

    /**
     * اختصارات سريعة (12.3-19) — تشير لمجالات أخرى تُبنى بالتوازي،
     * فلا يظهر منها إلّا المنشور فعلًا ولمن يملك صلاحيّته (2.15-أ-7).
     */
    private function quickActions(User $user): array
    {
        $candidates = [
            ['+ تدريب', 'admin.courses.index', 'courses.create'],
            ['+ منشور تعليمات', 'admin.guidance.index', 'announcements.create'],
            ['منح مكافأة', 'admin.rewards.index', 'manual_rewards.create'],
            ['إرسال إشعار', 'admin.guidance.index', 'notifications.create'],
            ['طلبات الاعتماد', 'admin.users.approvals', 'user_approvals.list'],
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
