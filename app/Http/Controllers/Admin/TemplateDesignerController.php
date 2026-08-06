<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificateTemplate;
use App\Models\CertificateType;
use App\Services\Admin\Content\ContentAudit;
use App\Services\Admin\Content\MediaLibrary;
use App\Services\Admin\Content\TemplateDesigner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ⭐ مصمّم القوالب المرئيّ (12.5-ب · 24.1).
 *
 * الواجهة **JS خام** بلا أيّ مكتبة سحب أو رسم — لأنّ الدستور يمنع المكتبات
 * الخارجيّة، ولأنّ السحب هنا حسابُ إزاحةٍ بسيط لا يستحقّ اعتمادًا خارجيًّا.
 * والحفظ يمرّ بـ`TemplateDesigner::sanitizeLayers` فلا تدخل قيمة خارج المدى
 * ولا ربطٌ خارج القائمة البيضاء.
 */
class TemplateDesignerController extends Controller
{
    public function __construct(
        private readonly TemplateDesigner $designer,
        private readonly ContentAudit $audit,
    ) {}

    public function edit(Request $request, CertificateType $type): View
    {
        $templates = $this->designer->templatesFor($type);
        $language = $request->string('lang')->toString();
        $language = in_array($language, ['ar', 'en'], true) ? $language : 'ar';
        $template = $templates[$language];

        return view('admin.certificates.designer', [
            'type' => $type,
            'template' => $template,
            'templates' => $templates,
            'language' => $language,
            'layers' => $this->designer->fromStorage($template->layers),
            'fields' => $this->designer->builtInFields(),
            'bindableTables' => $this->designer->bindableTables(),
            'sample' => $this->designer->sampleData($type, $language),
            'mediaItems' => app(MediaLibrary::class)->search(['kind' => 'image'])->take((int) setting('media.picker.limit', 12)),
            'grid' => (int) setting('certificates.designer.grid_step', 5),
            'snap' => (bool) setting('certificates.designer.snap_enabled', true),
        ]);
    }

    /** حفظ الطبقات — تُخزَّن JSON في `certificate_templates.layers` (12.5-ب). */
    public function save(Request $request, CertificateType $type): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'language' => ['required', 'string', 'in:ar,en'],
            'layers' => ['required'],
            'width_px' => ['nullable', 'integer', 'min:400', 'max:6000'],
            'height_px' => ['nullable', 'integer', 'min:400', 'max:6000'],
            'name' => ['nullable', 'string', 'max:190'],
        ]);

        $template = $this->designer->templatesFor($type)[$data['language']];
        $layers = $this->designer->sanitizeLayers($data['layers']);

        $template->update([
            'name' => ($data['name'] ?? null) ?: $template->name,
            'width_px' => $data['width_px'] ?? $template->width_px,
            'height_px' => $data['height_px'] ?? $template->height_px,
            'layers' => $this->designer->forStorage($layers),
            // رقم النسخة يتقدّم مع كلّ حفظ — والشهادات الصادرة تحتفظ بنسختها المجمَّدة
            'version' => (int) $template->version + 1,
        ]);

        $this->audit->record($template, 'certificate_template.saved', [], [
            'language' => $data['language'], 'layers' => count($layers),
        ]);

        $message = (string) setting('certificates.designer.saved_text', 'اتحفظ التصميم ✓');

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'version' => $template->version])
            : back()->with('status', $message);
    }

    /** رفع خلفيّة الشهادة — أو اختيارها من مكتبة الوسائط (12.5-ب). */
    public function background(Request $request, CertificateTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'background_path' => ['nullable', 'string', 'max:255'],
            'clear' => ['nullable', 'boolean'],
        ]);

        $template->update([
            'background_path' => $request->boolean('clear') ? null : ($data['background_path'] ?? null),
        ]);

        return back()->with('status', $request->boolean('clear') ? (string) setting('certificates.designer.background_ok', 'اتشالت الخلفيّة ✓') : (string) setting('certificates.designer.background_ok_2', 'اتظبطت الخلفيّة ✓'));
    }

    /** إعادة القالب للتصميم الافتراضيّ الجاهز (24.1). */
    public function reset(CertificateTemplate $template): RedirectResponse
    {
        $this->designer->reset($template);
        $this->audit->record($template, 'certificate_template.reset', [], []);

        return back()->with('status', (string) setting('certificates.designer.reset_ok', 'رجع للتصميم الافتراضيّ ✓'));
    }

    /** ⭐ نسخ التصميم إلى اللغة الشقيقة لنفس النوع — نقطة بداية لا تصميم من الصفر (24.2). */
    public function duplicate(CertificateTemplate $template): RedirectResponse
    {
        $target = $this->designer->duplicateToOtherLanguage($template);

        $this->audit->record($target, 'certificate_template.duplicated', [], ['from_language' => $template->language]);

        return back()->with('status', (string) setting('certificates.designer.duplicated_ok', 'اتنسخ التصميم للغة التانية ✓'));
    }

    /** ⭐ معاينة بالمقاس الحقيقيّ — نفس الوحدات التي يرسم بها الخادم. */
    public function preview(CertificateTemplate $template): View
    {
        $type = CertificateType::query()->findOrFail($template->certificate_type_id);

        return view('admin.certificates.template-preview', [
            'type' => $type,
            'template' => $template,
            'layers' => $this->designer->visibleLayers($this->designer->fromStorage($template->layers)),
            'sample' => $this->designer->sampleData($type, $template->language),
        ]);
    }

    /** الجداول والأعمدة المسموح الربط بها — واجهة آمنة (12.5-ب). */
    public function columns(): JsonResponse
    {
        return response()->json(['tables' => $this->designer->bindableTables()]);
    }
}
