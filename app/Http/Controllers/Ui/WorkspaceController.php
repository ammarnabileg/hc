<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Models\SavedView;
use App\Models\UserFirstRun;
use App\Services\Ui\FirstRunScreens;
use App\Services\Ui\UndoStack;
use App\Services\Ux\ViewMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * مساحة عمل المستخدم (2.15-د): **التثبيت (Pin)** · **العروض المحفوظة** ·
 * **شاشة أوّل مرّة** · **التراجع خلال 5 ثوانٍ**.
 *
 * ⭐ «آخر ما زرت» **مرفوض صراحةً** في الدستور، والبديل المعتمَد هو التثبيت —
 *   فلا يوجد هنا أيّ مسار يسجّل تاريخ التصفّح.
 */
class WorkspaceController extends Controller
{
    public function __construct(
        private readonly UndoStack $undo,
        private readonly FirstRunScreens $firstRun,
        private readonly ViewMode $viewMode,
    ) {}

    // ------------------------------------------------------------ التثبيت (Pin)

    /** تثبيت صفحة أو فكّ تثبيتها — والقائمة تظهر أعلى السايد بار */
    public function togglePin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'route' => ['required', 'string', 'max:120'],
            'label' => ['required', 'string', 'max:80'],
        ]);

        // لا نثبّت إلّا مسارًا حقيقيًّا — فلا يدخل رابط خارجيّ في السايد بار
        abort_unless(Route::has($data['route']), 422, 'الصفحة دي مش موجودة.');

        $user = $request->user();
        $pins = collect($user->pinned_pages ?? [])->values();
        $exists = $pins->firstWhere('route', $data['route']);

        if ($exists) {
            $pins = $pins->reject(fn ($p) => ($p['route'] ?? null) === $data['route'])->values();
        } else {
            $max = max(1, (int) setting('ux.pins.max', 8));

            if ($pins->count() >= $max) {
                return response()->json([
                    'ok' => false,
                    'message' => 'وصلت لحدّ '.$max.' صفحات مثبَّتة — شيل واحدة الأوّل.',
                ], 422);
            }

            $pins->push([
                'route' => $data['route'],
                'label' => $data['label'],
                'url' => route($data['route']),
            ]);
        }

        $user->forceFill(['pinned_pages' => $pins->all()])->save();

        return response()->json([
            'ok' => true,
            'pinned' => ! $exists,
            'message' => $exists ? 'شيلناها من المثبَّتة ✓' : 'اتثبّتت أعلى السايد بار ✓',
            'pins' => $pins->all(),
        ]);
    }

    /** الترتيب بالسحب — يُحفَظ لكلّ مستخدم (2.15-د) */
    public function reorderPins(Request $request): JsonResponse
    {
        $data = $request->validate([
            'routes' => ['required', 'array'],
            'routes.*' => ['string', 'max:120'],
        ]);

        $user = $request->user();
        $pins = collect($user->pinned_pages ?? []);

        $ordered = collect($data['routes'])
            ->map(fn ($route) => $pins->firstWhere('route', $route))
            ->filter()
            ->values();

        // أيّ مثبَّت لم يرد في الترتيب يبقى في آخر القائمة — فلا يضيع بالصدفة
        $rest = $pins->reject(fn ($p) => in_array($p['route'] ?? '', $data['routes'], true))->values();

        $user->forceFill(['pinned_pages' => $ordered->merge($rest)->all()])->save();

        return response()->json(['ok' => true, 'message' => 'اتحفظ ✓']);
    }

    // ------------------------------------------------------- العروض المحفوظة

    public function storeView(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'screen' => ['required', 'string', 'max:96'],
            'name' => ['required', 'string', 'max:96'],
            'filters' => ['nullable', 'array'],
        ], [], ['name' => 'اسم العرض']);

        abort_unless(Route::has($data['screen']), 422, 'الشاشة دي مش موجودة.');

        $max = max(1, (int) setting('ux.saved_views.max_per_screen', 10));

        $count = SavedView::query()
            ->where('user_id', $request->user()->id)
            ->where('screen', $data['screen'])
            ->count();

        if ($count >= $max) {
            return back()->with('status', 'وصلت لحدّ '.$max.' عروض للشاشة دي — امسح واحد وجرّب تاني.');
        }

        SavedView::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'screen' => $data['screen'],
                'name' => $data['name'],
            ],
            [
                'filters' => array_filter((array) ($data['filters'] ?? []), fn ($v) => $v !== null && $v !== ''),
                'sort_order' => $count,
            ],
        );

        return back()->with('status', 'اتحفظ العرض ✓');
    }

    public function destroyView(Request $request, SavedView $view): RedirectResponse
    {
        abort_unless($view->user_id === $request->user()->id, 403);

        $view->delete();

        return back()->with('status', 'اتشال العرض ✓');
    }

    // --------------------------------------------------------- شاشة أوّل مرّة

    /** تعليم الشاشة كمرئيّة — وزرّ «؟» يعيدها بإرسال `again` */
    public function seenFirstRun(Request $request): JsonResponse
    {
        $data = $request->validate([
            'screen' => ['required', 'string', 'max:96'],
            'again' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        if ($request->boolean('again')) {
            UserFirstRun::query()
                ->where('user_id', $user->id)
                ->where('screen', $data['screen'])
                ->delete();

            return response()->json(['ok' => true, 'message' => 'هتظهرلك تاني أوّل ما تفتح الصفحة.']);
        }

        UserFirstRun::updateOrCreate(
            ['user_id' => $user->id, 'screen' => $data['screen']],
            ['seen_at' => now()],
        );

        return response()->json(['ok' => true, 'message' => 'تمام ✓']);
    }

    /** محتوى شاشة أوّل مرّة لشاشة بعينها — قوالب جاهزة قابلة للتعديل من الأدمن */
    public function firstRunContent(Request $request): JsonResponse
    {
        $screen = (string) $request->query('screen', '');

        return response()->json([
            'screen' => $screen,
            'steps' => $this->firstRun->stepsFor($screen),
        ]);
    }

    // -------------------------------------------------- وضع «متقدّم» (2.15)

    /**
     * تبديل سويتش «وضع متقدّم» من أيّ صفحة — ويُحفَظ لكلّ مستخدم (2.15-أ-9).
     *
     * يعود للصفحة نفسها لأنّ الوضع **يفتح ما أُخفي فيها**؛ فلو أرجعناه للوحة
     * ضاع مكان المستخدم — وهو ما تمنعه 2.15-أ-6 («لا يفقد مكانه في القائمة»).
     */
    public function toggleMode(Request $request): RedirectResponse
    {
        $on = $this->viewMode->toggle($request->user());

        return back()->with('status', $on
            ? 'الوضع المتقدّم اتفتح ✓ — كلّ التفاصيل ظاهرة دلوقتي.'
            : 'رجعنا للوضع المبسّط ✓ — التفاصيل موجودة ورا السويتش.');
    }

    // ------------------------------------------------------------- التراجع

    /** التراجع خلال المهلة — والرسالة تقول ماذا حدث وماذا تفعل (2.17-ب) */
    public function undo(Request $request, string $token): JsonResponse
    {
        $result = $this->undo->undo($request->user(), $token);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
