<?php

namespace Tests\Feature\Admin\System;

use App\Models\Product;
use App\Services\Admin\System\StatsService;

/**
 * شاشات المتجر والإحصائيّات (24.3-أوّلًا · خامسًا):
 * جدول 5–7 أعمدة وثلاثة فلاتر ظاهرة · وحماية المنتج الرقميّ ·
 * ورسوم SVG بأيدينا بلا أيّ مكتبة خارجيّة.
 */
class AdminSystemStoreAndStatsTest extends SystemTestCase
{
    private const STORE_ADMIN = [
        'store_products.list', 'store_products.create', 'store_products.edit', 'store_products.archive',
        'bundles.list', 'bundles.create', 'coupons.list', 'coupons.create', 'coupons.edit',
        'orders.list', 'product_protection.view', 'product_protection.manage',
        'product_categories.create',
    ];

    private const STATS_ADMIN = [
        'reports_users.view', 'reports_training.view', 'reports_engagement.view', 'acquisition_sources.view',
    ];

    public function test_store_index_shows_all_tabs_the_admin_owns(): void
    {
        $admin = $this->admin(self::STORE_ADMIN);

        $this->actingAs($admin)->get(route('admin.store.index'))
            ->assertOk()
            ->assertSee('المنتجات والتصنيفات', false)
            ->assertSee('البندلز', false)
            ->assertSee('الكوبونات وOrder-bump', false)
            ->assertSee('الطلبات والفواتير', false)
            ->assertSee('المكتبة الرقميّة والحماية', false);
    }

    public function test_product_can_be_created_and_archived_not_deleted(): void
    {
        $admin = $this->admin(self::STORE_ADMIN);

        $this->actingAs($admin)->post(route('admin.store.products.store'), [
            'name_ar' => 'منتج اختبار',
            'type' => 'digital',
            'price_coins' => 90,
            'status' => 'published',
        ])->assertRedirect();

        $product = Product::query()->where('name_ar', 'منتج اختبار')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.store.products.archive', $product))->assertRedirect();

        $this->assertSame('archived', $product->refresh()->status);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    /** نوع كلّ منتج وإعدادات حمايته تُضبَط من هنا (20.2 · 20.5) */
    public function test_digital_product_protection_can_be_switched_to_flip_only(): void
    {
        $admin = $this->admin(self::STORE_ADMIN);
        $product = Product::query()->where('slug', 'mulakhkhas-almusar')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.store.protection.update', $product), [
            'protection' => 'flip',
            'teaser_pages' => 7,
        ])->assertRedirect();

        $product->refresh();

        $this->assertFalse((bool) $product->is_downloadable);
        $this->assertSame(7, (int) $product->teaser_pages);
    }

    /** المكتبة الرقميّة تنصّ صراحةً على أنّ التحليلات مجمّعة بلا سجلّ فتح فرديّ */
    public function test_library_tab_states_analytics_are_aggregate_only(): void
    {
        $admin = $this->admin(self::STORE_ADMIN);

        $this->actingAs($admin)->get(route('admin.store.index', ['tab' => 'library']))
            ->assertOk()
            ->assertSee('مجمّعة فقط', false);
    }

    /** ⛔ صفحة الطلبات تنصّ على أنّه لا استرجاع نقديّ (19.4) */
    public function test_orders_tab_states_there_is_no_cash_refund(): void
    {
        $admin = $this->admin(self::STORE_ADMIN);

        $this->actingAs($admin)->get(route('admin.store.index', ['tab' => 'orders']))
            ->assertOk()
            ->assertSee('مافيش استرجاع نقديّ', false);
    }

    public function test_stats_index_renders_hand_written_svg_without_any_library(): void
    {
        $admin = $this->admin(self::STATS_ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.stats.index'));

        $response->assertOk()
            ->assertSee('<svg', false)
            ->assertSee('قمع التحويل', false)
            ->assertSee('الاحتفاظ (Cohorts)', false)
            ->assertSee('أنشط الأوقات', false);

        // ممنوع أيّ مكتبة رسم خارجيّة — لا chart.js ولا غيرها
        $html = $response->getContent();
        $this->assertStringNotContainsString('chart.js', strtolower($html));
        $this->assertStringNotContainsString('cdn.jsdelivr', strtolower($html));
    }

    public function test_acquisition_tab_maps_source_to_registration_activation_purchase(): void
    {
        $admin = $this->admin(self::STATS_ADMIN);

        $this->actingAs($admin)->get(route('admin.stats.index', ['tab' => 'acquisition']))
            ->assertOk()
            ->assertSee('utm_source', false)
            ->assertSee('تفعيل', false);
    }

    public function test_stats_export_streams_a_csv(): void
    {
        $admin = $this->admin(self::STATS_ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.stats.export', ['tab' => 'users']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    }

    /** التصدير من تاب لا يملكه الأدمن مرفوض */
    public function test_export_of_a_forbidden_tab_is_rejected(): void
    {
        $admin = $this->admin(self::STATS_ADMIN);

        $this->actingAs($admin)->get(route('admin.stats.export', ['tab' => 'sales']))->assertForbidden();
    }

    /** فلتر الفترة العامّ ومقارنة الفترة السابقة يُحسبان في الخادم */
    public function test_period_filter_computes_the_previous_window(): void
    {
        $period = app(StatsService::class)->period('2026-01-01', '2026-01-10', true);

        $this->assertSame(10, $period['days']);
        $this->assertSame('2025-12-22', $period['prev_from']->toDateString());
        $this->assertTrue($period['compare']);
    }
}
