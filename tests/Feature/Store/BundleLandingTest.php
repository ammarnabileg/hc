<?php

namespace Tests\Feature\Store;

use App\Models\BundleItem;
use App\Models\LibraryEntitlement;
use App\Models\Order;
use App\Services\Store\PricingService;

/**
 * ⭐ لاندنج بيدج الباقة (18 — البند الوحيد المفتوح في القسم 22).
 *
 * وكلّ اختبار هنا يقابل قاعدةً تسقط حين يقع الخلل:
 *  - «وفّرت X» = **الفرق الحقيقيّ** بين مجموع العناصر وسعر الباقة (18 · 2.9).
 *  - سعر الباقة **سعرها هي** لا مجموع عناصرها (18).
 *  - الشراء يفتح **كلّ** عناصرها، ولا يُشترى مرّتين (18 · 24.5).
 *  - **السعر يُحسَب في الخادم حصرًا** ولا يُقبَل من الطلب (19.5-أ).
 */
class BundleLandingTest extends StoreTestCase
{
    // ------------------------------------------------------ قالبٌ مخصّص لا قالب المنتج

    /** الباقة لها قالبها الخاصّ — كانت تُعرَض بقالب `store/product` نفسه. */
    public function test_bundle_uses_its_own_landing_template(): void
    {
        $product = $this->product();
        $bundle = $this->bundle([$this->course(), $product]);

        $this->actingAs($this->trainee(1000))
            ->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertViewIs('store.bundle');

        // وصفحة المنتج تبقى على قالبها هي
        $this->actingAs($this->trainee(0))
            ->get(route('store.product', ['type' => 'product', 'slug' => $product->slug]))
            ->assertOk()
            ->assertViewIs('store.product');
    }

    // --------------------------------------------------- «وفّرت X» = الفرق الحقيقيّ

    /**
     * ⭐ «وفّرت X» **تساوي الفرق الحقيقيّ** بين مجموع قيم العناصر وسعر الباقة —
     * ولا تلتفت لـ`bundles.original_value` مهما نفخه الأدمن (18 · 2.9).
     */
    public function test_savings_equal_the_real_difference_between_items_and_price(): void
    {
        $course = $this->course(['price_coins' => 400]);
        $product = $this->product(['price_coins' => 100]);

        // الأدمن كتب 5000 والحقيقة 500 — فالتوفير 80 لا 4580
        $bundle = $this->bundle([$course, $product], ['price_coins' => 420, 'original_value' => 5000]);

        $itemsValue = app(PricingService::class)->bundleItemsValue($bundle);
        $expected = $itemsValue - (float) $bundle->price_coins;

        $this->assertSame(500.0, $itemsValue);
        $this->assertSame(80.0, $expected);

        $this->actingAs($this->trainee(1000))
            ->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee('وفّرت 80 كوين')
            ->assertDontSee('وفّرت 4,580 كوين')
            ->assertDontSee('5,000');
    }

    /** وتغيير سعر عنصرٍ يحرّك التوفير فورًا — الرقم محسوب لا مكتوب. */
    public function test_savings_follow_the_items_when_an_item_price_changes(): void
    {
        $course = $this->course(['price_coins' => 400]);
        $product = $this->product(['price_coins' => 100]);
        $bundle = $this->bundle([$course, $product], ['price_coins' => 420]);

        $course->update(['price_coins' => 600]);

        // 600 + 100 − 420 = 280
        $this->actingAs($this->trainee(1000))
            ->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee('وفّرت 280 كوين');
    }

    // ------------------------------------------------------------ Anchoring وميزان القيمة

