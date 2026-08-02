<?php

namespace App\Services\Store;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * ⭐ التسعير في الخادم حصرًا (19.5-أ): لا يأتي سعرٌ ولا خصمٌ من المتصفّح إطلاقًا.
 * المتصفّح يرسل **هويّة العنصر وكود الكوبون واختيار الـBump فقط** — والباقي يُحسَب هنا.
 *
 * وبلا Dark Patterns (2.9): الخصم بقيمته الحقيقيّة، والتوفير محسوب من قيمةٍ مسجَّلة
 * في `bundles.original_value` أو من السعر الأساسيّ — لا من رقمٍ مخترَع.
 */
class PricingService
{
    public function __construct(private readonly StoreCatalog $catalog) {}

    /**
     * ملخّص شراءٍ كامل يُعرَض في البوب-أب ويُنفَّذ به الطلب.
     *
     * @return array<string, mixed>
     */
    public function quote(?User $user, string $type, Model $item, ?string $couponCode = null, bool $withBump = false): array
    {
        $price = $this->priceOf($type, $item);
        $listPrice = $this->listPriceOf($type, $item);

        $lines = [[
            'type' => $type,
            'slug' => $item->slug,
            'title' => $item->name_ar,
            'price' => $price,
            'is_order_bump' => false,
        ]];

        $bump = null;
        $offers = $this->bumpOffers($user, $type, $item);

        if ($withBump && $offers !== []) {
            $bump = $offers[0];
            $lines[] = [
                'type' => $bump['type'],
                'slug' => $bump['slug'],
                'title' => $bump['title'],
                'price' => $bump['price'],
                'is_order_bump' => true,
            ];
        }

        $subtotal = round(array_sum(array_column($lines, 'price')), 2);
        $coupon = $this->applyCoupon($couponCode, $user, $type, $item, $subtotal);
        $discount = $coupon['amount'];
        $total = round(max($subtotal - $discount, 0), 2);

        $balanceBefore = $this->catalog->balance($user);
        $owned = $user ? $this->catalog->owns($user, $type, $item) : false;

        return [
            'type' => $type,
            'slug' => $item->slug,
            'title' => $item->name_ar,
            'lines' => $lines,
            'bump' => $bump,
            'bump_offers' => $offers,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'list_price' => $listPrice,
            // «وفّرت كذا» من قيمةٍ حقيقيّة مسجَّلة (18) — وإلّا فصفر ولا شارة
            'savings' => round(max($listPrice - $price, 0) + $discount, 2),
            'coupon' => $coupon,
            'balance_before' => round($balanceBefore, 2),
            'balance_after' => round($balanceBefore - $total, 2),
            'sufficient' => $balanceBefore + 0.0001 >= $total,
            'owned' => $owned,
            'sellable' => $this->catalog->isSellable($type),
        ];
    }

    /** السعر الفعليّ الآن — سعر العرض إن كان ساريًا، وإلّا السعر الأساسيّ (16) */
    public function priceOf(string $type, Model $item): float
    {
        if ($type === 'path') {
            return 0.0; // المسار حاوية بلا سعر — يُباع ضمن باقة فقط (16)
        }

        if ($type === 'course' && $item->is_free) {
            return 0.0;
        }

        if ($type === 'bundle') {
            return round((float) $item->price_coins, 2);
        }

        return round($this->catalog->activeOffer($item) ?? (float) $item->price_coins, 2);
    }

    /** السعر المرجعيّ المشطوب — من بيانات حقيقيّة فقط (Anchoring 18) */
    public function listPriceOf(string $type, Model $item): float
    {
        if ($type === 'bundle') {
            return round((float) $item->original_value, 2);
        }

        if ($type === 'path') {
            return 0.0;
        }

        return round((float) $item->price_coins, 2);
    }

    // ------------------------------------------------------------ الكوبون

