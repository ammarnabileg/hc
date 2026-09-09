<?php

namespace App\Services\Store;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderBumpOffer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * ⭐ التسعير في الخادم حصرًا (19.5-أ): لا يأتي سعرٌ ولا خصمٌ من المتصفّح إطلاقًا.
 * المتصفّح يرسل **هويّة العنصر وكود الكوبون واختيار الـBump فقط** — والباقي يُحسَب هنا.
 *
 * ⭐ **التسعير متعدّد العملات (17):** منتج المتجر يُسعَّر بـ Coins أو XP أو Tickets،
 * والعملة قرارٌ معلَن في `products.price_currency` لا استنتاجًا من عمودٍ غير صفريّ.
 * أمّا التدريب والمسار والباقة فبالكوينز دائمًا — «كلّ الأسعار الحقيقيّة بالـCoins» (16).
 *
 * وبلا Dark Patterns (2.9): الخصم بقيمته الحقيقيّة، و**«وفّرت X» في الباقة تُحسَب
 * من مجموع أسعار عناصرها الفعليّة** لا من رقمٍ يكتبه الأدمن بلا تحقّق (18).
 */
class PricingService
{
    public function __construct(private readonly StoreCatalog $catalog) {}

    /**
     * عملة سعر العنصر (17 · 16).
     *
     * التدريب والمسار والباقة **بالكوينز حصرًا** لأنّها محتوًى حقيقيّ (16 · 19.1
     * — «XP لا يُشترى بها محتوًى حقيقيّ»)، والمنتج وحده يقبل العملات الثلاث.
     */
    public function currencyOf(string $type, Model $item): string
    {
        $default = Coins::defaultCode();

        if ($type !== 'product') {
            return $default;
        }

        $code = (string) ($item->price_currency ?? $default);

        return in_array($code, $this->currencies(), true) ? $code : $default;
    }

    /**
     * العملات المقبولة في المتجر — إعدادٌ لا قائمة محروقة (2.13 · 17).
     *
     * @return array<int, string>
     */
    public function currencies(): array
    {
        $codes = setting('store.currencies', ['coins', 'tickets', 'xp']);
        $codes = array_values(array_filter(array_map('strval', (array) $codes)));

        return $codes !== [] ? $codes : ['coins'];
    }