    /** ⭐ Anchoring (18): القيمة الإجماليّة المحسوبة مشطوبةً مقابل سعر الباقة. */
    public function test_landing_shows_the_value_ledger_with_a_struck_list_price(): void
    {
        $bundle = $this->bundle([$this->course(), $this->product()], ['price_coins' => 420]);

        $html = $this->actingAs($this->trainee(1000))
            ->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee('القيمة الإجماليّة')
            ->assertSee('سعر الباقة')
            ->assertSee('اللي بتوفّره')
            ->assertSee('500 كوين')
            ->assertSee('420 كوين')
            ->getContent();

        // السعر الطبيعيّ يظهر **مشطوبًا** فعلًا لا نصًّا عاديًّا
        $this->assertMatchesRegularExpression('/line-through[^>]*>\s*500 كوين/u', $html);
    }

    /** والسطر الشفّاف يقول صراحةً إنّ القيمة محسوبة — حارسٌ نصّيّ ضدّ الـDark Patterns (2.9). */
    public function test_landing_states_that_the_total_value_is_computed(): void
    {
        $bundle = $this->bundle([$this->course(), $this->product()]);

        $this->actingAs($this->trainee(0))
            ->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee(setting('store.bundle.honest_note'));
    }

    // ------------------------------------------------------------------ البونص والعناصر

    /**
     * ⭐ العنصر الموسوم بونصًا يُعرَض **بقيمته الطبيعيّة** بقالب 18 حرفيًّا —
     * **والموسوم وحده**.
     *
     * وكان القالب يطبع السطر لكلّ عنصرٍ له سعر، فيقرأ الزائر «🎁 بونص» بعدد
     * عناصر الباقة كلّها؛ والبونص الذي يشمل كلّ شيء لا يعني شيئًا، وهو قيمةٌ
     * مُدرَكة منفوخة أيْ عين ما يمنعه 2.9. و24 يجعله **Toggle «اعرضه كبونص»**
     * لصفّ العنصر — قرارَ الأدمن لا وسمًا للكلّ.
     */
    public function test_only_an_item_flagged_as_bonus_shows_the_bonus_line(): void
    {
        $course = $this->course();
        $product = $this->product();
        $bundle = $this->bundle([$course, $product]);

        BundleItem::where('bundle_id', $bundle->id)
            ->where('itemable_type', $product::class)
            ->update(['is_bonus' => true]);

        $this->actingAs($this->trainee(0))
            ->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee(setting('store.bundle.includes_title'))
            ->assertSee('🎁 بونص: دليل أسئلة المقابلات بقيمة 100 كوين — مجّانًا مع الباقة')
            ->assertDontSee('🎁 بونص: إكسل للشغل');
    }

    /** وOverride سعر العنصر لا يظهر إلّا هنا — «قاعدة السعر السياقيّ» (18). */
    public function test_item_override_is_confined_to_the_landing_page(): void
    {
        $product = $this->product(['price_coins' => 100]);
        $bundle = $this->bundle([$this->course(), $product], [], [1 => 60.0]);

        $this->actingAs($this->trainee(0))
            ->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee('60 كوين');

        $this->actingAs($this->trainee(0))
            ->get(route('store.product', ['type' => 'product', 'slug' => $product->slug]))
            ->assertOk()
            ->assertSee('100 كوين')
            ->assertDontSee('60 كوين');
    }

    // ------------------------------------------------------------------ الشراء والسعر

    /** ⭐ الباقة تخصم **سعرها** لا مجموع عناصرها، وتفتح كلّ عناصرها (18). */
    public function test_bundle_charges_its_own_price_and_opens_every_item(): void
    {
        $course = $this->course(['price_coins' => 400]);
        $product = $this->product(['price_coins' => 100]);
        $bundle = $this->bundle([$course, $product], ['price_coins' => 420]);
        $user = $this->trainee(600);

        $this->actingAs($user)->post(route('store.checkout'), [
            'type' => 'bundle', 'slug' => $bundle->slug, 'refund_ack' => 1,
        ])->assertRedirect();

        $this->assertSame('420.00', Order::firstOrFail()->total);
        $this->assertSame(180.0, $this->balanceOf($user), 'اتخصم سعر الباقة لا مجموع عناصرها');
        $this->assertSame(3, LibraryEntitlement::where('user_id', $user->id)->count());
    }

