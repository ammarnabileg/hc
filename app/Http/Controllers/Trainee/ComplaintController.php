<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Services\Account\ComplaintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * الشكاوى والمقترحات (الدستور 11 · 24.5).
 *
 * تخطيط «قائمة + بانل» الموحَّد (2.15-ب): يمين القائمة ويسار سلسلة الردود —
 * وعلى الموبايل القائمة ثمّ Bottom Sheet، فلا يفقد المستخدم مكانه في القائمة.
 */
class ComplaintController extends Controller
{
    public function __construct(private readonly ComplaintService $complaints) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        // ثلاثة فلاتر ظاهرة فقط: الحالة · النوع · بحث (2.15-أ-4)
        $filters = [
            'status' => $request->string('status')->toString(),
            'type' => $request->string('type')->toString(),
            'q' => trim($request->string('q')->toString()),
        ];

        $mine = Complaint::query()->where('user_id', $user->id);

        $counts = (clone $mine)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $tickets = (clone $mine)
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['type'] !== '', fn ($q) => $q->where('type', $filters['type']))
            ->when($filters['q'] !== '', fn ($q) => $q->where(function ($sub) use ($filters) {
                $sub->where('number', 'like', '%'.$filters['q'].'%')
                    ->orWhere('title', 'like', '%'.$filters['q'].'%');
            }))
            ->orderByDesc('updated_at')
            ->get();

        // التذكرة المفتوحة في البانل: المطلوبة، وإلّا أحدث واحدة
        $selected = $tickets->firstWhere('id', (int) $request->integer('ticket')) ?: $tickets->first();

        $messages = $selected
            ? ComplaintMessage::with('user:id,name,avatar_path')
                ->where('complaint_id', $selected->id)
                ->where('is_internal', false) // الملاحظات الداخليّة للفريق فقط (12.1)
                ->orderBy('created_at')
                ->get()
            : collect();

        return view('support.complaints.index', [
            'tickets' => $tickets,
            'selected' => $selected,
            'messages' => $messages,
            'counts' => $counts,
            'filters' => $filters,
            'statuses' => ComplaintService::statusLabels(),
            'types' => ComplaintService::typeLabels(),
            'categories' => ComplaintService::categories(),
            'maxKb' => ComplaintService::attachmentMaxKb(),
            // «لم أجد إجابتي» في دليل المستخدم يفتح تذكرة بعنوان مملوء مسبقًا
            'prefillTitle' => trim($request->string('title')->toString()),
            'openNew' => $request->boolean('new') || $request->filled('title'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:complaint,suggestion'],
            'category' => ['required', 'string', 'max:48'],
            'title' => ['required', 'string', 'min:4', 'max:150'],
            'body' => ['required', 'string', 'min:10'],
            'attachment' => ['nullable', 'file', 'max:'.ComplaintService::attachmentMaxKb()],
        ], [
            'required' => 'الحقل ده مطلوب — اكتبه وجرّب تاني.',
            'category.required' => 'اختار السبب من القائمة.',
            'title.min' => 'العنوان قصيّر — اكتب جملة توضّح الموضوع.',
            'body.min' => 'اكتب تفاصيل أكتر شوية عشان نقدر نساعدك.',
            'in' => 'الاختيار ده مش من الخيارات المتاحة.',
            'attachment.max' => 'المرفق كبير — اختار ملفّ أصغر.',
        ], [
            'type' => 'النوع',
            'category' => 'السبب',
            'title' => 'العنوان المختصر',
            'body' => 'نصّ الشكوى أو المقترح',
            'attachment' => 'المرفق',
        ]);

        $complaint = $this->complaints->open($request->user(), $data, $request->file('attachment'));

        return redirect()
            ->route('complaints.index', ['ticket' => $complaint->id])
            ->with('status', 'وصلتنا رسالتك — رقم التذكرة '.$complaint->number.'. هنردّ عليك في أقرب وقت.');
    }

    public function reply(Request $request, Complaint $complaint): RedirectResponse
    {
        $this->authorizeOwner($request, $complaint);

        // المغلقة قراءة فقط بشارة (24.5) — والقيد يُشرَح لحظة كسره لا قبله (2.15-د)
        if ($this->complaints->isClosed($complaint)) {
            return back()->with('status', 'التذكرة دي مقفولة. لو لسّه محتاج مساعدة افتح تذكرة جديدة.');
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2'],
            'attachment' => ['nullable', 'file', 'max:'.ComplaintService::attachmentMaxKb()],
        ], [
            'body.required' => 'اكتب ردّك الأوّل.',
            'body.min' => 'اكتب كلمتين على الأقلّ.',
            'attachment.max' => 'المرفق كبير — اختار ملفّ أصغر.',
        ], [
            'body' => 'الردّ',
            'attachment' => 'المرفق',
        ]);

        $this->complaints->reply($complaint, $request->user(), $data['body'], $request->file('attachment'));

        return redirect()
            ->route('complaints.index', ['ticket' => $complaint->id])
            ->with('status', 'اتبعت ✓');
    }

    public function close(Request $request, Complaint $complaint): RedirectResponse
    {
        $this->authorizeOwner($request, $complaint);

        $this->complaints->close($complaint);

        return redirect()
            ->route('complaints.index', ['ticket' => $complaint->id])
            ->with('status', 'قفلنا التذكرة. شكرًا إنّك كلّمتنا.');
    }

    /** التذكرة يراها صاحبها (والمخوَّل من لوحة الإدارة) — 24.5 */
    private function authorizeOwner(Request $request, Complaint $complaint): void
    {
        abort_unless(
            $complaint->user_id === $request->user()->id || $request->user()->allows('complaints.manage', $complaint),
            403,
        );
    }
}
