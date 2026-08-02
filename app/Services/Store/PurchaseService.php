<?php

namespace App\Services\Store;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\CourseLearningPath;
use App\Models\Enrollment;
use App\Models\LearningPath;
use App\Models\LibraryEntitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * الشراء بالكوينز — **كلّه داخل معاملة قاعدة بيانات واحدة**:
 * خصم الرصيد + `orders` + `order_items` + `transactions` + `library_entitlements`
 * + `enrollments` للتدريبات والمسارات. فإمّا أن يتمّ كلّه أو لا شيء.
 *
 * لا استرجاع نقديّ (19.4): الإقرار إلزاميّ قبل الدفع، والتصحيح — إن لزم —
 * يكون بمعاملةٍ عكسيّة موثّقة من لوحة الإدارة لا باستردادٍ من هنا.
 */
class PurchaseService
{
    public function __construct(
        private readonly StoreCatalog $catalog,
        private readonly PricingService $pricing,
        private readonly CartService $cart,
    ) {}

    /**
     * شراء عنصرٍ واحد من بوب-أب الشراء — المسار الافتراضيّ (24.5).
     *
     * @param  array{coupon_code?:?string,add_bump?:bool,bumps?:array<int,string>,refund_ack?:bool}  $input
     *
     * @throws PurchaseException
     */
    public function purchase(User $user, string $type, string $slug, array $input = []): Order
    {
        $this->assertPayable($input);

        return DB::transaction(function () use ($user, $type, $slug, $input) {
            $item = $this->catalog->resolve($type, $slug);

            if (! $item || ! $this->catalog->isAvailable($type, $item) || ! $this->catalog->isSellable($type)) {
                throw PurchaseException::of('unavailable', 'store.unavailable_text', 'العنصر ده مش متاح للشراء دلوقتي.');
            }

            // منع الشراء المكرّر لما يملكه (20.4)
            if ($this->catalog->owns($user, $type, $item)) {
                throw PurchaseException::of('owned', 'store.owned_text', 'ده معاك بالفعل — تلاقيه في مكتبتك.');
            }

            // ⭐ إعادة الحساب في الخادم — ولا رقم من المتصفّح؛ والعملة تُعرَف منه
            $quote = $this->pricing->quote(
                user: $user,
                type: $type,
                item: $item,
                couponCode: $input['coupon_code'] ?? null,
                bumps: $this->bumpsOf($input),
            );

            $wallet = $this->lockedWallet($user, $quote['currency']);

            return $this->commit($user, $wallet, $quote);
        });
    }

    /**
     * شراء سلّةٍ من صفحة مراجعة الطلب (17) — نفس المعاملة ونفس القواعد.
     *
     * @param  array<int, array{type:string,slug:string}>  $rows
     * @param  array{coupon_code?:?string,bumps?:array<int,string>,refund_ack?:bool}  $input
     *
     * @throws PurchaseException
     */
    public function purchaseCart(User $user, array $rows, array $input = []): Order
    {
        $this->assertPayable($input);

        return DB::transaction(function () use ($user, $rows, $input) {
            // ⭐ السلّة تحمل هويّات فقط — وكلّ رقمٍ يُعاد حسابه هنا
            $quote = $this->cart->quote(
                user: $user,
                rows: $rows,
                couponCode: $input['coupon_code'] ?? null,
                bumpSlugs: (array) ($input['bumps'] ?? []),
            );

            if ($quote['lines'] === []) {
                throw PurchaseException::of('empty_cart', 'store.cart.empty_text', 'سلّتك فاضية — ضيف حاجة الأوّل.');
            }

            $wallet = $this->lockedWallet($user, $quote['currency']);

            return $this->commit($user, $wallet, $quote);
        });
    }

    // ------------------------------------------------------------ المشترك

    /** بوّابتان قبل أيّ دفع: المتجر مفتوح، والإقرار بسياسة عدم الاسترجاع (19.4) */
    private function assertPayable(array $input): void
    {
        if (! setting('store.enabled', true)) {
            throw PurchaseException::of('disabled', 'store.disabled_text', 'المتجر مقفول مؤقّتًا — جرّب بعد شويّة.');
        }

        if (! ($input['refund_ack'] ?? false)) {
            throw PurchaseException::of(
                'ack_required',
                'store.refund.ack_required_text',
                'محتاجين إقرارك بسياسة عدم الاسترجاع الأوّل، وبعدها نكمّل الشراء.',
            );
        }
    }

