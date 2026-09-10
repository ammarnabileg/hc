<?php

namespace App\Services\Store;

use App\Models\Course;
use App\Models\User;
use App\Services\Learning\AvailabilityService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * السلّة الاختياريّة وصفحة مراجعة الطلب (17).
 *
 * لماذا «اختياريّة»؟ لأنّ **بوب-أب الشراء المباشر يبقى المسار الافتراضيّ لعنصرٍ واحد**
 * (2.15 — البساطة أوّلًا · 24.5 — بلا مغادرة الصفحة)، والسلّة تخدم مَن يشتري أكثر
 * من عنصر فيحتاج مراجعةً واحدة قبل الدفع. المساران يتقاسمان **نفس التسعير ونفس الخصم
 * ونفس الإقرار** — فلا يوجد سعران لعنصرٍ واحد.
 *
 * ⭐ القاعدة الحمراء: **السلّة لا تقبل سعرًا من الطلب إطلاقًا** — الجلسة تحفظ
 *    (النوع + الـslug) فقط، وكلّ رقمٍ يُعاد حسابه هنا من قاعدة البيانات.
 */
class CartService
{
    /** مفتاح الجلسة — والقيمة قائمة [type, slug] لا أكثر */
    public const SESSION_KEY = 'store.cart';

    /**
     * ⭐ مفاتيح رسائل الفشل التي تقرؤها `fail()` عبر `$settingKey` — يسجّلها
     * `SettingsCoverage::deadKeys()` فلا يُبلَّغ عنها «ميّتة» (نفس علّة
     * `PurchaseException::MESSAGE_KEYS`: مفتاحٌ يصل بمعامل الدالّة لا حرفيًّا).
     *
     * @var array<int, string>
     */
    public const FAIL_MESSAGE_KEYS = [
        'store.unavailable_text',
        'store.owned_text',
        'store.cart.full_text',
    ];

    public function __construct(
        private readonly StoreCatalog $catalog,
        private readonly PricingService $pricing,
        private readonly NearestTopupOffer $topups,
        private readonly AvailabilityService $availability,
    ) {}

    // ------------------------------------------------------------ الحالة

    /** @return array<int, array{type:string,slug:string}> */
    public function raw(Request $request): array
    {
        $rows = (array) $request->session()->get(self::SESSION_KEY, []);
        $clean = [];

        foreach ($rows as $row) {
            $type = (string) ($row['type'] ?? '');
            $slug = (string) ($row['slug'] ?? '');

            if ($type === '' || $slug === '' || ! array_key_exists($type, StoreCatalog::TYPES)) {
                continue;
            }

            $clean[$type.':'.$slug] = ['type' => $type, 'slug' => $slug];
        }

        return array_values($clean);
    }

    public function count(Request $request): int
    {
        return count($this->raw($request));
    }

    /**
     * عملة السلّة = عملة أوّل سطرٍ صالح فيها، و`null` للسلّة الفارغة (17).
     *
     * @param  array<int, array{type:string,slug:string}>  $rows
     */
    public function currencyOf(array $rows): ?string
    {
        foreach ($rows as $row) {
            $item = $this->catalog->resolve($row['type'], $row['slug']);

            if ($item) {
                return $this->pricing->currencyOf($row['type'], $item);
            }
        }

        return null;
    }

    // ------------------------------------------------------------ التعديل

