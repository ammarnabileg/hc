<?php

namespace Tests\Feature\Admin\System;

use App\Models\Currency;
use App\Models\Setting;
use App\Services\Admin\System\SettingsRegistry;
use Illuminate\Support\Facades\Cache;

/**
 * ⭐ عملة Hours (19.1 · 24 القسم 12): «عملة Hours 🔒: Toggle الإظهار في المحفظة ·
 * سعر الصرف · مصادر الكسب» — كانت الشاشة الماليّة بلا مجموعة لها إطلاقًا رغم
 * أنّ العملة والكارت موجودان بالفعل. لا نخترع آليّة كسبٍ فعليّة: الدستور يترك
 * «مصادر الكسب» قائمةً يقرّرها المالك لاحقًا بلا افتراضٍ منصوص.
 */
class FinanceHoursSettingsTest extends SystemTestCase
{
    private const GENERAL_ADMIN = [
        'store_products.list',
        'settings_general.view',
        'settings_general.edit',
    ];

    public function test_the_hours_group_appears_on_the_finance_page_for_the_owner(): void
    {
        $this->actingAs($this->owner())
            ->get(route('admin.finance.index', ['group' => 'hours']))
            ->assertOk()
            ->assertSee('finance.hours.show_in_wallet')
            ->assertSee('finance.hours.exchange_rate')
            ->assertSee('finance.hours.earn_sources');
    }

    public function test_the_hours_group_is_hidden_from_general_admin(): void
    {
        $admin = $this->admin(self::GENERAL_ADMIN);

        $this->actingAs($admin)->get(route('admin.finance.index'))->assertForbidden();

        $registry = app(SettingsRegistry::class);
        $this->assertArrayNotHasKey('finance.hours.exchange_rate', $registry->export($admin));
        $this->assertArrayHasKey('finance.hours.exchange_rate', $registry->export($this->owner()));
    }

    /** الإعداد يُقرَأ من كاش `settings` — فتحديثه مباشرةً في القاعدة يستوجب نسيان الكاش (مثل ما يفعل `SettingsRegistry::save()`) */
    private function setHoursWalletToggle(string $value): void
    {
        Setting::query()->where('key', 'finance.hours.show_in_wallet')->update(['value' => $value]);
        Cache::forget('settings');
    }

    public function test_turning_the_wallet_toggle_off_hides_the_hours_card(): void
    {
        $trainee = $this->admin(['wallet.view'], 'متدرّب');

        $this->setHoursWalletToggle('1');

        $this->actingAs($trainee)->get(route('wallet.index'))
            ->assertOk()
            ->assertSee('الساعات', false);

        $this->setHoursWalletToggle('0');

        $this->actingAs($trainee)->get(route('wallet.index'))
            ->assertOk()
            ->assertDontSee('الساعات', false);

        $this->setHoursWalletToggle('1');
    }

    public function test_other_secondary_currencies_are_unaffected_by_the_hours_toggle(): void
    {
        $trainee = $this->admin(['wallet.view'], 'متدرّب');

        $this->setHoursWalletToggle('0');

        $tickets = Currency::query()->where('code', 'tickets')->firstOrFail();

        $this->actingAs($trainee)->get(route('wallet.index'))
            ->assertOk()
            ->assertSee($tickets->name_ar, false);

        $this->setHoursWalletToggle('1');
    }

    public function test_saving_a_finance_hours_setting_requires_a_reason_and_writes_the_audit_log(): void
    {
        $setting = Setting::query()->where('key', 'finance.hours.exchange_rate')->firstOrFail();

        $this->actingAs($this->owner())
            ->postJson(route('admin.finance.save'), ['key' => $setting->key, 'value' => 5])
            ->assertStatus(422);

        $this->actingAs($this->owner())
            ->postJson(route('admin.finance.save'), ['key' => $setting->key, 'value' => 5, 'reason' => 'تفعيل تجريبيّ لسعر الصرف'])
            ->assertOk();

        $this->assertSame('5', $setting->refresh()->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'finance.edit']);
    }
}
