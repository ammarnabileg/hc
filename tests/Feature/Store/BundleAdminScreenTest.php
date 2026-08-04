<?php

namespace Tests\Feature\Store;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\User;
use App\Services\Store\BundleLanding;
use App\Services\Store\PricingService;
use App\Services\Store\StoreCatalog;

/**
 * ⭐ **شاشة البندلز في الإدارة** (24 ← «🖥️ البندلز») و**عزل الماليّات** (12.7).
 *
 * والنصّ الحاكم للعزل حرفيًّا (12.2.2):
 *  > `pricing.edit` 🔒 · ENTITY · ALL · **مالك المنصّة فقط** · «تعديل الأسعار
 *  >  وأسعار العروض **وOverride عناصر البندل**».
 *
 * ومسؤول التسويق والمتجر (12.2.3-6) يملك `bundles.*` و**قراءة** التسعير لا
 * تعديله — فالحصر على **الحقل** لا على الباب: يدخل الشاشة، ويحفظ باقي أقسامها،
 * ولا يمرّ رقمُ سعرٍ واحد من حمولته.
 *
 * والطفرات التي يمسكها هذا الملفّ:
 *  - فتحُ حقل السعر لغير المالك ⟵ يسقط `a_marketing_admin_never_sees_the_pricing_fields`.
 *  - قبولُ السعر من حمولةٍ مزوَّرة ⟵ يسقط `a_forged_price_payload_changes_nothing`.
 *  - كتابةُ القيمة الإجماليّة بيدٍ ⟵ يسقط `the_total_value_is_computed_never_posted`.
 *  - إسقاطُ قالب البونص ⟵ يسقط `a_bonus_item_uses_the_constitution_template`.
 */
class BundleAdminScreenTest extends StoreTestCase
{
    // ============================================================ 🔒 عزل التسعير

    /** ⭐ حقول السعر **تُحذَف من المخرَج** لمسؤول التسويق — تُخفى لا تُعطَّل (2.15-أ-7). */
    public function test_a_marketing_admin_never_sees_the_pricing_fields(): void
    {
        $bundle = $this->bundle([$this->course(), $this->product()], ['price_coins' => 420]);

        $html = $this->actingAs($this->marketingAdmin())
            ->get(route('admin.store.bundles.show', $bundle))
            ->assertOk()
            ->assertSee(setting('store.admin.bundles.pricing_locked_text'))
            ->getContent();

        $this->assertStringNotContainsString('name="price_coins"', $html,
            'حقل سعر البندل ظاهرٌ لمن لا يملك `pricing.edit` — والعزل يُخفي لا يُعطّل (12.7 · 2.15-أ-7).');

        // ومالك المنصّة يراه
        $ownerHtml = $this->actingAs($this->owner())
            ->get(route('admin.store.bundles.show', $bundle))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="price_coins"', $ownerHtml);
    }

    /**
     * ⭐ **الحصر على الخادم**: حمولةٌ مزوَّرة من مسؤول تسويقٍ تحمل سعرًا
     * ⟵ **تُرَدّ ولا تُغيّر رقمًا** (قراءةٌ من القاعدة قبل وبعد).
     */
    public function test_a_forged_price_payload_changes_nothing(): void
    {
        $course = $this->course(['price_coins' => 400]);
        $product = $this->product(['price_coins' => 100]);
        $bundle = $this->bundle([$course, $product], ['price_coins' => 420], [1 => 60.0]);

        $priceBefore = (string) Bundle::whereKey($bundle->id)->value('price_coins');
        $itemBefore = (string) BundleItem::where('bundle_id', $bundle->id)->orderByDesc('id')->value('price_coins');

        $this->actingAs($this->marketingAdmin())
            ->put(route('admin.store.bundles.update', $bundle), [
                'name_ar' => 'اسم جديد يمرّ',
                'slug' => $bundle->slug,
                'status' => 'published',
                'price_coins' => 1, // ⟵ التزوير
            ])
            ->assertRedirect();

        $this->assertSame($priceBefore, (string) Bundle::whereKey($bundle->id)->value('price_coins'),
            'سعر البندل اتغيّر من حمولة مسؤول تسويق — عزل الماليّات مكسور (12.7).');

        // والاسم مرّ فعلًا: الحصر على **الحقل** لا على الطلب كلّه
        $this->assertSame('اسم جديد يمرّ', Bundle::whereKey($bundle->id)->value('name_ar'));

        // و«Override عناصر البندل» منصوصٌ في وصف `pricing.edit` — فلا يمرّ أيضًا
        $item = BundleItem::where('bundle_id', $bundle->id)->orderByDesc('id')->firstOrFail();

        $this->actingAs($this->marketingAdmin())
            ->put(route('admin.store.bundles.items.update', [$bundle, $item]), [
                'price_coins' => 1,
                'is_bonus' => 1,
            ])
            ->assertRedirect();

        $this->assertSame($itemBefore, (string) BundleItem::whereKey($item->id)->value('price_coins'),
            'Override عنصر البندل اتغيّر من غير `pricing.edit` — والنصّ يذكره بالاسم (12.2.2).');

        // ووسم البونص قرارُ عرضٍ يملكه مسؤول المتجر فمرّ
        $this->assertTrue((bool) BundleItem::whereKey($item->id)->value('is_bonus'));
    }

