<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Services\Gamification\ChallengeService;
use App\Services\Gamification\Exceptions\InsufficientBalanceException;
use App\Services\Gamification\LeaderboardService;
use App\Services\Gamification\WalletGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * التحديات/الحروب (15 · 24.5).
 * سؤال واحد لكلّ شاشة، وثلاثة فلاتر ظاهرة + بحث، والتفاصيل في بوب-أب (2.15).
 */
class ChallengeController extends Controller
{
    public function __construct(
        private readonly ChallengeService $challenges,
        private readonly LeaderboardService $leaderboards,
        private readonly WalletGateway $wallet,
    ) {}

    /** المتاحة: كروت الحروب + فلاتر (النوع · التكلفة · المدّة) + بحث */
    public function index(Request $request): View
    {
        $user = $request->user();

        $type = (string) $request->query('type', '');
        $cost = (string) $request->query('cost', '');
        $duration = (string) $request->query('duration', '');
        $search = trim((string) $request->query('q', ''));

        $challenges = Challenge::query()
            ->with('entry_currency')
            ->when($type !== '', fn ($q) => $q->where('limits->type', $type))
            ->when($cost === 'free', fn ($q) => $q->where('entry_cost', '<=', 0))
            ->when($cost === 'low', fn ($q) => $q->whereBetween('entry_cost', [0.01, (float) setting('challenges.filter.low_cost_max', 5)]))
            ->when($cost === 'high', fn ($q) => $q->where('entry_cost', '>', (float) setting('challenges.filter.low_cost_max', 5)))
            ->when($duration === 'short', fn ($q) => $q->where('duration_minutes', '<=', (int) setting('challenges.filter.short_minutes', 10)))
            ->when($duration === 'medium', fn ($q) => $q->whereBetween('duration_minutes', [
                (int) setting('challenges.filter.short_minutes', 10) + 1,
                (int) setting('challenges.filter.medium_minutes', 30),
            ]))
            ->when($duration === 'long', fn ($q) => $q->where('duration_minutes', '>', (int) setting('challenges.filter.medium_minutes', 30)))
            ->when($search !== '', fn ($q) => $q->where('name_ar', 'like', "%{$search}%"))
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get();

        $running = ChallengeParticipation::query()
            ->where('user_id', $user->id)
            ->where('status', 'running')
            ->pluck('id', 'challenge_id');

        // عدد المشاركين على الكارت — استعلامٌ واحد مجمَّع لا استعلام لكلّ كارت
        $participants = ChallengeParticipation::query()
            ->groupBy('challenge_id')
            ->get(['challenge_id', DB::raw('count(distinct user_id) as people')])
            ->mapWithKeys(fn ($row) => [(int) $row->challenge_id => (int) $row->people]);

        return view('challenges.index', [
            'challenges' => $challenges,
            'running' => $running,
            'participants' => $participants,
            'previews' => $challenges->mapWithKeys(
                fn (Challenge $c) => [$c->id => $this->challenges->entryPreview($user, $c)],
            ),
            'filters' => compact('type', 'cost', 'duration', 'search'),
            'types' => $this->types(),
            'ticketsBalance' => $this->wallet->balance($user, 'tickets'),
            'coinsBalance' => $this->wallet->balance($user, 'coins'),
            'runningCount' => $running->count(),
        ]);
    }

    /** بوب-أب الدخول ⟵ خصمٌ مرّة واحدة ثمّ شاشة التحدّي */
    public function enter(Request $request, Challenge $challenge): RedirectResponse
    {
        if (! $challenge->is_active) {
            return back()->with('status', 'الحرب دي موقوفة دلوقتي — جرّب واحدة تانية.');
        }

        try {
            $participation = $this->challenges->enter($request->user(), $challenge);
        } catch (InsufficientBalanceException $e) {
            // رسالة الخطأ = ماذا حدث + ماذا تفعل (2.17-ب)
            return back()->with('status', $e->getMessage())->with('topup_needed', $e->shortfall());
        }

        return redirect()->route('challenges.play', $participation);
    }

