<?php

namespace Tests\Feature\Store;

use App\Models\AuditLog;
use App\Models\Bundle;
use App\Models\User;
use Illuminate\Cookie\Middleware\EncryptCookies;

/**
 * ⭐ **[كود مخصّص] في صفحة البندل** — بنصّ المالك حرفيًّا:
 *
 * > «أنا طلبت إنّه يبقى فيه إنبوت **من جوّا البندل** أحطّ كود فيه، فيطلع بين
 * >  وسمَي الـ`head` بتاع البندل. وإنبوت زيّه يبقى **فوق `</body>` مباشرة**. **فقط**.»
 *
 * فحقلان اثنان لا غير، **لهذا البندل وحده**، يخرجان **خامّين بلا تعقيم**:
 *  - **لا مستوًى عامّ** لكلّ البندلات.
 *  - **لا خانة «متى يُحقَن؟»** ولا بوّابة موافقة — الكود يُحقَن **دائمًا**.
 *
 * 🔒 **والحارس الوحيد الباقي: مالك المنصّة.** جافاسكربت في `<head>` يملك جلسة كلّ
 *    من يفتح الصفحة — بما فيها جلسة المالك. فمنحُه لمسؤول التسويق (12.2.3-6)
 *    يمنحه المنصّة كلّها من بابٍ خلفيّ ويُبطِل عزل الماليّات (12.7) وكلّ سقفٍ في
 *    مصفوفة 12.2.2. وهذا حارسُ **مَن يحرّر**، لا طبقةَ سلوكٍ في الصفحة.
 *
 * والطفرات: نقلُ الحقل لمستوًى عامّ ⟵ يسقط `code_belongs_to_one_bundle_only`؛
 * إعادةُ شرطِ حقنٍ ⟵ يسقط `the_code_is_injected_for_everyone_including_a_tracking_refuser`؛
 * نزعُ حارس المالك ⟵ يسقط `a_marketing_admin_cannot_write_custom_code`.
 */
class BundleCustomCodeTest extends StoreTestCase
{
    private const HEAD = '<meta name="hc-test-head" content="1">';

    private const BODY = '<span id="hc-test-body"></span>';

    // ============================================================ الموضع

    /**
     * ⭐ **الموضعان مقيسان في الـHTML لا بالعين**: الأوّل **بين وسمَي `<head>`**،
     * والثاني **آخر ما قبل `</body>`**.
     */
    public function test_each_field_lands_in_its_exact_slot(): void
    {
        $bundle = $this->bundleWithCode();

        $html = $this->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->getContent();

        $headStart = strpos($html, '<head>');
        $headEnd = strpos($html, '</head>');
        $bodyEnd = strrpos($html, '</body>');

        $headAt = strpos($html, self::HEAD);
        $bodyAt = strpos($html, self::BODY);

        $this->assertNotFalse($headAt, 'كود الهيد مش موجود في المخرَج.');
        $this->assertNotFalse($bodyAt, 'كود نهاية البودي مش موجود في المخرَج.');

        // بين وسمَي الـhead بالضبط
        $this->assertGreaterThan($headStart, $headAt);
        $this->assertLessThan($headEnd, $headAt);

        /*
         | و**آخر ما قبل `</body>`**: يخرج داخل `@stack('scripts')` وهو آخر وسمٍ في
         | القالب الأمّ قبل الوسم الخاتم.
         |
         | ⚠️ وحدٌّ معلَن بصدق: `layouts/app` **ليس ملفّي** (⛔ ممنوع تعديله)، وهو
         |    يُدرِج الودجت العائمة **بعد** محتوى الصفحة فتُلحِق أنماطها بالمكدّس
         |    بعد دفعتي. فما بعد المقطع أنماطُ ودجتٍ عامّة **لا محتوى بندل** —
         |    وهذا مقيسٌ لا مُدّعًى.
         */
        $this->assertGreaterThan(strrpos($html, '</main>'), $bodyAt);
        $this->assertLessThan($bodyEnd, $bodyAt);

        $tail = substr($html, $bodyAt + strlen(self::BODY), $bodyEnd - $bodyAt - strlen(self::BODY));
        $this->assertStringNotContainsString('<section', $tail, 'في محتوى صفحةٍ بعد كود نهاية البودي.');
        $this->assertStringNotContainsString($bundle->name_ar, $tail);
    }

    /** ويُطبَع **خامًّا بلا تعقيم** — وهذا نصّ المالك صراحةً */
    public function test_the_snippet_is_printed_raw_without_escaping(): void
    {
        $bundle = $this->bundleWithCode();

        $html = $this->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('&lt;meta name=&quot;hc-test-head&quot;', $html,
            'الكود اتهرب (escaped) — الحقل بيتحوّل لنصٍّ ظاهر بدل ما يشتغل.');
    }

