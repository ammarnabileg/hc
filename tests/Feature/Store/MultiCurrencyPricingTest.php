<?php

namespace Tests\Feature\Store;

use App\Models\BundleItem;
use App\Models\LibraryEntitlement;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\Store\PricingService;

/**
 * التسعير متعدّد العملات (17) و«وفّرت X» المحسوبة (18 · 2.9).
 *
 * ثلاثة أعطال يغلقها هذا الملفّ ويمنع رجوعها:
 *  1. عمود `price_tickets` موجود ويُتجاهَل ⟵ منتجٌ بـ50 تذكرة كان يُسلَّم مجّانًا.
 *  2. الخصم من الكوينز حصرًا ⟵ رصيد التذاكر لا يُفحَص ولا يُخصَم.
 *  3. «وفّرت X» من رقمٍ يكتبه الأدمن ⟵ ادّعاءٌ لا يسنده شيء (Dark Pattern).
 */
class MultiCurrencyPricingTest extends StoreTestCase
{
    // ---------------------------------------------------------------- التذاكر

    public function test_a_ticket_priced_product_is_quoted_and_charged_in_tickets(): void
    {
        $product = $this->product([
            'price_coins' => 0,
            'price_tickets' => 50,
            'price_currency' => 'tickets',
        ]);

        $user = $this->trainee(0);              // بلا كوينز إطلاقًا
        $this->creditCurrency($user, 'tickets', 60);

        $this->actingAs($user)->post(route('store.checkout'), [
            'type' => 'product', 'slug' => $product->slug, 'refund_ack' => 1,
        ])->assertRedirect();

        $order = Order::firstOrFail();

        $this->assertSame('50.00', $order->total);
        $this->assertSame('tickets', $order->currency->code);
        $this->assertEqualsWithDelta(10.0, $this->balanceOf($user, 'tickets'), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->balanceOf($user), 0.001);
        $this->assertSame(1, LibraryEntitlement::where('user_id', $user->id)->count());

        // معاملة واحدة بعملة التذاكر لا بالكوينز (19.3)
        $transaction = Transaction::firstOrFail();
        $this->assertSame($order->currency_id, (int) $transaction->currency_id);
        $this->assertSame('-50.00', $transaction->amount);
    }