    /** تحدّياتي: جارية بعدّاداتها · منتهية بنتيجتها */
    public function mine(Request $request): View
    {
        $user = $request->user();
        $tab = $request->query('tab') === 'done' ? 'done' : 'running';

        $participations = ChallengeParticipation::query()
            ->with('challenge')
            ->where('user_id', $user->id)
            ->orderByDesc('started_at')
            ->get();

        // فرض انتهاء الوقت قبل العرض: الجارية المنتهي وقتها تُسلَّم تلقائيًّا
        $participations->where('status', 'running')->each(
            fn (ChallengeParticipation $p) => $this->challenges->enforceDeadline($p),
        );

        $participations = $participations->map->refresh();

        return view('challenges.mine', [
            'tab' => $tab,
            'running' => $participations->where('status', 'running')->values(),
            'done' => $participations->where('status', '!=', 'running')->values(),
            'service' => $this->challenges,
        ]);
    }

    /** لوحة الأبطال: ترتيب المتحدّين + صفّي مثبَّت أسفل القائمة دائمًا */
    public function leaderboard(Request $request): View
    {
        $user = $request->user();
        $days = (int) $request->query('days', (int) setting('ux.lists.default_range_days', 30));
        $challengeId = $request->query('challenge') ? (int) $request->query('challenge') : null;
        $search = trim((string) $request->query('q', ''));

        $board = $this->leaderboards->champions($user, $challengeId, $days, $search ?: null);

        return view('challenges.leaderboard', [
            'board' => $board,
            'challenges' => Challenge::query()->orderBy('name_ar')->get(['id', 'name_ar']),
            'filters' => ['days' => $days, 'challenge' => $challengeId, 'q' => $search],
        ]);
    }

    /** شاشة التحدّي — تركيز بلا سايد بار */
    public function play(Request $request, ChallengeParticipation $participation): View|RedirectResponse
    {
        $this->authorizeOwner($request, $participation);

        // انتهاء الوقت ⟵ تسليم تلقائيّ (على السيرفر لا على المتصفّح)
        if ($this->challenges->enforceDeadline($participation) || $participation->status !== 'running') {
            return redirect()->route('challenges.result', $participation);
        }

        return view('challenges.play', [
            'participation' => $participation,
            'challenge' => $participation->challenge,
            'items' => $this->challenges->publicItems($participation->challenge),
            'answers' => $participation->progress['answers'] ?? [],
            'secondsLeft' => $this->challenges->secondsLeft($participation),
        ]);
    }

    /** حفظ إجابة لحظيًّا — الانقطاع لا يعاقِب و«تقدّمك محفوظ» */
    public function answer(Request $request, ChallengeParticipation $participation): JsonResponse
    {
        $this->authorizeOwner($request, $participation);

        $data = $request->validate([
            'index' => ['required', 'integer', 'min:0'],
            'value' => ['nullable'],
        ]);

        $state = $this->challenges->answer($participation, (int) $data['index'], $data['value'] ?? null);

        return response()->json($state + [
            'redirect' => $state['status'] === 'running' ? null : route('challenges.result', $participation),
        ]);
    }

    public function submit(Request $request, ChallengeParticipation $participation): RedirectResponse
    {
        $this->authorizeOwner($request, $participation);

        $this->challenges->finish($participation, auto: (bool) $request->boolean('auto'));

        return redirect()->route('challenges.result', $participation);
    }

    /** شاشة النتيجة: احتفال بمستواه + لقطة إنجاز قابلة للمشاركة */
    public function result(Request $request, ChallengeParticipation $participation): View|RedirectResponse
    {
        $this->authorizeOwner($request, $participation);
        $this->challenges->enforceDeadline($participation);
        $participation->refresh();

        if ($participation->status === 'running') {
            return redirect()->route('challenges.play', $participation);
        }

        return view('challenges.result', [
            'participation' => $participation,
            'challenge' => $participation->challenge,
            'total' => count($this->challenges->items($participation->challenge)),
            // مرّة واحدة لكلّ حدث Server-side — لا يتكرّر بإعادة التحميل (2.14-ب)
            'celebration' => $this->challenges->celebrationFor($participation),
        ]);
    }

    // ------------------------------------------------------------------ داخليّ

    private function authorizeOwner(Request $request, ChallengeParticipation $participation): void
    {
        abort_unless($participation->user_id === $request->user()->id, 403, 'دي مشاركة حدّ تاني.');
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
