<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\Ops\OnboardingContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * محتوى الـOnboarding (12.7-أ) وشاشات «أوّل مرّة» (2.15-د).
 *
 * سؤال الشاشة الواحد: **ماذا يرى المستخدم الجديد أوّل ما يدخل؟**
 * ولذلك المعاينة هنا ليست زينة — هي الطريقة الوحيدة ليتأكّد الأدمن
 * أنّ ما كتبه يُقرأ فعلًا كما تخيّله، قبل أن يراه ألف مستخدم.
 */
class OnboardingContentController extends Controller
{
    public function __construct(private readonly OnboardingContent $content) {}

    public function index(Request $request): View
    {
        $screens = $this->content->screens();
        $screen = $request->string('screen')->toString();
        $screen = array_key_exists($screen, $screens) ? $screen : OnboardingContent::WELCOME;

        $tab = $request->string('tab')->toString() ?: 'slides';
        $tab = in_array($tab, ['slides', 'first_time'], true) ? $tab : 'slides';

        return view('admin.ops.onboarding', [
            'tab' => $tab,
            'screen' => $screen,
            'screens' => $screens,
            'counts' => $this->content->countsByScreen(),
            'kpis' => $this->content->kpis(),
            // تحميل كسول للتاب: لا نبني بيانات تاب مقفول (2.15-ب)
            'slides' => $tab === 'slides' ? $this->content->slides($screen) : collect(),
            'enabled' => $this->content->enabledScreens(),
            'templates' => $this->content->templates(),
            'isFull' => $this->content->isFull($screen),
            'journey' => $this->content->journey($screen),
        ]);
    }

    /** ⭐ معاينة كما يراها المستخدم — نفس البوب-أب بمراحله لا رسمًا تقريبيًّا له */
    public function preview(Request $request): View
    {
        $screens = $this->content->screens();
        $screen = $request->string('screen')->toString();
        $screen = array_key_exists($screen, $screens) ? $screen : OnboardingContent::WELCOME;

        return view('admin.ops.onboarding-preview', [
            'journey' => $this->content->journey($screen),
            'screens' => $screens,
            'screen' => $screen,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($this->content->isFull($data['screen'])) {
            return back()->withErrors([
                'title_ar' => 'وصلت للحدّ الأقصى للشرائح في الشاشة دي — امسح واحدة أو ارفع الحدّ من الإعدادات.',
            ])->withInput();
        }

        $data['image_path'] = $this->storeImage($request);
        $this->content->create($data, $request->user());

        return back()->with('status', 'الشريحة اتضافت ✓');
    }

    public function update(Request $request, int $slide): RedirectResponse
    {
        $data = $this->validated($request);
        $image = $this->storeImage($request);

        if ($image) {
            $data['image_path'] = $image;
        }

        if (! $this->content->update($slide, $data, $request->user())) {
            return back()->withErrors(['title_ar' => 'الشريحة دي مش موجودة — يمكن اتمسحت.']);
        }

        return back()->with('status', 'اتحفظ ✓');
    }

    public function toggle(Request $request, int $slide): RedirectResponse
    {
        $this->content->toggle($slide, $request->user());

        return back()->with('status', 'اتحفظ ✓');
    }

    public function destroy(Request $request, int $slide): RedirectResponse
    {
        $this->content->delete($slide, $request->user());

        return back()->with('status', 'الشريحة اتمسحت.');
    }

    /** الترتيب بالسحب — وهو تسلسل المراحل الذي سيمرّ عليه المستخدم بالضبط */
    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'screen' => ['required', 'string', 'max:64'],
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer'],
        ]);

        $this->content->reorder($data['screen'], $data['order'], $request->user());

        return back()->with('status', 'الترتيب اتحفظ ✓');
    }

    /** قالب جاهز قابل للتعديل — يضيف المراحل ولا يمسح ما كتبه الأدمن */
    public function applyTemplate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'screen' => ['required', 'string', 'max:64'],
        ]);

        $added = $this->content->applyTemplate($data['screen'], $request->user());

        return back()->with('status', $added > 0
            ? "اتضافت {$added} مرحلة من القالب — عدّلها زيّ ما تحبّ."
            : 'مافيش قالب جاهز للشاشة دي لسه.');
    }

    /** اختيار الشاشات التي تظهر فيها «أوّل مرّة» — على أهمّ الشاشات فقط (2.15-د) */
    public function saveFirstTime(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'screens' => ['nullable', 'array'],
            'screens.*' => ['string', 'max:64'],
        ]);

        $result = $this->content->saveEnabledScreens($data['screens'] ?? [], $request->user());

        return back()->with('status', ($result['saved'] ?? false)
            ? 'اتحفظ ✓ — '.count($result['screens']).' شاشة مفعَّلة.'
            : ($result['message'] ?? 'مقدرناش نحفظ.'));
    }

    // ------------------------------------------------------------------ داخليّ

    private function validated(Request $request): array
    {
        $maxKb = max(1, (int) setting('onboarding.slides.image_max_kb', 2048));

        return $request->validate([
            'screen' => ['required', 'string', 'max:64'],
            'title_ar' => ['required', 'string', 'max:120'],
            'body_ar' => ['nullable', 'string', 'max:600'],
            'action_label' => ['nullable', 'string', 'max:60'],
            'action_url' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'file', 'image', 'max:'.$maxKb],
        ]);
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        $folder = trim((string) setting('onboarding.slides.image_folder', 'onboarding'), '/');

        return Storage::disk('public')->put($folder, $request->file('image')) ?: null;
    }
}
