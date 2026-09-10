<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cv;
use App\Models\CvTemplate;
use App\Services\Library\CvTemplateDecor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * شاشة إدارة قوالب الـCV (الدستور 9 · 12.0 ⟵ «قوالب الـCV»).
 *
 * نصّ 9: «**5 قوالب مبدئيًّا، والأدمن يضيف قوالب. كل قالب له عدد تذاكر خاص**
 * — الافتراضيّ تذكرتان». فكانت القوالب تُبذَر ولا تُدار: لا إضافة ولا تسعير
 * ولا إيقاف من اللوحة.
 *
 * والسعر هنا **Override صريح**، وتركُه فارغًا يعني «اتبع جدول أوجه الصرف»
 * (`CvTemplate::priceTickets()`) — فمصدر السعر واحدٌ لا اثنان.
 */
class CvTemplateAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.cv-templates.index', [
            'templates' => CvTemplate::query()->orderBy('sort_order')->orderBy('id')->get(),
            // كم سيرةً تستعمل كلّ قالب — الرقم يوضّح أثر الإيقاف قبل الفعل (2.15-د)
            'usage' => Cv::query()
                ->selectRaw('cv_template_id, count(*) as total')
                ->whereNotNull('cv_template_id')
                ->groupBy('cv_template_id')
                ->pluck('total', 'cv_template_id')
                ->all(),
            'defaultPrice' => (float) setting('cv.template.default_price_tickets', 2),
            'views' => $this->availableViews(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = CvTemplate::create($this->rules($request) + ['sort_order' => (int) CvTemplate::max('sort_order') + 1]);

        $this->keepOneFreeTemplate($template);

        return back()->with('status', (string) setting('cv.template.admin.created_message', 'اتضاف القالب ✓'));
    }

    public function update(Request $request, CvTemplate $template): RedirectResponse
    {
        $template->update($this->rules($request));

        $this->keepOneFreeTemplate($template);

        return back()->with('status', (string) setting('cv.template.admin.saved_message', 'اتحفظ ✓'));
    }

    /**
     * الإيقاف لا الحذف ما دام القالب مستعمَلًا — فلا تُكسَر سيرةٌ منشورة
     * بحذف قالبها من تحتها.
     */
    public function destroy(CvTemplate $template): RedirectResponse
    {
        $inUse = Cv::where('cv_template_id', $template->id)->exists();

        if ($inUse) {
            $template->update(['is_active' => false]);

            return back()->with('status', (string) setting(
                'cv.template.admin.archived_message',
                'القالب مستعمَل في سِيَر قايمة — وقّفناه بدل ما نحذفه.',
            ));
        }

        $template->delete();

        return back()->with('status', (string) setting('cv.template.admin.deleted_message', 'اتشال القالب ✓'));
    }

    /**
     * ⭐ المحرّر المرئيّ (Drag-drop) لقوالب الـCV — حفظ الطبقة الزخرفيّة
     * (المرحلة 1/2 · 12.7-ب): هذه المرحلة خلفيّةٌ فقط، وواجهة السحب-والإفلات
     * نفسها مرحلةٌ ثانية لاحقة — الحارس هنا `cv_templates.edit` نفسه المستعمَل
     * لباقي تعديلات القالب، فلا صلاحيّة جديدة.
     *
     * `layers` تصل إمّا JSON خامّ (حقل مخفيّ واحد يكتبه محرّر Canvas لاحقًا —
     * أقرب لنمط `TemplateDesigner::sanitizeLayers()`) أو مصفوفةً مُرسَلة
     * بنمط الفورم `layers[i][key]` كالاستوديو (12.14) — الاثنان مقبولان هنا
     * فلا يتقيّد شكل الفورم القادم في المرحلة 2 بواحدٍ منهما مسبقًا.
     */
    public function updateDecor(Request $request, CvTemplate $template, CvTemplateDecor $decor): RedirectResponse
    {
        $raw = $request->input('layers', []);
        $raw = is_string($raw) ? (json_decode($raw, true) ?: []) : (array) $raw;

        $template->update(['decor_layers' => $decor->sanitize($raw)]);

        return back()->with('status', (string) setting('cv.template.admin.decor_saved_message', 'اتحفظت الطبقة الزخرفيّة ✓'));
    }

    /**
     * تنزيل ملفّ البلايد الخامّ للقالب (12.7-ب · cv_templates.export) — الحارس
     * `permission:cv_templates.export` وحده يفتح المسار، والزرّ نفسه مخفيّ
     * لا معطّل عن غير صاحب الصلاحيّة (2.15-أ-7).
     */
    public function download(CvTemplate $template): BinaryFileResponse
    {
        $path = resource_path('views/cv/templates/'.$template->view_path.'.blade.php');

        abort_unless(is_file($path), 404);

        return response()->download($path, $template->view_path.'.blade.php');
    }

    /** @return array<string, mixed> */
    private function rules(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'view_path' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'preview_path' => ['nullable', 'string', 'max:255'],
            // NULL = «اتبع جدول أوجه الصرف»، ورقمٌ = Override صريح لهذا القالب
            'price_tickets' => ['nullable', 'numeric', 'min:0', 'max:'.(int) setting('cv.template.max_price_tickets', 100)],
            'is_free' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'ats_options' => ['nullable', 'array'],
            'ats_options.*' => ['nullable', 'numeric'],
        ], [
            'name.required' => (string) setting('cv.admin.rules_msg', 'اكتب اسم القالب — هو اللي بيظهر للمستخدم.'),
            'view_path.required' => (string) setting('cv.admin.rules_msg_2', 'اختار ملفّ العرض من القائمة.'),
            'view_path.regex' => (string) setting('cv.admin.rules_msg_3', 'اسم ملفّ العرض حروف صغيرة وأرقام وشرطات بس.'),
            'price_tickets.max' => (string) setting('cv.admin.rules_msg_4', 'السعر عالي أوي — راجعه.'),
        ], [
            'name' => (string) setting('cv.admin.rules_msg_5', 'اسم القالب'),
            'view_path' => (string) setting('cv.admin.rules_msg_6', 'ملفّ العرض'),
            'price_tickets' => (string) setting('cv.admin.rules_msg_7', 'السعر بالتذاكر'),
        ]);

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'view_path' => $data['view_path'],
            'preview_path' => $data['preview_path'] ?? null,
            'price_tickets' => $data['price_tickets'] ?? null,
            'is_free' => (bool) ($data['is_free'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'ats_options' => array_filter(
                (array) ($data['ats_options'] ?? []),
                fn ($value) => $value !== null && $value !== '',
            ) ?: null,
        ];
    }

    /** قالبٌ مجّانيّ **واحد** باب الدخول (21.2-ج) — فلا يتعدّد ولا يختفي */
    private function keepOneFreeTemplate(CvTemplate $template): void
    {
        if ($template->is_free) {
            CvTemplate::where('id', '!=', $template->id)->where('is_free', true)->update(['is_free' => false]);

            return;
        }

        if (! CvTemplate::where('is_free', true)->where('is_active', true)->exists()) {
            $template->update(['is_free' => true, 'is_active' => true]);
        }
    }

    /**
     * ملفّات العرض المتاحة — من مجلّد قوالبنا وحده، فلا يُحمَّل مسارٌ من خارجه.
     *
     * @return array<int, string>
     */
    private function availableViews(): array
    {
        return collect(glob(resource_path('views/cv/templates/*.blade.php')) ?: [])
            ->map(fn (string $path) => str_replace('.blade.php', '', basename($path)))
            ->values()
            ->all();
    }
}
