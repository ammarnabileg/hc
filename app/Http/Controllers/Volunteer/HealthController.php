<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Services\Volunteer\Org\DepartmentScope;
use App\Services\Volunteer\Org\HealthReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * صحّة القسم (24.4-7 · 13.4-ح).
 *
 * ⛔ **لمسؤول القسم والأبلاين المخوَّل فقط** — والمسار محميّ بـ`team_health.view`،
 * والعنصر مخفيّ من السايد بار لمن لا يملكه (2.15-أ-7)، ولا يراها عضوٌ عاديّ.
 * و«أخوكم» خارج كلّ حساباتها (13.4-ص-ج).
 */
class HealthController extends Controller
{
    public function __construct(
        private readonly DepartmentScope $scope,
        private readonly HealthReport $report,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $root = $this->scope->rootFor($user, (int) $request->query('entity') ?: null);

        $periods = (array) setting('volunteer.health.periods', [30, 90]);
        $period = (int) $request->query('period') ?: (int) setting('volunteer.health.default_period_days', 30);
        $period = in_array($period, array_map('intval', $periods), true) ? $period : (int) $periods[0];

        $tab = $request->query('tab') === 'oversight' ? 'oversight' : 'indicators';

        if (! $root) {
            return view('volunteer.org.health', [
                'root' => null, 'roots' => collect(), 'indicators' => null, 'leaderboard' => collect(),
                'oversight' => null, 'period' => $period, 'periods' => $periods, 'tab' => $tab,
                'filters' => [], 'subEntities' => collect(), 'overall' => 0,
            ]);
        }

        $memberships = $this->scope->memberships($this->scope->entityIds($root))
            ->when($request->query('sub'), fn ($c) => $c->where('entity_id', (int) $request->query('sub')));

        $indicators = $this->report->indicators($memberships, $period);

        return view('volunteer.org.health', [
            'root' => $root,
            'roots' => $this->scope->rootsFor($user),
            'indicators' => $indicators,
            'leaderboard' => $this->report->leaderboard($memberships, $period),
            // التاب الثاني لا يُبنى إلّا عند فتحه — تحميل كسول (2.15-أ · 2.7)
            'oversight' => $tab === 'oversight' ? $this->report->oversight($memberships, $user, $period) : null,
            'period' => $period,
            'periods' => $periods,
            'tab' => $tab,
            'filters' => ['sub' => $request->query('sub')],
            'subEntities' => $this->scope->subEntities($root),
            'overall' => $this->overall($indicators),
        ]);
    }

    /** المؤشّر العامّ (حلقة ملوّنة): متوسّط الالتزام مطروحًا منه التأخير والإرجاع */
    private function overall(array $indicators): int
    {
        $score = $indicators['commitment_percent']
            - ($indicators['late_percent'] / 2)
            - ($indicators['return_percent'] / 2);

        return (int) max(0, min(100, round($score)));
    }
}