    /** @return array{ok:bool,reason:?string,message:string} */
    public function add(Request $request, ?User $user, string $type, string $slug): array
    {
        $item = $this->catalog->resolve($type, $slug);

        if (! $item || ! $this->catalog->isAvailable($type, $item) || ! $this->catalog->isSellable($type)) {
            return $this->fail('unavailable', 'store.unavailable_text');
        }

        // منع الشراء المكرّر يبدأ من السلّة نفسها — لا عند الدفع فقط (20.4)
        if ($user && $this->catalog->owns($user, $type, $item)) {
            return $this->fail('owned', 'store.owned_text');
        }

        $rows = $this->raw($request);

        if (count($rows) >= (int) setting('store.cart.max_items', 10)) {
            return $this->fail('cart_full', 'store.cart.full_text');
        }

        /*
         | ⭐ **سلّة بعملةٍ واحدة** (17 · 19.1): بعد أن صار المنتج يُسعَّر بـCoins أو
         | Tickets أو XP، جمعُ «400 كوين + 50 تذكرة» في إجماليٍّ واحد رقمٌ بلا معنى
         | ويخصم من محفظةٍ واحدة ما ليس لها. فالسلّة تقبل عملةً واحدة، والمستخدم
         | يُتمّ طلبه ثمّ يبدأ سلّةً بعملةٍ أخرى — والرسالة تشرح ماذا يفعل (2.17-ج).
         */
        $cartCurrency = $this->currencyOf($rows);
        $itemCurrency = $this->pricing->currencyOf($type, $item);

        if ($cartCurrency !== null && $cartCurrency !== $itemCurrency) {
            return [
                'ok' => false,
                'reason' => 'currency_mismatch',
                'message' => str_replace(
                    ['{cart}', '{item}'],
                    [Coins::currencyLabel($cartCurrency), Coins::currencyLabel($itemCurrency)],
                    (string) setting(
                        'store.cart.currency_mismatch_text',
                        'سلّتك دلوقتي بالـ{cart} والعنصر ده بالـ{item} — كمّل طلبك الأوّل وابدأ سلّة جديدة بيه.',
                    ),
                ),
            ];
        }

        $rows[] = ['type' => $type, 'slug' => $slug];
        $this->put($request, $rows);

        return ['ok' => true, 'reason' => null, 'message' => (string) setting('store.cart.added_text')];
    }

