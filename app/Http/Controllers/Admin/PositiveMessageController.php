<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PositiveMessage;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Engagement\EngagementSettings;
use App\Services\Engagement\PositiveMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * إدارة مكتبة الرسائل الإيجابيّة (2.6-ب · 2.13).
 *
 * شاشة واحدة تجيب عن سؤال واحد: «إيه الرسائل اللي بتظهر ومتى؟» (2.15-أ-1) —
 * إضافة/تعديل/حذف/تفعيل + **ربط كلّ رسالة بسياقها**، وتحتها إعدادات الميزة.
 */
class PositiveMessageController extends Controller
{
    public function __construct(private readonly PositiveMessages $messages) {}

    public function index(Request $request): View
    {
        $contexts = $this->messages->contexts();
        $context = $request->string('context')->toString();
        $state = $request->string('state')->toString();

        $query = PositiveMessage::query()
            ->when(isset($contexts[$context]), fn ($q) => $q->where('context', $context))
            ->when($state === 'active', fn ($q) => $q->where('is_active', true))
            ->when($state === 'paused', fn ($q) => $q->where('is_active', false))
            ->orderBy('context')
            ->orderBy('sort_order')
            ->orderByDesc('id');

        return view('admin.positive.index', [
            'messages' => $query->paginate((int) setting('engagement.positive.per_page', 20))->withQueryString(),
            'contexts' => $contexts,
            'context' => $context,
            'state' => $state,
            'settings' => EngagementSettings::rows(),
            'counts' => [
                'total' => PositiveMessage::count(),
                'active' => PositiveMessage::where('is_active', true)->count(),
                'contexts' => count($contexts),
                'shown' => (int) PositiveMessage::sum('shown_count'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $message = PositiveMessage::create([
            ...$data,
            'created_by' => $request->user()?->id,
        ]);

        AuditTrail::log($request->user(), 'positive_messages.create', $message, [], $data);

        return back()->with('status', 'اتحفظت الرسالة ✓');
    }

    public function update(Request $request, PositiveMessage $message): RedirectResponse
    {
        $data = $this->validated($request);
        $old = $message->only(array_keys($data));

        $message->update($data);

        AuditTrail::log($request->user(), 'positive_messages.update', $message, $old, $data);

        return back()->with('status', 'اتحفظ التعديل ✓');
    }

    /** التفعيل/الإيقاف بضغطة — والموقوفة تبقى في المكتبة ولا تُحذَف */
    public function toggle(Request $request, PositiveMessage $message): RedirectResponse
    {
        $message->update(['is_active' => ! $message->is_active]);

        AuditTrail::log($request->user(), 'positive_messages.toggle', $message, [], ['is_active' => $message->is_active]);

        return back()->with('status', $message->is_active ? 'اترجّعت للخدمة ✓' : 'اتوقفت ✓');
    }

    public function destroy(Request $request, PositiveMessage $message): RedirectResponse
    {
        AuditTrail::log($request->user(), 'positive_messages.delete', $message, $message->only(['context', 'body_ar']), []);

        $message->delete();

        return back()->with('status', 'اتحذفت الرسالة ✓');
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['settings' => ['required', 'array']]);

        EngagementSettings::putMany($data['settings'], $request->user());

        return back()->with('status', 'اتحفظ ✓');
    }

    public function resetSettings(Request $request): RedirectResponse
    {
        $count = EngagementSettings::resetAll($request->user());

        return back()->with('status', 'رجعت '.$count.' قيمة للافتراضيّ ✓');
    }

    /**
     * معاينة حيّة: يشوف الأدمن الرسالة كما يراها المستخدم قبل ما ينشرها
     * على الناس (2.13-د — معاينة قبل الحفظ للإعدادات المؤثّرة على الواجهة).
     */
    public function preview(Request $request): RedirectResponse
    {
        $context = $request->string('context')->toString() ?: PositiveMessages::ANY;
        $message = $this->messages->forContext($context);

        return back()->with('status', $message
            ? trim(($message->emoji ? $message->emoji.' ' : '').$message->body_ar)
            : 'مفيش رسائل مفعّلة في السياق ده لسّه.');
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'context' => ['required', 'string', 'max:48'],
            'body_ar' => ['required', 'string', 'max:400'],
            'emoji' => ['nullable', 'string', 'max:16'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [], [
            'context' => 'السياق',
            'body_ar' => 'نصّ الرسالة',
        ]);

        // سياق خارج القائمة المعتمَدة = رسالة لن تظهر أبدًا — نمنعه بدل أن نتركه صامتًا
        abort_unless(array_key_exists($data['context'], $this->messages->contexts()), 422);

        return [
            'context' => $data['context'],
            'body_ar' => trim($data['body_ar']),
            'emoji' => ($data['emoji'] ?? null) ?: null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }
}
