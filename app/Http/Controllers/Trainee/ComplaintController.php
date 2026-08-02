<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Services\Account\ComplaintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            // ⭐ الحقل رقم 1 في القسم 11 — **مطلوب** كبقيّة الحقول
            'wants_contact' => ['required', 'boolean'],
            'category' => ['required', 'string', 'max:48', Rule::in(ComplaintService::categories())],
            'title' => ['required', 'string', 'min:4', 'max:150'],
            'body' => ['required', 'string', 'min:10'],
            'attachment' => ['nullable', 'file', 'max:'.ComplaintService::attachmentMaxKb()],
        ], [
            'required' => (string) setting('complaints.error.required', 'الحقل ده مطلوب — اكتبه وجرّب تاني.'),
            'category.required' => (string) setting('complaints.error.category_required', 'اختار السبب من القائمة.'),
            'wants_contact.required' => (string) setting('complaints.error.wants_contact_required', 'قول لنا: ترحب إننا نتواصل معاك ولا لأ؟'),
            'title.min' => (string) setting('complaints.error.title_min', 'العنوان قصيّر — اكتب جملة توضّح الموضوع.'),
            'body.min' => (string) setting('complaints.error.body_min', 'اكتب تفاصيل أكتر شوية عشان نقدر نساعدك.'),
            'in' => (string) setting('complaints.error.not_allowed', 'الاختيار ده مش من الخيارات المتاحة.'),
            'attachment.max' => (string) setting('complaints.error.attachment_max', 'المرفق كبير — اختار ملفّ أصغر.'),
        ], [
            'type' => (string) setting('complaints.field.type_label', 'النوع'),
            'wants_contact' => (string) setting('complaints.field.wants_contact_label', 'هل ترغب في التواصل معك؟'),
            'category' => (string) setting('complaints.field.reason_label', 'السبب'),
            'title' => (string) setting('complaints.field.title_label', 'العنوان المختصر'),
            'body' => (string) setting('complaints.field.body_label', 'نصّ الشكوى أو المقترح'),
            'attachment' => (string) setting('complaints.field.attachment_label', 'مرفق (اختياريّ)'),
        ]);

        $complaint = $this->complaints->open($request->user(), $data, $request->file('attachment'));

        return redirect()
            ->route('complaints.index', ['ticket' => $complaint->id])
            ->with('status', str_replace(
                ':number',
                (string) $complaint->number,
                (string) setting('complaints.sent_message', 'وصلتنا رسالتك — رقم التذكرة :number. هنردّ عليك في أقرب وقت.'),
            ));
    }

    public function reply(Request $request, Complaint $complaint): RedirectResponse
    {
        $this->authorizeOwner($request, $complaint);

        // المغلقة قراءة فقط بشارة (24.5) — والقيد يُشرَح لحظة كسره لا قبله (2.15-د)
        if ($this->complaints->isClosed($complaint)) {
            return back()->with('status', (string) setting(
                'complaints.reply_on_closed_message',
                'التذكرة دي مقفولة. لو لسّه محتاج مساعدة افتح تذكرة جديدة.',
            ));
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2'],
            'attachment' => ['nullable', 'file', 'max:'.ComplaintService::attachmentMaxKb()],
        ], [
            'body.required' => (string) setting('complaints.error.reply_required', 'اكتب ردّك الأوّل.'),
            'body.min' => (string) setting('complaints.error.reply_min', 'اكتب كلمتين على الأقلّ.'),
            'attachment.max' => (string) setting('complaints.error.attachment_max', 'المرفق كبير — اختار ملفّ أصغر.'),
        ], [
            'body' => (string) setting('complaints.field.reply_label', 'الردّ'),
            'attachment' => (string) setting('complaints.field.attachment_label', 'مرفق (اختياريّ)'),
        ]);

        $this->complaints->reply($complaint, $request->user(), $data['body'], $request->file('attachment'));

        return redirect()
            ->route('complaints.index', ['ticket' => $complaint->id])
            ->with('status', (string) setting('complaints.reply_sent_message', 'اتبعت ✓'));
    }

    public function close(Request $request, Complaint $complaint): RedirectResponse
    {
        $this->authorizeOwner($request, $complaint);

        $this->complaints->close($complaint);

        return redirect()
            ->route('complaints.index', ['ticket' => $complaint->id])
            ->with('status', (string) setting('complaints.closed_message', 'قفلنا التذكرة. شكرًا إنّك كلّمتنا.'));
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
