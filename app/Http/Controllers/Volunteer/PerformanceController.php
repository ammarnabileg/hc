<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use App\Services\Volunteer\Goals\ChampionService;
use App\Services\Volunteer\Goals\EntityScope;
use App\Services\Volunteer\Goals\LeadershipService;
use App\Services\Volunteer\Goals\RepService;
use App\Services\Volunteer\Goals\VxpDistributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * الأداء: VXP وترتيبي · درجة الالتزام · مشرف الشهر · تقييماتي (الدستور 24.4 · 13.4-ن).
 *
 * كلّ قيمة Rep في هذه الشاشات تُقرأ من `rep_rule()`، وكلّ مهلة من `setting()` —
 * فما يظهر للمتطوّع هو ما في لوحة الإدارة بالضبط، بلا رقم مكتوب في الواجهة.
 */
class PerformanceController extends Controller
{
    public function __construct(
        private readonly RepService $rep,
        private readonly ChampionService $champion,
        private readonly LeadershipService $leadership,
        private readonly EntityScope $scope,
    ) {}

    // ------------------------------------------------------------------ VXP وترتيبي

    public function vxp(Request $request): View
    {
        $user = $request->user();

        $filters = [
            'scope' => $request->string('scope')->toString() ?: 'all',
            'days' => (int) ($request->integer('days') ?: setting('performance.vxp.curve_days', 30)),
            'q' => trim($request->string('q')->toString()),
        ];

        $board = $this->leaderboard($user, $filters);

        return view('volunteer.performance.vxp', [
            'balance' => round($this->balanceOf($user, VxpDistributionService::CURRENCY), 2),
            'rank' => $board['rank'],
            'total' => $board['total'],
            'earned' => $board['earned'],
            'rows' => $board['rows'],
            'me' => $board['me'],
            'series' => $this->rep->vxpSeries($user, $filters['days']),
            'sources' => $this->vxpSources($user, $filters['days']),
            'filters' => $filters,
            'scopes' => ['all' => (string) setting('performance.screen.vxp_msg', 'كلّ المتطوّعين'), 'entity' => (string) setting('performance.screen.vxp_msg_2', 'قسمي'), 'track' => (string) setting('performance.screen.vxp_msg_3', 'مساري')],
        ]);
    }

    // ------------------------------------------------------------------ درجة الالتزام

    public function rep(Request $request): View
    {
        $user = $request->user();

        $filters = [
            'source' => $request->string('source')->toString(),
            'entity' => $request->integer('entity') ?: null,
            'days' => (int) ($request->integer('days') ?: setting('ux.lists.default_range_days', 30)),
        ];

        $score = $this->rep->score($user);
        $movements = $this->rep->movements($user, $filters);

        return view('volunteer.performance.rep', [
            'score' => $score,
            'bounds' => $this->rep->bounds(),
            'warning' => $this->rep->warningThreshold(),
            'red' => $this->rep->redThreshold(),
            'state' => $this->rep->state($score),
            'isRed' => $score <= $this->rep->redThreshold(),
            'series' => $this->rep->dailySeries($user, $filters['days']),
            'movements' => $movements,
            'dailyCap' => $this->rep->dailyLossCap(),
            'lostToday' => $this->rep->lostToday($user),
            'exceeded' => $this->rep->exceededToday($user),
            'howToEarn' => $this->rep->howToEarn(),
            'nextReset' => $this->rep->nextResetAt(),
            'objectionDays' => $this->rep->objectionWindowDays(),
            'canObject' => $movements->mapWithKeys(fn (Transaction $t) => [$t->id => $this->rep->canObject($t)]),
            'unapplied' => $movements->mapWithKeys(fn (Transaction $t) => [$t->id => $this->rep->unappliedAmount($t)]),
            'filters' => $filters,
            'sources' => $this->repSourceLabels(),
            'memberships' => $this->scope->memberships($user),
        ]);
    }

    /** اعتراض على حركة ما دامت داخل مهلة الاعتراض (13.4-ط) */
    public function objectRep(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        abort_unless($transaction->user_id === $request->user()->id, 403);
        abort_unless($this->rep->canObject($transaction), 409, (string) setting('performance.screen.object_rep_msg', 'انتهت مهلة الاعتراض على هذه الحركة.'));

        $this->rep->openObjection($transaction, $request->user(), $data['reason']);

        return back()->with('status', (string) setting('performance.screen.object_rep_ok', 'اترفع اعتراضك ✓ — هيتراجع خلال نافذة القرار.'));
    }

