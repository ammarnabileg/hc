<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\LibraryEntitlement;
use App\Models\Product;
use App\Models\Referral;
use App\Services\Library\EntitlementGuard;
use App\Services\Library\LibraryShelf;
use App\Services\Referral\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * مكتبتي (الدستور 20 · 24.5): كلّ ما يملكه المستخدم بوصولٍ دائم
 * — يقفل حلقة «اشتريت ← فين ألاقيه». ولا يراها إلّا صاحبها.
 */
class LibraryController extends Controller
{
    public function __construct(
        private readonly LibraryShelf $shelf,
        private readonly EntitlementGuard $guard,
        private readonly ReferralService $referrals,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $items = $this->shelf->items($user);
        $counts = $this->shelf->counts($items);

        $tab = in_array($request->query('tab'), LibraryShelf::TABS, true)
            ? (string) $request->query('tab')
            : 'all';

        $filtered = $this->shelf->filter($items, [
            'tab' => $tab,
            'q' => $request->query('q'),
            'type' => $request->query('type'),
            'sort' => $request->query('sort'),
            'currency' => $request->query('currency'),
        ]);

        return view('library.index', [
            'items' => $filtered,
            'counts' => $counts,
            'tab' => $tab,
            'sortOptions' => $this->shelf->sortOptions(),
            'typeOptions' => $this->shelf->typeOptions(),
            'sort' => (string) ($request->query('sort') ?: setting('library.shelf.default_sort', 'recent')),
            'hasAnything' => $items->isNotEmpty(),
            'storeUrl' => Route::has('store.index') ? route('store.index') : url('/'),
            // ⭐ رابط الدعوة يظهر على صورة المشاركة (20.4) — من محرّك الريفيرال الواحد
            'referralLink' => $this->referrals->link($user),
        ]);
    }

    /** تفاصيل العنصر: معاينة + الفاتورة/الإيصال — في بوب-أب لا صفحة جديدة (2.15-أ-6) */
    public function item(Request $request, LibraryEntitlement $entitlement): JsonResponse
    {
        $this->authorizeOwnership($request, $entitlement);

        $entitlement->load(['itemable', 'order.currency']);
        $item = $entitlement->itemable;
        $order = $entitlement->order;

        return response()->json([
            'title' => $item?->name_ar ?? '',
            'description' => $item?->description ?? $item?->description_ar ?? '',
            // معاينة قبل الفتح (20.1): الغلاف، وللمنتج المحميّ أوّل صفحة عيّنة
            'preview' => $this->previewUrl($entitlement),
            'availability' => $this->guard->availability($entitlement),
            'invoice' => $order ? [
                'number' => $order->number,
                'date' => $order->paid_at?->translatedFormat((string) setting('library.invoice.date_format', 'j F Y')),
                'total' => (float) $order->total,
                'currency' => $order->currency?->name_ar ?? '',
                'status' => $order->status,
                /*
                 | حقول الفاتورة المنصوصة في 19.4 وقالب الفاتورة (24.3):
                 | **الرسوم** و**طريقة الدفع** و**إشارة سياسة عدم الاسترجاع**.
                 | والرسوم تُشتقّ في الخادم من أرقام الطلب نفسها لا من المتصفّح.
                 */
                'fees' => round((float) $order->total - ((float) $order->subtotal - (float) $order->discount), 2),
                'payment_method' => strtr((string) setting('library.screen.item_msg', 'خصم من رصيد المحفظة — :a1'), [':a1' => (string) (($order->currency?->name_ar ?? ''))]),
                'refund_note' => setting('finance.refund.show_on_invoice', true)
                    ? (string) setting('library.invoice.refund_note', 'لا يوجد استرجاع نقديّ — ورصيدك يفضل في محفظتك تشتري بيه اللي انت عايزه من الموقع.')
                    : null,
            ] : null,
            'recommend_url' => route('library.recommend', $entitlement),
        ]);
    }

    /**
     * «أوصِ بهذا» ⟵ رابط ريفيرال مربوط بعمولة 7% (20.4 · 19.3)،
     * وبـUTM موحّد على كلّ رابط تولّده المنصّة (21.2-ح).
     */
    public function recommend(Request $request, LibraryEntitlement $entitlement): JsonResponse
    {
        $this->authorizeOwnership($request, $entitlement);

        $user = $request->user();
        $entitlement->load('itemable');

        $referral = Referral::firstOrCreate(
            [
                'referrer_id' => $user->id,
                'landing_type' => $entitlement->itemable_type,
                'landing_id' => $entitlement->itemable_id,
            ],
            [
                'code' => (string) $user->code,
                'commission_percent' => (float) setting('library.recommend.commission_percent', 7),
                'utm_source' => (string) setting('library.recommend.utm_source', 'library'),
                'utm_medium' => (string) setting('library.recommend.utm_medium', 'recommend'),
                'utm_campaign' => (string) setting('library.recommend.utm_campaign', 'organic'),
            ],
        );

        $target = Route::has('store.index') ? route('store.index') : url('/');

        $url = $target.'?'.http_build_query([
            'ref' => $referral->code,
            'utm_source' => $referral->utm_source,
            'utm_medium' => $referral->utm_medium,
            'utm_campaign' => $referral->utm_campaign,
        ]);

        return response()->json([
            'url' => $url,
            'message' => (string) setting('library.recommend.done_message', 'الرابط جاهز — كلّ عمليّة شراء منه ليك فيها عمولة.'),
        ]);
    }

    /** صورة المعاينة: الغلاف إن وُجد، وإلّا أوّل صفحة من القارئ المحميّ نفسه */
    private function previewUrl(LibraryEntitlement $entitlement): ?string
    {
        $item = $entitlement->itemable;

        if ($item?->cover_path) {
            return Storage::disk('public')->url($item->cover_path);
        }

        if ($item instanceof Product && ($item->type === 'protected_pdf' || ! $item->is_downloadable)) {
            return route('library.page', ['product' => $item->id, 'page' => 1]);
        }

        return null;
    }

    /** الملكيّة أوّلًا: المكتبة لا يراها إلّا صاحبها (20.1) */
    private function authorizeOwnership(Request $request, LibraryEntitlement $entitlement): void
    {
        abort_if($entitlement->user_id !== $request->user()->id, 403, (string) setting(
            'library.forbidden_message',
            'العنصر ده مش في مكتبتك.',
        ));
    }
}
