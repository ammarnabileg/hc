<?php

namespace Tests\Feature\Store;

use App\Models\OrderBumpOffer;
use App\Models\User;
use App\Services\Store\PricingService;

/**
 * إدارة عروض Order-bump (17) — كانت نصّ JSON حرًّا في شاشة الإعدادات، بلا تحقّق
 * من صحّة العنصر ولا شاشة إدارةٍ حقيقيّة، رغم أنّ المصفوفة تنصّ على ثلاث
 * صلاحيّات منفصلة `order_bump.create/edit/delete` (12.2.2) — مالك المنصّة وحده.
 *
 * ما يثبته هذا الملفّ حرفيًّا:
 *  1) العرض يُنشأ عبر فورمٍ حقيقيّ لا نصٍّ حرّ — وسلاجٌ غير موجود في الكتالوج يُرفَض.
 *  2) الإدارة لمالك المنصّة وحده — حتى لِمن يملك صلاحيّات الكوبونات الأخرى.
 *  3) الإيقاف يزيل العرض فعليًّا من عروض الشراء — لا شكليًّا فقط.
 */
class OrderBumpAdminTest extends StoreTestCase
{
    protected function owner(): User
    {
        return $this->userWithRole('platform_owner', 'O');
    }

    /** مسؤول التسويق والمتجر (12.2.3-6): يملك `bundles.*` لكن لا يملك `order_bump.*` (owner-only) */
    protected function couponsAdmin(): User
    {
        $user = $this->userWithRole('marketing_admin', 'M');

        $this->assertTrue($user->allows('bundles.edit'), 'الاختبار يفترض دورًا يملك صلاحيّات المتجر العاديّة.');
        $this->assertFalse($user->allows('order_bump.create'), '`order_bump.create` لمالك المنصّة فقط (12.2.2).');

        return $user;
    }

    private function userWithRole(string $role, string $prefix): User
    {
        $user = User::create([
            'name' => 'مستخدم اختبار',
            'email' => strtolower($prefix).uniqid().'@test.local',
            'password' => 'secret-password',
            'code' => $prefix.strtoupper(substr(uniqid(), -7)),
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user->fresh();
    }

    // ------------------------------------------------------------ الإنشاء

    public function test_owner_can_create_an_order_bump_offer_via_the_form(): void
    {
        $course = $this->course();
        $product = $this->product();

        $this->actingAs($this->owner())->post(route('admin.store.order-bumps.store'), [
            'parent_type' => 'course',
            'parent_slug' => $course->slug,
            'bump_type' => 'product',
            'bump_slug' => $product->slug,
            'price_coins' => 45,
            'teaser' => 'ضيفه معاك.',
        ])->assertRedirect();

        $this->assertDatabaseHas('order_bump_offers', [
            'parent_type' => 'course',
            'parent_slug' => $course->slug,
            'bump_type' => 'product',
            'bump_slug' => $product->slug,
            'is_active' => true,
        ]);
    }

    /** ⭐ سلاجٌ غير موجود في الكتالوج يُرفَض على الخادم — لا يمرّ كنصٍّ حرّ (17) */
    public function test_creating_an_offer_with_a_nonexistent_item_slug_is_rejected(): void
    {
        $course = $this->course();

        $this->actingAs($this->owner())->post(route('admin.store.order-bumps.store'), [
            'parent_type' => 'course',
            'parent_slug' => $course->slug,
            'bump_type' => 'product',
            'bump_slug' => 'مفيش-منتج-بالاسم-ده',
            'price_coins' => 45,
        ])->assertSessionHasErrors('bump_slug');

        $this->assertDatabaseCount('order_bump_offers', 0);
    }

    // ------------------------------------------------------------ 🔒 مالك المنصّة وحده

    public function test_a_coupons_admin_cannot_create_or_toggle_or_delete_an_offer(): void
    {
        $course = $this->course();
        $product = $this->product();
        $admin = $this->couponsAdmin();

        $this->actingAs($admin)->post(route('admin.store.order-bumps.store'), [
            'parent_type' => 'course', 'parent_slug' => $course->slug,
            'bump_type' => 'product', 'bump_slug' => $product->slug,
        ])->assertForbidden();

        $offer = OrderBumpOffer::create([
            'parent_type' => 'course', 'parent_slug' => $course->slug,
            'bump_type' => 'product', 'bump_slug' => $product->slug,
        ]);

        $this->actingAs($admin)->post(route('admin.store.order-bumps.toggle', $offer))->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.store.order-bumps.destroy', $offer))->assertForbidden();

        // والفورم أصلًا لا يظهر له في تاب الكوبونات (2.15-أ-7)
        $this->actingAs($admin)
            ->get(route('admin.store.index', ['tab' => 'coupons']))
            ->assertOk()
            ->assertDontSee((string) setting('admin.store.partials.table_coupons.dyf_ard', 'ضيف عرض'));
    }

    // ------------------------------------------------------------ الإيقاف الفعليّ

    public function test_toggling_an_offer_off_removes_it_from_bump_offers(): void
    {
        $course = $this->course();
        $product = $this->product();
        $offer = OrderBumpOffer::create([
            'parent_type' => 'course', 'parent_slug' => $course->slug,
            'bump_type' => 'product', 'bump_slug' => $product->slug,
            'price_coins' => 45,
        ]);

        $before = app(PricingService::class)->bumpOffers(null, 'course', $course);
        $this->assertNotEmpty($before);

        $this->actingAs($this->owner())->post(route('admin.store.order-bumps.toggle', $offer))->assertRedirect();
        $this->assertFalse($offer->fresh()->is_active);

        $after = app(PricingService::class)->bumpOffers(null, 'course', $course);
        $this->assertEmpty($after, 'الإيقاف من الأدمن لازم يمنع العرض فعليًّا من شاشة الشراء — لا تعطيلًا شكليًّا (2.15-أ-7).');
    }

    public function test_deleting_an_offer_removes_it_permanently(): void
    {
        $course = $this->course();
        $product = $this->product();
        $offer = OrderBumpOffer::create([
            'parent_type' => 'course', 'parent_slug' => $course->slug,
            'bump_type' => 'product', 'bump_slug' => $product->slug,
        ]);

        $this->actingAs($this->owner())->delete(route('admin.store.order-bumps.destroy', $offer))->assertRedirect();

        $this->assertDatabaseMissing('order_bump_offers', ['id' => $offer->id]);
    }
}