    // ============================================================ الميزان محسوب

    /**
     * ⭐ **القيمة الإجماليّة محسوبة لا مكتوبة** (24: «محسوبة تلقائيًّا، للقراءة»):
     * لا حقل لها في الفورم، وتغييرُ Override عنصرٍ يحرّكها ويحرّك التوفير **بلا
     * أن يكتب أحدٌ رقمًا**.
     */
    public function test_the_total_value_is_computed_never_posted(): void
    {
        $course = $this->course(['price_coins' => 400]);
        $product = $this->product(['price_coins' => 100]);
        $bundle = $this->bundle([$course, $product], ['price_coins' => 420]);

        $html = $this->actingAs($this->owner())
            ->get(route('admin.store.bundles.show', $bundle))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('name="original_value"', $html);
        $this->assertStringNotContainsString('name="total_value"', $html);

        $landing = app(BundleLanding::class);
        $quote = fn () => app(PricingService::class)->quote(null, 'bundle', $bundle->fresh());

        $before = $landing->build($bundle->fresh(), null, $quote());
        $this->assertSame(500.0, $before['total_value']);
        $this->assertSame(80.0, $before['savings']);

        // الأدمن يغيّر Override عنصرٍ واحد — ولا يكتب قيمةً إجماليّةً ولا توفيرًا
        $item = BundleItem::where('bundle_id', $bundle->id)->where('itemable_type', $product::class)->firstOrFail();

        $this->actingAs($this->owner())
            ->put(route('admin.store.bundles.items.update', [$bundle, $item]), ['price_coins' => 250])
            ->assertRedirect();

        $after = $landing->build($bundle->fresh(), null, $quote());
        $this->assertSame(650.0, $after['total_value'], 'القيمة الإجماليّة ماتحرّكتش مع الـOverride.');
        $this->assertSame(230.0, $after['savings']);
        $this->assertSame(35, $after['percent_off']);
    }

    /** والعمود المرآة يتزامن مع الحساب فلا يفترقان (18) */
    public function test_the_mirror_column_follows_the_computed_value(): void
    {
        $bundle = $this->bundle([$this->course(['price_coins' => 400])], ['original_value' => 9999]);
        $item = BundleItem::where('bundle_id', $bundle->id)->firstOrFail();

        $this->actingAs($this->owner())
            ->put(route('admin.store.bundles.items.update', [$bundle, $item]), ['price_coins' => 400])
            ->assertRedirect();

        $this->assertSame('400.00', (string) Bundle::whereKey($bundle->id)->value('original_value'),
            '`original_value` فضل رقمًا مكتوبًا باليد بدل ما يتزامن مع الحساب (18 · 2.9).');
    }

    // ============================================================ البونص بقالبه

    /**
     * ⭐ سطر البونص بقالبه **المنصوص حرفيًّا** في 18، **ولعنصرٍ وسمه الأدمن وحده** —
     * فبونصٌ يشمل كلّ عنصرٍ قيمةٌ مُدرَكة منفوخة، وهو عين ما تمنعه 2.9.
     */
    public function test_a_bonus_item_uses_the_constitution_template(): void
    {
        $course = $this->course(['price_coins' => 400]);
        $product = $this->product(['price_coins' => 100]);
        $bundle = $this->bundle([$course, $product], ['price_coins' => 420]);

        $bonusRow = BundleItem::where('bundle_id', $bundle->id)->where('itemable_type', $product::class)->firstOrFail();
        $bonusRow->update(['is_bonus' => true]);

        $this->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee('🎁 بونص: دليل أسئلة المقابلات بقيمة 100 كوين — مجّانًا مع الباقة')
            // والعنصر غير الموسوم **لا** يظهر كبونص
            ->assertDontSee('🎁 بونص: إكسل للشغل');
    }

    // ============================================================ الجدول والهيدر والحالات

    /** ⭐ أعمدة الجدول التسعة وأزرار الهيدر الثلاثة — بندًا بندًا كما في 24 */
    public function test_the_bundles_table_carries_all_nine_columns(): void
    {
        $this->bundle([$this->course(), $this->product()], ['name_en' => 'Job Ready Pack']);

        $response = $this->actingAs($this->owner())
            ->get(route('admin.store.index', ['tab' => 'bundles']))
            ->assertOk();

        foreach ([
            'store.admin.bundles.col_cover', 'store.admin.bundles.col_name', 'store.admin.bundles.col_items',
            'store.admin.bundles.col_value', 'store.admin.bundles.col_price', 'store.admin.bundles.col_savings',
            'store.admin.bundles.col_purchases', 'store.admin.bundles.col_status', 'store.admin.bundles.col_actions',
        ] as $key) {
            $response->assertSee((string) setting($key));
        }

        // إجراءات الصفّ وأزرار الهيدر
        $response->assertSee(setting('store.admin.bundles.preview_label'))
            ->assertSee(setting('store.admin.bundles.duplicate_label'))
            ->assertSee(setting('store.admin.bundles.copy_link_label'))
            ->assertSee('Job Ready Pack');
    }

