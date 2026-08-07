<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use App\Services\Volunteer\People\AuditTrail;
use App\Services\Volunteer\People\CandidatePipeline;
use App\Services\Volunteer\People\PlacementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        private readonly AuditTrail $audit,
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

    /**
     * ⭐ + مرشّح يدويّ (24.4-12 — هيدر شاشة التوظيف): خارج مسار الدخول
     * التلقائيّ عبر المسار التأهيليّ — لمرشّحٍ يعرفه فريق التوظيف مباشرةً.
     * يدخل «تقديم» بلا درجة تأهيليّة، مثل أيّ كارت آخر على اللوحة.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ], [], [
            'code' => (string) setting('volunteer.people_recruitment.store_msg', 'كود المستخدم'),
        ]);

        $target = User::query()->where('code', mb_strtoupper($data['code']))->first();

        if (! $target) {
            return back()->withInput()->with('status', (string) setting('volunteer.people_recruitment.store_denied', 'الكود ده مش موجود — راجع الكود وجرّب تاني.'));
        }

        $existing = RecruitmentCandidate::query()->where('user_id', $target->id)->latest('id')->first();

        if ($existing && $existing->stage !== 'rejected') {
            throw ValidationException::withMessages([
                'code' => (string) setting('volunteer.people_recruitment.store_exists', 'المرشّح ده موجود بالفعل على اللوحة.'),
            ]);
        }

        $candidate = DB::transaction(function () use ($target) {
            return RecruitmentCandidate::create([
                'user_id' => $target->id,
                'stage' => 'applied',
                'applied_at' => now(),
                'stage_changed_at' => now(),
            ]);
        });

        $this->audit->record($request->user(), 'candidate.added_manually', $candidate, [], ['stage' => 'applied']);

        return redirect()->route('volunteer.recruitment')
            ->with('status', (string) setting('volunteer.people_recruitment.store_ok', 'اتضاف المرشّح ✓ — هيظهر في عمود «تقديم».'));
    }

    /** تصدير CSV (24.4-12 — هيدر شاشة التوظيف): نفس فلاتر اللوحة الحاليّة */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();

        $filters = array_filter([
            'stage' => $request->string('stage')->toString(),
            'entity' => $request->integer('entity') ?: null,
            'score_min' => $request->has('score_min') ? (float) $request->input('score_min') : null,
            'score_max' => $request->has('score_max') ? (float) $request->input('score_max') : null,
            'waiting' => $request->string('waiting')->toString(),
            'q' => $request->string('q')->toString(),
        ], fn ($v) => $v !== null && $v !== '');

        $candidates = $this->pipeline->query($user, $filters)->get();
        $stages = $this->pipeline->stages();
        $headers = json_decode((string) setting('volunteer.people_recruitment.export_headers', '[]'), true) ?: [];

        return response()->streamDownload(function () use ($candidates, $stages, $headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM — كي يفتح إكسل العربيّة سليمةً
            fputcsv($out, $headers);

            foreach ($candidates as $candidate) {
                fputcsv($out, [
                    $candidate->user?->code,
                    $candidate->user?->name,
                    $stages[$candidate->stage] ?? $candidate->stage,
                    $candidate->qualifying_score,
                    $candidate->applied_at?->toDateTimeString(),
                    $candidate->is_returning ? '1' : '0',
                ]);
            }

            fclose($out);
        }, (string) setting('volunteer.people_recruitment.export_file', 'candidates.csv'), [
            'Content-Type' => 'text/csv; charset=UTF-8',
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
