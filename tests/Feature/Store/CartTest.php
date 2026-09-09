<?php

namespace Tests\Feature\Store;

use App\Models\LibraryEntitlement;
use App\Models\Order;
use App\Models\OrderBumpOffer;
use App\Models\OrderItem;
use App\Models\TopupOffer;

/**
 * السلّة الاختياريّة وصفحة مراجعة الطلب (17) + Order-bump اثنان (17)
 * + «أقرب عرض يكفّيك» في بوب-أب الشراء (19.5-ب-2).
 */
class CartTest extends StoreTestCase
{
    // ------------------------------------------------------------ السلّة

    public function test_cart_review_page_prices_everything_on_the_server(): void
    {
        $product = $this->product(['price_coins' => 100]);
        $course = $this->course(['price_coins' => 400]);
        $user = $this->trainee(1000);

        $this->actingAs($user)->post(route('store.cart.add'), ['type' => 'product', 'slug' => $product->slug]);
        $this->actingAs($user)->post(route('store.cart.add'), ['type' => 'course', 'slug' => $course->slug]);

        $this->actingAs($user)->get(route('store.cart'))
            ->assertOk()
            ->assertSee($product->name_ar)
            ->assertSee($course->name_ar)
            ->assertSee('500');
    }

    /** ⭐ القاعدة الحمراء: السلّة لا تقبل سعرًا من الطلب إطلاقًا */
    public function test_cart_never_accepts_a_price_from_the_request(): void
    {
        $product = $this->product(['price_coins' => 100]);
        $course = $this->course(['price_coins' => 400]);
        $user = $this->trainee(1000);

        // محاولة حقن سعر عند الإضافة…
        $this->actingAs($user)->post(route('store.cart.add'), [
            'type' => 'product', 'slug' => $product->slug, 'price' => 1, 'price_coins' => 1,
        ]);
        $this->actingAs($user)->post(route('store.cart.add'), [
            'type' => 'course', 'slug' => $course->slug, 'price' => 0,
        ]);

        // …ومحاولة حقن إجماليّ وخصم عند الدفع
        $this->actingAs($user)->post(route('store.cart.checkout'), [
            'refund_ack' => 1,
            'total' => 5,
            'subtotal' => 5,
            'discount' => 495,
            'price' => 5,
        ])->assertRedirect();

        $order = Order::firstOrFail();

        $this->assertSame('500.00', $order->total);
        $this->assertSame('0.00', $order->discount);
        $this->assertSame(500.0, $this->balanceOf($user));
    }

    public function test_cart_checkout_grants_every_line_and_empties_the_cart(): void
    {
        $product = $this->product(['price_coins' => 100]);
        $course = $this->course(['price_coins' => 400]);
        $user = $this->trainee(1000);

        $this->actingAs($user)->post(route('store.cart.add'), ['type' => 'product', 'slug' => $product->slug]);
        $this->actingAs($user)->post(route('store.cart.add'), ['type' => 'course', 'slug' => $course->slug]);
        $this->actingAs($user)->post(route('store.cart.checkout'), ['refund_ack' => 1])->assertRedirect();

        $this->assertSame(2, OrderItem::where('order_id', Order::firstOrFail()->id)->count());
        $this->assertSame(2, LibraryEntitlement::where('user_id', $user->id)->count());

        // السلّة فرغت بعد الدفع فلا يتكرّر الطلب
        $this->actingAs($user)->get(route('store.cart'))
            ->assertOk()
            ->assertSee(setting('store.cart.empty_text'));
    }

    public function test_cart_refuses_checkout_without_the_refund_acknowledgement(): void
    {
        $product = $this->product(['price_coins' => 100]);
        $user = $this->trainee(1000);

        $this->actingAs($user)->post(route('store.cart.add'), ['type' => 'product', 'slug' => $product->slug]);

        $this->actingAs($user)
            ->postJson(route('store.cart.checkout'), [])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'ack_required');

