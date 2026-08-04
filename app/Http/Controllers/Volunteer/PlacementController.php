<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\PlacementRequest;
use App\Models\Position;
use App\Models\RecruitmentCandidate;
use App\Services\Volunteer\People\CandidatePipeline;
use App\Services\Volunteer\People\PlacementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * القوائم والتسكين (13.4-هـ · 24.4-12).
 *
 * **عمودان في صفحة واحدة بلا تنقّل** — وعلى الموبايل قائمة والتفاصيل Bottom Sheet.
 * والأقفال الثلاثة كلّها في `PlacementService` لا في المتحكّم، فالقاعدة واحدة
 * مهما تعدّدت أبواب الدخول.
 */
class PlacementController extends Controller
{
    public function __construct(
        private readonly PlacementService $placement,
        private readonly CandidatePipeline $pipeline,
    ) {}

    public function index(Request $request): View
    {
        // ⭐ مهلة الـ48 ساعة تُطبَّق عند كلّ فتح — فلا تتعلّق الميزة بوجود كرون
        $this->placement->expireOverdue();

        $sort = $request->string('sort')->toString() ?: 'newest';

        $filters = [
            'q' => $request->string('q')->toString(),
            'score_min' => $request->has('score_min') ? (float) $request->input('score_min') : null,
            'score_max' => $request->has('score_max') ? (float) $request->input('score_max') : null,
        ];

        $candidates = $this->placement->finalList(array_filter($filters, fn ($v) => $v !== null && $v !== ''), $sort);
        $selected = $request->integer('candidate')
            ? $candidates->firstWhere('id', $request->integer('candidate'))
            : $candidates->first();

        $requests = PlacementRequest::query()
            ->with(['entity', 'position'])
            ->whereIn('recruitment_candidate_id', $candidates->pluck('id'))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('recruitment_candidate_id');

        return view('volunteer.people.placement.index', [
            'candidates' => $candidates,
            'selected' => $selected,
            'requests' => $requests,
            'placement' => $this->placement,
            'pipeline' => $this->pipeline,
            'sort' => $sort,
            'sortOptions' => $this->placement->sortOptions(),
            'filters' => $filters,
            'statuses' => $this->placement->statusLabels(),
            'counts' => $this->counts($requests),
            'occupancy' => $this->placement->occupancy(),
            'positions' => Position::query()->where('is_active', true)->where('is_honorary', false)->orderBy('rank')->get(),
            'fits' => $this->pipeline->fitsFor($candidates->pluck('id')->all()),
            'scoreFloor' => $this->pipeline->scoreFloor(),
            'scoreCeiling' => $this->pipeline->scoreCeiling(),
            'canPlace' => $request->user()->allows('placements.create'),
        ]);
    }

    /** إرسال طلب تسكين — والأقفال تحرس السباق (13.4-هـ) */
    public function store(Request $request, RecruitmentCandidate $candidate): RedirectResponse
    {
        $data = $request->validate([
            'entity_id' => ['required', 'exists:entities,id'],
            'position_id' => ['required', 'exists:positions,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], [
            'entity_id' => (string) setting('placement.screen.store_msg', 'القسم الفرعيّ'),
            'position_id' => (string) setting('placement.screen.store_msg_2', 'البوزشن'),
        ]);

        try {
            $this->placement->request(
                $candidate,
                Entity::findOrFail($data['entity_id']),
                Position::findOrFail($data['position_id']),
                $request->user(),
                $data['note'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', strtr((string) setting('placement.screen.store_ok', 'اتبعت ✓ المرشّح عنده :a1 ساعة يردّ.'), [':a1' => (string) ($this->placement->responseHours())]));
    }

    public function withdraw(Request $request, PlacementRequest $placementRequest): RedirectResponse
    {
        try {
            $this->placement->withdraw($placementRequest, $request->user());
        } catch (\RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', (string) setting('placement.screen.withdraw_ok', 'اتسحب ✓ المرشّح رجع للقائمة.'));
    }

    /** ردّ المرشّح نفسه: موافقة ⟵ عضويّة واحتفال ذروة · رفض ⟵ لا شيء يحصل */
    public function respond(Request $request, PlacementRequest $placementRequest): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:accepted,rejected'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless(
            (int) $placementRequest->recruitment_candidate?->user_id === (int) $request->user()->id,
            403,
            (string) setting('placement.screen.respond_denied', 'الطلب ده مش بتاعك.'),
        );

        try {
            $this->placement->respond($placementRequest, $data['decision'], $request->user(), $data['note'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', $data['decision'] === 'accepted'
            ? (string) setting('placement.screen.respond_msg', 'مبروك 🎉 أهلًا بيك معانا.')
            : (string) setting('placement.screen.respond_ok', 'اتسجّل ✓ شكرًا لوضوحك.'));
    }

    /** @param  Collection  $requests */
    private function counts($requests): array
    {
        $flat = $requests->flatten(1);

        return [
            'sent' => $flat->whereIn('status', ['sent', 'awaiting'])->count(),
            'accepted' => $flat->where('status', 'accepted')->count(),
            'rejected' => $flat->where('status', 'rejected')->count(),
            'expired' => $flat->where('status', 'expired')->count(),
        ];
    }
}
