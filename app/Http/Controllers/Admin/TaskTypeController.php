<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Task;
use App\Models\TaskType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * شاشة أنواع المهامّ (الدستور 23-0.3).
 *
 * النوع **وسم وقالب فقط**: «يحدّد القالب والحقول الجاهزة (تشيك ليست + حقول
 * مقترحة)، وتظلّ دورة الحياة والمراجعة والتصعيد والاعتماد موحّدة بلا استثناء
 * بحسب النوع». فلا سلوك خاصًّا هنا — كتابةٌ موفَّرة لا مسارٌ موازٍ.
 *
 * وكلّ نوع **سجلّ في لوحة الإدارة قابل للإضافة والتعديل** (القاعدة الذهبيّة 2.13)
 * — فبلا هذه الشاشة يصير الكتالوج رقمًا محروقًا في سيدر لا يملكه المالك.
 */
class TaskTypeController extends Controller
{
    /** أنواع التسليم المتاحة — إعداد لا قائمة محروقة (2.13) */
    public function deliveryKinds(): array
    {
        $value = setting('workflow.task_types.delivery_kinds', [
            'link' => 'رابط',
            'file' => 'ملفّ',
            'text' => 'نصّ',
            'confirm' => 'تأكيد',
        ]);

        return is_array($value) && $value !== [] ? $value : ['link' => 'رابط', 'file' => 'ملفّ', 'text' => 'نصّ', 'confirm' => 'تأكيد'];
    }

    public function index(Request $request): View
    {
        $types = TaskType::query()->orderByDesc('is_active')->orderBy('id')->get();

        return view('admin.task-types.index', [
            'types' => $types,
            'usage' => Task::query()
                ->whereNotNull('task_type_id')
                ->selectRaw('task_type_id, COUNT(*) as total')
                ->groupBy('task_type_id')
                ->pluck('total', 'task_type_id'),
            'editing' => $request->integer('edit')
                ? $types->firstWhere('id', $request->integer('edit'))
                : null,
            'deliveryKinds' => $this->deliveryKinds(),
            'priorities' => ['low' => 'منخفضة', 'normal' => 'عاديّة', 'high' => 'مرتفعة'],
        ]);
    }

    /** حفظ نوع — إضافةً أو تعديلًا، والتشيك ليست سطرٌ لكلّ بند */
    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:task_types,id'],
            'key' => ['required', 'string', 'max:48', 'regex:/^[a-z0-9_]+$/'],
            'name_ar' => ['required', 'string', 'max:64'],
            'icon' => ['nullable', 'string', 'max:16'],
            'color' => ['nullable', 'string', 'max:16'],
            'default_brief' => ['nullable', 'string', 'max:2000'],
            'default_deliverable_spec' => ['nullable', 'string', 'max:2000'],
            'checklist' => ['nullable', 'string', 'max:2000'],
            'default_vxp' => ['nullable', 'numeric', 'min:0'],
            'default_priority' => ['nullable', 'string', 'max:16'],
            'default_delivery_kind' => ['nullable', 'string', 'max:16'],
        ], [], [
            'key' => 'المفتاح',
            'name_ar' => 'الاسم',
        ]);

        $checklist = collect(preg_split('/\r\n|\r|\n/', (string) ($data['checklist'] ?? '')))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        $type = TaskType::updateOrCreate(
            ['id' => $data['id'] ?? null],
            [
                'key' => $data['key'],
                'name_ar' => $data['name_ar'],
                'icon' => $data['icon'] ?? null,
                'color' => $data['color'] ?? null,
                'default_brief' => $data['default_brief'] ?? null,
                'default_deliverable_spec' => $data['default_deliverable_spec'] ?? null,
                'checklist' => $checklist,
                'default_vxp' => $data['default_vxp'] ?? null,
                'default_priority' => $data['default_priority'] ?? null,
                'default_delivery_kind' => $data['default_delivery_kind'] ?? null,
                'is_active' => true,
            ],
        );

        $this->audit($request, $type, 'task_types.edit');

        return redirect()
            ->route('admin.volunteer.task-types.index')
            ->with('status', 'اتحفظ ✓ — القالب هيتعبّى تلقائيًّا لمّا حد يختار النوع ده.');
    }

    /**
     * تشغيل/إيقاف النوع — **ولا حذف**: المهامّ القديمة موسومة به، وحذفه يمحو
     * تاريخها. المتوقّف يختفي من قوائم الاختيار ويبقى في التقارير.
     */
    public function toggle(Request $request, TaskType $taskType): RedirectResponse
    {
        $taskType->forceFill(['is_active' => ! $taskType->is_active])->save();

        $this->audit($request, $taskType, 'task_types.edit');

        return back()->with('status', $taskType->is_active ? 'النوع اشتغل ✓' : 'النوع اتوقف — مش هيظهر في الاختيار.');
    }

    private function audit(Request $request, TaskType $type, string $action): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => $type->getMorphClass(),
            'auditable_id' => $type->getKey(),
            'old_values' => [],
            'new_values' => $type->only(['key', 'name_ar', 'is_active']),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
