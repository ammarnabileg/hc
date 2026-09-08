<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImageTemplate;
use App\Models\NameParticle;
use App\Models\User;
use App\Services\Admin\Content\MediaLibrary;
use App\Services\Images\ImageBatchExporter;
use App\Services\Images\ImageRenderer;
use App\Services\Images\ImageTemplateFields;
use App\Services\Images\TemplateLayers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
        private readonly MediaLibrary $media,
    ) {}

    public function index(Request $request): View
    {
        $templates = ImageTemplate::query()
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->when($request->string('audience')->toString(), fn ($q, $a) => $q->where('audience', $a))
            // «مجلّدات ووسوم · بحث» (12.14-أ) — العمودان بلا فلترٍ يقرؤهما كانا زينة
            ->when($request->string('folder')->toString(), fn ($q, $f) => $q->whereJsonContains('folders', $f))
            ->when($request->string('tag')->toString(), fn ($q, $t) => $q->whereJsonContains('tags', $t))
            ->when(! $request->boolean('archived'), fn ($q) => $q->where('is_archived', false))
            ->latest('id')
            ->paginate((int) setting('images.admin.per_page', 12))
            ->withQueryString();

        return view('admin.studio.index', [
            'templates' => $templates,
            'presets' => $this->layers->presets(),
            'audiences' => $this->fields->audiences(),
            'purposes' => $this->fields->purposes(),
            'folderList' => $this->folderList(),
            'tagList' => $this->tagList(),
            'particles' => NameParticle::query()->orderBy('locale')->orderBy('particle')->get(),
        ]);
    }

    public function edit(ImageTemplate $template): View
    {
        return view('admin.studio.edit', [
            'template' => $template,
            'presets' => $this->layers->presets(),
            'audiences' => $this->fields->audiences(),
            'purposes' => $this->fields->purposes(),
            'languages' => $this->fields->languages(),
            'folderList' => $this->folderList(),
            'tagList' => $this->tagList(),
            'frameUrl' => $template->frame_path && Storage::disk('public')->exists($template->frame_path)
                ? Storage::disk('public')->url($template->frame_path)
                : null,
            // ⭐ القائمة المقفولة وحدها تظهر في الاختيار — لا حقل ممنوع ولو معطَّلًا
            'allowedFields' => $this->fields->all(),
            'sampleUsers' => User::query()->where('status', 'active')->limit((int) setting('images.sample_users_limit', 20))->get(['id', 'name', 'code']),
            // ⭐ نفس إمكانات محرّك 12.5-ب على الاستوديو (12.14): سحب-إفلات + شبكة محاذاة
            'grid' => (int) setting('images.studio.grid_step', 5),
            'snap' => (bool) setting('images.studio.snap_enabled', true),
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

        return redirect()->route('admin.studio.edit', $template)->with('status', (string) setting('images.admin.store_ok', 'القالب اتحفظ ✓'));
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

        return back()->with('status', (string) setting('images.admin.update_ok', 'التعديل اتحفظ ✓'));
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

        return back()->with('status', (string) setting('images.admin.move_layer_ok', 'ترتيب الطبقات اتغيّر ✓'));
    }

    public function duplicate(ImageTemplate $template): RedirectResponse
    {
        $copy = $template->replicate(['created_at', 'updated_at']);
        $copy->name = strtr((string) setting('images.admin.duplicate_msg', ':a1 — نسخة'), [':a1' => (string) ($template->name)]);
        $copy->is_archived = false;
        $copy->save();

        return redirect()->route('admin.studio.edit', $copy)->with('status', (string) setting('images.admin.duplicate_ok', 'اتعملت نسخة ✓'));
    }

    /** ⭐ أرشفة لا حذف: القالب المستخدَم في نشرٍ قائم لا يُحذَف أبدًا */
    public function archive(ImageTemplate $template): RedirectResponse
    {
        $template->update(['is_archived' => true, 'is_active' => false]);

        return back()->with('status', (string) setting('images.admin.archive_msg', 'القالب اتأرشف — والمنشور القديم بيفضل شغّال.'));
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

        return back()->with('status', strtr((string) setting('images.admin.batch_msg', 'الأرشيف جاهز: :a1'), [':a1' => (string) (Storage::disk('public')->url($zip))]));
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

        return back()->with('status', (string) setting('images.admin.store_particle_ok', 'الأداة اتضافت ✓'));
    }

    public function deleteParticle(NameParticle $particle): RedirectResponse
    {
        $particle->delete();

        return back()->with('status', (string) setting('images.admin.delete_particle_ok', 'الأداة اتشالت ✓'));
    }

    // ---------------------------------------------------------------- داخليّ

    /**
     * المجلّدات المتاحة: افتراضيّات الإعدادات + ما كتبه المصمّمون فعلًا —
     * فالاقتراح يمنع تشتّت التسمية بلا أن يمنع مجلّدًا جديدًا.
     *
     * @return array<int,string>
     */
    private function folderList(): array
    {
        return $this->distinctJson('folders', (array) setting('images.folders.defaults', []));
    }

    /** @return array<int,string> */
    private function tagList(): array
    {
        return $this->distinctJson('tags', []);
    }

    /**
     * @param  array<int,string>  $defaults
     * @return array<int,string>
     */
    private function distinctJson(string $column, array $defaults): array
    {
        return ImageTemplate::query()
            ->whereNotNull($column)
            ->pluck($column)
            ->flatMap(fn ($value) => is_array($value) ? $value : (json_decode((string) $value, true) ?: []))
            ->merge($defaults)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'purpose' => ['nullable', Rule::in(array_keys($this->fields->purposes()))],
            'width_px' => ['required', 'integer', 'min:64', 'max:4096'],
            'height_px' => ['required', 'integer', 'min:64', 'max:4096'],
            'preset' => ['nullable', Rule::in(array_keys($this->layers->presets()))],
            // ⭐ «رفع الفريم/الخلفيّة كصورة وتُبنى فوقها الطبقات» (12.14-أ) —
            //    والمسار يأتي من مكتبة الوسائط نفسها لا من منتقٍ ثانٍ (2.14-ب).
            'frame_path' => ['nullable', 'string', 'max:255'],
            'audience' => ['required', 'in:admin,volunteers,everyone'],
            'language' => ['nullable', Rule::in(array_keys($this->fields->languages()))],
            'is_active' => ['nullable', 'boolean'],
            'layers' => ['nullable', 'array'],
            // «مجلّدات ووسوم» (12.14-أ): تُكتَب مفصولةً بفاصلة وتُخزَّن مصفوفةً
            'tags' => ['nullable'],
            'folders' => ['nullable'],
        ]);

        $data['layers'] = $this->layers->sanitize($data['layers'] ?? []);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['purpose'] = $data['purpose'] ?? 'marketing';
        $data['language'] = $data['language'] ?? 'ar';
        $data['frame_path'] = $this->frame($data['frame_path'] ?? null);
        // ⭐ نفس منظّف الوسوم المستعمَل في مكتبة الوسائط — مصدرٌ واحد لا نسختان
        $data['tags'] = $this->media->cleanTags($data['tags'] ?? []);
        $data['folders'] = $this->media->cleanTags($data['folders'] ?? []);

        return $data;
    }

    /**
     * مسار الفريم: لا يُقبل إلّا ملفٌّ موجود فعلًا على القرص العامّ — فمسارٌ
     * مكتوب بيدٍ لا يرسم شيئًا ويترك المصمّم يظنّ أنّه رفع.
     */
    private function frame(?string $candidate): ?string
    {
        $path = trim((string) $candidate);

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return $path;
    }
}
