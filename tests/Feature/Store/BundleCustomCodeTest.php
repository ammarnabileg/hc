<?php

namespace Tests\Feature\Store;

use App\Models\Bundle;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ads\Consent;
use App\Services\Store\BundleLanding;
use Illuminate\Support\Facades\Cache;

/**
 * ⭐ **[كود مخصّص] في صفحة البندل** — حقلان يحقنان كودًا حرًّا: أحدهما في `<head>`
 * والآخر **قبل `</body>` مباشرةً**، على مستويين (عامّ لكلّ البندلات + خاصّ لكلّ
 * بندل)، والترتيب: **العامّ أوّلًا ثمّ الخاصّ**.
 *
 * والمحتوى يُطبَع **خامًّا بلا تعقيم** — هذا نصّ المالك: «مسموح أضيف فيهم أي حاجة».
 * ولذلك بالضبط قيدان لا يُتنازَل عنهما، وكلاهما مقيسٌ هنا:
 *
 * **1) 🔒 مالك المنصّة وحده يحرّرهما.** جافاسكربت في `<head>` يملك جلسة كلّ من
 *    يفتح الصفحة — بما فيها جلسة المالك — فمنحُه لمسؤول التسويق (12.2.3-6)
 *    يمنحه المنصّة كلّها من بابٍ خلفيّ ويُبطِل عزل الماليّات (12.7) وكلّ سقفٍ في
 *    مصفوفة 12.2.2. ولا مفتاح في 12.2.2 يصف حقن كودٍ حرّ، وممنوعٌ اختراع مفتاح —
 *    فالحارس صفةُ **مالك المنصّة** نفسها (12.2.1-ز-3)، وهي أضيق من أيّ مفتاح.
 *
 * **2) بوّابة «متى يُحقَن؟».** الرفض في المنصّة **يوقف التتبّع فعليًّا** (21.3-د · 2.9)،
 *    وأغلب ما يوضَع في هذين الحقلين بكسلاتُ تتبّع — فحقنُها بلا شرطٍ يكسر ضمانًا
 *    قائمًا بصمت ويجعل بانر الموافقة يَعِد بما لا يقع. والافتراضيّ **الأضيق** (`ads`).
 *
 * والطفرات: نزعُ حارس الموافقة ⟵ يسقط `a_consent_gated_snippet_is_absent_for_a_refuser`؛
 * نزعُ حارس المالك ⟵ يسقط `a_marketing_admin_cannot_write_custom_code`؛
 * حقنُ العامّ بلا الخاصّ ⟵ يسقط `both_levels_are_injected_global_first`.
 */
class BundleCustomCodeTest extends StoreTestCase
{
    private const GLOBAL_HEAD = '<meta name="hc-test-global-head" content="1">';

    private const BUNDLE_HEAD = '<meta name="hc-test-bundle-head" content="1">';

    private const GLOBAL_BODY = '<span id="hc-test-global-body"></span>';

    private const BUNDLE_BODY = '<span id="hc-test-bundle-body"></span>';

    // ============================================================ الحقن والموضع