    // ------------------------------------------------------------------ مشرف الشهر

    public function champion(Request $request): View
    {
        $user = $request->user();

        $filters = [
            'entity' => $request->integer('entity') ?: null,
            'month' => $request->string('month')->toString() ?: null,
        ];

        $board = $this->champion->board($filters['entity'], $filters['month']);

        return view('volunteer.performance.champion', [
            'board' => $board,
            'filters' => $filters,
            'criteria' => $this->champion->criteriaStatement(),
            'nextUpdate' => $this->champion->nextUpdateAt(),
            'archive' => $this->champion->archiveMonths(),
            'memberships' => $this->scope->memberships($user),
        ]);
    }

    // ------------------------------------------------------------------ تقييماتي

    public function evaluations(Request $request): View
    {
        $user = $request->user();
        $tab = $request->string('tab')->toString() ?: 'received';
        $upline = $this->leadership->uplineOf($user);
        $summary = $this->leadership->receivedSummary($user);

        return view('volunteer.performance.evaluations', [
            'tab' => $tab,
            'upline' => $upline,
            // أثر متوسّطي على Rep — من `rep_rule()` لا من رقم في الواجهة
            'myImpact' => $summary['visible']
                ? rep_rule($this->rep->leadershipRuleKeyFor((float) $summary['average']))
                : null,
            'criteria' => $this->leadership->criteria(),
            'maxScore' => $this->leadership->maxScore(),
            'minRaters' => $this->leadership->minRaters(),
            'alreadyEvaluated' => $upline ? $this->leadership->alreadyEvaluated($user, $upline) : false,
            'weekStart' => $this->leadership->weekStart(),
            'summary' => $summary,
            'series' => $this->leadership->weeklySeries($user),
            'given' => $this->leadership->givenBy($user),
            'impact' => $this->rep->leadershipImpactTable(),
            'windowWeeks' => $this->leadership->windowWeeks(),
        ]);
    }