    /** ⭐ **السعر يُحسَب في الخادم حصرًا**: رقمٌ مدسوس في الطلب لا يغيّر شيئًا (19.5-أ). */
    public function test_a_price_sent_by_the_browser_is_ignored(): void
    {
        $bundle = $this->bundle([$this->course(), $this->product()], ['price_coins' => 420]);
        $user = $this->trainee(600);

        $this->actingAs($user)->post(route('store.checkout'), [
            'type' => 'bundle',
            'slug' => $bundle->slug,
            'refund_ack' => 1,
            // محاولة تزوير: سعر وخصم وإجماليّ من المتصفّح
            'price' => 1,
            'total' => 1,
            'discount' => 419,
            'price_coins' => 1,
        ])->assertRedirect();

        $this->assertSame('420.00', Order::firstOrFail()->total);
        $this->assertSame(180.0, $this->balanceOf($user));
    }

    /** ⭐ منع الشراء المكرّر: الباقة المملوكة تعرض حالتها ولا تعرض زرّ الشراء (24.5). */
    public function test_an_owned_bundle_cannot_be_bought_twice(): void
    {
        $bundle = $this->bundle([$this->course(), $this->product()], ['price_coins' => 420]);
        $user = $this->trainee(1000);

        $payload = ['type' => 'bundle', 'slug' => $bundle->slug, 'refund_ack' => 1];

        $this->actingAs($user)->post(route('store.checkout'), $payload)->assertRedirect();

        $this->actingAs($user)->post(route('store.checkout'), $payload)
            ->assertSessionHas('checkout_reason', 'owned')
            ->assertSessionHasErrors('checkout');

        $this->assertSame(1, Order::count());

        $this->actingAs($user->fresh())
            ->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee('تملكه بالفعل')
            ->assertSee(setting('store.bundle.owned_text'))
            ->assertDontSee(setting('store.bundle.cta_label'));
    }

    // ------------------------------------------------------------------ محتوى اللاندنج

    /** اللاندنج يحمل أسئلة ما قبل الشراء من الإعدادات — بلا نصّ محروق (2.13). */
    public function test_landing_shows_the_pre_purchase_faq_from_settings(): void
    {
        $this->setting('store.bundle.faq', json_encode([
            ['q' => 'سؤال اختبار؟', 'a' => 'إجابة اختبار.'],
        ], JSON_UNESCAPED_UNICODE), 'json');

        $bundle = $this->bundle([$this->course(), $this->product()]);

        $this->actingAs($this->trainee(0))
            ->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee(setting('store.bundle.faq_title'))
            ->assertSee('سؤال اختبار؟')
            ->assertSee('إجابة اختبار.');
    }

    /** والزائر بلا تسجيل يعاين اللاندنج كاملة، والشراء وحده محميّ (21.1-أ). */
    public function test_a_guest_sees_the_landing_and_is_asked_to_log_in_to_buy(): void
    {
        $bundle = $this->bundle([$this->course(), $this->product()]);

        $this->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee(setting('store.bundle.includes_title'))
            ->assertSee(setting('store.bundle.login_cta'))
            ->assertSee('"@type":"Product"', false)
            // ⭐ توافرٌ حقيقيّ في Schema.org لا `InStock` دائمًا (21.2-ب · 2.9)
            ->assertSee('"availability":"https://schema.org/InStock"', false);
    }

    /** وبوب-أب الشراء المشترك حاضر بإقرار سياسة عدم الاسترجاع (19.4). */
    public function test_landing_reuses_the_shared_purchase_sheet(): void
    {
        $bundle = $this->bundle([$this->course(), $this->product()]);

        $this->actingAs($this->trainee(100))
            ->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee(setting('store.purchase.sheet_title'))
            ->assertSee('قرأت سياسة عدم الاسترجاع وموافق عليها.')
            ->assertSee('اشحن المحفظة');
    }
}
