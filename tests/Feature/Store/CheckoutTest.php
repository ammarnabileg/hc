<?php

namespace Tests\Feature\Store;

use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LibraryEntitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Transaction;

/** الشراء بالكوينز داخل معاملة واحدة (17 · 19.3 · 19.4 · 20.4). */
class CheckoutTest extends StoreTestCase
{
    public function test_price_is_computed_on_the_server_and_ignores_the_request(): void
    {
        $product = $this->product(['price_coins' => 100]);
        $user = $this->trainee(500);

        // المتصفّح يحاول فرض سعر وخصم — والخادم لا يقرأ منه شيئًا (19.5-أ)
        $this->actingAs($user)->post(route('store.checkout'), [
            'type' => 'product',
            'slug' => $product->slug,
            'refund_ack' => 1,
            'price' => 1,
            'total' => 1,
            'subtotal' => 1,
            'discount' => 99,
            'price_coins' => 1,
        ])->assertRedirect();

        $order = Order::firstOrFail();

        $this->assertSame('100.00', $order->total);
        $this->assertSame('0.00', $order->discount);
        $this->assertSame(400.0, $this->balanceOf($user));
    }

    public function test_offer_price_is_the_one_charged(): void
    {
        $product = $this->product([
            'price_coins' => 100,
            'offer_price_coins' => 70,
            'offer_ends_at' => now()->addDays(3),
        ]);
        $user = $this->trainee(500);

        $this->actingAs($user)->post(route('store.checkout'), [
            'type' => 'product', 'slug' => $product->slug, 'refund_ack' => 1,
        ])->assertRedirect();

        $this->assertSame('70.00', Order::firstOrFail()->total);
    }