    /** فورم التقييم: منزلق /10 لكلّ معيار — مجهول تمامًا، ومن الداونلاين للأبلاين فقط */
    public function storeEvaluation(Request $request): RedirectResponse
    {
        $user = $request->user();
        $upline = $this->leadership->uplineOf($user);

        abort_unless($upline !== null, 403, (string) setting('performance.screen.store_evaluation_empty', 'مفيش أبلاين مباشر لتقييمه.'));

        $data = $request->validate([
            'scores' => ['required', 'array'],
            'scores.*' => ['numeric', 'min:0', 'max:'.$this->leadership->maxScore()],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->leadership->submit($user, $upline, $data['scores'], $data['note'] ?? null);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()
            ->route('volunteer.performance.evaluations', ['tab' => 'given'])
            ->with('status', (string) setting('performance.screen.store_evaluation_ok', 'اتسجّل تقييمك ✓ — مجهول تمامًا ويظهر متوسّطًا فقط.'));
    }

    // ------------------------------------------------------------------ داخليّ

    private function balanceOf(User $user, string $code): float
    {
        return (float) WalletBalance::query()
            ->where('user_id', $user->id)
            ->whereHas('currency', fn ($q) => $q->where('code', $code))
            ->value('balance');
    }

    /**
     * الليدر بورد: صفوف مرتّبة + **صفّي أنا** ليُثبَّت أسفل القائمة دائمًا.
     *
     * @return array{rows:Collection,me:?array,rank:int,total:int,earned:float}
     */
    private function leaderboard(User $user, array $filters): array
    {
        $currencyId = Currency::query()->where('code', VxpDistributionService::CURRENCY)->value('id');

        $userIds = $this->boardUserIds($user, $filters['scope']);

        $wallets = WalletBalance::query()
            ->where('currency_id', $currencyId)
            ->whereIn('user_id', $userIds)
            ->orderByDesc('balance')
            ->get(['user_id', 'balance', 'lifetime_earned']);

        $users = User::query()->whereIn('id', $wallets->pluck('user_id'))->get()->keyBy('id');
        $memberships = Membership::query()
            ->whereIn('user_id', $wallets->pluck('user_id'))->where('status', 'active')
            // «أخوكم» لا يُحتسَب في الليدر بورد (13.4-ص-ج)
            ->whereDoesntHave('position', fn ($q) => $q->where('is_honorary', true))
            ->with('entity', 'position')->get()->keyBy('user_id');

        $gains = Transaction::query()
            ->where('currency_id', $currencyId)
            ->whereIn('user_id', $wallets->pluck('user_id'))
            ->where('created_at', '>=', now()->subDays($filters['days']))
            ->selectRaw('user_id, COALESCE(SUM(CASE WHEN COALESCE(applied_amount, amount) > 0 THEN COALESCE(applied_amount, amount) ELSE 0 END), 0) AS total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $rank = 0;
        $myRank = 0;
        $me = null;

        $rows = $wallets->map(function ($wallet) use (&$rank, &$myRank, &$me, $users, $memberships, $gains, $user) {
            $rank++;
            $row = [
                'rank' => $rank,
                'user' => $users->get($wallet->user_id),
                'membership' => $memberships->get($wallet->user_id),
                'balance' => round((float) $wallet->balance, 2),
                'gain' => round((float) ($gains[$wallet->user_id] ?? 0), 2),
            ];

            if ((int) $wallet->user_id === $user->id) {
                $myRank = $rank;
                $me = $row;
            }

            return $row;
        })->filter(function (array $row) use ($filters) {
            return $filters['q'] === '' || str_contains(mb_strtolower((string) $row['user']?->name), mb_strtolower($filters['q']));
        })->values();

        return [
            'rows' => $rows,
            'me' => $me,
            'rank' => $myRank,
            'total' => $wallets->count(),
            'earned' => round((float) ($gains[$user->id] ?? 0), 2),
        ];
    }

    private function boardUserIds(User $user, string $scope): array
    {
        $query = Membership::query()->where('status', 'active')
            // «أخوكم» خارج الليدر بورد ومشرف الشهر ونطاقات الإشراف (13.4-ص-ج)
            ->whereDoesntHave('position', fn ($q) => $q->where('is_honorary', true));

        if ($scope === 'entity' && ($entityId = $user->activeMembership()?->entity_id)) {
            $query->whereIn('entity_id', $this->scope->withDescendants([(int) $entityId]));
        }

        if ($scope === 'track') {
            $trackId = $user->activeMembership()?->entity?->track_id;

            if ($trackId) {
                $query->whereIn('entity_id', Entity::query()->where('track_id', $trackId)->select('id'));
            }
        }

        return $query->pluck('user_id')->unique()->values()->all();
    }

    /** كارت «مصادر نقاطي»: مهامّ · مساهمات · نوبات متكرّرة · قرارات محكّم */
    private function vxpSources(User $user, int $days): array
    {
        $currencyId = Currency::query()->where('code', VxpDistributionService::CURRENCY)->value('id');

        $labels = [
            'task' => (string) setting('performance.screen.vxp_sources_msg', 'مهامّ'),
            'contribution' => (string) setting('performance.screen.vxp_sources_msg_2', 'مساهمات'),
            'recurring' => (string) setting('performance.screen.vxp_sources_msg_3', 'نوبات متكرّرة'),
            'arbitration' => (string) setting('performance.screen.vxp_sources_msg_4', 'قرارات محكّم'),
        ];

        $rows = Transaction::query()
            ->where('user_id', $user->id)
            ->where('currency_id', $currencyId)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('source, COALESCE(SUM(CASE WHEN COALESCE(applied_amount, amount) > 0 THEN COALESCE(applied_amount, amount) ELSE 0 END), 0) AS total')
            ->groupBy('source')
            ->pluck('total', 'source');

        $sources = [];

        foreach ($labels as $key => $label) {
            $sources[] = ['key' => $key, 'label' => $label, 'value' => round((float) ($rows[$key] ?? 0), 2)];
        }

        return $sources;
    }

    private function repSourceLabels(): array
    {
        return [
            'task' => (string) setting('performance.screen.rep_source_labels_msg', 'مهامّ'),
            'meeting' => (string) setting('performance.screen.rep_source_labels_msg_2', 'اجتماعات'),
            'academy' => (string) setting('performance.screen.rep_source_labels_msg_3', 'أكاديمية'),
            'leadership' => (string) setting('performance.screen.rep_source_labels_msg_4', 'مؤشّر القيادة'),
            'behavior' => (string) setting('performance.screen.rep_source_labels_msg_5', 'سلوك'),
        ];
    }
}