    /**
     * ⭐ **المستويان معًا، والعامّ أوّلًا** — وموضعُ كلٍّ **مقيسٌ في الـHTML** لا
     * بالعين: الهيد قبل `</head>`، والبودي **آخر ما قبل `</body>`**.
     */
    public function test_both_levels_are_injected_global_first(): void
    {
        $bundle = $this->bundleWithCode();
        $this->allowEverything();

        $html = $this->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->getContent();

        $headEnd = strpos($html, '</head>');
        $bodyEnd = strrpos($html, '</body>');

        foreach ([self::GLOBAL_HEAD, self::BUNDLE_HEAD, self::GLOBAL_BODY, self::BUNDLE_BODY] as $snippet) {
            $this->assertStringContainsString($snippet, $html, "المقطع «{$snippet}» مش موجود في المخرَج.");
        }

        // الهيد **داخل** الهيد
        $this->assertLessThan($headEnd, strpos($html, self::GLOBAL_HEAD));
        $this->assertLessThan($headEnd, strpos($html, self::BUNDLE_HEAD));

        // ونهاية البودي **بعد** الهيد وقبل الوسم الخاتم
        $this->assertGreaterThan($headEnd, strpos($html, self::GLOBAL_BODY));
        $this->assertLessThan($bodyEnd, strpos($html, self::BUNDLE_BODY));

        // ⭐ والترتيب: العامّ أوّلًا ثمّ الخاصّ — في الموضعين
        $this->assertLessThan(strpos($html, self::BUNDLE_HEAD), strpos($html, self::GLOBAL_HEAD),
            'الخاصّ سبق العامّ في الهيد — والترتيب المتّفق عليه: العامّ أوّلًا.');
        $this->assertLessThan(strpos($html, self::BUNDLE_BODY), strpos($html, self::GLOBAL_BODY),
            'الخاصّ سبق العامّ في نهاية البودي.');

        /*
         | ⭐ **آخر ما قبل `</body>`**: الكود يخرج داخل `@stack('scripts')` وهو آخر
         | وسمٍ في القالب الأمّ قبل `</body>` مباشرةً — فلا محتوى صفحةٍ بعده.
         |
         | ⚠️ وحدٌّ معلَن بصدق: `layouts/app` **ليس ملفّي** (⛔ ممنوع تعديله)، وهو
         |    يُدرِج الودجت العائمة **بعد** محتوى الصفحة، فتُلحِق دفعاتها بالمكدّس
         |    بعد دفعتي. فما بعد المقطع أنماطُ ودجتٍ عامّة **لا محتوى بندل** —
         |    وهذا مقيسٌ لا مُدّعًى: لا يظهر بعده شيءٌ من الصفحة نفسها.
         */
        $mainEnd = strrpos($html, '</main>');
        $this->assertGreaterThan($mainEnd, strpos($html, self::BUNDLE_BODY),
            'كود نهاية البودي اتطبع جوّه محتوى الصفحة لا في نهايتها.');

        $tail = substr($html, strpos($html, self::BUNDLE_BODY) + strlen(self::BUNDLE_BODY));
        $this->assertStringNotContainsString('<section', $tail, 'في محتوى صفحةٍ بعد كود نهاية البودي.');
        $this->assertStringNotContainsString($bundle->name_ar, $tail);
    }

    /** والكود يُطبَع **خامًّا بلا تعقيم** — وهذا نصّ المالك صراحةً */
    public function test_the_snippet_is_printed_raw_without_escaping(): void
    {
        $bundle = $this->bundleWithCode();
        $this->allowEverything();

        $html = $this->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('&lt;meta name=&quot;hc-test-global-head&quot;', $html,
            'الكود اتهرب (escaped) — الحقل بيتحوّل لنصٍّ ظاهر بدل ما يشتغل.');
    }

    /**
     * ووسمٌ ناقص الإغلاق **يُحفَظ كما هو** بلا تعقيم — وهذا ما طلبه المالك.
     * ⚠️ ويُبلَّغ في التقرير: الوسم الناقص **يكسر التصيير فعلًا** في المتصفّح،
     *    وهو ثمن «مسموح أضيف فيهم أي حاجة» — والحارس هو المالك لا المصفّي.
     */
    public function test_a_broken_tag_is_stored_verbatim_and_not_sanitised(): void
    {
        $bundle = $this->bundle([$this->product()]);
        $broken = '<script>console.log("unclosed"';

        $this->actingAs($this->owner())
            ->put(route('admin.store.bundles.update', $bundle), $this->payload($bundle, [
                'landing_head_code' => $broken,
                'landing_head_code_when' => BundleLanding::INJECT_ALWAYS,
            ]))
            ->assertRedirect();

        $this->assertSame($broken, Bundle::whereKey($bundle->id)->value('landing_head_code'),
            'الكود اتعقّم أو اتفلتر — والمالك نصّ على «مسموح أضيف فيهم أي حاجة».');
    }

    // ============================================================ بوّابة الموافقة