    /** @return bool|array<int, string> */
    private function bumpsOf(array $input): bool|array
    {
        $slugs = array_values(array_filter(array_map('strval', (array) ($input['bumps'] ?? []))));

        return $slugs !== [] ? $slugs : (bool) ($input['add_bump'] ?? false);
    }

    /**
     * تنفيذ الطلب داخل المعاملة المقفولة: الخصم والطلب والسطور والملكيّة.
     * **إمّا أن يتمّ كلّه أو لا شيء** — والرصيد مقفول بـ`lockForUpdate` قبل الوصول هنا.
     */
    private function commit(User $user, WalletBalance $wallet, array $quote): Order
    {
        if ((float) $wallet->balance + 0.0001 < $quote['total']) {
            throw PurchaseException::of(
                'insufficient',
                'store.insufficient_text',
                'رصيدك أقلّ من قيمة الطلب — اشحن محفظتك وكمّل من نفس المكان.',
            );
        }

        $order = $this->createOrder($user, $quote);
        $this->createItems($order, $quote);

        $transaction = $this->debit($user, $wallet, $order, $quote);

        $order->forceFill([
            'transaction_id' => $transaction->id,
            'status' => 'paid',
            'paid_at' => now(),
        ])->save();

        foreach ($quote['lines'] as $line) {
            $lineItem = $this->catalog->resolve($line['type'], $line['slug']);

            if ($lineItem) {
                $this->grant($user, $line['type'], $lineItem, $order, 'purchase');
            }
        }

        if ($quote['coupon']['valid'] && $quote['coupon']['id']) {
            Coupon::query()->whereKey($quote['coupon']['id'])->increment('used_count');
        }

        return $order->refresh();
    }

    // ------------------------------------------------------------ خطوات المعاملة

