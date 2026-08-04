<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\RecruitmentCandidate;
use App\Services\Volunteer\People\CandidatePipeline;
use App\Services\Volunteer\People\PlacementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * المرشّحون — كانبان بالسحب (13.4-د · 24.4-12).
 *
 * سؤال واحد للشاشة: «فين كلّ مرشّح في رحلته؟» — والفعل الرئيسيّ الوحيد هو نقل الكارت،
 * وكلّ نقل **بسبب** يدخل سجلّ التدقيق.
 */
class RecruitmentController extends Controller
{
    public function __construct(
        private readonly CandidatePipeline $pipeline,
        private readonly PlacementService $placement,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $filters = [
            'stage' => $request->string('stage')->toString(),
            'entity' => $request->integer('entity') ?: null,
            'score_min' => $request->has('score_min') ? (float) $request->input('score_min') : null,
            'score_max' => $request->has('score_max') ? (float) $request->input('score_max') : null,
            'waiting' => $request->string('waiting')->toString(),
            'q' => $request->string('q')->toString(),
        ];

        $columns = $this->pipeline->board($user, array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        return view('volunteer.people.recruitment.index', [
            'stages' => $this->pipeline->stages(),
            'columns' => $columns,
            'pipeline' => $this->pipeline,
            'tree' => $this->pipeline->entityTree(),
            'filters' => $filters,
            'scoreFloor' => $this->pipeline->scoreFloor(),
            'scoreCeiling' => $this->pipeline->scoreCeiling(),
            // طبقتا الخصوصيّة: الرقم لفريق التوظيف · وسبب الخروج للمخوَّلين وحدهم
            'canSeePhone' => $this->pipeline->canSeePhone($user),
            'canSeeExitReason' => $this->pipeline->canSeeExitReason($user),
            'canMove' => $user->allows('candidates.edit'),
        ]);
    }

    /** بانل تفاصيل المرشّح — يفتح بوب-أب بلا مغادرة اللوحة (2.15-أ-6) */
    public function show(Request $request, RecruitmentCandidate $candidate): View
    {
        $user = $request->user();
        $candidate->load('user');

        return view('volunteer.people.recruitment.partials.detail', [
            'candidate' => $candidate,
            'pipeline' => $this->pipeline,
            'fits' => $this->pipeline->fitsFor([$candidate->id])[$candidate->id] ?? collect(),
            'canSeePhone' => $this->pipeline->canSeePhone($user),
            'canSeeExitReason' => $this->pipeline->canSeeExitReason($user),
            'whatsapp' => $this->placement->whatsappTemplate($candidate),
        ]);
    }

    /** سحب الكارت بين الأعمدة ⟵ تأكيد بسبب ⟵ `audit_logs` */
    public function move(Request $request, RecruitmentCandidate $candidate): RedirectResponse
    {
        $data = $request->validate([
            'stage' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [], [
            'stage' => (string) setting('recruitment.screen.move_msg', 'المرحلة'),
            'reason' => (string) setting('recruitment.screen.move_msg_2', 'سبب النقل'),
        ]);

        try {
            $this->pipeline->move($candidate, $data['stage'], $data['reason'], $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', (string) setting('recruitment.screen.move_ok', 'اتنقل ✓ الحركة اتسجّلت في سجلّ التدقيق.'));
    }
}
