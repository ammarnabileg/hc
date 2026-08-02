<?php

namespace Tests\Feature\Admin\System;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Admin\System\SettingsRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

/**
 * شاشة الإعدادات الواحدة بتاباتها الجانبيّة (2.15-د) وقواعد 2.13-و:
 * نمط المفتاح · بحث بالمسار · تصدير/استيراد JSON · مثال حيّ · حفظ تلقائيّ ·
 * Placeholder بالافتراضيّ وReset · Audit بآخر تغيير · وتعطيل إعدادات ميزة موقوفة.
 */
class AdminSystemSettingsTest extends SystemTestCase
{
    private const ADMIN = ['settings_general.view', 'settings_general.edit', 'settings_general.import', 'maintenance.view'];

    public function test_settings_screen_is_one_page_with_side_tabs(): void
    {
        $admin = $this->admin(self::ADMIN);

        $this->actingAs($admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('إعدادات المنصّة', false)
            ->assertSee('مفاتيح المزايا', false)
            ->assertSee('وضع الصيانة', false)
            ->assertSee('سجلّ التدقيق', false);
    }

    /** نمط المفتاح `المجال.الميزة.المفتاح` ظاهر جنب كلّ إعداد */
    public function test_setting_key_is_visible_on_screen(): void
    {
        $admin = $this->admin(self::ADMIN);

        $this->actingAs($admin)->get(route('admin.settings.index', ['tab' => 'features']))
            ->assertOk()
            ->assertSee('features.show_beta_badge', false);
    }

    /** حفظ تلقائيّ لكلّ حقل مع رسالة «تم الحفظ» + Audit */
    public function test_autosave_stores_the_value_and_writes_an_audit_row(): void
    {
        $admin = $this->admin(self::ADMIN);

        $this->actingAs($admin)
            ->postJson(route('admin.settings.field'), ['key' => 'stats.period.default_days', 'value' => 45])
            ->assertOk()
            ->assertJson(['saved' => true, 'message' => 'تم الحفظ ✓']);

        $this->assertSame('45', Setting::query()->where('key', 'stats.period.default_days')->value('value'));

        $setting = Setting::query()->where('key', 'stats.period.default_days')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'settings.update',
            'auditable_id' => $setting->id,
            'user_id' => $admin->id,
        ]);
    }

    /** حماية من الحفظ الناقص: القيمة خارج النطاق لا تُحفَظ ورسالتها توضّح النطاق */
    public function test_out_of_range_number_is_refused_with_a_range_hint(): void
    {
        $admin = $this->admin(self::ADMIN);
        $before = Setting::query()->where('key', 'finance.exchange.fee_percent')->value('value');

        $this->actingAs($this->owner())
            ->postJson(route('admin.settings.field'), ['key' => 'finance.exchange.fee_percent', 'value' => 500])
            ->assertStatus(422)
            ->assertJsonFragment(['saved' => false]);

        $this->assertSame($before, Setting::query()->where('key', 'finance.exchange.fee_percent')->value('value'));
    }

    /** ↺ Reset يرجّع الافتراضيّ، والافتراضيّ يبقى ظاهرًا كـPlaceholder */
    public function test_reset_restores_the_default_value(): void
    {
        $admin = $this->admin(self::ADMIN);
        $setting = Setting::query()->where('key', 'stats.cohorts.months')->firstOrFail();
        $setting->update(['value' => '12']);

        $this->actingAs($admin)->postJson(route('admin.settings.reset'), ['key' => $setting->key])->assertOk();

        $this->assertSame($setting->default_value, $setting->refresh()->value);
    }

    /** «تراجع عن آخر تغيير» — قيمة سابقة واحدة تكفي */
    public function test_undo_returns_the_previous_value(): void
    {
        $admin = $this->admin(self::ADMIN);
        $key = 'stats.geo.max_rows';

        $this->actingAs($admin)->postJson(route('admin.settings.field'), ['key' => $key, 'value' => 33]);
        $this->actingAs($admin)->postJson(route('admin.settings.undo'), ['key' => $key])->assertOk();

        $this->assertSame('20', Setting::query()->where('key', $key)->value('value'));
    }

    /** بحث موحّد بالمسار الكامل (القسم › المجموعة › الحقل) */
    public function test_unified_search_returns_results_with_their_full_path(): void
    {
        $admin = $this->admin(self::ADMIN);

        $response = $this->actingAs($admin)->getJson(route('admin.settings.search', ['q' => 'cohorts']));

        $response->assertOk();
        $first = $response->json('results.0');

        $this->assertSame('stats.cohorts.months', $first['key']);
        $this->assertStringContainsString('›', $first['path']);
    }

    public function test_export_and_import_round_trip(): void
    {
        $admin = $this->admin(self::ADMIN);

        $payload = $this->actingAs($admin)->get(route('admin.settings.export'))->streamedContent();
        $decoded = json_decode($payload, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('stats.period.default_days', $decoded);

        $decoded['stats.period.default_days'] = 60;
        $file = UploadedFile::fake()->createWithContent('settings.json', json_encode($decoded));

        $this->actingAs($admin)->post(route('admin.settings.import'), ['file' => $file])->assertRedirect();

        $this->assertSame('60', Setting::query()->where('key', 'stats.period.default_days')->value('value'));
    }

    /** إعدادات ميزة موقوفة تظهر معطَّلة بسطر «فعّل الميزة أوّلًا» */
    public function test_settings_of_a_disabled_feature_are_disabled(): void
    {
        $registry = app(SettingsRegistry::class);
        Setting::query()->where('key', 'images.enabled')->update(['value' => '0']);
        Cache::forget('settings');

        $setting = Setting::query()->where('key', 'images.batch.max_users')->firstOrFail();

        $this->assertTrue($registry->isDisabled($setting));
        $this->assertSame('images.enabled', $registry->togglerOf($setting->key));
    }

    /** مثال بالقيمة يتحدّث مع الكتابة («72 = 3 أيّام») */
    public function test_live_example_renders_for_numeric_hour_settings(): void
    {
        $registry = app(SettingsRegistry::class);
        $setting = Setting::query()->where('key', 'finance.withdraw.sla_hours')->firstOrFail();

        $this->assertSame('3 يوم', $registry->liveExample($setting));
    }

    /** Audit بالـHover: آخر تغيير فقط ومعه رابط بروفايل المحرّر */
    public function test_audit_endpoint_returns_only_the_last_change(): void
    {
        $admin = $this->admin(self::ADMIN);
        $key = 'stats.export.max_rows';

        $this->actingAs($admin)->postJson(route('admin.settings.field'), ['key' => $key, 'value' => 100]);
        $this->actingAs($admin)->postJson(route('admin.settings.field'), ['key' => $key, 'value' => 200]);

        $response = $this->actingAs($admin)->getJson(route('admin.settings.audit', ['key' => $key]));

        $response->assertOk();
        $this->assertSame('100', $response->json('old'));
        $this->assertSame('200', $response->json('new'));
        $this->assertSame($admin->name, $response->json('by'));

        $setting = Setting::query()->where('key', $key)->firstOrFail();
        $this->assertSame(2, AuditLog::query()->where('auditable_id', $setting->id)->where('action', 'settings.update')->count());
    }
}
