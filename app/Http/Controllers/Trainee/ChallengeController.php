<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\User;
use App\Models\WarMatch;
use App\Services\Admin\Volunteer\WarSettingsService;
use App\Services\Gamification\LeaderboardService;
use App\Services\Gamification\WalletGateway;
use App\Services\Gamification\Wars\Exceptions\WarRuleException;
use App\Services\Gamification\Wars\MatchmakingService;
use App\Services\Gamification\Wars\WarMatchService;
use App\Services\Gamification\Wars\WarQuestionFunnel;
use App\Services\Gamification\Wars\WarRules;
use App\Services\Gamification\Wars\WarStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * التحديات — **حروب PvP** كما نصّ القسم 15.
 *
 * ملاحظة حَسْم: القسم 24.5 وصف شاشة تحدٍّ فرديّ بزرّ [ادخل التحدّي]، والقسم 15
 * وصف مواجهة بين محاربَين بمحصّلة صفريّة. **القسم 15 هو الحاكم** لأنّه
 * المواصفة الوظيفيّة، ولأنّ قاعدة منع الفارمينج (15.2-6) قاعدة اقتصاديّة
 * صارمة: التحدّي الفرديّ بمكافأة مسكوكة يفتح بابًا لضخّ تذاكر بلا مقابل.
 */
class ChallengeController extends Controller
{
    public function __construct(
        private readonly MatchmakingService $matchmaking,
        private readonly WarMatchService $matches,
        private readonly WarQuestionFunnel $funnel,
        private readonly WarRules $rules,
        private readonly WarStats $stats,
        private readonly LeaderboardService $leaderboards,
        private readonly WalletGateway $wallet,
    ) {}

    // ------------------------------------------------------------------ الساحات

    /** «المتاحة»: ساحات الحروب بشروط الدخول ومحصّلتها الصفريّة (15) */
    public function index(Request $request): View
    {
        $user = $request->user();

        $type = (string) $request->query('type', '');
        $state = (string) $request->query('state', '');
        $search = trim((string) $request->query('q', ''));

        $challenges = Challenge::query()
            ->when($type !== '', fn ($q) => $q->where('limits->type', $type))
            ->when($state === 'open', fn ($q) => $q->where('is_active', true))
            ->when($state === 'paused', fn ($q) => $q->where('is_active', false))
            ->when($search !== '', fn ($q) => $q->where('name_ar', 'like', "%{$search}%"))
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get();

        $readiness = $this->matchmaking->readinessOf($user);
        $stat = $this->stats->of($user);

        return view('challenges.index', [
            'challenges' => $challenges,
            'arenas' => $challenges->mapWithKeys(fn (Challenge $c) => [$c->id => $this->arenaCard($c)]),
            'readiness' => $readiness,
            'running' => $this->matchmaking->runningMatchOf($user),
            'filters' => compact('type', 'state', 'search'),
            'types' => $this->types(),
            'ticketsBalance' => $this->wallet->balance($user, 'tickets'),
            'gate' => $this->rules->readyTickets(),
            'stat' => $stat,
        ]);
    }

    /** شاشة الساحة: أيقونة ضخمة + هيدلاين + [استعداد] + المحاربون الجاهزون (15.1) */
    public function arena(Request $request, Challenge $challenge): View|RedirectResponse
    {
        $user = $request->user();

        // حرب التركيز ساحتها مختلفة تمامًا (15.3) — لها شاشتها المستقلّة
        if ($this->rules->typeOf($challenge) === 'focus') {
            return redirect()->route('challenges.focus.index');
        }

        $readiness = $this->matchmaking->readinessOf($user);
        $isReadyHere = $readiness && (int) $readiness->challenge_id === (int) $challenge->id;

        return view('challenges.arena', [
            'challenge' => $challenge,
            'card' => $this->arenaCard($challenge),
            'readiness' => $readiness,
            'isReadyHere' => $isReadyHere,
            'running' => $this->matchmaking->runningMatchOf($user),
            'fighters' => $isReadyHere ? $this->matchmaking->fighters($user, $challenge) : collect(),
            'stat' => $this->stats->of($user),
            'ticketsBalance' => $this->wallet->balance($user, 'tickets'),
        ]);
    }

