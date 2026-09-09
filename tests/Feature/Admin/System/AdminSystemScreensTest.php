<?php

namespace Tests\Feature\Admin\System;

use App\Models\AdAudience;
use App\Models\AdAudienceExport;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    /**
     * ⭐ من لا هاتف له يخرج بخانة فارغة في التصدير — لا ببصمة النصّ الفارغ
     * (e3b0c442…) التي تُطابِق كلّ عضوٍ بلا هاتف في المنصّات الإعلانيّة بلا فائدة.
     */
    public function test_audience_export_leaves_phone_blank_when_user_has_no_phone(): void
    {
        Storage::fake('local');

        // بلا أيّ شرطٍ إضافيّ في «أفضل المستخدمين» — النشاط والموافقة وحدهما يكفيان (21.3-ج)
        // (الإعداد معرَّف أصلًا في database/seeders/SettingSeeder.php:212 ولا يُزرَع هنا؛
        // SystemTestCase لا يشغّله، فنكتب نفس الصفّ يدويًّا بنفس القيم)
        Setting::query()->updateOrCreate(
            ['key' => 'ads.best_user.rule'],
            [
                'group' => 'ads',
                'label_ar' => 'تعريف «أفضل مستخدم»',
                'type' => 'json',
                'value' => '{"completed_course":false,"purchased":false,"returned":false}',
                'default_value' => '{"completed_course":true,"purchased":true,"returned":true}',
            ],
        );
        Cache::forget('settings');

        $withPhone = User::create([
            'name' => 'له هاتف',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'phone' => '0100 111 2222',
            'password' => 'secret-password',
            'code' => Str::upper(Str::random(8)),
            'status' => 'active',
            'tracking_consent' => 'accepted',
        ]);

        $noPhone = User::create([
            'name' => 'بلا هاتف',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'phone' => null,
            'password' => 'secret-password',
            'code' => Str::upper(Str::random(8)),
            'status' => 'active',
            'tracking_consent' => 'accepted',
        ]);

        $audience = AdAudience::create([
            'name' => 'أفضل المستخدمين — اختبار',
            'kind' => 'lookalike_source',
            'rule' => ['key' => 'best_users'],
            'ttl_days' => 30,
            'refresh_hours' => 24,
            'is_active' => true,
        ]);

        $owner = $this->owner();
        $this->actingAs($owner)->post(route('admin.ads.audiences.export', $audience))->assertRedirect();

        $export = AdAudienceExport::query()->where('ad_audience_id', $audience->id)->firstOrFail();
        $csv = Storage::disk('local')->get($export->file_path);

        $emptyPhoneHash = hash('sha256', '');
        $withPhoneRow = hash('sha256', mb_strtolower(trim($withPhone->email))).','.hash('sha256', '01001112222');
        $noPhoneRow = hash('sha256', mb_strtolower(trim($noPhone->email))).',';

        $this->assertStringContainsString($withPhoneRow, $csv);
        $this->assertStringContainsString($noPhoneRow, $csv);
        $this->assertStringNotContainsString(','.$emptyPhoneHash, $csv);
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