    /**
     * ملخّص شراءٍ كامل يُعرَض في البوب-أب ويُنفَّذ به الطلب.
     *
     * @param  bool|array<int, string>  $bumps  اختيار الـBump: قائمة slugs (17 — واحد أو اثنان)
     *                                          و`true` تعني «الأوّل» توافقًا مع الاستدعاء القديم.
     * @return array<string, mixed>
     */
    public function quote(?User $user, string $type, Model $item, ?string $couponCode = null, bool|array $bumps = []): array
    {
        $currency = $this->currencyOf($type, $item);
        $price = $this->priceOf($type, $item);
        $listPrice = $this->listPriceOf($type, $item);

        $lines = [[
            'type' => $type,
            'slug' => $item->slug,
            'title' => $item->name_ar,
            'price' => $price,
            'is_order_bump' => false,
        ]];

        $offers = $this->bumpOffers($user, $type, $item);
        $chosen = $this->chooseBumps($offers, $bumps);

        foreach ($chosen as $bumpOffer) {
            $lines[] = [
                'type' => $bumpOffer['type'],
                'slug' => $bumpOffer['slug'],
                'title' => $bumpOffer['title'],
                'price' => $bumpOffer['price'],
                'is_order_bump' => true,
            ];
        }

        $bump = $chosen[0] ?? null;

        $subtotal = round(array_sum(array_column($lines, 'price')), 2);
        $coupon = $this->applyCoupon($couponCode, $user, $type, $item, $subtotal);
        $discount = $coupon['amount'];
        $total = round(max($subtotal - $discount, 0), 2);

        $balanceBefore = $this->catalog->balance($user, $currency);
        $owned = $user ? $this->catalog->owns($user, $type, $item) : false;

        return [
            'type' => $type,
            'slug' => $item->slug,
            'title' => $item->name_ar,
            // ⭐ العملة تسافر مع الملخّص كلّه — فلا يُخصَم من محفظةٍ غير التي سُعِّر بها
            'currency' => $currency,
            'currency_label' => Coins::currencyLabel($currency),
            'lines' => $lines,
            'bump' => $bump,
            'bumps' => $chosen,
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

    /**
     * السعر الفعليّ الآن **بعملة العنصر** — سعر العرض إن كان ساريًا، وإلّا الأساسيّ (16 · 17).
     *
     * سعر العرض (`offer_price_coins`) خاصّ بالكوينز وحدها، فمنتجٌ مسعَّر بالتذاكر
     * أو الـXP يُقرأ من عموده مباشرةً — وهذا ما كان مفقودًا: العمود موجود ويُتجاهَل
     * فيمرّ المنتج بسعر صفر ويُسلَّم مجّانًا.
     */
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

        $currency = $this->currencyOf($type, $item);

        if ($currency !== Coins::defaultCode()) {
            return round((float) ($item->{$this->priceColumn($currency)} ?? 0), 2);
        }

        return round($this->catalog->activeOffer($item) ?? (float) $item->price_coins, 2);
    }

    /**
     * السعر المرجعيّ المشطوب — **من بياناتٍ حقيقيّة محسوبة** (Anchoring 18).
     *
     * وباقةً: القيمة الإجماليّة **تُحسَب تلقائيًّا من عناصرها** كما ينصّ 18، لا من
     * `bundles.original_value` الذي يكتبه الأدمن بلا تحقّق — فقد كان بندلٌ عناصره
     * 500 و«قيمته الأصليّة» 5000 يعرض «وفّرت 4580» والحقيقة 80، وهو Dark Pattern
     * يمنعه 2.9 صراحةً.
     */
    public function listPriceOf(string $type, Model $item): float
    {
        if ($type === 'bundle') {
            return $this->bundleItemsValue($item);
        }

        if ($type === 'path') {
            return 0.0;
        }

        $currency = $this->currencyOf($type, $item);

        return round((float) ($item->{$this->priceColumn($currency)} ?? 0), 2);
    }

    /**
     * ⭐ **«القيمة الإجماليّة» = مجموع أسعار عناصر الباقة كما ضبطها الأدمن** —
     * أيْ **بالـOverride** إن وُجد وإلّا فالسعر الطبيعيّ — محسوبًا لحظةَ العرض.
     * وهي المصدر الوحيد للرقم في 18 («القيمة الإجماليّة محسوبةً تلقائيًّا»)
     * وفي عمود الجدول في 24 («محسوبة تلقائيًّا، للقراءة»).
     *
     * ولماذا بالـOverride لا بالسعر الطبيعيّ؟ لأنّ المرساة يجب أن تطابق **ما
     * يقرؤه الزائر في قائمة العناصر أمامه**: لو جمعنا الطبيعيّ بينما السطور
     * تعرض الـOverride، لصار مجموع ما يراه ≠ «القيمة الإجماليّة» المعلَنة —
     * وذلك رقمٌ لا يسنده ما على الشاشة، أيْ عين «لا أرقام وهميّة» (2.9 · 21.1-د).
     *
     * ⚠️ والقيمة الطبيعيّة تبقى قائمةً حيث نصّ عليها الدستور تحديدًا: **سطر
     *    البونص** يُعرَض «بقيمته **الطبيعيّة**» (18) — راجع `BundleLanding::bonusLines()`.
     *
     * وعناصرها بالكوينز لأنّ الباقة تُسعَّر بالكوينز (16).
     */
    public function bundleItemsValue(Model $bundle): float
    {
        return round(
            (float) $this->catalog->includes('bundle', $bundle)->sum(fn (array $line) => (float) $line['value']),
            2,
        );
    }

    /** عمود السعر المقابل للعملة — coins · tickets · xp (17) */
    private function priceColumn(string $currency): string
    {
        return 'price_'.$currency;
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
     * ⭐ الاختيار **هويّةٌ لا سعر**: المتصفّح يرسل slug العرض فقط، ونحن نطابقه
     * بالعروض المحسوبة في الخادم — فما لم يُعرَض لا يُضاف مهما أُرسِل (17).
     *
     * @param  array<int, array<string, mixed>>  $offers
     * @param  bool|array<int, string>  $bumps
     * @return array<int, array<string, mixed>>
     */
    private function chooseBumps(array $offers, bool|array $bumps): array
    {
        if ($offers === [] || $bumps === false || $bumps === []) {
            return [];
        }

        // التوافق مع `add_bump=1` القديم: تعني العرض الأوّل وحده
        if ($bumps === true) {
            return [$offers[0]];
        }

        $wanted = array_values(array_unique(array_map('strval', $bumps)));
        $max = max((int) setting('store.order_bump.max', 2), 1);

        $chosen = array_values(array_filter(
            $offers,
            fn (array $offer) => in_array((string) $offer['slug'], $wanted, true),
        ));

        return array_slice($chosen, 0, $max);
    }

    /**
     * عروض الـBump المرتبطة بالعنصر (17) — كلّ عرضٍ صفٌّ مستقلّ في `order_bump_offers`
     * يُدار من شاشة إدارةٍ حقيقيّة لا نصّ JSON حرّ، وبحدٍّ أقصى مضبوط في
     * `store.order_bump.max` (قاعدة 17: اثنان كحدٍّ أقصى).
     *
     * @return array<int, array<string, mixed>>
     */
    public function bumpOffers(?User $user, string $type, Model $item): array
    {
        if (! setting('store.order_bump.enabled', true)) {
            return [];
        }

        $configured = OrderBumpOffer::query()
            ->where('parent_type', $type)
            ->where('parent_slug', $item->slug)
            ->where('is_active', true)
            ->get();
        $max = (int) setting('store.order_bump.max', 2);
        $offers = [];

        foreach ($configured as $row) {
            $bumpType = (string) $row->bump_type;
            $bumpItem = $this->catalog->resolve($bumpType, (string) $row->bump_slug);

            if (! $bumpItem || ! $this->catalog->isAvailable($bumpType, $bumpItem)) {
                continue;
            }

            // ما يملكه المستخدم لا يُعرَض عليه ثانيةً
            if ($user && $this->catalog->owns($user, $bumpType, $bumpItem)) {
                continue;
            }

            // عرضان بنفس العنصر لا يظهران مرّتين — والاختيار يقع بالـslug (17)
            if (in_array((string) $bumpItem->slug, array_column($offers, 'slug'), true)) {
                continue;
            }

            $offers[] = [
                'type' => $bumpType,
                'slug' => $bumpItem->slug,
                'title' => $bumpItem->name_ar,
                'price' => round((float) ($row->price_coins ?? $this->priceOf($bumpType, $bumpItem)), 2),
                'list_price' => $this->priceOf($bumpType, $bumpItem),
                'teaser' => (string) ($row->teaser ?? ''),
            ];

            if (count($offers) >= max($max, 1)) {
                break;
            }
        }

        return $offers;
    }
}