    /** ضغط «استعداد» — والاستعداد حصريّ لنوع واحد (15.0) */
    public function ready(Request $request, Challenge $challenge): RedirectResponse
    {
        try {
            $this->matchmaking->ready($request->user(), $challenge);
        } catch (WarRuleException $e) {
            return back()->with('status', $e->getMessage())->with('topup_needed', $e->shortfall());
        }

        return redirect()->route('challenges.arena', $challenge)->with('status', (string) setting('wars.messages.ready', 'إنت دلوقتي مستعدّ ⚔️'));
    }

    /** إلغاء الاستعداد من الشريط العائم — من أيّ صفحة (15.0) */
    public function unready(Request $request): RedirectResponse
    {
        $user = $request->user();
        $match = $this->matchmaking->runningMatchOf($user);

        if ($match) {
            // إلغاء الاستعداد أثناء حرب نشطة = انسحاب صريح: −خسارة −عقوبة (15.0)
            $this->matches->withdraw($match, $user);
            $this->matchmaking->cancelReady($user);

            return redirect()->route('challenges.result', $match)
                ->with('status', (string) setting('wars.messages.withdrew_match', 'انسحبت من المواجهة — والخصم كسبها.'));
        }

        $this->matchmaking->cancelReady($user);

        return back()->with('status', (string) setting('wars.messages.ready_cancelled', 'اتلغى استعدادك — ارجع للساحة وقت ما تحبّ.'));
    }

    /** القائمة تتحدّث تلقائيًّا لحظة دخول أحدهم حربًا أو إلغائه الاستعداد (15.1) */
    public function fighters(Request $request, Challenge $challenge): JsonResponse
    {
        $user = $request->user();
        $readiness = $this->matchmaking->readinessOf($user);

        if (! $readiness || (int) $readiness->challenge_id !== (int) $challenge->id) {
            return response()->json(['ready' => false, 'fighters' => []]);
        }

        $fighters = $this->matchmaking->fighters($user, $challenge)->map(fn (array $row) => [
            'id' => $row['user']->id,
            'name' => $row['user']->name,
            'wins' => $row['wins'],
            'losses' => $row['losses'],
            'url' => route('challenges.duel', [$challenge, $row['user']]),
        ]);

        return response()->json(['ready' => true, 'fighters' => $fighters]);
    }

    /** [تحدّاه] ⟵ قفل ذرّيّ للطرفين ثمّ صفحة المواجهة (15.2-1) */
    public function duel(Request $request, Challenge $challenge, User $opponent): RedirectResponse
    {
        try {
            $match = $this->matchmaking->start($request->user(), $opponent, $challenge);
        } catch (WarRuleException $e) {
            return back()->with('status', $e->getMessage());
        }

        return redirect()->route('challenges.play', $match);
    }

    // ------------------------------------------------------------------ المواجهة

    /** شاشة المواجهة — تركيز بلا سايد بار، والاستئناف بلا خصمٍ ثانٍ (15.2-7) */
    public function play(Request $request, WarMatch $match): View|RedirectResponse
    {
        $user = $this->authorizeSide($request, $match);

        $this->matches->enforceTimers($match);
        $match->refresh();

        $side = $this->matches->sideOf($match, $user);

        if ($match->status !== 'running' || $side->status !== 'running') {
            return redirect()->route('challenges.result', $match);
        }

        return view('challenges.play', [
            'match' => $match,
            'challenge' => $match->challenge,
            'side' => $side,
            'rival' => $this->matches->rivalSide($match, $user)->user,
            'items' => $this->matches->publicItems($match),
            'answers' => $side->progress['answers'] ?? [],
            'questionSecondsLeft' => $this->matches->questionSecondsLeft($match, $side),
            'decisionSecondsLeft' => $this->matches->decisionSecondsLeft($match),
            'questionSeconds' => $this->rules->questionSeconds($match->challenge),
        ]);
    }

