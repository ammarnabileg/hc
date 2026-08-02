<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Services\Store\CartService;
use App\Services\Store\Coins;
use App\Services\Store\NearestTopupOffer;
use App\Services\Store\PricingService;
use App\Services\Store\PurchaseException;
use App\Services\Store\PurchaseService;
use App\Services\Store\StoreCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * بوب-أب الشراء (24.5): الملخّص · الرصيد قبل/بعد · الكوبون · Order-bump (واحد أو اثنان) ·
 * إقرار سياسة عدم الاسترجاع (19.4) · شحن المحفظة من داخل البوب-أب · و**أقرب عرض يكفّيك** (19.5-ب-2).
 *
 * وصفحة **مراجعة الطلب** للسلّة الاختياريّة (17) — بنفس التسعير ونفس الإقرار.
 *
 * ⭐ الطلب لا يحمل سعرًا ولا خصمًا — يحمل هويّة العنصر والكوبون واختيار الـBump فقط.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly StoreCatalog $catalog,
        private readonly PricingService $pricing,
        private readonly PurchaseService $purchases,
        private readonly CartService $cart,
        private readonly NearestTopupOffer $topups,
    ) {}

    /** ملخّص محسوب في الخادم — يحدّث البوب-أب بلا مغادرة الصفحة */
    public function quote(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $item = $this->catalog->resolve($data['type'], $data['slug']);

        if (! $item || ! $this->catalog->isAvailable($data['type'], $item)) {
            return response()->json([
                'message' => (string) setting('store.unavailable_text', 'العنصر ده مش متاح للشراء دلوقتي.'),
            ], 404);
        }

        $quote = $this->pricing->quote(
            user: $request->user(),
            type: $data['type'],
            item: $item,
            couponCode: $data['coupon_code'] ?? null,
            bumps: $this->bumpsOf($data),
        );

        return response()->json($this->present($quote));
    }

    public function checkout(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request);

        try {
            $order = $this->purchases->purchase($request->user(), $data['type'], $data['slug'], [
                'coupon_code' => $data['coupon_code'] ?? null,
                'add_bump' => (bool) ($data['add_bump'] ?? false),
                'bumps' => (array) ($data['bumps'] ?? []),
                'refund_ack' => (bool) ($data['refund_ack'] ?? false),
            ]);
        } catch (PurchaseException $e) {
            return $this->failed($request, $e);
        }

        $message = $this->successText($order->number);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'order' => $order->number]);
        }

        return redirect()
            ->route('store.product', ['type' => $data['type'], 'slug' => $data['slug']])
            ->with('status', $message);
    }

    // ------------------------------------------------------------ السلّة (17)

    /** صفحة مراجعة الطلب: سؤال واحد — «أراجع وأدفع» (2.15) */
    public function cart(Request $request): View
    {
        $rows = $this->cart->raw($request);
        $quote = $this->cart->quote($request->user(), $rows);

        return view('store.cart', [
            'rows' => $rows,
            'quote' => $quote,
            'suggestionText' => $this->topups->sentence($quote['suggestion']),
            'storeUrl' => route('store.index'),
        ]);
    }

    public function addToCart(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(StoreCatalog::TYPES))],
            'slug' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->cart->add($request, $request->user(), $data['type'], $data['slug']);

        return $result['ok']
            ? redirect()->route('store.cart')->with('status', $result['message'])
            : back()->with('status', $result['message']);
    }

    public function removeFromCart(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(StoreCatalog::TYPES))],
            'slug' => ['required', 'string', 'max:255'],
        ]);

        $this->cart->remove($request, $data['type'], $data['slug']);

        return back()->with('status', setting('store.cart.removed_text'));
    }

    /** ملخّص السلّة محسوبًا في الخادم — لتحديث الـBump والكوبون بلا مغادرة الصفحة (17) */
    public function cartQuote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'bumps' => ['nullable', 'array', 'max:2'],
            'bumps.*' => ['string', 'max:255'],
        ]);

        $quote = $this->cart->quote(
            $request->user(),
            $this->cart->raw($request),
            $data['coupon_code'] ?? null,
            (array) ($data['bumps'] ?? []),
        );

        return response()->json($this->present($quote));
    }

    public function cartCheckout(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'bumps' => ['nullable', 'array', 'max:2'],
            'bumps.*' => ['string', 'max:255'],
            'refund_ack' => ['nullable', 'boolean'],
        ]);

        try {
            $order = $this->purchases->purchaseCart($request->user(), $this->cart->raw($request), [
                'coupon_code' => $data['coupon_code'] ?? null,
                'bumps' => (array) ($data['bumps'] ?? []),
                'refund_ack' => (bool) ($data['refund_ack'] ?? false),
            ]);
        } catch (PurchaseException $e) {
            return $this->failed($request, $e);
        }

        $this->cart->clear($request);
        $message = $this->successText($order->number);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'order' => $order->number]);
        }

        return redirect()->route('store.index')->with('status', $message);
    }

    // ------------------------------------------------------------ داخليّ

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(StoreCatalog::TYPES))],
            'slug' => ['required', 'string', 'max:255'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            // اختيار الـBump هويّةٌ لا سعر — والحدّ اثنان (17)
            'bumps' => ['nullable', 'array', 'max:2'],
            'bumps.*' => ['string', 'max:255'],
            'add_bump' => ['nullable', 'boolean'],
            'refund_ack' => ['nullable', 'boolean'],
        ]);
    }

    /** @return bool|array<int, string> */
    private function bumpsOf(array $data): bool|array
    {
        $slugs = array_values(array_filter(array_map('strval', (array) ($data['bumps'] ?? []))));

        return $slugs !== [] ? $slugs : (bool) ($data['add_bump'] ?? false);
    }

    private function successText(string $number): string
    {
        return str_replace(
            '{number}',
            $number,
            (string) setting('store.checkout.success_text', 'تمّ الشراء ✓ — طلبك رقم {number} وتلاقي شراءك في مكتبتك.'),
        );
    }

    private function failed(Request $request, PurchaseException $e): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['reason' => $e->reason, 'message' => $e->getMessage()], 422);
        }

        return back()
            ->withInput()
            ->with('checkout_reason', $e->reason)
            ->withErrors(['checkout' => $e->getMessage()]);
    }

    /**
     * الأرقام تخرج مصاغةً من الخادم — فلا تحسب الواجهة سعرًا ولا خصمًا.
     *
     * @return array<string, mixed>
     */
    private function present(array $quote): array
    {
        // كلّ رقمٍ يخرج **بعملة الطلب** (17) — والرقم بلا عملته يضلّل
        $currency = $quote['currency'] ?? Coins::defaultCode();

        // ⭐ أقرب عرض يكفّيك يُحسَب من العجز بعد الخصم (19.5-ب-2) — عرضٌ فقط
        $suggestion = $quote['suggestion'] ?? $this->topups->forDeficit($quote['total'] - $quote['balance_before'], $currency);

        return [
            'currency' => $currency,
            'subtotal' => Coins::label($quote['subtotal'], $currency),
            'discount' => Coins::label($quote['discount'], $currency),
            'total' => Coins::label($quote['total'], $currency),
            'balance_before' => Coins::label($quote['balance_before'], $currency),
            'balance_after' => Coins::label($quote['balance_after'], $currency),
            'savings' => $quote['savings'] > 0 ? Coins::label($quote['savings'], $currency) : null,
            'sufficient' => $quote['sufficient'],
            'owned' => $quote['owned'],
            'coupon_valid' => $quote['coupon']['valid'],
            'coupon_message' => $quote['coupon']['message'],
            'suggestion' => $this->topups->sentence($suggestion),
            'suggestion_url' => $suggestion['offer']['url'] ?? null,
            'lines' => array_map(fn ($line) => [
                'title' => $line['title'],
                'price' => Coins::label($line['price'], $currency),
                'is_order_bump' => $line['is_order_bump'],
            ], $quote['lines']),
        ];
    }
}
