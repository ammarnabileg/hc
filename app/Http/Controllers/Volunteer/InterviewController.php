<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Interview;
use App\Models\InterviewScorecard;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use App\Services\Library\AtsPdfWriter;
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
        private readonly AtsPdfWriter $pdf,
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
            'interviewer_id' => (string) setting('interviews.screen.store_msg', 'المُقابِل'),
            'scheduled_at' => (string) setting('interviews.screen.store_msg_2', 'الموعد'),
            'external_link' => (string) setting('interviews.screen.store_msg_3', 'رابط الميتينج'),
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

        return back()->with('status', (string) setting('interviews.screen.store_ok', 'اتجدولت ✓ والطرفان اتبلّغوا.'));
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

        return back()->with('status', (string) setting('interviews.screen.status_ok', 'اتحدّثت ✓'));
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
            'archivedTag' => ScorecardEngine::archivedTag(),
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
            'message' => (string) setting('interviews.screen.autosave_ok', 'اتحفظ ✓'),
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
                ? (string) setting('interviews.screen.decide_ok', 'اتسجّل ✓ المرشّح راح للقائمة النهائيّة.')
                : (string) setting('interviews.screen.decide_ok_2', 'اتسجّل ✓ القرار والسبب اتحفظوا.'));
    }

    /**
     * ⭐ تصدير PDF للأرشيف (13.4-د · §12 · scorecards.export) — يُبنى على الخادم
     * بلا مكتبة خارجيّة، بنفس كاتب الـATS المستخدَم لتصدير الـCV (9).
     * ولو غاب الخطّ المضمَّن نشرح ماذا حدث بدل صفحة خطأ (2.17-ب).
     */
    public function export(Request $request, Interview $interview): Response|RedirectResponse
    {
        $card = InterviewScorecard::firstOrNew(['interview_id' => $interview->id]);
        $interview->load(['recruitment_candidate.user', 'interviewer']);
        $name = (string) ($interview->recruitment_candidate?->user?->name ?? setting('interviews.screen.export_msg', 'مرشّح'));

        if (! $this->pdf->available()) {
            return redirect()->route('volunteer.interviews.scorecard', $interview)->with('status', $this->pdf->unavailableReason());
        }

        $binary = $this->pdf->build(
            $name,
            strtr((string) setting('interviews.screen.export_title', 'نتيجة مقابلة — :name'), [':name' => $name]),
            $this->exportSections($interview, $card),
        );

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="scorecard-'.$interview->id.'.pdf"',
        ]);
    }

    /**
     * أقسام الـPDF: بيانات المقابلة · المهارات وتحليل الشخصيّة · المعايير بدرجاتها · القرار.
     *
     * @return array<int, array{heading:string, lines:array<int,string>}>
     */
    private function exportSections(Interview $interview, InterviewScorecard $card): array
    {
        $sections = [];

        $sections[] = [
            'heading' => (string) setting('interviews.screen.export_section_interview', 'بيانات المقابلة'),
            'lines' => array_filter([
                strtr((string) setting('interviews.screen.export_interviewer', 'المُقابِل: :p1'), [':p1' => (string) ($interview->interviewer?->name ?? '—')]),
                strtr((string) setting('interviews.screen.export_scheduled_at', 'الموعد: :p1'), [':p1' => (string) ($interview->scheduled_at?->format('Y-m-d H:i') ?? '—')]),
            ]),
        ];

        $sections[] = [
            'heading' => (string) setting('interviews.screen.export_section_notes', 'المهارات وتحليل الشخصيّة'),
            'lines' => [
                strtr((string) setting('recruitment.scorecard_engine.summary_1', 'المهارات: :p1'), [':p1' => (string) (trim((string) $card->skills_notes) ?: '—')]),
                strtr((string) setting('recruitment.scorecard_engine.summary_2', 'تحليل الشخصيّة: :p1'), [':p1' => (string) (trim((string) $card->personality_notes) ?: '—')]),
            ],
        ];

        $criteriaLines = [];

        foreach ($this->engine->rows($card->exists ? $card : null) as $row) {
            $criteriaLines[] = strtr((string) setting('recruitment.scorecard_engine.summary_3', ':p1:p2: :p3/:p4 — وزن :p5'), [
                ':p1' => (string) $row['label'],
                ':p2' => (string) ($row['archived'] ? ' ('.ScorecardEngine::archivedTag().')' : ''),
                ':p3' => (string) ($row['score'] ?? '—'),
                ':p4' => (string) $this->engine->scale(),
                ':p5' => (string) $row['weight'],
            ]);
        }

        $criteriaLines[] = strtr((string) setting('recruitment.scorecard_engine.summary_4', 'الدرجة الإجماليّة: :p1/:p2'), [':p1' => (string) $card->total_score, ':p2' => (string) $this->engine->scale()]);

        $sections[] = ['heading' => (string) setting('interviews.screen.export_section_criteria', 'المعايير'), 'lines' => $criteriaLines];

        if ($card->decision) {
            $sections[] = [
                'heading' => (string) setting('interviews.screen.export_section_decision', 'القرار'),
                'lines' => array_filter([
                    $card->decision === 'passed'
                        ? (string) setting('interviews.screen.export_decision_passed', 'نجح ⟵ القائمة النهائيّة')
                        : (string) setting('interviews.screen.export_decision_rejected', 'رفض'),
                    $card->rejection_reason ? strtr((string) setting('interviews.screen.export_decision_reason', 'السبب: :p1'), [':p1' => (string) $card->rejection_reason]) : null,
                ]),
            ];
        }

        return $sections;
    }
}