    /** Autosave: كلّ إجابة تُحفَظ وتُصحَّح على الخادم لحظيًّا (15.1) */
    public function answer(Request $request, WarMatch $match): JsonResponse
    {
        $user = $this->authorizeSide($request, $match);

        $data = $request->validate([
            'index' => ['required', 'integer', 'min:0'],
            'value' => ['nullable'],
        ]);

        $state = $this->matches->answer($match, $user, (int) $data['index'], $data['value'] ?? null);

        return response()->json($state + [
            'redirect' => $state['status'] === 'running' ? null : route('challenges.result', $match),
        ]);
    }

    /** حالة المواجهة لحظيًّا: عدّاد الحسم + هل خلّص الخصم (15.1) */
    public function state(Request $request, WarMatch $match): JsonResponse
    {
        $user = $this->authorizeSide($request, $match);

        $this->matches->enforceTimers($match);
        $match->refresh();

        $side = $this->matches->sideOf($match, $user);
        $rival = $this->matches->rivalSide($match, $user);

        return response()->json([
            'status' => $match->status,
            'rival_finished' => $rival->status !== 'running',
            'decision_seconds' => $this->matches->decisionSecondsLeft($match),
            'question_seconds' => $this->matches->questionSecondsLeft($match, $side),
            'redirect' => $match->status === 'running' && $side->status === 'running'
                ? null
                : route('challenges.result', $match),
        ]);
    }

    public function submit(Request $request, WarMatch $match): RedirectResponse
    {
        $user = $this->authorizeSide($request, $match);

        $this->matches->finishSide($match, $this->matches->sideOf($match, $user));

        return redirect()->route('challenges.result', $match);
    }

    /** الانسحاب إجراء **متعمَّد** وحده — والانقطاع لا يعاقِب (15.2-2) */
    public function withdraw(Request $request, WarMatch $match): RedirectResponse
    {
        $user = $this->authorizeSide($request, $match);

        $this->matches->withdraw($match, $user);

        return redirect()->route('challenges.result', $match)
            ->with('status', (string) setting('wars.messages.withdrew_penalty', 'انسحبت — والانسحاب بيكلّف، خلّي بالك المرّة الجاية.'));
    }

    /** شاشة النتيجة: فوز · خسارة · **تعادل** (15.2-5) */
    public function result(Request $request, WarMatch $match): View|RedirectResponse
    {
        $user = $this->authorizeSide($request, $match);

        $this->matches->enforceTimers($match);
        $match->refresh();

        $side = $this->matches->sideOf($match, $user);

        if ($match->status === 'running' && $side->status === 'running') {
            return redirect()->route('challenges.play', $match);
        }

        return view('challenges.result', [
            'match' => $match,
            'challenge' => $match->challenge,
            'side' => $side,
            'rivalSide' => $this->matches->rivalSide($match, $user),
            'total' => count((array) $match->questions),
            'waiting' => $match->status === 'running',
            'decisionSecondsLeft' => $this->matches->decisionSecondsLeft($match),
            'celebration' => $match->status === 'finished' ? $this->matches->celebrationFor($side) : null,
            'ticketsBalance' => $this->wallet->balance($user, 'tickets'),
        ]);
    }

    // ------------------------------------------------------------------ صفحاتي

