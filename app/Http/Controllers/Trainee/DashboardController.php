<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use App\Services\Dashboard\DashboardStatsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * الرئيسيّة — داشبورد المستخدم (الدستور 14 · 24.5).
 * سؤال واحد للشاشة: «أين أقف في كلّ تدريباتي؟» — والباقي تابات (2.15-أ-1).
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly DashboardStatsService $stats,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $this->resolveTab($request, $user);
        $hasEnrollments = $this->dashboard->hasEnrollments($user);

        $data = [
            'tab' => $tab,
            'hasEnrollments' => $hasEnrollments,
            'greeting' => str_replace(
                ':name',
                $user->shortName(),
                (string) setting('dashboard.header.greeting', 'أهلًا :name'),
            ),
            'primaryLabel' => (string) setting('dashboard.primary_action.label', 'أكمل آخر درس'),
            'nextLesson' => $hasEnrollments ? $this->dashboard->nextLesson($user) : null,
            'tabs' => $this->tabs($tab),
        ];

        // تحميل كسول للتابات (2.15-د): لا يُحسَب إلّا ما يُعرَض فعلًا
        $data += match ($tab) {
            'details' => ['details' => $this->dashboard->details($user)],
            'stats' => $this->statsData($request, $user),
            default => [
                'kpis' => $this->dashboard->kpis($user),
                'courses' => $this->dashboard->activeCourses($user),
                'deadlines' => $this->dashboard->upcomingDeadlines($user),
            ],
        };

        return view('dashboard.index', $data);
    }

    /** بيانات تاب «إحصائيّاتي» — بفلتر الفترة الخاصّ به وحده لا بفلاتر على رأس الصفحة */
    private function statsData(Request $request, $user): array
    {
        $days = $this->stats->resolveRange($request->integer('days') ?: null);

        return [
            'days' => $days,
            'rangeOptions' => $this->stats->rangeOptions(),
            'xpSeries' => $this->stats->xpSeries($user, $days),
            'donut' => $this->stats->completionDonut($user),
            'heatmap' => $this->stats->attendanceHeatmap($user),
            'radar' => $this->stats->achievementsRadar($user),
            'ticketBars' => $this->stats->ticketBars($user, $days),
            // ميزان التذاكر الكلّيّ — يفسّر لماذا تختلف أرقام التذاكر الثلاثة (ن-2)
            'ticketSheet' => $this->stats->ticketBalanceSheet($user),
        ];
    }

    /** @return array<int, array<string, string>> */
    private function tabs(string $current): array
    {
        return collect([
            'overview' => (string) setting('dashboard.screen.tabs_msg', 'نظرة عامّة'),
            'details' => (string) setting('dashboard.screen.tabs_msg_2', 'تفاصيل'),
            'stats' => (string) setting('dashboard.screen.tabs_msg_3', 'إحصائيّاتي'),
        ])->map(fn (string $label, string $key) => [
            'key' => $key,
            'label' => $label,
            'url' => route('dashboard', $key === 'overview' ? [] : ['tab' => $key]),
        ])->values()->all();
    }

    /** الصفحة تفتح على آخر تاب فُتِح فيها ويُحفَظ لكلّ مستخدم (2.15-د) */
    private function resolveTab(Request $request, $user): string
    {
        $allowed = ['overview', 'details', 'stats'];
        $requested = (string) $request->query('tab', '');

        if (in_array($requested, $allowed, true)) {
            $tabs = $user->last_tabs ?? [];

            if (($tabs['dashboard'] ?? null) !== $requested) {
                $tabs['dashboard'] = $requested;
                $user->forceFill(['last_tabs' => $tabs])->saveQuietly();
            }

            return $requested;
        }

        $remembered = ($user->last_tabs ?? [])['dashboard'] ?? 'overview';

        return in_array($remembered, $allowed, true) ? $remembered : 'overview';
    }
}