    private function lockedWallet(User $user): WalletBalance
    {
        $currency = $this->catalog->coinsCurrency();

        if (! $currency) {
            throw PurchaseException::of('unavailable', 'store.unavailable_text', 'العنصر ده مش متاح للشراء دلوقتي.');
        }

        WalletBalance::query()->firstOrCreate(
            ['user_id' => $user->id, 'currency_id' => $currency->id],
            ['balance' => 0, 'lifetime_earned' => 0, 'lifetime_spent' => 0],
        );

        // قفل الصفّ يمنع الخصم المزدوج عند طلبين متزامنين
        return WalletBalance::query()
            ->where('user_id', $user->id)
            ->where('currency_id', $currency->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function createOrder(User $user, array $quote): Order
    {
        return Order::create([
            'number' => $this->nextNumber(),
            'user_id' => $user->id,
            'subtotal' => $quote['subtotal'],
            'discount' => $quote['discount'],
            'total' => $quote['total'],
            'currency_id' => $this->catalog->coinsCurrency()->id,
            'coupon_id' => $quote['coupon']['valid'] ? $quote['coupon']['id'] : null,
            'status' => 'pending',
            // إقرار سياسة عدم الاسترجاع محفوظ مع الطلب (19.4)
            'refund_policy_acknowledged' => true,
        ]);
    }

    private function createItems(Order $order, array $quote): void
    {
        foreach ($quote['lines'] as $line) {
            $item = $this->catalog->resolve($line['type'], $line['slug']);

            if (! $item) {
                continue;
            }

            OrderItem::create([
                'order_id' => $order->id,
                'purchasable_type' => $item::class,
                'purchasable_id' => $item->id,
                'title' => $line['title'],
                'price' => $line['price'],
                'quantity' => 1,
                'is_order_bump' => $line['is_order_bump'],
            ]);
        }
    }

    /** معاملة واحدة لكلّ طلب في الجدول الموحّد (19.3) بمرجعها */
    private function debit(User $user, WalletBalance $wallet, Order $order, array $quote): Transaction
    {
        $balanceAfter = round((float) $wallet->balance - $quote['total'], 2);

        $wallet->forceFill([
            'balance' => $balanceAfter,
            'lifetime_spent' => round((float) $wallet->lifetime_spent + $quote['total'], 2),
        ])->save();

        return Transaction::create([
            'user_id' => $user->id,
            'currency_id' => $wallet->currency_id,
            'amount' => -1 * $quote['total'],
            'balance_after' => $balanceAfter,
            'layer' => 'training',
            'source' => 'purchase',
            'reason' => str_replace('{item}', $quote['title'], (string) setting('store.transaction.reason_text', 'شراء: {item}')),
            'reference_type' => Order::class,
            'reference_id' => $order->id,
            'meta' => [
                'order_number' => $order->number,
                'subtotal' => $quote['subtotal'],
                'discount' => $quote['discount'],
                'coupon' => $quote['coupon']['valid'] ? $quote['coupon']['code'] : null,
            ],
        ]);
    }

    // ------------------------------------------------------------ منح الملكيّة

    /**
     * الملكيّة دائمة في «مكتبتي» (20)، والتدريب يُسجَّل تسجيلًا فعليًّا.
     * والباقة تفتح كلّ ما بداخلها — ولا يوجد نوع «هديّة» (18).
     */
    public function grant(User $user, string $type, Model $item, ?Order $order, string $source = 'purchase'): void
    {
        LibraryEntitlement::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'itemable_type' => $item::class,
                'itemable_id' => $item->id,
            ],
            [
                'order_id' => $order?->id,
                'source' => $source,
                'available_from' => now(),
                // صلاحيّة زمنيّة لكلّ منتج من شاشة الحماية (20.5) — و`null` = وصول دائم
                'available_until' => $this->accessUntil($item),
            ],
        );

        if ($item instanceof Course) {
            $this->enroll($user, $item, $source);

            return;
        }

        if ($item instanceof LearningPath) {
            $this->enrollPath($user, $item);

            return;
        }

        if ($item instanceof Bundle) {
            foreach (BundleItem::query()->where('bundle_id', $item->id)->orderBy('sort_order')->get() as $row) {
                $child = $row->itemable;

                if ($child) {
                    $childType = array_search($row->itemable_type, StoreCatalog::TYPES, true) ?: 'product';
                    $this->grant($user, $childType, $child, $order, 'bundle');
                }
            }
        }
    }

    /**
     * الصلاحيّة الزمنيّة للمنتج الرقميّ (20.5): يضبطها الأدمن بالأيّام لكلّ منتج،
     * والافتراضيّ **وصولٌ دائم** لأنّ الدستور يفرض «بوصولٍ دائم» ما لم يُقيَّد صراحةً (20).
     */
    private function accessUntil(Model $item): ?Carbon
    {
        $days = (int) ($item->access_days ?? 0);

        return $days > 0 ? now()->addDays($days) : null;
    }

    private function enrollPath(User $user, LearningPath $path): void
    {
        $courseIds = CourseLearningPath::query()
            ->where('learning_path_id', $path->id)
            ->orderBy('sort_order')
            ->pluck('course_id');

        foreach (Course::query()->whereIn('id', $courseIds)->get() as $course) {
            LibraryEntitlement::query()->firstOrCreate(
                ['user_id' => $user->id, 'itemable_type' => Course::class, 'itemable_id' => $course->id],
                ['source' => 'bundle', 'available_from' => now()],
            );

            $this->enroll($user, $course, 'bundle');
        }
    }

    private function enroll(User $user, Course $course, string $source): void
    {
        Enrollment::query()->firstOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            [
                'source' => $source,
                'started_at' => now(),
                'deadline_at' => $course->deadline_days ? now()->addDays((int) $course->deadline_days) : null,
                'status' => 'active',
            ],
        );
    }

    /** رقم الطلب: بادئته وطول تسلسله من الإعدادات (24.3) */
    private function nextNumber(): string
    {
        $prefix = (string) setting('store.order.number_prefix', 'ORD-');
        $padding = (int) setting('store.order.number_padding', 6);
        $next = (int) Order::query()->max('id') + 1;

        do {
            $number = $prefix.str_pad((string) $next, max($padding, 1), '0', STR_PAD_LEFT);
            $next++;
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}
