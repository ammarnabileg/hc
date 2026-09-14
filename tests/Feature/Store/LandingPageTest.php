<?php

namespace Tests\Feature\Store;

use App\Models\LandingPage;
use App\Models\Permission;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Illuminate\Database\Eloquent\Model;

/**
 * ⭐⭐ صفحات الهبوط **المستقلّة** (12.2.3 `landing_pages`) — سدّ الفجوة المؤكَّدة:
 * كانت الصلاحيّات الستّ (`view · create · edit · archive · restore · delete`)
 * حيّةً في المصفوفة ومسنَدة لـ«مسؤول التسويق والمتجر» **بلا أيّ مسار يحرسه
 * واحدٌ منها**، والمُنجَز الوحيد (`BundleLanding`) محصورٌ في شاشة البندل
 * ومحروسٌ بـ`bundles.edit` — لا صفحة مستقلّة لمنتج متجرٍ إطلاقًا.
 *
 * وكلّ اختبارٍ هنا يقابل صلاحيّةً تسقط حين يُزال حارسها (اختبار الطفرة):
 *  - كلّ فعلٍ إداريّ محروسٌ **بمفتاحه هو فقط** — مالك مفتاحٍ واحد لا يفتح أخاه.
 *  - العرض العامّ (`landing_pages.view`) شرطه **الحالة = منشور** حرفَ نصّ
 *    المصفوفة — بلا حارس تسجيل دخول، لأنّه للزائر بالتعريف.
 *  - الميزة تعمل **لمنتج متجرٍ** لا للبندل وحده (الفجوة الأصليّة).
 */
class LandingPageTest extends StoreTestCase
{
    protected function adminWithPermissions(array $keys): User
    {
        $user = User::create([
            'name' => 'مسؤول تسويق',
            'email' => 'marketing'.uniqid().'@test.local',
            'password' => 'secret-password',
            'code' => 'M'.strtoupper(substr(uniqid(), -7)),
            'status' => 'active',
        ]);

        foreach ($keys as $key) {
            $permission = Permission::where('key', $key)->firstOrFail();
            $user->permissionOverrides()->attach($permission->id, [
                'scope' => 'ALL',
                'effect' => 'allow',
            ]);
        }

        app(AccessEngine::class)->forget($user);

        return $user;
    }

    // ================================================================ create