    /**
     * التحقّق من الكوبون **في الخادم**: الصلاحيّة والنافذة والحدود والنطاق.
     *
     * @return array{code:?string,valid:bool,amount:float,message:?string,id:?int}
     */
    public function applyCoupon(?string $code, ?User $user, string $type, Model $item, float $subtotal): array
    {
        $empty = ['code' => null, 'valid' => false, 'amount' => 0.0, 'message' => null, 'id' => null];

        if (! $code || ! setting('store.coupons.enabled', true)) {
            return $empty;
        }

        $code = trim($code);
        $invalidText = (string) setting('store.coupon.invalid_text', 'الكود ده مش صالح للطلب ده — راجعه أو أكمل من غيره.');
        $coupon = Coupon::query()->whereRaw('lower(code) = ?', [mb_strtolower($code)])->first();

        if (! $coupon || ! $coupon->is_active) {
            return [...$empty, 'code' => $code, 'message' => $invalidText];
        }

        if ($coupon->starts_at && now()->lessThan(Carbon::parse($coupon->starts_at))) {
            return [...$empty, 'code' => $code, 'message' => $invalidText];
        }

        if ($coupon->ends_at && now()->greaterThan(Carbon::parse($coupon->ends_at))) {
            return [...$empty, 'code' => $code, 'message' => (string) setting('store.coupon.expired_text', 'الكود ده خلصت مدّته.')];
        }

        if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
            return [...$empty, 'code' => $code, 'message' => (string) setting('store.coupon.exhausted_text', 'الكود ده اتستخدم بالكامل.')];
        }

        if ($user && $coupon->max_uses_per_user > 0) {
            $usedByUser = Order::query()
                ->where('user_id', $user->id)
                ->where('coupon_id', $coupon->id)
                ->where('status', 'paid')
                ->count();

            if ($usedByUser >= $coupon->max_uses_per_user) {
                return [...$empty, 'code' => $code, 'message' => (string) setting('store.coupon.per_user_text', 'استخدمت الكود ده قبل كده.')];
            }
        }

        if (! $this->couponApplies($coupon, $type, $item)) {
            return [...$empty, 'code' => $code, 'message' => $invalidText];
        }

        $amount = $coupon->type === 'percent'
            ? round($subtotal * ((float) $coupon->value) / 100, 2)
            : round(min((float) $coupon->value, $subtotal), 2);

        return [
            'code' => $coupon->code,
            'valid' => true,
            'amount' => round(max($amount, 0), 2),
            'message' => null,
            'id' => $coupon->id,
        ];
    }

    /** نطاق الكوبون: `applies_to` = {"types":[…],"slugs":[…]} — وفارغه يعني الكلّ */
    private function couponApplies(Coupon $coupon, string $type, Model $item): bool
    {
        $scope = $coupon->applies_to;

        if (! is_array($scope) || $scope === []) {
            return true;
        }

        if (! empty($scope['types']) && ! in_array($type, (array) $scope['types'], true)) {
            return false;
        }

        if (! empty($scope['slugs']) && ! in_array($item->slug, (array) $scope['slugs'], true)) {
            return false;
        }

        return true;
    }

    // ------------------------------------------------------------ Order-bump

    /**
     * عروض الـBump المرتبطة بالعنصر (17) — كلّها من الإعدادات (2.13)،
     * وبحدٍّ أقصى مضبوط في `store.order_bump.max` (قاعدة 17: اثنان كحدٍّ أقصى).
     *
     * @return array<int, array<string, mixed>>
     */
    public function bumpOffers(?User $user, string $type, Model $item): array
    {
        if (! setting('store.order_bump.enabled', true)) {
            return [];
        }

        $configured = setting('store.order_bump.offers', []);
        $configured = is_array($configured) ? $configured : [];
        $max = (int) setting('store.order_bump.max', 2);
        $offers = [];

        foreach ($configured as $row) {
            if (($row['parent_type'] ?? null) !== $type || ($row['parent_slug'] ?? null) !== $item->slug) {
                continue;
            }

            $bumpType = (string) ($row['bump_type'] ?? '');
            $bumpItem = $this->catalog->resolve($bumpType, (string) ($row['bump_slug'] ?? ''));

            if (! $bumpItem || ! $this->catalog->isAvailable($bumpType, $bumpItem)) {
                continue;
            }

            // ما يملكه المستخدم لا يُعرَض عليه ثانيةً
            if ($user && $this->catalog->owns($user, $bumpType, $bumpItem)) {
                continue;
            }

            $offers[] = [
                'type' => $bumpType,
                'slug' => $bumpItem->slug,
                'title' => $bumpItem->name_ar,
                'price' => round((float) ($row['price_coins'] ?? $this->priceOf($bumpType, $bumpItem)), 2),
                'list_price' => $this->priceOf($bumpType, $bumpItem),
                'teaser' => (string) ($row['teaser'] ?? ''),
            ];

            if (count($offers) >= max($max, 1)) {
                break;
            }
        }

        return $offers;
    }
}
