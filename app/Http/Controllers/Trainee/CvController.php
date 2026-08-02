<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\CvTemplate;
use App\Models\User;
use App\Services\Library\CvBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\View\View;

/**
 * خبراتي ← السيرة الذاتيّة (الدستور 9 · 24.5).
 *
 * Stepper بخمس خطوات **بحفظ تلقائيّ بينها** مع «اتحفظ ✓» (2.15-د · 2.17-ب)،
 * ومعاينة حيّة بالمقاس الحقيقيّ بجوارها — وعلى الموبايل تاب منفصل.
 */
class CvController extends Controller
{
    /** مفتاح جلسة الزائر في القالب المجّانيّ بلا تسجيل (21.2-ج) */
    private const GUEST_KEY = 'cv.guest.draft';

    public function __construct(private readonly CvBuilder $builder) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $cv = $this->builder->forUser($user);
        $data = array_replace($this->builder->blank(), (array) $cv->data);

        return view('cv.index', $this->payload($user, $data, $cv->cv_template_id) + [
            'guest' => false,
            'autosaveUrl' => route('cv.autosave'),
            'downloadUrl' => route('cv.download'),
        ]);
    }

    /** الحفظ التلقائيّ بين الخطوات — ردٌّ فوريّ بلا إعادة تحميل (2.17-ب) */
    public function autosave(Request $request): JsonResponse
    {
        $user = $request->user();
        $cv = $this->builder->forUser($user);

        $step = (string) $request->input('step', 'profile');
        $data = $this->builder->merge((array) $cv->data, $step, (array) $request->input('data', []));

        $cv->data = $data;
        $cv->completion_percent = $this->builder->completion($data);
        $cv->save();

        return response()->json([
            'saved' => true,
            'label' => (string) setting('cv.autosave.saved_label', 'اتحفظ ✓'),
            'completion' => $cv->completion_percent,
            'missing' => $this->builder->missing($data),
        ]);
    }

    /**
     * اختيار القالب — والمدفوع بالتذاكر بعلامة سعر واضحة، والشراء بالرصيد
     * قبل/بعد داخل البوب-أب نفسه (24.5).
     */
    public function chooseTemplate(Request $request, CvTemplate $template): JsonResponse
    {
        abort_unless($template->is_active, 404);

        $user = $request->user();
        $cv = $this->builder->forUser($user);
        $data = array_replace($this->builder->blank(), (array) $cv->data);

        if (! $this->builder->owns($template, $data)) {
            if (! $request->boolean('confirm')) {
                $balance = $this->builder->ticketBalance($user);

                return response()->json([
                    'ok' => false,
                    'needs_purchase' => true,
                    'price' => (float) $template->price_tickets,
                    'balance_before' => $balance,
                    'balance_after' => $balance - (float) $template->price_tickets,
                ]);
            }

            $result = $this->builder->purchase($user, $template);

            if (! $result['ok']) {
                return response()->json([
                    'ok' => false,
                    'needs_purchase' => true,
                    'message' => $result['message'],
                    'balance_before' => $result['balance'],
                    'topup_url' => Route::has('wallet.tickets') ? route('wallet.tickets') : null,
                ], 422);
            }

            $data['purchased_templates'] = array_values(array_unique(array_merge(
                array_map('intval', (array) ($data['purchased_templates'] ?? [])),
                [$template->id],
            )));
        }

        $cv->data = $data;
        $cv->cv_template_id = $template->id;
        $cv->save();

        return response()->json([
            'ok' => true,
            'template_id' => $template->id,
            'message' => (string) setting('cv.template.selected_message', 'اتغيّر القالب — شوف المعاينة.'),
        ]);
    }

    /** السحب التلقائيّ من المنصّة مع إمكانيّة الإخفاء (9) */
    public function togglePull(Request $request, string $source): JsonResponse
    {
        abort_unless(in_array($source, ['profile', 'certificates'], true), 404);

        $user = $request->user();
        $cv = $this->builder->forUser($user);
        $data = array_replace($this->builder->blank(), (array) $cv->data);

        $data['pull'][$source] = $request->boolean('enabled');
        $cv->data = $data;
        $cv->save();

        return response()->json([
            'saved' => true,
            'enabled' => $data['pull'][$source],
            'label' => (string) setting('cv.autosave.saved_label', 'اتحفظ ✓'),
        ]);
    }

    /** المعاينة الحيّة بالمقاس الحقيقيّ — تُحمَّل داخل إطار بجوار الخطوات */
    public function preview(Request $request): View
    {
        $user = $request->user();
        $cv = $this->builder->forUser($user);
        $data = array_replace($this->builder->blank(), (array) $cv->data);

        return view('cv.preview', [
            'sheet' => $this->sheet($user, $data, $cv->cv_template_id),
            'standalone' => true,
            'print' => false,
        ]);
    }

    /**
     * [تحميل PDF] كفعل رئيسيّ — بصفحة طباعة بمقاس A4 يحفظها المتصفّح PDF.
     * لماذا لا مكتبة PDF: كلّ مكتبات التوليد تُنزَّل من الشبكة وهي محجوبة هنا،
     * فنستعمل محرّك الطباعة المثبَّت في كلّ متصفّح — بلا تبعيّة ولا تكلفة.
     */
    public function download(Request $request): View
    {
        $user = $request->user();
        $cv = $this->builder->forUser($user);
        $data = array_replace($this->builder->blank(), (array) $cv->data);

        return view('cv.preview', [
            'sheet' => $this->sheet($user, $data, $cv->cv_template_id),
            'standalone' => true,
            'print' => true,
        ]);
    }

    // ------------------------------------------------------------------ بلا تسجيل (21.2-ج)

    /** منشئ CV بقالبٍ واحد مجّانيّ بلا تسجيل، والتحميل يطلب إنشاء حساب */
    public function free(Request $request): View
    {
        if ($request->user()) {
            return $this->index($request);
        }

        $data = array_replace($this->builder->blank(), (array) $request->session()->get(self::GUEST_KEY, []));
        $free = $this->builder->freeTemplate();

        return view('cv.index', $this->payload(new User, $data, $free?->id) + [
            'guest' => true,
            'autosaveUrl' => route('cv.free.autosave'),
            'downloadUrl' => Route::has('register') ? route('register') : url('/'),
        ]);
    }

    public function freePreview(Request $request): View
    {
        if ($request->user()) {
            return $this->preview($request);
        }

        $data = array_replace($this->builder->blank(), (array) $request->session()->get(self::GUEST_KEY, []));

        return view('cv.preview', [
            'sheet' => $this->sheet(new User, $data, $this->builder->freeTemplate()?->id),
            'standalone' => true,
            'print' => false,
        ]);
    }

    public function freeAutosave(Request $request): JsonResponse
    {
        $data = $this->builder->merge(
            (array) $request->session()->get(self::GUEST_KEY, []),
            (string) $request->input('step', 'profile'),
            (array) $request->input('data', []),
        );

        $request->session()->put(self::GUEST_KEY, $data);

        return response()->json([
            'saved' => true,
            'label' => (string) setting('cv.autosave.saved_label', 'اتحفظ ✓'),
            'completion' => $this->builder->completion($data),
            'missing' => $this->builder->missing($data),
        ]);
    }

    // ------------------------------------------------------------------ داخليّ

    private function payload(User $user, array $data, ?int $templateId): array
    {
        $templates = $this->builder->templates();

        return [
            'user' => $user,
            'data' => $data,
            'steps' => $this->builder->steps(),
            'completion' => $this->builder->completion($data),
            'missing' => $this->builder->missing($data),
            'templates' => $templates,
            'templateId' => $templateId,
            'ownedTemplateIds' => $templates->filter(fn (CvTemplate $t) => $this->builder->owns($t, $data))->pluck('id')->all(),
            'ticketBalance' => $user->exists ? $this->builder->ticketBalance($user) : 0.0,
            'certificates' => $user->exists ? $this->builder->allCertificates($user) : collect(),
            'pulled' => $user->exists ? $this->builder->pulled($user, $data) : ['profile' => [], 'certificates' => collect()],
            'sheet' => $this->sheet($user, $data, $templateId),
        ];
    }

    /** ورقة المعاينة: القالب المختار — ولا يُحمَّل إلّا من مجلّد قوالبنا */
    private function sheet(User $user, array $data, ?int $templateId): array
    {
        $template = $templateId ? CvTemplate::find($templateId) : null;
        $key = preg_replace('/[^a-z0-9_\-]/', '', (string) ($template?->view_path ?? '')) ?: 'classic';
        $view = 'cv.templates.'.$key;

        if (! ViewFactory::exists($view)) {
            $view = 'cv.templates.classic';
        }

        return [
            'view' => $view,
            'user' => $user,
            'data' => $data,
            'pulled' => $user->exists ? $this->builder->pulled($user, $data) : ['profile' => [], 'certificates' => collect()],
            'templateName' => $template?->name ?? (string) setting('cv.template.default_name', 'كلاسيك'),
        ];
    }
}
