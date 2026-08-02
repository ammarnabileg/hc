<?php

namespace Tests\Feature\Store;

use App\Models\LibraryEntitlement;
use App\Models\Product;

/** شاشة المتجر وصفحة العنصر وصفحة السياسة (24.5 · 21.1 · 19.4). */
class StoreBrowseTest extends StoreTestCase
{
    public function test_grid_shows_price_in_coins_and_balance_in_header(): void
    {
        $this->product();
        $user = $this->trainee(500);

        $this->actingAs($user)->get(route('store.index'))
            ->assertOk()
            ->assertSee('دليل أسئلة المقابلات')
            ->assertSee('100 كوين')
            ->assertSee('رصيدي')
            ->assertSee('500');
    }

    public function test_owned_item_is_badged_not_hidden(): void
    {
        $product = $this->product();
        $user = $this->trainee(0);

        LibraryEntitlement::create([
            'user_id' => $user->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'source' => 'purchase',
        ]);

        $this->actingAs($user)->get(route('store.index'))
            ->assertOk()
            ->assertSee('دليل أسئلة المقابلات')
            ->assertSee('تملكه بالفعل');
    }

    public function test_price_range_filter_narrows_the_unified_grid(): void
    {
        $this->product();
        $this->course();
        $user = $this->trainee(0);

        $this->actingAs($user)->get(route('store.index', ['min' => 0, 'max' => 150]))
            ->assertOk()
            ->assertSee('دليل أسئلة المقابلات')
            ->assertDontSee('إكسل للشغل');
    }

    public function test_bundles_page_shows_real_savings_from_original_value(): void
    {
        $bundle = $this->bundle([$this->course(), $this->product()]);
        $user = $this->trainee(0);

        // 500 قيمة العناصر − 420 سعر الباقة = 80 توفيرًا حقيقيًّا (18)
        $this->assertSame(500.0, (float) $bundle->original_value);

        $this->actingAs($user)->get(route('store.bundles'))
            ->assertOk()
            ->assertSee('باقة الاستعداد للوظيفة')
            ->assertSee('وفّرت 80 كوين');
    }

    public function test_course_page_is_indexed_with_course_schema(): void
    {
        $course = $this->course();

        $this->get(route('store.product', ['type' => 'course', 'slug' => $course->slug]))
            ->assertOk()
            ->assertSee('"@type":"Course"', false)
            ->assertDontSee('name="robots"', false);
    }

    public function test_course_page_is_noindex_when_setting_is_off(): void
    {
        $this->setting('growth.seo.index_courses', '0');
        $course = $this->course();

        $this->get(route('store.product', ['type' => 'course', 'slug' => $course->slug]))
            ->assertOk()
            ->assertSee('name="robots"', false);
    }

    public function test_product_page_shows_free_preview_note_and_single_primary_action(): void
    {
        $product = $this->product();
        $user = $this->trainee(1000);

        $this->actingAs($user)->get(route('store.product', ['type' => 'product', 'slug' => $product->slug]))
            ->assertOk()
            ->assertSee('أوّل 3 صفحات مجّانيّة كمعاينة قبل الشراء.')
            ->assertSee('شراء بالكوينز');
    }

    public function test_draft_item_is_not_reachable(): void
    {
        $product = $this->product(['status' => 'draft']);

        $this->get(route('store.product', ['type' => 'product', 'slug' => $product->slug]))
            ->assertNotFound();
    }

    public function test_refund_policy_page_renders_the_setting_text(): void
    {
        $this->setting('store.refund.policy_text', '<p>لا استرجاع نقديّ نهائيًّا.</p>');

        $this->get(route('store.refund-policy'))
            ->assertOk()
            ->assertSee('لا استرجاع نقديّ نهائيًّا.');
    }

    public function test_guest_cannot_reach_the_grid(): void
    {
        $this->get(route('store.index'))->assertRedirect(route('login'));
    }
}
