<?php

namespace Tests\Feature\Admin\System;

use App\Models\Setting;
use App\Services\Admin\System\SettingsRegistry;

/**
 * 🔒 عزل الحسّاس (12.2.1 · 2.13-و · 24.3):
 * المجموعة الماليّة ومفاتيح البوّابة **لمالك المنصّة وحده**،
 * **والعنصر لا يظهر أصلًا لغيره** — لا معطَّلًا ولا رماديًّا.
 */
class AdminSystemFinanceIsolationTest extends SystemTestCase
{
    private const GENERAL_ADMIN = [
        'store_products.list',
        'bundles.list',
        'coupons.list',
        'orders.list',
        'settings_general.view',
        'settings_general.edit',
        'reports_users.view',
    ];

    public function test_general_admin_is_blocked_from_the_finance_page(): void
    {
        $admin = $this->admin(self::GENERAL_ADMIN);

        $this->actingAs($admin)->get(route('admin.finance.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.finance.audit'))->assertForbidden();
    }

    public function test_platform_owner_reaches_the_finance_page(): void
    {
        $this->actingAs($this->owner())->get(route('admin.finance.index'))
            ->assertOk()
            ->assertSee('الماليّات', false);
    }

    /** العنصر الماليّ لا يظهر في شاشة المتجر لغير مالك المنصّة */
    public function test_finance_entry_is_hidden_from_the_store_screen_for_general_admin(): void
    {
        $admin = $this->admin(self::GENERAL_ADMIN);

        $this->actingAs($admin)->get(route('admin.store.index'))
            ->assertOk()
            ->assertDontSee(route('admin.finance.index'));

        $this->actingAs($this->owner())->get(route('admin.store.index'))
            ->assertOk()
            ->assertSee(route('admin.finance.index'));
    }

    /** تاب الماليّات في الإعدادات غير موجود أصلًا لغير المالك */
    public function test_finance_tab_is_absent_from_the_settings_registry_for_general_admin(): void
    {
        $admin = $this->admin(self::GENERAL_ADMIN);
        $registry = app(SettingsRegistry::class);

        $this->assertArrayNotHasKey('finance', $registry->tabsFor($admin));
        $this->assertArrayHasKey('finance', $registry->tabsFor($this->owner()));
    }

    /** الإعداد الماليّ لا يُقرَأ ولا يُصدَّر لغير المالك */
    public function test_finance_settings_are_excluded_from_export_for_general_admin(): void
    {
        $admin = $this->admin(self::GENERAL_ADMIN);
        $registry = app(SettingsRegistry::class);

        $this->assertArrayNotHasKey('finance.rates.usd_to_coins', $registry->export($admin));
        $this->assertArrayHasKey('finance.rates.usd_to_coins', $registry->export($this->owner()));
    }

    /** ولا يُكتَب: الحفظ يُرفَض حتى لو وصل المفتاح مباشرةً للـAPI */
    public function test_general_admin_cannot_write_a_finance_setting_even_through_the_api(): void
    {
        $admin = $this->admin(self::GENERAL_ADMIN);
        $setting = Setting::query()->where('key', 'finance.rates.usd_to_coins')->firstOrFail();
        $before = $setting->value;

        $this->actingAs($admin)
            ->postJson(route('admin.settings.field'), ['key' => $setting->key, 'value' => 999])
            ->assertStatus(422);

        $this->assertSame($before, $setting->refresh()->value);
    }

    /** 🔒 مفاتيح البوّابة لا تُعرَض لغير مالك المنصّة */
    public function test_gateway_secret_keys_are_hidden_from_general_admin(): void
    {
        $admin = $this->admin(array_merge(self::GENERAL_ADMIN, ['topup_requests.list']));

        $this->actingAs($admin)->get(route('admin.topups.gateway'))
            ->assertOk()
            ->assertDontSee('topup.gateway.api_key')
            ->assertDontSee('topup.gateway.vendor_key');

        $this->actingAs($this->owner())->get(route('admin.topups.gateway'))
            ->assertOk()
            ->assertSee('topup.gateway.api_key');
    }

    /** التاب الماليّ في الإحصائيّات لا يظهر لغير المخوَّل */
    public function test_finance_stats_tab_is_hidden_from_general_admin(): void
    {
        $admin = $this->admin(self::GENERAL_ADMIN);

        $this->actingAs($admin)->get(route('admin.stats.index'))
            ->assertOk()
            ->assertDontSee('المبيعات والماليّات', false);

        $this->actingAs($this->owner())->get(route('admin.stats.index', ['tab' => 'sales']))
            ->assertOk()
            ->assertSee('المبيعات والماليّات', false);
    }
}
