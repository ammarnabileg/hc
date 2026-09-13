<?php

namespace Tests\Feature\Admin\System;

use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Order;

/**
 * «تصدير تقرير الاستخدام» (24.3 — هيدر تاب الكوبونات): صلاحيّة `coupons.export`
 * مستقلّة عن `coupons.list` (12.2.2)، والملفّ الحقيقيّ يحمل بيانات استخدامٍ حقيقيّة
 * لا مجرّد 200 فارغة.
 */
class CouponExportTest extends SystemTestCase
{
    private function seedUsage(): Coupon
    {
        $currency = Currency::query()->where('code', 'coins')->firstOrFail();

        $coupon = Coupon::create([
            'code' => 'WELCOME10',
            'type' => 'percent',
            'value' => 10,
            'max_uses' => 100,
            'used_count' => 1,
            'max_uses_per_user' => 1,
            'is_active' => true,
        ]);

        $buyer = $this->makeUser('مشتري الكوبون');

        Order::create([
            'number' => 'ORD-TEST-1',
            'user_id' => $buyer->id,
            'subtotal' => 100,
            'discount' => 10,
            'total' => 90,
            'currency_id' => $currency->id,
            'coupon_id' => $coupon->id,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // كوبونٌ ثانٍ بلا استخدام — يجب أن يظهر في التقرير بصفٍّ فارغ الاستخدام لا أن يختفي
        Coupon::create([
            'code' => 'UNUSED5',
            'type' => 'fixed',
            'value' => 5,
            'max_uses_per_user' => 1,
            'is_active' => true,
        ]);

        return $coupon;
    }

    public function test_user_with_export_permission_downloads_real_usage_data(): void
    {
        $this->seedUsage();

        $admin = $this->admin(['coupons.list', 'coupons.export']);

        $response = $this->actingAs($admin)->get(route('admin.store.coupons.export'))->assertOk();

        $csv = $response->streamedContent();

        // الترويسة العربيّة + بيانات الاستخدام الحقيقيّة: الكود والمشتري والخصم والطلب
        $this->assertStringContainsString('الكود', $csv);
        $this->assertStringContainsString('WELCOME10', $csv);
        $this->assertStringContainsString('ORD-TEST-1', $csv);
        $this->assertStringContainsString('مشتري الكوبون', $csv);
        $this->assertStringContainsString('10', $csv); // قيمة الخصم في الطلب

        // الكوبون بلا استخدام يظهر أيضًا — لا يختفي من التقرير
        $this->assertStringContainsString('UNUSED5', $csv);
    }

    public function test_user_without_export_permission_is_forbidden(): void
    {
        $this->seedUsage();

        // يملك العرض فقط — لا التصدير (12.2.2: سلطتان منفصلتان)
        $admin = $this->admin(['coupons.list']);

        $this->actingAs($admin)->get(route('admin.store.coupons.export'))->assertForbidden();
    }

    public function test_header_export_link_follows_the_export_permission(): void
    {
        $this->seedUsage();

        $withExport = $this->admin(['coupons.list', 'coupons.export'], 'أدمن بصلاحيّة التصدير');
        $this->actingAs($withExport)->get(route('admin.store.index', ['tab' => 'coupons']))
            ->assertOk()
            ->assertSee(route('admin.store.coupons.export'), false);

        $withoutExport = $this->admin(['coupons.list'], 'أدمن بلا صلاحيّة التصدير');
        $this->actingAs($withoutExport)->get(route('admin.store.index', ['tab' => 'coupons']))
            ->assertOk()
            ->assertDontSee(route('admin.store.coupons.export'), false);
    }
}