    public function test_insufficient_balance_blocks_the_purchase(): void
    {
        $product = $this->product(['price_coins' => 100]);
        $user = $this->trainee(40);

        $this->from(route('store.product', ['type' => 'product', 'slug' => $product->slug]))
            ->actingAs($user)
            ->post(route('store.checkout'), [
                'type' => 'product', 'slug' => $product->slug, 'refund_ack' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('checkout_reason', 'insufficient')
            ->assertSessionHasErrors('checkout');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, LibraryEntitlement::count());
        $this->assertSame(0, Transaction::count());
        $this->assertSame(40.0, $this->balanceOf($user));
    }

    public function test_purchase_grants_entitlement_with_one_transaction(): void
    {
        $product = $this->product(['price_coins' => 100]);
        $user = $this->trainee(250);

        $this->actingAs($user)->post(route('store.checkout'), [
            'type' => 'product', 'slug' => $product->slug, 'refund_ack' => 1,
        ])->assertRedirect();

        $order = Order::firstOrFail();

        $this->assertSame('paid', $order->status);
        $this->assertTrue($order->refund_policy_acknowledged);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(1, OrderItem::where('order_id', $order->id)->count());

        // معاملة واحدة فقط بقيمة سالبة ومرجعها الطلب (19.3)
        $this->assertSame(1, Transaction::count());
        $transaction = Transaction::firstOrFail();
        $this->assertSame('-100.00', $transaction->amount);
        $this->assertSame('150.00', $transaction->balance_after);
        $this->assertSame('purchase', $transaction->source);
        $this->assertSame(Order::class, $transaction->reference_type);
        $this->assertSame($order->id, $transaction->reference_id);
        $this->assertSame($transaction->id, $order->transaction_id);

        $this->assertDatabaseHas('library_entitlements', [
            'user_id' => $user->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'order_id' => $order->id,
        ]);

        $this->assertSame(150.0, $this->balanceOf($user));
    }

    public function test_course_purchase_creates_an_enrollment(): void
    {
        $course = $this->course(['price_coins' => 400]);
        $user = $this->trainee(400);

        $this->actingAs($user)->post(route('store.checkout'), [
            'type' => 'course', 'slug' => $course->slug, 'refund_ack' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'source' => 'purchase',
            'status' => 'active',
        ]);
    }

    public function test_bundle_purchase_opens_every_item_inside_it(): void
    {
        $course = $this->course(['price_coins' => 400]);
        $product = $this->product(['price_coins' => 100]);
        $bundle = $this->bundle([$course, $product], ['price_coins' => 420]);
        $user = $this->trainee(500);

        $this->actingAs($user)->post(route('store.checkout'), [
            'type' => 'bundle', 'slug' => $bundle->slug, 'refund_ack' => 1,
        ])->assertRedirect();

        $this->assertSame('420.00', Order::firstOrFail()->total);
        $this->assertSame(80.0, $this->balanceOf($user));

        // ملكيّة للباقة ولكلّ عنصر بداخلها — ولا يوجد نوع «هديّة» (18)
        $this->assertSame(3, LibraryEntitlement::where('user_id', $user->id)->count());
        $this->assertSame(1, Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->count());
        $this->assertDatabaseHas('library_entitlements', [
            'user_id' => $user->id,
            'itemable_type' => Course::class,
            'itemable_id' => $course->id,
            'source' => 'bundle',
        ]);
    }

    public function test_buying_the_same_item_twice_is_prevented(): void
    {
        $product = $this->product(['price_coins' => 100]);
        $user = $this->trainee(500);

        $payload = ['type' => 'product', 'slug' => $product->slug, 'refund_ack' => 1];

        $this->actingAs($user)->post(route('store.checkout'), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('store.checkout'), $payload)
            ->assertSessionHas('checkout_reason', 'owned')
            ->assertSessionHasErrors('checkout');

        $this->assertSame(1, Order::count());
        $this->assertSame(1, Transaction::count());
        $this->assertSame(400.0, $this->balanceOf($user));
    }

    public function test_refund_policy_acknowledgement_is_required(): void
    {
        $product = $this->product(['price_coins' => 100]);
        $user = $this->trainee(500);

        $this->actingAs($user)->post(route('store.checkout'), [
            'type' => 'product', 'slug' => $product->slug,
        ])->assertSessionHas('checkout_reason', 'ack_required');

        $this->assertSame(0, Order::count());
        $this->assertSame(500.0, $this->balanceOf($user));
    }

    public function test_coupon_discount_is_applied_by_the_server(): void
    {
        $product = $this->product(['price_coins' => 200]);
        $user = $this->trainee(500);

        $coupon = Coupon::create([
            'code' => 'AHLAN15',
            'type' => 'percent',
            'value' => 15,
            'max_uses' => 10,
            'max_uses_per_user' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('store.checkout'), [
            'type' => 'product', 'slug' => $product->slug, 'refund_ack' => 1, 'coupon_code' => 'ahlan15',
        ])->assertRedirect();

        $order = Order::firstOrFail();

        $this->assertSame('200.00', $order->subtotal);
        $this->assertSame('30.00', $order->discount);
        $this->assertSame('170.00', $order->total);
        $this->assertSame($coupon->id, $order->coupon_id);
        $this->assertSame(1, (int) $coupon->refresh()->used_count);
        $this->assertSame(330.0, $this->balanceOf($user));
    }

    public function test_invalid_coupon_does_not_change_the_price(): void
    {
        $product = $this->product(['price_coins' => 200]);
        $user = $this->trainee(500);

        $this->actingAs($user)->post(route('store.checkout'), [
            'type' => 'product', 'slug' => $product->slug, 'refund_ack' => 1, 'coupon_code' => 'NOT-REAL',
        ])->assertRedirect();

        $this->assertSame('200.00', Order::firstOrFail()->total);
    }

    public function test_order_bump_is_priced_from_settings_and_granted(): void
    {
        $course = $this->course(['price_coins' => 400]);
        $product = $this->product(['price_coins' => 100]);
        $user = $this->trainee(600);

        $this->setting('store.order_bump.offers', json_encode([[
            'parent_type' => 'course',
            'parent_slug' => $course->slug,
            'bump_type' => 'product',
            'bump_slug' => $product->slug,
            'price_coins' => 45,
            'teaser' => 'ضيفه معاك.',
        ]], JSON_UNESCAPED_UNICODE));

        $this->actingAs($user)->post(route('store.checkout'), [
            'type' => 'course', 'slug' => $course->slug, 'refund_ack' => 1, 'add_bump' => 1,
        ])->assertRedirect();

        $order = Order::firstOrFail();

        $this->assertSame('445.00', $order->total);
        $this->assertSame(1, OrderItem::where('order_id', $order->id)->where('is_order_bump', true)->count());
        $this->assertSame(2, LibraryEntitlement::where('user_id', $user->id)->count());
        $this->assertSame(155.0, $this->balanceOf($user));
    }

    public function test_quote_endpoint_returns_server_side_numbers_only(): void
    {
        $product = $this->product(['price_coins' => 200]);
        $user = $this->trainee(50);

        $this->actingAs($user)
            ->postJson(route('store.quote'), ['type' => 'product', 'slug' => $product->slug, 'total' => 1])
            ->assertOk()
            ->assertJson([
                'total' => '200 كوين',
                'balance_before' => '50 كوين',
                'balance_after' => '-150 كوين',
                'sufficient' => false,
            ]);
    }
}