    public function remove(Request $request, string $type, string $slug): void
    {
        $this->put($request, array_values(array_filter(
            $this->raw($request),
            fn (array $row) => ! ($row['type'] === $type && $row['slug'] === $slug),
        )));
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /** @param  array<int, array{type:string,slug:string}>  $rows */
    private function put(Request $request, array $rows): void
    {
        $request->session()->put(self::SESSION_KEY, array_values($rows));
    }

    // ------------------------------------------------------------ التسعير

    /**
     * ملخّص السلّة كاملًا — بنفس شكل ملخّص البوب-أب كي يمشي في نفس مسار الشراء.
     *
     * @param  array<int, array{type:string,slug:string}>  $rows
     * @param  array<int, string>  $bumpSlugs
     * @return array<string, mixed>
     */
    public function quote(?User $user, array $rows, ?string $couponCode = null, array $bumpSlugs = []): array
    {
        $lines = [];
        $listTotal = 0.0;
        $owned = [];
        $offers = [];
        // عملة السلّة تُقرَأ من محتواها، وما خالفها يُستبعَد بدل أن يُجمَع خطأً (17)
        $currency = $this->currencyOf($rows) ?? Coins::defaultCode();

        foreach ($rows as $row) {
            $item = $this->catalog->resolve($row['type'], $row['slug']);

            if (! $item || ! $this->catalog->isAvailable($row['type'], $item) || ! $this->catalog->isSellable($row['type'])) {
                continue;
            }

            if ($this->pricing->currencyOf($row['type'], $item) !== $currency) {
                continue;
            }

            if ($user && $this->catalog->owns($user, $row['type'], $item)) {
                $owned[] = (string) $item->name_ar;

                continue;
            }

            $price = $this->pricing->priceOf($row['type'], $item);
            $listTotal += $this->pricing->listPriceOf($row['type'], $item);

            $lines[] = [
                'type' => $row['type'],
                'slug' => $item->slug,
                'title' => $item->name_ar,
                'price' => $price,
                'is_order_bump' => false,
                // النافذة الزمنيّة تؤثّر على **الوصول** لا **إمكانيّة الشراء** (16) —
                // فالسطر يبقى في السلّة ويُشترى، لكن يُعرَض بحالة إغلاقه الحاليّة.
                'availability' => $this->courseAvailability($row['type'], $item, $user),
            ];

            foreach ($this->pricing->bumpOffers($user, $row['type'], $item) as $offer) {
                if (! in_array($offer['slug'], array_column($offers, 'slug'), true)) {
                    $offers[] = $offer;
                }
            }
        }

        // Bump في صفحة المراجعة: واحد أو اثنان كحدٍّ أقصى (17)
        $offers = array_slice($this->withoutCartItems($offers, $lines), 0, max((int) setting('store.order_bump.max', 2), 1));
        $chosen = array_values(array_filter(
            $offers,
            fn (array $offer) => in_array((string) $offer['slug'], array_map('strval', $bumpSlugs), true),
        ));

        foreach ($chosen as $offer) {
            $bumpItem = $this->catalog->resolve($offer['type'], $offer['slug']);

            $lines[] = [
                'type' => $offer['type'],
                'slug' => $offer['slug'],
                'title' => $offer['title'],
                'price' => $offer['price'],
                'is_order_bump' => true,
                'availability' => $bumpItem ? $this->courseAvailability($offer['type'], $bumpItem, $user) : null,
            ];
            $listTotal += (float) $offer['list_price'];
        }

        $subtotal = round(array_sum(array_column($lines, 'price')), 2);
        $coupon = $this->cartCoupon($couponCode, $user, $lines, $subtotal);
        $total = round(max($subtotal - $coupon['amount'], 0), 2);
        $balance = $this->catalog->balance($user, $currency);

        return [
            'type' => 'cart',
            'slug' => 'cart',
            'title' => (string) setting('store.cart.order_title'),
            'currency' => $currency,
            'currency_label' => Coins::currencyLabel($currency),
            'lines' => $lines,
            'bump' => $chosen[0] ?? null,
            'bumps' => $chosen,
            'bump_offers' => $offers,
            'subtotal' => $subtotal,
            'discount' => $coupon['amount'],
            'total' => $total,
            'list_price' => round($listTotal, 2),
            // «وفّرت X» من فرق سعرٍ حقيقيّ مسجَّل + الكوبون (17 · 2.9)
            'savings' => round(max($listTotal - $subtotal, 0) + $coupon['amount'], 2),
            'coupon' => $coupon,
            'balance_before' => round($balance, 2),
            'balance_after' => round($balance - $total, 2),
            'sufficient' => $balance + 0.0001 >= $total,
            'owned' => $owned !== [],
            'owned_titles' => $owned,
            'sellable' => true,
            'suggestion' => $this->topups->forDeficit(round($total - $balance, 2), $currency),
        ];
    }

    /**
     * إتاحة التدريب الزمنيّة لسطر السلّة (16) — نفس الآليّة المستعملة في شاشات
     * التعلّم (`AvailabilityService::forCourse`)، فلا حساب ثانٍ للنافذة اليوميّة
     * وفترات الإتاحة. `null` لغير التدريبات — لا معنى للنافذة الزمنيّة لمنتجٍ رقميّ.
     *
     * ⭐ `StoreCatalog::isAvailable()` تبقى بوّابة النشر/الحالة وحدها (سطر 71 هناك) —
     * هذا الفحص لا يمنع الشراء، يُعلِم فقط: الوصول شيءٌ والشراء شيءٌ آخر.
     *
     * @return array{open:bool,state:string,reason:?string}|null
     */
    private function courseAvailability(string $type, Model $item, ?User $user): ?array
    {
        if ($type !== 'course' || ! $item instanceof Course) {
            return null;
        }

        return $this->availability->forCourse($item, null, $user);
    }

    /** ما هو في السلّة أصلًا لا يُعرَض كـBump */
    private function withoutCartItems(array $offers, array $lines): array
    {
        $inCart = array_column($lines, 'slug');

        return array_values(array_filter($offers, fn (array $o) => ! in_array($o['slug'], $inCart, true)));
    }

    /**
     * الكوبون على السلّة: يسري إن كان نطاقه يشمل **أيّ** سطر فيها، ويُحسَب على المجموع.
     * والتحقّق كلّه في `PricingService` — مصدرٌ واحد لقواعد الكوبون (لا نسخة ثانية).
     *
     * @return array{code:?string,valid:bool,amount:float,message:?string,id:?int}
     */
    private function cartCoupon(?string $code, ?User $user, array $lines, float $subtotal): array
    {
        $empty = ['code' => null, 'valid' => false, 'amount' => 0.0, 'message' => null, 'id' => null];

        if (! $code || $lines === []) {
            return $empty;
        }

        $last = $empty;

        foreach ($lines as $line) {
            $item = $this->catalog->resolve($line['type'], $line['slug']);

            if (! $item instanceof Model) {
                continue;
            }

            $result = $this->pricing->applyCoupon($code, $user, $line['type'], $item, $subtotal);
            $last = $result;

            if ($result['valid']) {
                return $result;
            }
        }

        return $last;
    }

    /** @return array{ok:bool,reason:string,message:string} */
    private function fail(string $reason, string $settingKey): array
    {
        return ['ok' => false, 'reason' => $reason, 'message' => (string) setting($settingKey)];
    }
}