    /**
     * ⭐ **الكود ملكُ بندلٍ واحد**: بندلٌ بلا كود ⟵ **صفر أثر** في صفحته.
     * وهذا ما يسقط لو عاد أحدٌ فجعل الحقل إعدادًا عامًّا لكلّ البندلات.
     */
    public function test_code_belongs_to_one_bundle_only(): void
    {
        $withCode = $this->bundleWithCode();
        $withoutCode = $this->bundle([$this->product(['slug' => 'p-clean', 'name_ar' => 'منتج نظيف'])], [
            'slug' => 'clean-pack',
            'name_ar' => 'باقة بلا كود',
        ]);

        $this->get(route('store.product', ['type' => 'bundle', 'slug' => $withCode->slug]))
            ->assertOk()
            ->assertSee(self::HEAD, false)
            ->assertSee(self::BODY, false);

        $this->get(route('store.product', ['type' => 'bundle', 'slug' => $withoutCode->slug]))
            ->assertOk()
            ->assertDontSee(self::HEAD, false)
            ->assertDontSee(self::BODY, false);
    }

    // ============================================================ بلا شرطِ حقن

    /**
     * ⭐ **الكود يُحقَن دائمًا — حتّى لزائرٍ رافضٍ للتتبّع.**
     *
     * وهذا **تغييرٌ مقصود ومسجَّل**: كانت هنا خانة «متى يُحقَن؟» وبوّابة موافقة
     * تمنع الحقن عن الرافض، فحذفها المالك صراحةً. والاختبار يوثّق القرار بدل أن
     * يبقى ضمنيًّا: من يضع بكسل تتبّعٍ هنا **يضعه بعلمه**، والحقل بيد مالك المنصّة
     * وحده فهو صاحب القرار وصاحب تبعته.
     */
    public function test_the_code_is_injected_for_everyone_including_a_tracking_refuser(): void
    {
        $bundle = $this->bundleWithCode();
        $url = route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]);

        // زائرٌ لم يختر شيئًا
        $this->get($url)->assertOk()->assertSee(self::HEAD, false)->assertSee(self::BODY, false);

        // وزائرٌ **رفض** التتبّع صراحةً — والكود يظهر له كذلك
        EncryptCookies::except(['tracking_consent', 'tracking_scopes']);
        $this->withUnencryptedCookies([
            'tracking_consent' => 'rejected',
            'tracking_scopes' => json_encode([]),
        ]);

        $this->get($url)->assertOk()
            ->assertSee(self::HEAD, false)
            ->assertSee(self::BODY, false);
    }

    /** ولا أثر لأيّ إعدادٍ عامّ — فالمفاتيح محذوفة أصلًا ولا يقرؤها أحد */
    public function test_no_global_code_setting_survives(): void
    {
        foreach ([
            'store.bundle.head_code',
            'store.bundle.head_code_when',
            'store.bundle.body_end_code',
            'store.bundle.body_end_code_when',
            'store.bundle.code_when_labels',
            'store.bundle.code_consent_note',
        ] as $key) {
            $this->assertDatabaseMissing('settings', ['key' => $key]);
        }
    }

    /** ووسمٌ ناقص الإغلاق **يُحفَظ كما هو** بلا تعقيم — وهذا ما طلبه المالك */
    public function test_a_broken_tag_is_stored_verbatim_and_not_sanitised(): void
    {
        $bundle = $this->bundle([$this->product()]);
        $broken = '<script>console.log("unclosed"';

        $this->actingAs($this->owner())
            ->put(route('admin.store.bundles.update', $bundle), $this->payload($bundle, [
                'landing_head_code' => $broken,
            ]))
            ->assertRedirect();

        $this->assertSame($broken, Bundle::whereKey($bundle->id)->value('landing_head_code'),
            'الكود اتعقّم أو اتفلتر — والمالك نصّ على «مسموح أضيف فيهم أي حاجة».');
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
                'landing_body_end_code' => '<script>steal()</script>',
            ]))
            ->assertRedirect();

        $this->assertSame($headBefore, Bundle::whereKey($bundle->id)->value('landing_head_code'),
            'مسؤول تسويق كتب كودًا في الهيد — ده باب خلفيّ للمنصّة كلّها.');
        $this->assertSame($bodyBefore, Bundle::whereKey($bundle->id)->value('landing_body_end_code'));
    }

    /** ⭐ والمحاولة **تُسجَّل في الأوديت** — فهو أخطر حقلٍ في الشاشة */
    public function test_the_rejected_attempt_is_audited(): void
    {
        $bundle = $this->bundle([$this->product()]);

        $this->actingAs($this->marketingAdmin())
            ->put(route('admin.store.bundles.update', $bundle), $this->payload($bundle, [
                'landing_head_code' => '<script>x</script>',
            ]))
            ->assertRedirect();

        $logged = AuditLog::where('auditable_id', $bundle->id)->get()
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
                'landing_head_code' => self::HEAD,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'bundles.edit',
            'auditable_id' => $bundle->id,
            'user_id' => $owner->id,
        ]);

        $this->assertSame(self::HEAD, Bundle::whereKey($bundle->id)->value('landing_head_code'));
    }

    // ============================================================ مساعدات

    private function bundleWithCode(): Bundle
    {
        return $this->bundle([$this->product()], [
            'landing_head_code' => self::HEAD,
            'landing_body_end_code' => self::BODY,
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
