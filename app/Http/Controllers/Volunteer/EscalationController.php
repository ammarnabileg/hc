<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Escalation;
use App\Models\Task;
use App\Models\User;
use App\Services\Volunteer\Escalation\CaseCatalog;
use App\Services\Volunteer\Escalation\EscalationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «يحتاج قرارك» (الدستور 24.4 · 23 — القسم 5).
 *
 * كلّ صفّ يكتب صراحةً **التسوية الآليّة المنتظَرة إن فاتت نافذة السقف**،
 * لأنّ الشفافيّة نفسها رادع: مَن يرى مآل سكوته يقرّر بدل أن يماطل.
 */
class EscalationController extends Controller
{
    public function __construct(private readonly EscalationEngine $engine) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        // ثلاثة فلاتر ظاهرة: نوع الحالة · الشخص · الأقرب لانتهاء النافذة
        $filters = [
            'case_type' => $request->string('case_type')->toString(),
            'person' => $request->integer('person'),
            'urgent' => $request->boolean('urgent'),
        ];

        $rows = $this->engine->deskOf($user)
            ->when($filters['case_type'] !== '', fn ($q) => $q->where('case_type', $filters['case_type']))
            ->when($filters['person'], fn ($q) => $q->where('requested_by', $filters['person']))
            ->get()
            ->when($filters['urgent'], fn ($items) => $items->filter(
                fn ($e) => in_array($this->engine->windowState($e->window_due_at), ['warn', 'danger'], true)
            ))
            ->values();

        $subjects = $rows->mapWithKeys(fn (Escalation $e) => [$e->id => $this->engine->subjectOf($e)]);

        return view('volunteer.escalations.index', [
            'rows' => $rows,
            'subjects' => $subjects,
            'requesters' => User::query()->whereIn('id', $rows->pluck('requested_by')->filter()->unique())->get()->keyBy('id'),
            'counters' => $this->engine->counters($user),
            'catalog' => CaseCatalog::all(),
            'engine' => $this->engine,
            'filters' => $filters,
            'repMin' => (float) setting('workflow.repeated_return.rep_min', -0.5),
            'repMax' => (float) setting('workflow.repeated_return.rep_max', 0.25),
        ]);
    }

    public function show(Request $request, Escalation $escalation): JsonResponse|View
    {
        $this->authorizeHandler($request->user(), $escalation);

        $payload = [
            'escalation' => $escalation,
            'subject' => $this->engine->subjectOf($escalation),
            'payload' => $this->engine->payload($escalation),
            'decisions' => CaseCatalog::decisions($escalation->case_type),
            'settlement' => CaseCatalog::settlementLabel($escalation->case_type),
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return view('volunteer.escalations.show', $payload);
    }

    /** فتح حالة جديدة على المحرّك — النوع من القائمة التسع لا غير */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'case_type' => ['required', 'in:'.implode(',', CaseCatalog::types())],
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'note' => ['nullable', 'string'],
            'new_deadline' => ['nullable', 'date'],
            'link' => ['nullable', 'string'],
            'responsible_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $task = Task::query()->findOrFail($data['task_id']);

        $this->engine->open($data['case_type'], $task, $request->user(), [
            'note' => $data['note'] ?? null,
            'new_deadline' => $data['new_deadline'] ?? null,
            'link' => $data['link'] ?? null,
            'responsible_id' => $data['responsible_id'] ?? null,
        ]);

        return back()->with('status', 'اترفعت الحالة للمراجِع ✓');
    }

    /**
     * القرار داخل النافذة.
     * · مسار عدم التسليم: ثلاثة أزرار بلا خصم تباطؤ.
     * · الإرجاع المتكرّر: إنهاء بقيمة Rep يدويّة محصورة بين الحدّين بمبرّر.
     */
    public function decide(Request $request, Escalation $escalation): RedirectResponse
    {
        $this->authorizeHandler($request->user(), $escalation);

        $decisions = array_keys(CaseCatalog::decisions($escalation->case_type));

        $data = $request->validate([
            'decision' => ['required', 'in:'.implode(',', $decisions)],
            'note' => ['nullable', 'string'],
            'rep_value' => ['nullable', 'numeric'],
            'justification' => ['nullable', 'string'],
            'deleted_ids' => ['nullable', 'array'],
        ]);

        // «إنهاء بقيمة Rep يدويّة» لا يمرّ بلا مبرّر مكتوب (23 — القسم 5 الحالة 8)
        if ($data['decision'] === 'finished' && trim((string) ($data['justification'] ?? '')) === '') {
            return back()->withErrors(['justification' => 'المبرّر إجباريّ مع قيمة Rep اليدويّة.'])->withInput();
        }

        $this->engine->decide(
            $escalation,
            $request->user(),
            $data['decision'],
            $data['note'] ?? ($data['justification'] ?? null),
            [
                'rep_value' => $data['rep_value'] ?? 0,
                'justification' => $data['justification'] ?? null,
                'deleted_ids' => $data['deleted_ids'] ?? [],
            ],
        );

        return back()->with('status', 'اتسجّل قرارك ✓');
    }

    // ------------------------------------------------------------------ داخليّ

    /** الحالة تُقرَّر من صاحب نافذتها الحاليّ وحده — لا سلطة عابرة للمكاتب */
    private function authorizeHandler(User $user, Escalation $escalation): void
    {
        abort_unless(
            (int) $escalation->current_handler_id === (int) $user->id || $user->allows('escalations.manage'),
            403,
            'الحالة دي على مكتب غيرك.',
        );
    }
}
