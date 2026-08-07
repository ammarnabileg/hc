<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvestigationCase;
use App\Models\User;
use App\Services\Volunteer\Retention\CommitteePath;
use App\Services\Volunteer\Retention\InvestigationCommitteeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * لجنة التحقيق (23-0.2-4) — أربع محطّات: تفعيل مسودّة آليّة · تعيين مقعدين
 * (مع تجاوز اختياريّ) · قرار الميتينج · القرار البشريّ النهائيّ لمشرف عام
 * التطوّع، ثمّ الأرشفة.
 */
class InvestigationAdminController extends Controller
{
    public function __construct(private readonly InvestigationCommitteeService $committee) {}

    /** «يحتاج قرارك»: المسودّات المفتوحة بلا ملفٍّ مفعَّل + الملفّات الجارية */
    public function index(Request $request): View
    {
        return view('admin.volunteer.investigations.index', [
            'queue' => $this->committee->queue(),
            'cases' => InvestigationCase::query()
                ->with(['user:id,name,code', 'seatUpline:id,name,code', 'seatDept:id,name,code'])
                ->where('status', '!=', 'closed')
                ->latest('id')
                ->limit((int) setting('volunteer_investigation.admin_rows', 50))
                ->get(),
            'closed' => InvestigationCase::query()
                ->with(['user:id,name,code'])
                ->where('status', 'closed')
                ->latest('closed_at')
                ->limit((int) setting('volunteer_investigation.admin_rows', 50))
                ->get(),
        ]);
    }

    /** تفعيل بضغطة واحدة — حصريّة لمشرف عام التطوّع (23-0.2-4-1) */
    public function activate(Request $request, int $referral): RedirectResponse
    {
        $row = DB::table(CommitteePath::TABLE)
            ->where('id', $referral)
            ->first();

        if (! $row || $row->status !== 'open') {
            return back()->with('status', (string) setting('volunteer_investigation.admin.activate_denied', 'المسودّة دي مش موجودة أو مقفولة بالفعل.'));
        }

        $case = $this->committee->activate($row, $request->user());

        return redirect()->route('admin.volunteer.investigations.show', $case)
            ->with('status', (string) setting('volunteer_investigation.admin.activate_ok', 'اتفتح ملفّ التحقيق ✓ — والمقعدان اتعيّنا آليًّا.'));
    }

    public function show(Request $request, InvestigationCase $case): View
    {
        $case->load(['user:id,name,code,phone', 'seatUpline:id,name,code,phone', 'seatDept:id,name,code,phone', 'offboarding']);

        return view('admin.volunteer.investigations.show', [
            'case' => $case,
            'canSeatAssign' => $request->user()->allows('investigations.assign', $case),
            'canRecordVerdict' => $case->hasSeat($request->user()) || $request->user()->allows('investigations.approve'),
            'canDecide' => $request->user()->allows('investigations.approve'),
            'canArchive' => $request->user()->allows('investigations.archive'),
        ]);
    }

    /** تجاوز اختيار مقعدٍ بالكود — لمشرف عام التطوّع وحده */
    public function assignSeats(Request $request, InvestigationCase $case): RedirectResponse
    {
        abort_unless($request->user()->allows('investigations.assign', $case), 403);

        $data = $request->validate([
            'slot' => ['required', 'string', 'in:upline,dept'],
            'code' => ['required', 'string', 'max:32'],
        ]);

        $candidate = User::query()->where('code', mb_strtoupper($data['code']))->first();

        if (! $candidate) {
            return back()->with('status', (string) setting('volunteer_investigation.admin.assign_denied', 'الكود ده مش موجود — راجع الكود وجرّب تاني.'));
        }

        $this->committee->overrideSeat($case, $data['slot'], $candidate, $request->user());

        return back()->with('status', (string) setting('volunteer_investigation.admin.assign_ok', 'اتحدّد المقعد ✓'));
    }

    public function scheduleMeeting(Request $request, InvestigationCase $case): RedirectResponse
    {
        abort_unless($request->user()->allows('investigations.assign', $case) || $case->hasSeat($request->user()), 403);

        $data = $request->validate(['at' => ['required', 'date']]);
        $at = Carbon::parse($data['at']);

        $case->meeting_scheduled_at === null
            ? $this->committee->scheduleMeeting($case, $at, $request->user())
            : $this->committee->reschedule($case, $at, $request->user());

        return back()->with('status', (string) setting('volunteer_investigation.admin.meeting_ok', 'اتحدّد ميعاد الميتينج ✓'));
    }

    /** قرار الميتينج — مقعدا اللجنة وحدهما (23-0.2-4-7) */
    public function recordVerdict(Request $request, InvestigationCase $case): RedirectResponse
    {
        $data = $request->validate([
            'verdict' => ['required', 'string', Rule::in(InvestigationCommitteeService::VERDICTS)],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $this->committee->recordVerdict($case, $data['verdict'], $data['reason'], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', (string) setting('volunteer_investigation.admin.verdict_ok', 'اتسجّل قرار الميتينج ✓'));
    }

    /** القرار البشريّ النهائيّ — لمشرف عام التطوّع وحده */
    public function decide(Request $request, InvestigationCase $case): RedirectResponse
    {
        abort_unless($request->user()->allows('investigations.approve'), 403);

        $data = $request->validate([
            'decision' => ['required', 'string', Rule::in(InvestigationCommitteeService::DECISIONS)],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $this->committee->decide($case, $data['decision'], $data['reason'], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', (string) setting('volunteer_investigation.admin.decide_ok', 'اتسجّل القرار ✓'));
    }

    public function archive(Request $request, InvestigationCase $case): RedirectResponse
    {
        abort_unless($request->user()->allows('investigations.archive'), 403);

        try {
            $this->committee->archive($case, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('admin.volunteer.investigations.index')
            ->with('status', (string) setting('volunteer_investigation.admin.archive_ok', 'اتقفل الملفّ وأُرشِف ✓'));
    }
}
