<?php

namespace Tests\Feature\Growth;

use App\Services\Ads\Consent;

/**
 * ⭐ **صفحة سياسة الخصوصيّة العامّة** (21.3-د):
 *
 *  > «**صفحة سياسة خصوصيّة** توضّح ما يُجمَع ولمن يُرسَل، **وحقّ السحب في أيّ
 *  >  وقت** من إعدادات الخصوصيّة والأمان (24.5).»
 *
 * وبانر الموافقة (21.3-د) يظهر **للزائر قبل أيّ حساب** (`layouts.app` بلا
 * `@auth` حول `partials.consent-banner`)، فرابط الشرح بجوار البانر لازم يفتح
 * **بلا تسجيل دخول** — وكان يشير قبلًا إلى `settings.privacy` المحجوبة خلف
 * `Route::middleware('auth')` في `routes/parts/account.php`.
 *
 * والطفرات التي يمسكها هذا الملفّ:
 *  - إزالة المسار العامّ `privacy.policy` (أو حجبه خلف `auth`) ⟵ الاختبار
 *    الأوّل يفشل بـ404/302 بدل 200.
 *  - إرجاع رابط البانر إلى `settings.privacy` ⟵ الاختبار الثاني يفشل لأنّ
 *    الصفحة تحوي رابط `/settings/privacy` بدل `/privacy-policy`.
 *  - عدم كتابة محتوًى حقيقيّ (ما يُجمَع/لمن يُرسَل) في الصفحة ⟵ `assertSee`
 *    يفشل لغياب النصّ.
 */
class PublicPrivacyPolicyPageTest extends GrowthTestCase
{
    private function enableTracking(): void
    {
        $this->setSetting('ads.tracking.enabled', '1', 'bool');
    }

    /** ⭐ زائرٌ بلا تسجيل دخول يقدر يفتح صفحة سياسة الخصوصيّة ويقرأ محتواها الحقيقيّ */
    public function test_a_guest_can_view_the_public_privacy_policy_page(): void
    {
        $this->get(route('privacy.policy'))
            ->assertOk()
            // ما يُجمَع
            ->assertSee('إيه اللي بنجمعه')
            // لمن يُرسَل
            ->assertSee('لمين بيتبعت')
            // حقّ السحب في أيّ وقت
            ->assertSee('حقّك في السحب في أيّ وقت');
    }

    /** والزائر — بلا حساب — يقرأ شرحًا لمكان اختياره لا رابطًا لشاشةٍ محجوبة عنه */
    public function test_the_guest_view_explains_where_their_choice_lives_instead_of_a_gated_link(): void
    {
        $this->get(route('privacy.policy'))
            ->assertOk()
            ->assertSee('اختيارك بيتسجّل على المتصفّح ده')
            ->assertDontSee(route('settings.privacy', [], false), false);
    }

    /** ⭐ رابط بانر الموافقة يشير للصفحة العامّة الجديدة لا لشاشة الإعدادات المحجوبة */
    public function test_the_consent_banner_links_to_the_public_policy_page_not_the_gated_settings_screen(): void
    {
        $this->enableTracking();

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(route('privacy.policy'), false);
        $response->assertDontSee(route('settings.privacy', [], false), false);
    }

    /** والبانر يظهر فعلًا لزائرٍ بلا تسجيل دخول — لا لمستخدمين فقط */
    public function test_the_consent_banner_itself_appears_for_a_guest(): void
    {
        $this->enableTracking();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('أرفض');
    }

    /** ⭐ مستخدم مسجَّل دخوله يشوف بدل الشرح — رابطًا مباشرًا لإعدادات الخصوصيّة */
    public function test_a_logged_in_user_sees_a_direct_link_to_their_privacy_settings(): void
    {
        $user = $this->trainee(['tracking_consent' => Consent::ACCEPTED]);

        $this->actingAs($user)->get(route('privacy.policy'))
            ->assertOk()
            ->assertSee(route('settings.privacy'), false);
    }
}