    /** بلا `landing_pages.create` — لا فورم ولا حفظ، حتّى مع صلاحيّاتٍ أخرى من نفس المورد */
    public function test_user_without_create_permission_cannot_create_a_landing_page(): void
    {
        $product = $this->product();
        $user = $this->adminWithPermissions(['landing_pages.edit']);

        $this->actingAs($user)
            ->get(route('admin.store.landing-pages.create', ['type' => 'product', 'id' => $product->id]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.store.landing-pages.store'), ['type' => 'product', 'id' => $product->id])
            ->assertForbidden();

        $this->assertDatabaseCount('landing_pages', 0);
    }

    /** ⭐ صاحب `landing_pages.create` وحدها ينشئ صفحة هبوط **لمنتج متجر مستقلّ** — الفجوة الأصليّة */
    public function test_user_with_create_permission_can_create_a_landing_page_for_a_standalone_product(): void
    {
        $product = $this->product();
        $user = $this->adminWithPermissions(['landing_pages.create']);

        $response = $this->actingAs($user)
            ->post(route('admin.store.landing-pages.store'), ['type' => 'product', 'id' => $product->id, 'headline' => 'عرض محدود']);

        $landingPage = LandingPage::firstOrFail();

        $response->assertRedirect(route('admin.store.landing-pages.edit', $landingPage));
        $this->assertSame($product::class, $landingPage->landingable_type);
        $this->assertSame($product->id, $landingPage->landingable_id);
        $this->assertSame(LandingPage::STATUS_DRAFT, $landingPage->status); // ⭐ إنشاءٌ مسوّدة دائمًا — لا نشر صامت
        $this->assertNotEmpty($landingPage->slug);
    }

    /** ⭐ وتعمل للبندل أيضًا — بلا مساسٍ بأعمدة `landing_*` الخاصّة بـ`BundleLanding` */
    public function test_user_with_create_permission_can_create_a_landing_page_for_a_bundle(): void
    {
        $bundle = $this->bundle([]);
        $user = $this->adminWithPermissions(['landing_pages.create']);

        $this->actingAs($user)
            ->post(route('admin.store.landing-pages.store'), ['type' => 'bundle', 'id' => $bundle->id])
            ->assertRedirect();

        $landingPage = LandingPage::firstOrFail();
        $this->assertSame($bundle::class, $landingPage->landingable_type);
    }

    /** ⭐ صفحة هبوط واحدة فقط لكلّ كيان — لا تكرار صامت */
    public function test_cannot_create_a_second_landing_page_for_the_same_entity(): void
    {
        $product = $this->product();
        $user = $this->adminWithPermissions(['landing_pages.create']);

        $this->actingAs($user)->post(route('admin.store.landing-pages.store'), ['type' => 'product', 'id' => $product->id])->assertRedirect();
        $this->actingAs($user)->post(route('admin.store.landing-pages.store'), ['type' => 'product', 'id' => $product->id])->assertStatus(409);

        $this->assertDatabaseCount('landing_pages', 1);
    }

    // ================================================================ manage (نقطة الدخول من شاشتَي البندل/المنتج)

    /** ⭐ لا صفحة بعد ⟵ نقطة الدخول تحوّل لفورم الإنشاء لصاحب `create` */
    public function test_manage_entry_point_redirects_to_create_when_none_exists(): void
    {
        $product = $this->product();
        $user = $this->adminWithPermissions(['landing_pages.create']);

        $this->actingAs($user)
            ->get(route('admin.store.landing-pages.manage', ['type' => 'product', 'id' => $product->id]))
            ->assertRedirect(route('admin.store.landing-pages.create', ['type' => 'product', 'id' => $product->id]));
    }

    /** وصفحةٌ موجودة بالفعل ⟵ تحوّل لفورم التعديل لصاحب `edit` — لا نموذج إنشاءٍ ثانٍ */
    public function test_manage_entry_point_redirects_to_edit_when_one_exists(): void
    {
        $product = $this->product();
        $landingPage = $this->makeLandingPage($product);
        $user = $this->adminWithPermissions(['landing_pages.edit']);

        $this->actingAs($user)
            ->get(route('admin.store.landing-pages.manage', ['type' => 'product', 'id' => $product->id]))
            ->assertRedirect(route('admin.store.landing-pages.edit', $landingPage));
    }

    /** وبلا الصلاحيّة المناسبة لكلّ حالة — 403 حتّى مع اجتياز حارس المسار العامّ */
    public function test_manage_entry_point_403s_without_the_specific_permission_the_case_needs(): void
    {
        $product = $this->product();
        // يملك `create` فقط، لكنّ الكيان **له صفحة بالفعل** — فالمطلوب `edit` لا `create`
        $this->makeLandingPage($product);
        $user = $this->adminWithPermissions(['landing_pages.create']);

        $this->actingAs($user)
            ->get(route('admin.store.landing-pages.manage', ['type' => 'product', 'id' => $product->id]))
            ->assertForbidden();
    }

    // ================================================================ edit

    /** بلا `landing_pages.edit` — لا فورم تعديل ولا حفظ، حتّى لصاحب `create` */
    public function test_user_without_edit_permission_cannot_edit_a_landing_page(): void
    {
        $landingPage = $this->makeLandingPage($this->product());
        $user = $this->adminWithPermissions(['landing_pages.create']);

        $this->actingAs($user)
            ->get(route('admin.store.landing-pages.edit', $landingPage))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('admin.store.landing-pages.update', $landingPage), ['status' => 'draft', 'headline' => 'محاولة'])
            ->assertForbidden();

        $this->assertNull($landingPage->fresh()->headline);
    }

    /** ⭐ صاحب `landing_pages.edit` يحرّر الأقسام **وينشر** بحقل الحالة الصريح (12.2.2) */
    public function test_user_with_edit_permission_can_edit_and_publish(): void
    {
        $landingPage = $this->makeLandingPage($this->product());
        $user = $this->adminWithPermissions(['landing_pages.edit']);

        $this->actingAs($user)->put(route('admin.store.landing-pages.update', $landingPage), [
            'status' => 'published',
            'headline' => 'وفّر ٣٠٪ اليوم',
            'subheadline' => 'عرضٌ لفترة محدودة',
            'outcomes' => "هتقدر تعمل كذا\nوهتقدر تعمل كذا كمان",
            'faq' => 'هل ده مضمون؟ | آه طبعًا',
        ])->assertRedirect();

        $landingPage->refresh();
        $this->assertSame('published', $landingPage->status);
        $this->assertNotNull($landingPage->published_at);
        $this->assertSame('وفّر ٣٠٪ اليوم', $landingPage->headline);
        $this->assertSame(['هتقدر تعمل كذا', 'وهتقدر تعمل كذا كمان'], $landingPage->outcomes);
        $this->assertSame([['q' => 'هل ده مضمون؟', 'a' => 'آه طبعًا']], $landingPage->faq);
    }

    /** ولا يعدَّل مؤرشفةٌ مباشرةً — لازم تُستعاد أوّلًا (`restore`) */
    public function test_editing_an_archived_landing_page_is_blocked_until_restored(): void
    {
        $landingPage = $this->makeLandingPage($this->product(), ['status' => LandingPage::STATUS_ARCHIVED, 'archived_at' => now()]);
        $user = $this->adminWithPermissions(['landing_pages.edit']);

        $this->actingAs($user)
            ->put(route('admin.store.landing-pages.update', $landingPage), ['status' => 'draft'])
            ->assertStatus(409);
    }

    // ================================================================ archive

    public function test_user_without_archive_permission_cannot_archive(): void
    {
        $landingPage = $this->makeLandingPage($this->product(), ['status' => LandingPage::STATUS_PUBLISHED]);
        $user = $this->adminWithPermissions(['landing_pages.edit']);

        $this->actingAs($user)
            ->post(route('admin.store.landing-pages.archive', $landingPage))
            ->assertForbidden();

        $this->assertSame('published', $landingPage->fresh()->status);
    }

    /** ⭐ الأرشفة تلغي النشر — ولا تحذف السجلّ (بخلاف `delete`) */
    public function test_user_with_archive_permission_can_archive(): void
    {
        $landingPage = $this->makeLandingPage($this->product(), ['status' => LandingPage::STATUS_PUBLISHED]);
        $user = $this->adminWithPermissions(['landing_pages.archive']);

        $this->actingAs($user)
            ->post(route('admin.store.landing-pages.archive', $landingPage))
            ->assertRedirect();

        $landingPage->refresh();
        $this->assertSame(LandingPage::STATUS_ARCHIVED, $landingPage->status);
        $this->assertNotNull($landingPage->archived_at);
        $this->assertDatabaseCount('landing_pages', 1); // لا يزال موجودًا
    }

    // ================================================================ restore

    public function test_user_without_restore_permission_cannot_restore(): void
    {
        $landingPage = $this->makeLandingPage($this->product(), ['status' => LandingPage::STATUS_ARCHIVED, 'archived_at' => now()]);
        $user = $this->adminWithPermissions(['landing_pages.archive']);

        $this->actingAs($user)
            ->post(route('admin.store.landing-pages.restore', $landingPage))
            ->assertForbidden();

        $this->assertSame(LandingPage::STATUS_ARCHIVED, $landingPage->fresh()->status);
    }

    /** ⭐ الاستعادة ترجع **مسوّدة لا منشورة مباشرةً** — مراجعةٌ قبل عودة النشر */
    public function test_user_with_restore_permission_can_restore_to_draft(): void
    {
        $landingPage = $this->makeLandingPage($this->product(), ['status' => LandingPage::STATUS_ARCHIVED, 'archived_at' => now()]);
        $user = $this->adminWithPermissions(['landing_pages.restore']);

        $this->actingAs($user)
            ->post(route('admin.store.landing-pages.restore', $landingPage))
            ->assertRedirect();

        $landingPage->refresh();
        $this->assertSame(LandingPage::STATUS_DRAFT, $landingPage->status);
        $this->assertNull($landingPage->archived_at);
    }

    // ================================================================ delete

    public function test_user_without_delete_permission_cannot_delete(): void
    {
        $landingPage = $this->makeLandingPage($this->product());
        $user = $this->adminWithPermissions(['landing_pages.archive', 'landing_pages.restore']);

        $this->actingAs($user)
            ->delete(route('admin.store.landing-pages.destroy', $landingPage))
            ->assertForbidden();

        $this->assertDatabaseCount('landing_pages', 1);
    }

    /** ⭐ حذفٌ نهائيّ فعلًا — يزول السجلّ من القاعدة (بخلاف الأرشفة) */
    public function test_user_with_delete_permission_can_delete_permanently(): void
    {
        $landingPage = $this->makeLandingPage($this->product());
        $user = $this->adminWithPermissions(['landing_pages.delete']);

        $this->actingAs($user)
            ->delete(route('admin.store.landing-pages.destroy', $landingPage))
            ->assertRedirect();

        $this->assertDatabaseCount('landing_pages', 0);
    }

    // ================================================================ view (معاينة الأدمن)

    /**
     * ⭐ `landing_pages.view` إداريًّا = معاينة سجلّ الصفحة (بما فيها المسوَّدة).
     * وهي **منفصلة** عن `edit`: تفتح المعاينة ولا تفتح فورم التعديل.
     */
    public function test_user_without_view_permission_cannot_open_admin_preview(): void
    {
        $landingPage = $this->makeLandingPage($this->product());
        $user = $this->adminWithPermissions(['landing_pages.edit']);

        $this->actingAs($user)
            ->get(route('admin.store.landing-pages.show', $landingPage))
            ->assertForbidden();
    }

    public function test_user_with_view_permission_can_open_admin_preview_but_not_edit(): void
    {
        $landingPage = $this->makeLandingPage($this->product(), ['headline' => 'مسوّدة سرّيّة']);
        $user = $this->adminWithPermissions(['landing_pages.view']);

        $this->actingAs($user)
            ->get(route('admin.store.landing-pages.show', $landingPage))
            ->assertOk()
            ->assertSee('مسوّدة سرّيّة');

        $this->actingAs($user)
            ->get(route('admin.store.landing-pages.edit', $landingPage))
            ->assertForbidden();
    }

    // ================================================================ العرض العامّ (الزائر)

    /**
     * ⭐⭐ **الزائر غير المسجَّل** يفتح الصفحة **المنشورة** بلا أيّ حارس صلاحيّة —
     * تمامًا كصفحات `store.product`/`store.bundle` العامّة. ويعمل **لمنتج
     * متجرٍ مستقلّ** لا للبندل وحده (الفجوة الأصليّة التي طلب الدستور سدّها).
     */
    public function test_a_guest_can_view_a_published_landing_page_for_a_standalone_product(): void
    {
        $product = $this->product();
        $landingPage = $this->makeLandingPage($product, [
            'status' => LandingPage::STATUS_PUBLISHED,
            'published_at' => now(),
            'headline' => 'دليلك الشامل للمقابلات',
            'cta_label' => 'اشتري الدليل',
            'outcomes' => ['تجاوب على أصعب الأسئلة', 'تفاوض على الراتب بثقة'],
        ]);

        $this->get(route('landing-pages.show', $landingPage->slug))
            ->assertOk()
            ->assertViewIs('store.landing-page')
            ->assertSee('دليلك الشامل للمقابلات')
            ->assertSee('اشتري الدليل')
            ->assertSee('تجاوب على أصعب الأسئلة')
            ->assertSee(route('store.product', ['type' => 'product', 'slug' => $product->slug]), false);
    }

    /** والبندل كذلك — الميزة عامّة للكيانين لا للمنتج وحده */
    public function test_a_guest_can_view_a_published_landing_page_for_a_bundle(): void
    {
        $bundle = $this->bundle([]);
        $landingPage = $this->makeLandingPage($bundle, ['status' => LandingPage::STATUS_PUBLISHED, 'published_at' => now()]);

        $this->get(route('landing-pages.show', $landingPage->slug))->assertOk();
    }

    /** ⭐ الشرط المنصوص حرفًا: «الحالة = منشور» — مسوّدة لا تفتح للزائر */
    public function test_a_guest_gets_404_for_a_draft_landing_page(): void
    {
        $landingPage = $this->makeLandingPage($this->product(), ['status' => LandingPage::STATUS_DRAFT]);

        $this->get(route('landing-pages.show', $landingPage->slug))->assertNotFound();
    }

    /** ومؤرشفةٌ كذلك — إلغاء النشر يقفل الرابط العامّ فعلًا لا شكليًّا */
    public function test_a_guest_gets_404_for_an_archived_landing_page(): void
    {
        $landingPage = $this->makeLandingPage($this->product(), ['status' => LandingPage::STATUS_ARCHIVED, 'archived_at' => now()]);

        $this->get(route('landing-pages.show', $landingPage->slug))->assertNotFound();
    }

    // ================================================================ مساعد

    protected function makeLandingPage(Model $landingable, array $overrides = []): LandingPage
    {
        return LandingPage::create([
            'landingable_type' => $landingable::class,
            'landingable_id' => $landingable->id,
            'slug' => 'landing-'.uniqid(),
            'status' => LandingPage::STATUS_DRAFT,
            ...$overrides,
        ]);
    }
}