        $this->assertSame(0, Order::count());
    }

    public function test_cart_refuses_an_item_the_user_already_owns(): void
    {
        $product = $this->product(['price_coins' => 100]);
        $user = $this->trainee(1000);

        LibraryEntitlement::create([
            'user_id' => $user->id,
            'itemable_type' => $product::class,
            'itemable_id' => $product->id,
            'source' => 'purchase',
        ]);

        $this->actingAs($user)->post(route('store.cart.add'), ['type' => 'product', 'slug' => $product->slug]);

        $this->actingAs($user)->get(route('store.cart'))
            ->assertOk()
            ->assertSee(setting('store.cart.empty_text'));
    }

    // ------------------------------------------------------------ Order-bump (اثنان)

    public function test_two_order_bumps_are_offered_and_both_can_be_added(): void
    {
        $course = $this->course(['price_coins' => 400]);
        $first = $this->product(['slug' => 'bump-one', 'name_ar' => 'ملخّص المعادلات', 'price_coins' => 100]);
        $second = $this->product(['slug' => 'bump-two', 'name_ar' => 'كرّاسة التمارين', 'price_coins' => 80]);
        $user = $this->trainee(1000);

        OrderBumpOffer::create([
            'parent_type' => 'course', 'parent_slug' => $course->slug,
            'bump_type' => 'product', 'bump_slug' => $first->slug,
            'price_coins' => 45, 'teaser' => 'ضيفه معاك.',
        ]);
        OrderBumpOffer::create([
            'parent_type' => 'course', 'parent_slug' => $course->slug,
            'bump_type' => 'product', 'bump_slug' => $second->slug,
            'price_coins' => 30, 'teaser' => 'وده كمان.',
        ]);

        // العرضان يظهران في بوب-أب الشراء داخل صفحة العنصر
        $this->actingAs($user)
            ->get(route('store.product', ['type' => 'course', 'slug' => $course->slug]))
            ->assertOk()
            ->assertSee($first->name_ar)
            ->assertSee($second->name_ar);

        $this->actingAs($user)->post(route('store.checkout'), [
            'type' => 'course',
            'slug' => $course->slug,
            'refund_ack' => 1,
            'bumps' => [$first->slug, $second->slug],
        ])->assertRedirect();

        $order = Order::firstOrFail();

        $this->assertSame('475.00', $order->total);
        $this->assertSame(2, OrderItem::where('order_id', $order->id)->where('is_order_bump', true)->count());
        $this->assertSame(525.0, $this->balanceOf($user));
    }

    public function test_a_bump_that_was_never_offered_is_ignored(): void
    {
        $course = $this->course(['price_coins' => 400]);
        $stranger = $this->product(['slug' => 'not-offered', 'price_coins' => 100]);
        $user = $this->trainee(1000);

        // بلا عروض مضبوطة أصلًا — العرض الغريب يُتجاهَل مهما طُلِب (17)
        $this->actingAs($user)->post(route('store.checkout'), [
            'type' => 'course', 'slug' => $course->slug, 'refund_ack' => 1, 'bumps' => [$stranger->slug],
        ])->assertRedirect();

        $this->assertSame('400.00', Order::firstOrFail()->total);
    }

    // ------------------------------------------------------------ الصلاحيّة الزمنيّة (20.5)

    public function test_product_access_days_become_the_entitlement_validity(): void
    {
        $limited = $this->product(['slug' => 'limited-file', 'price_coins' => 50, 'access_days' => 30]);
        $forever = $this->product(['slug' => 'forever-file', 'price_coins' => 50]);
        $user = $this->trainee(500);

        $this->actingAs($user)->post(route('store.checkout'), [
            'type' => 'product', 'slug' => $limited->slug, 'refund_ack' => 1,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('store.checkout'), [
            'type' => 'product', 'slug' => $forever->slug, 'refund_ack' => 1,
        ])->assertRedirect();

        $limitedRow = LibraryEntitlement::where('itemable_id', $limited->id)->firstOrFail();
        $foreverRow = LibraryEntitlement::where('itemable_id', $forever->id)->firstOrFail();

        $this->assertNotNull($limitedRow->available_until);
        $this->assertSame(30, (int) round(now()->diffInDays($limitedRow->available_until)));
        // الأصل في «مكتبتي» وصولٌ دائم ما لم يُقيَّد صراحةً (20)
        $this->assertNull($foreverRow->available_until);
    }

    // ------------------------------------------------------------ أقرب عرض يكفّيك

    public function test_the_nearest_topup_offer_that_covers_the_gap_is_suggested(): void
    {
        $product = $this->product(['price_coins' => 300]);
        $user = $this->trainee(100);

        TopupOffer::create(['method' => 'manual', 'label_ar' => 'عرض صغير', 'pay_amount' => 100, 'credit_amount' => 110, 'bonus_percent' => 10]);
        TopupOffer::create(['method' => 'manual', 'label_ar' => 'عرض متوسّط', 'pay_amount' => 200, 'credit_amount' => 220, 'bonus_percent' => 10]);
        TopupOffer::create(['method' => 'manual', 'label_ar' => 'عرض كبير', 'pay_amount' => 500, 'credit_amount' => 600, 'bonus_percent' => 20]);

        // العجز 200 ⟵ أقرب عرض يكفّي هو المتوسّط (220) لا الكبير
        $response = $this->actingAs($user)->postJson(route('store.quote'), [
            'type' => 'product', 'slug' => $product->slug,
        ])->assertOk();

        $this->assertFalse($response->json('sufficient'));
        $this->assertStringContainsString('200', (string) $response->json('suggestion'));
        $this->assertStringContainsString('220', (string) $response->json('suggestion'));
        $this->assertStringNotContainsString('600', (string) $response->json('suggestion'));
    }

    public function test_no_offer_is_suggested_when_the_balance_is_enough(): void
    {
        $product = $this->product(['price_coins' => 100]);
        $user = $this->trainee(500);

        TopupOffer::create(['method' => 'manual', 'label_ar' => 'عرض', 'pay_amount' => 100, 'credit_amount' => 110]);

        $this->actingAs($user)->postJson(route('store.quote'), [
            'type' => 'product', 'slug' => $product->slug,
        ])->assertOk()->assertJsonPath('suggestion', null);
    }
}
