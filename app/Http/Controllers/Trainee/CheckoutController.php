<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Services\Store\Coins;
use App\Services\Store\PricingService;
use App\Services\Store\PurchaseException;
use App\Services\Store\PurchaseService;
use App\Services\Store\StoreCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * بوب-أب الشراء (24.5): الملخّص · الرصيد قبل/بعد · الكوبون · Order-bump ·
 * إقرار سياسة عدم الاسترجاع (19.4) · وشحن المحفظة من داخل البوب-أب نفسه.
 *
 * ⭐ الطلب لا يحمل سعرًا ولا خصمًا — يحمل هويّة العنصر والكوبون واختيار الـBump فقط.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly StoreCatalog $catalog,
        private readonly PricingService $pricing,
        private readonly PurchaseService $purchases,
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
            withBump: (bool) ($data['add_bump'] ?? false),
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
                'refund_ack' => (bool) ($data['refund_ack'] ?? false),
            ]);
        } catch (PurchaseException $e) {
            if ($request->expectsJson()) {
                return response()->json(['reason' => $e->reason, 'message' => $e->getMessage()], 422);
            }

            return back()
                ->withInput()
                ->with('checkout_reason', $e->reason)
                ->withErrors(['checkout' => $e->getMessage()]);
        }

        $message = str_replace(
            '{number}',
            $order->number,
            (string) setting('store.checkout.success_text', 'تمّ الشراء ✓ — طلبك رقم {number} وتلاقي شراءك في مكتبتك.'),
        );

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'order' => $order->number]);
        }

        return redirect()
            ->route('store.product', ['type' => $data['type'], 'slug' => $data['slug']])
            ->with('status', $message);
    }

    // ------------------------------------------------------------ داخليّ

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(StoreCatalog::TYPES))],
            'slug' => ['required', 'string', 'max:255'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'add_bump' => ['nullable', 'boolean'],
            'refund_ack' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * الأرقام تخرج مصاغةً من الخادم — فلا تحسب الواجهة سعرًا ولا خصمًا.
     *
     * @return array<string, mixed>
     */
    private function present(array $quote): array
    {
        return [
            'subtotal' => Coins::label($quote['subtotal']),
            'discount' => Coins::label($quote['discount']),
            'total' => Coins::label($quote['total']),
            'balance_before' => Coins::label($quote['balance_before']),
            'balance_after' => Coins::label($quote['balance_after']),
            'savings' => $quote['savings'] > 0 ? Coins::label($quote['savings']) : null,
            'sufficient' => $quote['sufficient'],
            'owned' => $quote['owned'],
            'coupon_valid' => $quote['coupon']['valid'],
            'coupon_message' => $quote['coupon']['message'],
            'lines' => array_map(fn ($line) => [
                'title' => $line['title'],
                'price' => Coins::label($line['price']),
                'is_order_bump' => $line['is_order_bump'],
            ], $quote['lines']),
        ];
    }
}
