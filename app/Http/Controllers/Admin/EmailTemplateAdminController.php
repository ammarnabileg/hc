<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Services\Admin\Content\GuidanceComposer;
use App\Services\Notifications\AnnouncementPersonalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * «قوالب البريد» (email_templates.* — 8 أفعال مزروعةٌ في الصلاحيّات بلا أيّ
 * تنفيذ حتى `_STATUS.md` وثّق الفجوة صراحةً 2026-09-10). الشاشة تابعةٌ لمجال
 * «الإشعارات» (24.3 · سطر 5065-5072): زرّ «قوالب البريد» في هيدر تلك الشاشة
 * يفتح هذه، وعمود «نصّ القالب»/«مفعّل» في مصفوفتها يستهلكان نفس الجدول
 * عبر `upsertForCategory()` دون المرور بهذه الصفحة الكاملة.
 */
class EmailTemplateAdminController extends Controller
{
    public function __construct(private readonly GuidanceComposer $guidance) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        $templates = EmailTemplate::query()
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByRaw('category is null')
            ->orderBy('category')
            ->get();

        return view('admin.guidance.email-templates.index', [
            'templates' => $templates,
            'types' => $this->guidance->notificationTypes(),
            'status' => $status,
            'tokens' => AnnouncementPersonalizer::tokens(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = EmailTemplate::create($this->rules($request) + ['created_by' => $request->user()?->id]);

        return back()->with('status', strtr((string) setting('admin.guidance.email_templates.created_message', 'اتضاف القالب «:a1» ✓'), [':a1' => (string) $template->name]));
    }

    public function update(Request $request, EmailTemplate $template): RedirectResponse
    {
        $template->update($this->rules($request, $template));

        return back()->with('status', (string) setting('admin.guidance.email_templates.saved_message', 'اتحفظ القالب ✓'));
    }

    public function destroy(EmailTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('status', (string) setting('admin.guidance.email_templates.deleted_message', 'اتشال القالب ✓'));
    }

    /** أرشفة قالبٍ متوقّف عن الاستخدام — أو إعادته للعمل بنفس الزرّ حسب حالته. */
    public function archive(EmailTemplate $template): RedirectResponse
    {
        $template->update(['status' => $template->status === 'archived' ? 'active' : 'archived']);

        return back()->with('status', $template->status === 'archived'
            ? (string) setting('admin.guidance.email_templates.archived_message', 'اتأرشف القالب ✓')
            : (string) setting('admin.guidance.email_templates.restored_message', 'رجع القالب شغّال ✓'));
    }

    public function export(): StreamedResponse
    {
        $rows = EmailTemplate::query()->orderBy('id')->get();
        $name = 'email-templates-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['name', 'category', 'subject', 'body', 'variables', 'is_enabled', 'status']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->name, $row->category, $row->subject, $row->body,
                    implode(',', $row->variables ?? []), $row->is_enabled ? '1' : '0', $row->status,
                ]);
            }

            fclose($handle);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * استيراد قوالب بريد جاهزة (CSV بنفس ترويسة التصدير) — استيرادٌ إضافيّ لا
     * استبدال: قالبٌ بنفس `category` موجودًا يُحدَّث، وغير المربوط يُنشأ صفًّا جديدًا.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        // «BOM» الذي تكتبه دالّة التصدير نفسها (ليفتح إكسل العربيّة سليمةً) يلتصق
        // بأوّل خليّةٍ في الترويسة لو قُرئت بـfgetcsv مباشرةً — فيضيع مفتاح `name`.
        $header = array_map(
            fn (string $column) => ltrim($column, "\xEF\xBB\xBF"),
            fgetcsv($handle) ?: [],
        );
        $count = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);

            if (empty($data['name']) || empty($data['body'])) {
                continue;
            }

            $category = ($data['category'] ?? '') !== '' ? $data['category'] : null;

            $attributes = [
                'name' => $data['name'],
                'subject' => $data['subject'] ?: null,
                'body' => $data['body'],
                'variables' => ! empty($data['variables']) ? explode(',', $data['variables']) : null,
                'is_enabled' => (bool) ($data['is_enabled'] ?? false),
                'status' => $data['status'] ?: 'active',
                'created_by' => $request->user()?->id,
            ];

            // بلا نوعٍ: لا مفتاح تفرّدٍ يُطابَق عليه — صفٌّ جديد دائمًا (قوالبٌ عامّة بلا حدّ)
            $category !== null
                ? EmailTemplate::updateOrCreate(['category' => $category], $attributes)
                : EmailTemplate::create($attributes + ['category' => null]);

            $count++;
        }

        fclose($handle);

        return back()->with('status', strtr((string) setting('admin.guidance.email_templates.imported_message', 'اتستوردت :a1 قوالب ✓'), [':a1' => (string) $count]));
    }

    /**
     * ⭐ عمودا «نصّ القالب» (تعديل) و«مفعّل» في مصفوفة الإشعارات (24.3): بدل
     * فتح شاشة القوالب الكاملة لكلّ نوع، صفٌّ في المصفوفة يعدّل قالبه مباشرةً.
     * القالب يُنشأ أوّل مرّة لو لم يكن موجودًا لهذا النوع.
     */
    public function upsertForCategory(Request $request, string $category): RedirectResponse
    {
        abort_unless(array_key_exists($category, $this->guidance->notificationTypes()), 404);

        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:2000'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        EmailTemplate::updateOrCreate(
            ['category' => $category],
            [
                'name' => $this->guidance->notificationTypes()[$category] ?? $category,
                'subject' => $data['subject'] ?? null,
                'body' => $data['body'],
                'is_enabled' => (bool) ($data['is_enabled'] ?? false),
                'status' => 'active',
                'created_by' => $request->user()?->id,
            ],
        );

        return back()->with('status', (string) setting('admin.guidance.email_templates.saved_message', 'اتحفظ القالب ✓'));
    }

    /** @return array<string, mixed> */
    private function rules(Request $request, ?EmailTemplate $template = null): array
    {
        $categories = array_keys($this->guidance->notificationTypes());

        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', Rule::in($categories), Rule::unique('email_templates', 'category')->ignore($template?->id)],
            'subject' => ['nullable', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:2000'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['nullable', 'string', 'max:60'],
            'is_enabled' => ['nullable', 'boolean'],
        ], [], [
            'name' => (string) setting('admin.guidance.email_templates.rules_msg_1', 'اسم القالب'),
            'category' => (string) setting('admin.guidance.email_templates.rules_msg_2', 'النوع المرتبط'),
            'body' => (string) setting('admin.guidance.email_templates.rules_msg_3', 'محتوى القالب'),
        ])->validate();

        return [
            'name' => $data['name'],
            'category' => ($data['category'] ?? '') !== '' ? $data['category'] : null,
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'variables' => array_values(array_filter((array) ($data['variables'] ?? []))) ?: null,
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
        ];
    }
}