    /** ⭐ الحالة الفارغة بنصّها **المنصوص حرفيًّا** في 24 */
    public function test_the_empty_state_uses_the_exact_constitution_text(): void
    {
        $this->assertSame('لا بندلز — اجمع عناصرك في عرض واحد', (string) setting('store.admin.bundles.empty_text'));

        $this->actingAs($this->owner())
            ->get(route('admin.store.index', ['tab' => 'bundles']))
            ->assertOk()
            ->assertSee('لا بندلز — اجمع عناصرك في عرض واحد');
    }

    /** ⭐ بلوك الإعدادات ومعه **القاعدتان المقفولتان** ملاحظتين لا مفتاحين (24) */
    public function test_the_settings_block_shows_the_two_locked_rules(): void
    {
        $this->bundle([$this->product()]);

        $this->actingAs($this->owner())
            ->get(route('admin.store.index', ['tab' => 'bundles']))
            ->assertOk()
            ->assertSee(setting('store.admin.bundles.settings_title'))
            ->assertSee('store.bundles.enabled')
            ->assertSee('store.bundle.anchoring_enabled')
            ->assertSee(setting('store.bundle.rule_contextual_title'))
            ->assertSee(setting('store.bundle.rule_no_gift_title'));
    }

    /** «تكرار بندل» — نسخةٌ **مسودّة** بعناصرها، فلا يُنشَر عرضٌ بالخطأ */
    public function test_duplicating_a_bundle_copies_its_items_as_a_draft(): void
    {
        $bundle = $this->bundle([$this->course(), $this->product()], ['price_coins' => 420]);

        $this->actingAs($this->owner())
            ->post(route('admin.store.bundles.duplicate', $bundle))
            ->assertRedirect();

        $copy = Bundle::where('id', '!=', $bundle->id)->latest('id')->firstOrFail();

        $this->assertSame('draft', $copy->status);
        $this->assertNotSame($bundle->slug, $copy->slug);
        $this->assertSame(2, BundleItem::where('bundle_id', $copy->id)->count());
        $this->assertSame('500.00', (string) $copy->original_value);
    }

    // ============================================================ أعلامٌ لها أثر

    /**
     * ⭐ **علَمٌ لا يفعل شيئًا أسوأ من غيابه** (2.13-و): كلّ توجّلٍ في بلوك
     * إعدادات 24 لازم يغيّر ما يراه المستخدم فعلًا — لا لافتةً في شاشة إعدادات.
     */
    public function test_the_bundles_enabled_flag_actually_hides_bundles(): void
    {
        $bundle = $this->bundle([$this->course(), $this->product()]);
        $url = route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]);

        $this->get($url)->assertOk();

        $this->setting('store.bundles.enabled', '0', 'bool');

        $this->get($url)->assertNotFound();
        $this->assertTrue(app(StoreCatalog::class)->bundleCards([])->isEmpty(),
            'الباقات فضلت في شبكة المتجر رغم إطفاء `bundles.enabled` — علَمٌ بلا أثر.');
    }

    /** وتوجّلا Anchoring والقيمة الإجماليّة العامّان يقفلان الشطب والميزان للمنصّة كلّها */
    public function test_the_global_anchoring_and_total_value_flags_have_teeth(): void
    {
        $bundle = $this->bundle([$this->course(['price_coins' => 400]), $this->product(['price_coins' => 100])], [
            'price_coins' => 420,
        ]);
        $url = route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]);

        $html = $this->get($url)->assertOk()->getContent();
        $this->assertStringContainsString('line-through', $html);
        $this->assertStringContainsString((string) setting('store.bundle.ledger_title'), $html);

        $this->setting('store.bundle.anchoring_enabled', '0', 'bool');
        $this->setting('store.bundle.total_value_enabled', '0', 'bool');

        $html = $this->get($url)->assertOk()->getContent();
        $this->assertStringNotContainsString('line-through', $html,
            'الشطب فضل شغّالًا رغم إطفاء Toggle Anchoring العامّ (24).');
        $this->assertStringNotContainsString((string) setting('store.bundle.ledger_title'), $html,
            'ميزان القيمة فضل ظاهرًا رغم إطفاء Toggle القيمة الإجماليّة العامّ (24).');
    }

    // ============================================================ مساعدات

    protected function owner(): User
    {
        return $this->userWithRole('platform_owner', 'O');
    }

    /** مسؤول التسويق والمتجر (12.2.3-6): يملك `bundles.*` بلا `pricing.edit` */
    protected function marketingAdmin(): User
    {
        $user = $this->userWithRole('marketing_admin', 'M');

        $this->assertTrue($user->allows('bundles.edit'), 'مسؤول التسويق لازم يملك `bundles.edit` — وإلّا فالاختبار لا يقيس العزل.');
        $this->assertFalse($user->allows('pricing.edit'), '`pricing.edit` لمالك المنصّة فقط (12.2.2).');

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
}
