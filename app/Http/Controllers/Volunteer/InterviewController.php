<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Interview;
use App\Models\InterviewScorecard;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use App\Services\Volunteer\People\CandidatePipeline;
use App\Services\Volunteer\People\InterviewScheduler;
use App\Services\Volunteer\People\ScorecardEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * المقابلات والـScorecards (13.4-د · 24.4-12).
 *
 * تابان: تقويم يتفادى التعارض · نتائج بدرجاتها. والـScorecard **يُحفَظ تلقائيًّا مسودّة**
 * ولا يصير قرارًا إلّا بضغطة صريحة بعد اكتمال الحقول الإجباريّة.
 */
class InterviewController extends Controller
{
    public function __construct(
        private readonly InterviewScheduler $scheduler,
        private readonly ScorecardEngine $engine,
        private readonly CandidatePipeline $pipeline,
    ) {}

    public function index(Request $request): View
    {
        $month = Carbon::parse($request->string('month')->toString() ?: now()->toDateString())->startOfMonth();
        $tab = $request->string('tab')->toString() ?: 'calendar';

        $filters = [
            'interviewer' => $request->integer('interviewer') ?: null,
            'status' => $request->string('status')->toString(),
            'q' => $request->string('q')->toString(),
        ];

        $list = Interview::query()
            ->with(['recruitment_candidate.user', 'interviewer'])
            ->when($filters['interviewer'], fn ($b) => $b->where('interviewer_id', $filters['interviewer']))
            ->when($filters['status'] !== '', fn ($b) => $b->where('status', $filters['status']))
            ->orderByDesc('scheduled_at')
            ->get();

        $cards = InterviewScorecard::query()
            ->whereIn('interview_id', $list->pluck('id'))
            ->get()
            ->keyBy('interview_id');

        return view('volunteer.people.interviews.index', [
            'tab' => $tab,
            'month' => $month,
            'calendar' => $this->scheduler->calendar($month, $filters['interviewer']),
            'interviews' => $list,
            'cards' => $cards,
            'scheduler' => $this->scheduler,
            'statuses' => $this->scheduler->statusLabels(),
            'filters' => $filters,
            'interviewers' => User::query()->whereIn('id', $list->pluck('interviewer_id')->push($request->user()->id))->get(),
            'candidates' => RecruitmentCandidate::query()->with('user')->whereIn('stage', ['screening', 'interview'])->get(),
            'canSchedule' => $request->user()->allows('interviews.create'),
            'canSeeResults' => $request->user()->allows('scorecards.view'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recruitment_candidate_id' => ['required', 'exists:recruitment_candidates,id'],
            'interviewer_id' => ['required', 'exists:users,id'],
            'scheduled_at' => ['required', 'date'],
            'external_link' => ['nullable', 'url', 'max:255'],
        ], [], [
            'interviewer_id' => 'المُقابِل',
            'scheduled_at' => 'الموعد',
            'external_link' => 'رابط الميتينج',
        ]);

        try {
            $this->scheduler->schedule(
                RecruitmentCandidate::findOrFail($data['recruitment_candidate_id']),
                User::findOrFail($data['interviewer_id']),
                Carbon::parse($data['scheduled_at']),
                $data['external_link'] ?? null,
                $request->user(),
            );
        } catch (\RuntimeException $e) {
            return back()->with('status', $e->getMessage())->withInput();
        }

        return back()->with('status', 'اتجدولت ✓ والطرفان اتبلّغوا.');
    }

    /** الحالات الأربع: مجدولة · تمّت · لم يحضر · مُلغاة */
    public function status(Request $request, Interview $interview): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', InterviewScheduler::STATUSES)],
            'reason' => ['nullable', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        try {
            if (! empty($data['scheduled_at'])) {
                $this->scheduler->reschedule($interview, Carbon::parse($data['scheduled_at']), $request->user());
            } else {
                $this->scheduler->setStatus($interview, $data['status'], $data['reason'] ?? null, $request->user());
            }
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', 'اتحدّثت ✓');
    }

    // ------------------------------------------------------------ Scorecard

    public function scorecard(Request $request, Interview $interview): View
    {
        $card = InterviewScorecard::firstOrNew(['interview_id' => $interview->id]);
        $interview->load(['recruitment_candidate.user', 'interviewer']);

        return view('volunteer.people.interviews.scorecard', [
            'interview' => $interview,
            'card' => $card,
            'engine' => $this->engine,
            'rows' => $this->engine->rows($card->exists ? $card : null),
            'criteria' => $this->engine->activeCriteria(),
            'scale' => $this->engine->scale(),
            'missing' => $card->exists ? $this->engine->missing($card) : [],
            'tree' => $this->pipeline->entityTree(),
            'selectedFits' => $this->pipeline->fitsFor([$interview->recruitment_candidate_id])[$interview->recruitment_candidate_id]
                ?? collect(),
            'archivedTag' => ScorecardEngine::ARCHIVED_TAG,
            'canEdit' => $request->user()->allows('scorecards.edit') || $request->user()->allows('scorecards.create'),
        ]);
    }

    /** حفظ تلقائيّ كمسودّة — يرجع «اتحفظ ✓» والدرجة الإجماليّة المحسوبة (2.17-ب) */
    public function autosave(Request $request, Interview $interview): JsonResponse
    {
        $data = $request->validate([
            'skills_notes' => ['nullable', 'string'],
            'personality_notes' => ['nullable', 'string'],
            'criteria_scores' => ['nullable', 'array'],
            'entity_ids' => ['nullable', 'array'],
            'entity_ids.*' => ['integer'],
        ]);

        $card = $this->engine->autosave($interview, $data, $request->user());

        if ($request->has('entity_ids')) {
            $this->engine->syncFits($interview, $data['entity_ids'] ?? [], $request->user());
        }

        return response()->json([
            'saved' => true,
            'message' => 'اتحفظ ✓',
            'total' => (float) $card->total_score,
            'missing' => $this->engine->missing($card),
        ]);
    }

    public function decide(Request $request, Interview $interview): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:passed,rejected'],
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $card = InterviewScorecard::firstOrCreate(['interview_id' => $interview->id]);

        try {
            $this->engine->decide($card, $data['decision'], $data['rejection_reason'] ?? null, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('status', $e->getMessage());
        }

        return redirect()
            ->route('volunteer.interviews', ['tab' => 'results'])
            ->with('status', $data['decision'] === 'passed'
                ? 'اتسجّل ✓ المرشّح راح للقائمة النهائيّة.'
                : 'اتسجّل ✓ القرار والسبب اتحفظوا.');
    }

    /** تصدير الملخّص للأرشيف */
    public function export(Request $request, Interview $interview): Response
    {
        $card = InterviewScorecard::firstOrNew(['interview_id' => $interview->id]);
        $name = $interview->recruitment_candidate?->user?->name ?? 'candidate';

        $body = "نتيجة مقابلة — {$name}\n".str_repeat('─', 32)."\n".$this->engine->summary($card);

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="scorecard-'.$interview->id.'.txt"',
        ]);
    }
}
