<?php

namespace Tests\Feature\Growth;

use App\Models\Country;
use App\Models\Governorate;
use App\Models\User;
use App\Services\Ads\Consent;
use Illuminate\Http\Request;
use Tests\Feature\Auth\RegistersThroughTwoScreens;

/**
 * ⭐ **موافقة الزائر تنتقل إلى حسابه لحظة التسجيل** (21.3-د).
 *
 * النصّ الحاكم حرفيًّا (21.3-د):
 *  > «**بانر موافقة على التتبّع (Consent)** عند **أوّل زيارة**، بخيارات واضحة:
 *  >  **قبول · رفض · تخصيص**.»
 *  > «**الرفض يوقف البكسل وأحداث الخادم لهذا المستخدم فعليًّا** — لا شكليًّا.»
 *  > «**وحقّ السحب في أيّ وقت** من إعدادات الخصوصيّة والأمان (24.5).»
 *
 * والعلّة المرصودة: البانر يُعرَض **عند أوّل زيارة** أي على الزائر، فقراره لا
 * يعيش إلّا في الكوكي؛ ثمّ يُنشَأ الحساب بـ`tracking_consent = NULL`. فالقرار
 * ينجو على **نفس المتصفّح** وحده ويضيع على جهازٍ آخر — والرفض الذي لا يسافر مع
 * صاحبه ليس رفضًا «فعليًّا»، وكذلك السحب.
 *
 * ⛔ **والاتّجاه لا يُعكَس** — وهو ما يقيسه `silence_is_never_consent`: من لم
 *    يختر يبقى عموده `NULL`، وتقرؤه `allows()` **رفضًا**. لا يُفترَض قبولٌ لمن
 *    لم يوافق (2.9 — «ممنوع Dark Patterns»).
 *
 * والطفرات التي يمسكها هذا الملفّ:
 *  - نزعُ نداء `Consent::adopt()` من `createAccount` ⟵ تسقط الثلاثة الأولى.
 *  - جعلُ غياب الكوكي يكتب `accepted` ⟵ يسقط `silence_is_never_consent`.
 *  - دهسُ اختيارٍ محفوظ على الحساب بكوكي ⟵ يسقط `a_choice_on_the_account_is_never_overwritten`.
 *  - إسقاطُ الأغراض ⟵ يسقط `custom_purposes_travel_with_the_choice`.
 *  - قراءةُ الموافقة من الطلب **المصطنَع** بدل الحقيقيّ (`adopt($user, $request)`)
 *    ⟵ يسقط `the_replay_path_keeps_the_consent_too` وحده — وهو الطريق الذي يخدع
 *    الحدس لأنّ الرحلتين الأخريين تمرّان به سالمتين.
 */
class ConsentTravelsToTheAccountTest extends GrowthTestCase
{
    use RegistersThroughTwoScreens;

    /** ⭐ الرفض يلاحق صاحبه إلى **جهازٍ آخر** — وهو معنى «فعليًّا لا شكليًّا». */
    public function test_a_guest_rejection_reaches_the_new_account(): void
    {
        $user = $this->registerAfterChoosing(Consent::REJECTED);

        $this->assertSame(Consent::REJECTED, $user->tracking_consent,
            'الرفض بقي في الكوكي ولم يصل الحساب — فيضيع على أوّل جهازٍ آخر.');
        $this->assertNotNull($user->tracking_consent_at);

        // جهازٌ آخر = طلبٌ بلا كوكي إطلاقًا
        $this->assertFalse(app(Consent::class)->allowsAds($user, Request::create('/')));
    }

    /** ⭐ والقبول كذلك — وإلّا صار المقبولُ مسؤولًا مرّةً أخرى على كلّ جهاز. */
    public function test_a_guest_acceptance_reaches_the_new_account(): void
    {
        $this->enableTracking();
        $user = $this->registerAfterChoosing(Consent::ACCEPTED);

        $this->assertSame(Consent::ACCEPTED, $user->tracking_consent);
        $this->assertTrue(app(Consent::class)->allowsAds($user, Request::create('/')),
            'قَبِل ضيفًا ثمّ لم يُقاس له شيء على جهازٍ آخر — فالقبول لم يسافر.');
    }

    /** ⭐ و«تخصيص» ينتقل **بأغراضه** — وإلّا صار خيارًا بلا معنى (21.3-د). */
    public function test_custom_purposes_travel_with_the_choice(): void
    {
        $this->enableTracking();
        $user = $this->registerAfterChoosing(Consent::CUSTOM, ['analytics']);

        $this->assertSame(Consent::CUSTOM, $user->tracking_consent);
        $this->assertSame(['analytics'], $user->tracking_scopes);

        $consent = app(Consent::class);
        $bare = Request::create('/');

        $this->assertTrue($consent->allowsAnalytics($user, $bare));
        $this->assertFalse($consent->allowsAds($user, $bare),
            'غرضٌ لم يُختَر صار مسموحًا — و«تخصيص» لا يعطي إلّا ما اختاره صراحةً.');
    }