    /** ⭐ «بعد موافقة الإعلان»: **رافضٌ ⟵ صفر أثر**، وموافقٌ ⟵ يظهر. */
    public function test_a_consent_gated_snippet_is_absent_for_a_refuser(): void
    {
        $bundle = $this->bundleWithCode(BundleLanding::INJECT_ADS);
        $url = route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]);

        $this->refuseEverything();
        $this->get($url)->assertOk()
            ->assertDontSee(self::GLOBAL_HEAD, false)
            ->assertDontSee(self::BUNDLE_HEAD, false)
            ->assertDontSee(self::GLOBAL_BODY, false)
            ->assertDontSee(self::BUNDLE_BODY, false);

        $this->allowEverything();
        $this->get($url)->assertOk()
            ->assertSee(self::BUNDLE_HEAD, false)
            ->assertSee(self::BUNDLE_BODY, false);
    }

    /** ونفسه لـ«بعد موافقة القياس» — والغرضان مستقلّان لا واحد */
    public function test_the_analytics_gate_is_independent_of_the_ads_gate(): void
    {
        $bundle = $this->bundleWithCode(BundleLanding::INJECT_ANALYTICS);
        $url = route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]);

        // «تخصيص» بالإعلان وحده — والقياس مرفوض، فلا يُحقَن ما شُرِط بالقياس
        $this->customConsent(['ads']);
        $this->get($url)->assertOk()->assertDontSee(self::BUNDLE_HEAD, false);

        $this->customConsent(['analytics']);
        $this->get($url)->assertOk()->assertSee(self::BUNDLE_HEAD, false);
    }

    /** و«دائمًا» يمرّ للرافض — وهو خيارُ الكود غير التتبّعيّ (خطّ · ستايل · وسم ملكيّة) */
    public function test_always_injects_even_for_a_refuser(): void
    {
        $bundle = $this->bundleWithCode(BundleLanding::INJECT_ALWAYS);

        $this->refuseEverything();

        $this->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->assertSee(self::BUNDLE_HEAD, false)
            ->assertSee(self::BUNDLE_BODY, false);
    }

    /** والافتراضيّ **الأضيق**: بندلٌ جديد بلا اختيارٍ يبدأ على «بعد موافقة الإعلان» */
    public function test_the_default_gate_is_the_narrowest_one(): void
    {
        $bundle = $this->bundle([$this->product()])->fresh();

        $this->assertSame(BundleLanding::INJECT_ADS, $bundle->landing_head_code_when);
        $this->assertSame(BundleLanding::INJECT_ADS, $bundle->landing_body_end_code_when);
        $this->assertSame(BundleLanding::INJECT_ADS, (string) setting('store.bundle.head_code_when'));
    }

    // ============================================================ 🔒 حارس المالك

    /** ⭐ الحقلان **مخفيّان** لمسؤول التسويق — يُخفيان لا يُعطَّلان (2.15-أ-7). */
    public function test_a_marketing_admin_never_sees_the_custom_code_fields(): void
    {
        $bundle = $this->bundle([$this->product()]);

        $marketing = $this->marketingAdmin();
        $this->assertFalse($marketing->isPlatformOwner());

        $html = $this->actingAs($marketing)
            ->get(route('admin.store.bundles.show', $bundle))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('name="landing_head_code"', $html);
        $this->assertStringNotContainsString('name="landing_body_end_code"', $html);

        $ownerHtml = $this->actingAs($this->owner())
            ->get(route('admin.store.bundles.show', $bundle))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="landing_head_code"', $ownerHtml);
        $this->assertStringContainsString('name="landing_body_end_code"', $ownerHtml);
    }

    /** ⭐ **والحمولة المزوَّرة تُرَدّ ولا تُغيّر حرفًا** — قراءةٌ من القاعدة قبل وبعد. */
    public function test_a_marketing_admin_cannot_write_custom_code(): void
    {
        $bundle = $this->bundle([$this->product()]);

        $headBefore = Bundle::whereKey($bundle->id)->value('landing_head_code');
        $bodyBefore = Bundle::whereKey($bundle->id)->value('landing_body_end_code');

        $this->actingAs($this->marketingAdmin())
            ->put(route('admin.store.bundles.update', $bundle), $this->payload($bundle, [
                'landing_head_code' => '<script>steal(document.cookie)</script>',
                'landing_head_code_when' => BundleLanding::INJECT_ALWAYS,
                'landing_body_end_code' => '<script>steal()</script>',
                'landing_body_end_code_when' => BundleLanding::INJECT_ALWAYS,
            ]))
            ->assertRedirect();

        $this->assertSame($headBefore, Bundle::whereKey($bundle->id)->value('landing_head_code'),
            'مسؤول تسويق كتب كودًا في الهيد — ده باب خلفيّ للمنصّة كلّها.');
        $this->assertSame($bodyBefore, Bundle::whereKey($bundle->id)->value('landing_body_end_code'));
        $this->assertSame(BundleLanding::INJECT_ADS, Bundle::whereKey($bundle->id)->value('landing_head_code_when'));
    }

    /** ⭐ والمحاولة **تُسجَّل في الأوديت** — فهو أخطر إعدادٍ في الشاشة */
    public function test_the_rejected_attempt_is_audited(): void
    {
        $bundle = $this->bundle([$this->product()]);

        $this->actingAs($this->marketingAdmin())
            ->put(route('admin.store.bundles.update', $bundle), $this->payload($bundle, [
                'landing_head_code' => '<script>x</script>',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'bundles.edit',
            'auditable_id' => $bundle->id,
        ]);

        $logged = \App\Models\AuditLog::where('auditable_id', $bundle->id)->get()
            ->contains(fn ($row) => isset(((array) $row->new_values)['rejected_custom_code']));

        $this->assertTrue($logged, 'محاولة كتابة الكود المرفوضة ماتسجّلتش في الأوديت.');
    }

    /** وتغيير المالك للحقل **يُسجَّل** أيضًا (مَن · متى · أيّ بندل) */
    public function test_an_owner_edit_is_audited_too(): void
    {
        $bundle = $this->bundle([$this->product()]);
        $owner = $this->owner();

        $this->actingAs($owner)
            ->put(route('admin.store.bundles.update', $bundle), $this->payload($bundle, [
                'landing_head_code' => self::BUNDLE_HEAD,
                'landing_head_code_when' => BundleLanding::INJECT_ALWAYS,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'bundles.edit',
            'auditable_id' => $bundle->id,
            'user_id' => $owner->id,
        ]);
    }

    // ============================================================ مساعدات

    private function bundleWithCode(string $when = BundleLanding::INJECT_ALWAYS): Bundle
    {
        $this->putSetting('store.bundle.head_code', self::GLOBAL_HEAD);
        $this->putSetting('store.bundle.head_code_when', $when);
        $this->putSetting('store.bundle.body_end_code', self::GLOBAL_BODY);
        $this->putSetting('store.bundle.body_end_code_when', $when);

        return $this->bundle([$this->product()], [
            'landing_head_code' => self::BUNDLE_HEAD,
            'landing_head_code_when' => $when,
            'landing_body_end_code' => self::BUNDLE_BODY,
            'landing_body_end_code_when' => $when,
        ]);
    }

    private function putSetting(string $key, string $value): void
    {
        Setting::query()->where('key', $key)->update(['value' => $value]);
        Cache::forget('settings');
    }

    /** موافقة كاملة — والحارس نفسه الذي يحكم كلّ التتبّع في المنصّة (21.3-د) */
    private function allowEverything(): void
    {
        $this->consent(Consent::ACCEPTED, Consent::PURPOSES);
    }

    private function refuseEverything(): void
    {
        $this->consent(Consent::REJECTED, []);
    }

    /** @param  array<int, string>  $scopes */
    private function customConsent(array $scopes): void
    {
        $this->consent(Consent::CUSTOM, $scopes);
    }

    /**
     * اختيار الموافقة كما تقرؤه المنصّة فعلًا — من الكوكي (21.3-د).
     * ولا نمرّ من طريقٍ جانبيّ: نفس مصدر `Consent::choice()` نفسه.
     *
     * @param  array<int, string>  $scopes
     */
    private function consent(string $choice, array $scopes): void
    {
        $this->putSetting('ads.tracking.enabled', '1');

        // كوكيّ الموافقة يُقرأ خامًّا في الاختبار — والمنطق المقيس هو `Consent` لا التشفير
        \Illuminate\Cookie\Middleware\EncryptCookies::except(['tracking_consent', 'tracking_scopes']);

        $this->withUnencryptedCookies([
            'tracking_consent' => $choice,
            'tracking_scopes' => json_encode($scopes),
        ]);
    }

    private function payload(Bundle $bundle, array $overrides = []): array
    {
        return array_merge([
            'name_ar' => $bundle->name_ar,
            'slug' => $bundle->slug,
            'status' => $bundle->status,
        ], $overrides);
    }

    private function owner(): User
    {
        return $this->userWithRole('platform_owner', 'O');
    }

    private function marketingAdmin(): User
    {
        return $this->userWithRole('marketing_admin', 'M');
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
