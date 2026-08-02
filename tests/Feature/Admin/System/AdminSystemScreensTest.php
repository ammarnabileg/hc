<?php

namespace Tests\Feature\Admin\System;

use App\Models\AdAudience;
use App\Models\Setting;

/**
 * تأكّد أنّ كلّ شاشة في المجال تفتح فعلًا لمن يملكها — ولا تنكسر بحالة فارغة.
 * (اختبار Feature لكلّ شاشة رئيسيّة — قاعدة البناء §6.)
 */
class AdminSystemScreensTest extends SystemTestCase
{
    public function test_every_owned_screen_opens_for_the_platform_owner(): void
    {
        $owner = $this->owner();

        $routes = [
            route('admin.store.index'),
            route('admin.finance.index'),
            route('admin.finance.index', ['group' => 'refund']),
            route('admin.finance.audit'),
            route('admin.topups.index'),
            route('admin.topups.methods'),
            route('admin.topups.gateway'),
            route('admin.topups.gateway.logs'),
            route('admin.stats.index'),
            route('admin.settings.index'),
            route('admin.settings.index', ['tab' => 'audit']),
            route('admin.settings.index', ['tab' => 'maintenance']),
            route('admin.studio.index'),
            route('admin.articles.index'),
            route('admin.articles.create'),
            route('admin.ads.index'),
        ];

        foreach ($routes as $url) {
            $this->actingAs($owner)->get($url)->assertOk();
        }
    }

    /** ⭐ سياسة الاسترجاع: نسختان (ع/إ) بمعاينة وسبب إلزاميّ وAudit (19.4) */
    public function test_refund_policy_is_saved_in_both_locales_with_an_audit_trail(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('admin.finance.refund-policy'), [
            'locale' => 'ar',
            'body' => '<p>لا استرجاع نقديّ — والرصيد يفضل في محفظتك.</p>',
            'reason' => 'تحديث صياغة السياسة',
        ])->assertRedirect();

        $setting = Setting::query()->where('key', 'finance.refund.policy_ar')->firstOrFail();

        $this->assertStringContainsString('لا استرجاع نقديّ', (string) $setting->value);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'refunds.edit',
            'auditable_id' => $setting->id,
        ]);

        // سبب فاضي ⟵ يُرفَض
        $this->actingAs($owner)->post(route('admin.finance.refund-policy'), [
            'locale' => 'en',
            'body' => 'No cash refunds.',
            'reason' => '',
        ])->assertSessionHasErrors('reason');
    }

    /** ⭐ تصدير الشريحة الإعلانيّة مشفَّر SHA-256 ولمالك المنصّة وحده (21.3) */
    public function test_audience_export_is_hashed_and_owner_only(): void
    {
        $owner = $this->owner();
        $audience = AdAudience::query()->firstOrFail();

        $this->actingAs($owner)->post(route('admin.ads.audiences.export', $audience))->assertRedirect();

        $this->assertDatabaseHas('ad_audience_exports', [
            'ad_audience_id' => $audience->id,
            'hash_algo' => 'sha256',
        ]);

        $admin = $this->admin(['ad_audiences.view', 'ad_audiences.create']);
        $this->actingAs($admin)->post(route('admin.ads.audiences.export', $audience))->assertForbidden();
    }

    /** طرق التحويل وعروض الشحن تُدار كلّها من اللوحة (19.5-ب-1/2) */
    public function test_transfer_methods_and_offers_are_managed_from_the_panel(): void
    {
        $admin = $this->admin(['topup_requests.list', 'topup.manage']);

        $this->actingAs($admin)->post(route('admin.topups.methods.save'), [
            'type' => 'instapay',
            'name_ar' => 'إنستا باي جديدة',
            'account_number' => 'new@instapay',
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('transfer_methods', ['name_ar' => 'إنستا باي جديدة']);

        $this->actingAs($admin)->post(route('admin.topups.offers.save'), [
            'method' => 'manual',
            'label_ar' => 'باقة 2000',
            'pay_amount' => 2000,
            'credit_amount' => 2400,
            'is_active' => 1,
        ])->assertRedirect();

        // ⭐ نسبة الزيادة تُحسَب في الخادم وتُعرَض صراحةً
        $this->assertDatabaseHas('topup_offers', ['label_ar' => 'باقة 2000', 'bonus_percent' => 20]);
    }
}