    /** ⛔ **الصمت ليس موافقة**: من لم يختر يبقى بلا اختيار — والغياب رفضٌ عمليًّا. */
    public function test_silence_is_never_consent(): void
    {
        $this->enableTracking();

        $this->registerThroughBothScreens('silent@test.local');
        $user = User::where('email', 'silent@test.local')->firstOrFail();

        $this->assertNull($user->tracking_consent,
            'كُتِب اختيارٌ لمن لم يختر — وهذا عكسٌ للاتّجاه يمنعه 2.9.');
        $this->assertFalse(app(Consent::class)->allowsAds($user, Request::create('/')));
        $this->assertFalse(app(Consent::class)->allowsAnalytics($user, Request::create('/')));
    }

    /**
     * ⚠️ **ومسار إعادة التشغيل كذلك** — وهو الطريق الذي يخدع الحدس:
     * حارس تأكيد البريد يحجز الفورم الكامل ثمّ يعيد تشغيله بطلبٍ **مُصطنَع**
     * (`Request::create`) **لا كوكي فيه**. فلو قُرِئت الموافقة من وسيط الطلب
     * لضاعت هنا بالضبط — ولذلك تُقرَأ من الطلب الحقيقيّ (`request()`)، تمامًا
     * كما يفعل `AcquisitionSource::attach()` للسبب نفسه.
     */
    public function test_the_replay_path_keeps_the_consent_too(): void
    {
        $email = 'replay@test.local';

        $this->post(route('consent.tracking'), ['choice' => Consent::REJECTED]);
        $this->withCookies(['tracking_consent' => Consent::REJECTED]);

        // فورمٌ كامل قبل التأكيد ⟵ الحارس يحجزه ويحوّل إلى شاشة الرمز
        $this->post('/register', $this->fullForm($email))->assertRedirect(route('register.verify'));

        // والتأكيد يعيد تشغيل التسجيل بطلبٍ مصطنَع بلا كوكي
        $this->post(route('register.verify.send'), ['email' => $email]);
        $this->post(route('register.verify.confirm'), ['email' => $email, 'code' => $this->otpFor($email)]);

        $user = User::where('email', $email)->firstOrFail();

        $this->assertSame(Consent::REJECTED, $user->tracking_consent,
            'الموافقة ضاعت في مسار إعادة التشغيل — فالقراءة تمّت من الطلب المصطنَع لا الحقيقيّ.');
    }

    /** ⛔ اختيارٌ محفوظ على الحساب **لا يُدهَس** بكوكي متصفّحٍ عابر (حقّ السحب). */
    public function test_a_choice_on_the_account_is_never_overwritten(): void
    {
        $withdrawn = $this->trainee(['tracking_consent' => Consent::REJECTED, 'tracking_scopes' => []]);

        $adopted = app(Consent::class)->adopt(
            $withdrawn,
            Request::create('/', 'GET', [], ['tracking_consent' => Consent::ACCEPTED]),
        );

        $this->assertFalse($adopted);
        $this->assertSame(Consent::REJECTED, $withdrawn->fresh()->tracking_consent,
            'كوكي قديمة أبطلت سحبًا وقع فعلًا — و21.3-د يجعل السحب حقًّا في أيّ وقت.');
    }

    // ------------------------------------------------------------------ أدوات

    /**
     * حمولة الفورم **كاملةً في طلبٍ واحد** — مسار حارس تأكيد البريد.
     *
     * @return array<string, mixed>
     */
    private function fullForm(string $email): array
    {
        $country = Country::query()->where('is_active', true)->first();

        return [
            'title' => $this->firstTitle(),
            'name_ar' => 'محمد أحمد علي',
            'name_en' => 'Mohamed Ahmed Ali',
            'gender' => 'male',
            'country_id' => $country?->id,
            'governorate_id' => $country
                ? Governorate::query()->where('country_id', $country->id)->value('id')
                : null,
            'address_line' => 'شارع التحرير',
            'email' => $email,
            'phone' => '10'.random_int(10000000, 99999999),
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ];
    }

    private function enableTracking(): void
    {
        $this->setSetting('ads.tracking.enabled', '1', 'bool');
    }

    /**
     * الرحلة الحقيقيّة: الزائر يختار من البانر (`POST /consent/tracking`) ثمّ
     * يسجّل بالشاشتين — بطلبات HTTP كاملة لا بكتابةٍ مباشرة على الصفّ.
     *
     * @param  array<int, string>  $scopes
     */
    private function registerAfterChoosing(string $choice, array $scopes = []): User
    {
        $email = $choice.'@test.local';

        $this->post(route('consent.tracking'), ['choice' => $choice, 'scopes' => $scopes]);

        // الكوكي الراجعة من البانر تُعاد في الطلبات التالية كما يفعل المتصفّح
        // (`withCookies` تُعمّيها بنفس مُعمِّي التطبيق، فيقرؤها `EncryptCookies`)
        $this->withCookies(array_filter([
            'tracking_consent' => $choice,
            'tracking_scopes' => $scopes === [] ? null : json_encode($scopes),
        ]));

        $this->registerThroughBothScreens($email);

        return User::where('email', $email)->firstOrFail();
    }
}
