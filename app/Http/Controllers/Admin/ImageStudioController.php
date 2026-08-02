<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImageTemplate;
use App\Models\NameParticle;
use App\Models\User;
use App\Services\Images\ImageBatchExporter;
use App\Services\Images\ImageRenderer;
use App\Services\Images\ImageTemplateFields;
use App\Services\Images\TemplateLayers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

/**
 * استوديو الصور والقوالب البصريّة (12.14).
 *
 * ⛔ **الحقول الممنوعة غير موجودة أصلًا** (الموبايل · البريد · جهة الطوارئ ·
 *    الملاحظات الإداريّة) — والخادم يرفض أيّ محاولة لحقنها ولو من خارج الواجهة.
 * ⭐ والقالب المستخدَم في نشرٍ قائم **يُؤرشَف لا يُحذَف**.
 */
class ImageStudioController extends Controller
{
    public function __construct(
        private readonly ImageTemplateFields $fields,
        private readonly TemplateLayers $layers,
        private readonly ImageRenderer $renderer,
        private readonly ImageBatchExporter $batch,
    ) {}

    public function index(Request $request): View
    {
        $templates = ImageTemplate::query()
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->when($request->string('audience')->toString(), fn ($q, $a) => $q->where('audience', $a))
            ->when(! $request->boolean('archived'), fn ($q) => $q->where('is_archived', false))
            ->latest('id')
            ->paginate((int) setting('images.admin.per_page', 12))
            ->withQueryString();

        return view('admin.studio.index', [
            'templates' => $templates,
            'presets' => $this->layers->presets(),
            'audiences' => $this->fields->audiences(),
            'particles' => NameParticle::query()->orderBy('locale')->orderBy('particle')->get(),
        ]);
    }

    public function edit(ImageTemplate $template): View
    {
        return view('admin.studio.edit', [
            'template' => $template,
            'presets' => $this->layers->presets(),
            'audiences' => $this->fields->audiences(),
            // ⭐ القائمة المقفولة وحدها تظهر في الاختيار — لا حقل ممنوع ولو معطَّلًا
            'allowedFields' => $this->fields->all(),
            'sampleUsers' => User::query()->where('status', 'active')->limit((int) setting('images.sample_users_limit', 20))->get(['id', 'name', 'code']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $this->fields->validateLayers($data['layers']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['layers' => $e->getMessage()])->withInput();
        }

        $template = ImageTemplate::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('admin.studio.edit', $template)->with('status', 'القالب اتحفظ ✓');
    }

    public function update(Request $request, ImageTemplate $template): RedirectResponse
    {
        $data = $this->validated($request);

        // ⭐ تغيير المقاس يعيد ترتيب الطبقات نسبيًّا فلا يفسد التصميم (12.14-أ)
        if ((int) $template->width_px !== (int) $data['width_px'] || (int) $template->height_px !== (int) $data['height_px']) {
            $data['layers'] = $this->layers->rescale(
                $data['layers'],
                (int) $template->width_px,
                (int) $template->height_px,
                (int) $data['width_px'],
                (int) $data['height_px'],
            );
        }

        try {
            $this->fields->validateLayers($data['layers']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['layers' => $e->getMessage()])->withInput();
        }

        $template->update($data);

        return back()->with('status', 'التعديل اتحفظ ✓');
    }

    /** رفع طبقة أو إنزالها — والمقفولة لا تتحرّك */
    public function moveLayer(Request $request, ImageTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'index' => ['required', 'integer', 'min:0'],
            'direction' => ['required', 'in:up,down'],
        ]);

        $template->update([
            'layers' => $this->layers->move((array) $template->layers, (int) $data['index'], $data['direction']),
        ]);

        return back()->with('status', 'ترتيب الطبقات اتغيّر ✓');
    }

    public function duplicate(ImageTemplate $template): RedirectResponse
    {
        $copy = $template->replicate(['created_at', 'updated_at']);
        $copy->name = $template->name.' — نسخة';
        $copy->is_archived = false;
        $copy->save();

        return redirect()->route('admin.studio.edit', $copy)->with('status', 'اتعملت نسخة ✓');
    }

    /** ⭐ أرشفة لا حذف: القالب المستخدَم في نشرٍ قائم لا يُحذَف أبدًا */
    public function archive(ImageTemplate $template): RedirectResponse
    {
        $template->update(['is_archived' => true, 'is_active' => false]);

        return back()->with('status', 'القالب اتأرشف — والمنشور القديم بيفضل شغّال.');
    }

    /** معاينة ببيانات مستخدم حقيقيّ — يختاره المصمّم ليرى الشكل النهائيّ فعلًا */
    public function preview(Request $request, ImageTemplate $template): Response
    {
        $user = $request->integer('user')
            ? User::query()->find($request->integer('user'))
            : User::query()->where('status', 'active')->first();

        try {
            $path = $this->renderer->render($template, $user, $request->user());
        } catch (RuntimeException $e) {
            return response($e->getMessage(), 429);
        }

        return response(Storage::disk('public')->get($path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age='.(int) setting('images.preview.cache_seconds', 60),
        ]);
    }

    /** ⭐ التوليد الجماعيّ بشريحة ⟵ ملفّ ZIP */
    public function batch(Request $request, ImageTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'segment' => ['required', 'string'],
            'limit' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $users = $this->batch->resolveSegment($data['segment'], (int) $data['limit']);
            $zip = $this->batch->build($template, $users, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['segment' => $e->getMessage()]);
        }

        return back()->with('status', 'الأرشيف جاهز: '.Storage::disk('public')->url($zip));
    }

    // ---------------------------------------------------------------- أدوات الاسم

    /** ⭐ إدارة قائمة أدوات الاسم من هنا — لأنّها تختلف بالثقافات (12.14-ج) */
    public function storeParticle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'particle' => ['required', 'string', 'max:32', 'unique:name_particles,particle'],
            'locale' => ['required', 'in:ar,en'],
        ]);

        NameParticle::create($data + ['is_active' => true]);

        return back()->with('status', 'الأداة اتضافت ✓');
    }

    public function deleteParticle(NameParticle $particle): RedirectResponse
    {
        $particle->delete();

        return back()->with('status', 'الأداة اتشالت ✓');
    }

    // ---------------------------------------------------------------- داخليّ

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'purpose' => ['nullable', 'string', 'max:48'],
            'width_px' => ['required', 'integer', 'min:64', 'max:4096'],
            'height_px' => ['required', 'integer', 'min:64', 'max:4096'],
            'preset' => ['nullable', 'string', 'max:32'],
            'audience' => ['required', 'in:admin,volunteers,everyone'],
            'is_active' => ['nullable', 'boolean'],
            'layers' => ['nullable', 'array'],
            'tags' => ['nullable', 'array'],
            'folders' => ['nullable', 'array'],
        ]);

        $data['layers'] = $this->layers->sanitize($data['layers'] ?? []);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['purpose'] = $data['purpose'] ?? 'marketing';

        return $data;
    }
}