    /** تحدّياتي: مواجهات جارية · منتهية بنتيجتها */
    public function mine(Request $request): View
    {
        $user = $request->user();
        $tab = $request->query('tab') === 'done' ? 'done' : 'running';

        $sides = ChallengeParticipation::query()
            ->with(['challenge'])
            ->whereNotNull('war_match_id')
            ->where('user_id', $user->id)
            ->orderByDesc('started_at')
            ->get();

        $matches = WarMatch::query()
            ->whereIn('id', $sides->pluck('war_match_id'))
            ->get()
            ->keyBy('id');

        foreach ($matches as $match) {
            if ($match->status === 'running') {
                $this->matches->enforceTimers($match);
            }
        }

        $sides = $sides->map->refresh();

        return view('challenges.mine', [
            'tab' => $tab,
            'matches' => $matches->map->refresh(),
            'running' => $sides->where('status', 'running')->values(),
            'done' => $sides->where('status', '!=', 'running')->values(),
            'stat' => $this->stats->of($user),
        ]);
    }

    /** لوحة الأبطال — صفّي مثبَّت أسفل القائمة دائمًا */
    public function leaderboard(Request $request): View
    {
        $user = $request->user();
        $days = (int) $request->query('days', (int) setting('ux.lists.default_range_days', 30));
        $challengeId = $request->query('challenge') ? (int) $request->query('challenge') : null;
        $search = trim((string) $request->query('q', ''));

        return view('challenges.leaderboard', [
            'board' => $this->leaderboards->champions($user, $challengeId, $days, $search ?: null),
            'challenges' => Challenge::query()->orderBy('name_ar')->get(['id', 'name_ar']),
            'filters' => ['days' => $days, 'challenge' => $challengeId, 'q' => $search],
        ]);
    }

    // ------------------------------------------------------------------ داخليّ

    /** الطرفان وحدهما يريان المواجهة — وغيرهما 403 لا صفحة فارغة */
    private function authorizeSide(Request $request, WarMatch $match): User
    {
        $user = $request->user();

        abort_unless($match->involves($user->id), 403, (string) setting('wars.messages.not_your_match', 'دي مواجهة ناس تانية.'));

        return $user;
    }

    /** بطاقة الساحة: النصوص والعتبات كلّها من الإعدادات (12.10-ج) */
    private function arenaCard(Challenge $challenge): array
    {
        $type = $this->rules->typeOf($challenge);

        // عنوان الساحة وسطرها التعريفيّ إعدادان لكلّ نوع (2.13)، وOverride الحرب فوقهما
        $anchors = [
            'knowledge' => ['ساحة الحرب', 'اختبر مهاراتك الذهنية والسرعة، وواجه خصمك وجهًا لوجه!'],
            'survival' => ['ساحة البقاء', 'جاوب صح وابقى… أول غلطة تخرجك!'],
            'estimation' => ['ساحة التقدير', 'قدّر الرقم الأقرب للصح واكسب!'],
            'focus' => ['ساحة التركيز', 'عمل عميق بلا مقاطعة — والعدّ مبنيّ على أمانتك.'],
        ];

        [$anchorHeadline, $anchorTagline] = $anchors[$type] ?? $anchors['knowledge'];

        $headline = (string) setting("wars.arena.{$type}.headline", $anchorHeadline);
        $tagline = (string) setting("wars.arena.{$type}.tagline", $anchorTagline);

        return [
            'type' => $type,
            'headline' => $this->rules->text($challenge, 'headline', $headline),
            'tagline' => $this->rules->text($challenge, 'tagline', $tagline),
            'win' => $this->rules->winAmount($challenge),
            'loss' => $this->rules->lossAmount($challenge),
            'withdraw' => $this->rules->withdrawPenalty($challenge),
            'gate' => $this->rules->readyTickets($challenge),
            'questions' => $this->rules->questionCount($type),
            'bank_ready' => $this->funnel->isBankReady($challenge),
            'locked' => WarSettingsService::isActive($challenge),
        ];
    }

    /** أنواع الحروب المعتمَدة (15) — التسمية من الإعدادات لا محروقة */
    private function types(): array
    {
        $types = setting('challenges.types', null);

        return is_array($types) ? $types : [
            'knowledge' => 'حرب المعلومات',
            'focus' => 'حرب التركيز',
            'survival' => 'حرب البقاء',
            'estimation' => 'حرب التقدير',
        ];
    }
}