    public function test_a_ticket_priced_product_is_refused_when_tickets_are_short(): void
    {
        $product = $this->product([
            'price_coins' => 0,
            'price_tickets' => 50,
            'price_currency' => 'tickets',
        ]);

        // رصيد كوينز ضخم لا يشتري منتجًا مسعَّرًا بالتذاكر
        $user = $this->trainee(100000);
        $this->creditCurrency($user, 'tickets', 10);

        $this->from(route('store.product', ['type' => 'product', 'slug' => $product->slug]))
            ->actingAs($user)
            ->post(route('store.checkout'), [
                'type' => 'product', 'slug' => $product->slug, 'refund_ack' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('checkout_reason', 'insufficient');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, LibraryEntitlement::count());
        $this->assertSame(0, Transaction::count());
        $this->assertEqualsWithDelta(10.0, $this->balanceOf($user, 'tickets'), 0.001);
        $this->assertEqualsWithDelta(100000.0, $this->balanceOf($user), 0.001);
    }

    public function test_the_product_page_shows_the_price_in_its_own_currency(): void
    {
        $product = $this->product([
            'price_coins' => 0,
            'price_tickets' => 50,
            'price_currency' => 'tickets',
        ]);

        $this->actingAs($this->trainee(0))
            ->get(route('store.product', ['type' => 'product', 'slug' => $product->slug]))
            ->assertOk()
            ->assertSee('50 تذكرة')
            ->assertSee('شراء بالتذاكر')
            // ولا يُعرَض بصفر كوين — وهو ما كان يحدث حين كان العمود يُتجاهَل
            ->assertDontSee('0 كوين');
    }

    public function test_currency_filter_narrows_the_unified_grid(): void
    {
        $this->product(['price_coins' => 0, 'price_tickets' => 50, 'price_currency' => 'tickets']);
        $this->course();

        $this->actingAs($this->trainee(0))
            ->get(route('store.index', ['currencies' => ['tickets']]))
            ->assertOk()
            ->assertSee('دليل أسئلة المقابلات')
            ->assertDontSee('إكسل للشغل');
    }

    public function test_the_cart_refuses_mixing_two_currencies(): void
    {
        $course = $this->course();
        $ticketProduct = $this->product(['price_coins' => 0, 'price_tickets' => 50, 'price_currency' => 'tickets']);
        $user = $this->trainee(1000);

        $this->actingAs($user)->post(route('store.cart.add'), ['type' => 'course', 'slug' => $course->slug]);
        $this->actingAs($user)->post(route('store.cart.add'), ['type' => 'product', 'slug' => $ticketProduct->slug]);

        // العنصر الثاني لم يدخل السلّة — فلا إجماليّ يجمع كوينز وتذاكر
        $this->actingAs($user)->get(route('store.cart'))
            ->assertOk()
            ->assertSee('إكسل للشغل')
            ->assertDontSee('دليل أسئلة المقابلات');
    }

    // ---------------------------------------------------------------- «وفّرت X» (18)

    public function test_bundle_savings_ignore_an_inflated_original_value(): void
    {
        // عناصر حقيقيّة بـ500، والأدمن كتب 5000 — والتوفير الحقيقيّ 80 لا 4580
        $bundle = $this->bundle(
            [$this->course(), $this->product()],
            ['price_coins' => 420, 'original_value' => 5000],
        );

        $this->actingAs($this->trainee(0))
            ->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee('وفّرت 80 كوين')
            ->assertDontSee('وفّرت 4,580 كوين');
    }

    /**
     * ⭐ سطر البونص **لعنصرٍ وسمه الأدمن بونصًا وحده** (24: Toggle «اعرضه كبونص») —
     * وكان يُطبَع لكلّ عنصرٍ له سعر، فيقرأ الزائر «🎁 بونص» أربع مرّات في باقةٍ من
     * أربعة، وهي قيمةٌ مُدرَكة منفوخة أي عين ما تمنعه 2.9.
     */
    public function test_bundle_page_shows_the_bonus_line_and_the_computed_total_value(): void
    {
        $course = $this->course();
        $bundle = $this->bundle([$course, $this->product()]);

        BundleItem::where('bundle_id', $bundle->id)
            ->where('itemable_type', $course::class)
            ->update(['is_bonus' => true]);

        $this->actingAs($this->trainee(0))
            ->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee('🎁 بونص: إكسل للشغل بقيمة 400 كوين — مجّانًا مع الباقة')
            ->assertSee(setting('store.bundle.total_value_label'))
            ->assertSee('500 كوين');
    }

    public function test_item_override_price_shows_only_inside_the_bundle_page(): void
    {
        // العنصر سعره الطبيعيّ 100، وداخل الباقة 60 (Override — 18)
        $product = $this->product(['price_coins' => 100]);
        $bundle = $this->bundle([$this->course(), $product], [], [1 => 60.0]);

        $this->actingAs($this->trainee(0))
            ->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee('60 كوين');

        // وصفحة المنتج نفسه تُبقي السعر الطبيعيّ — «قاعدة السعر السياقيّ» (18)
        $this->actingAs($this->trainee(0))
            ->get(route('store.product', ['type' => 'product', 'slug' => $product->slug]))
            ->assertOk()
            ->assertSee('100 كوين')
            ->assertDontSee('60 كوين');

        /*
         | ⭐ والقيمة الإجماليّة **مجموع ما يقرؤه الزائر في السطور أمامه** —
         | أيْ بالـOverride: 400 + 60 = 460 لا 500. ولو جمعنا الطبيعيّ بينما
         | السطور تعرض الـOverride لصار مجموع ما على الشاشة ≠ الرقم المعلَن،
         | وهو رقمٌ لا يسنده شيء (2.9 · 21.1-د).
         */
        $this->assertEqualsWithDelta(
            460.0,
            app(PricingService::class)->bundleItemsValue($bundle),
            0.001,
        );
    }
}
