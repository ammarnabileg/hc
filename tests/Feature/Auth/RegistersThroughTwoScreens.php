<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\AuthController;
use App\Models\Country;
use App\Models\Governorate;
use App\Services\Security\OtpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * رحلة التسجيل **بشاشتيها المنصوصتين** (2.5-ب ثمّ 2.5-ج) في سطرٍ واحد للاختبارات.
 *
 * لماذا مشترَكة؟ لأنّ عشرة اختبارات في خمسة مجالات (الدخول · الرحلة · الأمان ·
 * الاكتساب · الريفيرال) تحتاج «مستخدمًا سجّل» لا أن تختبر التسجيل نفسه. فلو
 * كتبت كلّ واحدةٍ خطواتها بيدها لتجمّدت على شكل الفورم، وأيّ تعديلٍ مشروع في
 * ترتيب الشاشتين كسر عشرة اختبارات بلا أن يكسر ميزةً واحدة.
 *
 * والخطوات هنا **هي الخطوات الحقيقيّة** بطلبات HTTP كاملة — لا اختصار يلتفّ
 * على حارس الـOTP ولا إنشاء مستخدمٍ من وراء المتحكّم.
 */
trait RegistersThroughTwoScreens
{
    /**
     * الشاشة الأولى (2.5-ب): بريد ⟵ إرسال ⟵ تأكيد ⟵ استكمال.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function passFirstScreen(string $email, array $overrides = []): TestResponse
    {
        $payload = array_merge(array_filter([
            'step' => AuthController::STEP_ACCOUNT,
            'email' => $email,
            // قائمة الأعلام قد تكون فارغة في اختبارٍ لا يزرع الدول — والحقل وقتها
            // اختياريّ في المتحكّم نفسه، فلا نرسل مفتاحًا فارغًا يوهم بأنّه أُرسِل
            'phone_iso2' => $this->anyIso2(),
            'phone_national' => '10'.random_int(10000000, 99999999),
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]), $overrides);

        $this->post(route('register.verify.send'), ['email' => $email]);
        $this->post(route('register.verify.confirm'), ['email' => $email, 'code' => $this->otpFor($email)]);

        return $this->post('/register', $payload);
    }

    /**
     * الشاشة الثانية (2.5-ج): بيانات الشهادات والإفادات — وعندها يُنشَأ الحساب.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function passSecondScreen(array $overrides = []): TestResponse
    {
        $country = Country::query()->where('is_active', true)->first();
        $governorate = $country
            ? Governorate::query()->where('country_id', $country->id)->first()
            : null;

        return $this->post('/register', array_merge([
            'step' => AuthController::STEP_IDENTITY,
            'title' => $this->firstTitle(),
            'name_ar' => 'محمد أحمد علي',
            'name_en' => 'Mohamed Ahmed Ali',
            'gender' => 'male',
            'country_id' => $country?->id,
            'governorate_id' => $governorate?->id,
            'address_line' => 'شارع التحرير',
        ], $overrides));
    }

    /**
     * الرحلة كاملةً — لمن يحتاج «مستخدمًا سجّل» لا أن يختبر التسجيل.
     *
     * @param  array<string, mixed>  $account
     * @param  array<string, mixed>  $identity
     */
    protected function registerThroughBothScreens(string $email, array $account = [], array $identity = []): TestResponse
    {
        $this->passFirstScreen($email, $account);

        return $this->passSecondScreen($identity);
    }

    /** الرمز كما هو في القاعدة — والرمز الدائم لا يتغيّر لنفس البريد (2.5-ب) */
    protected function otpFor(string $email): string
    {
        return decrypt(DB::table('security_otp_codes')
            ->where('email', mb_strtolower(trim($email)))
            ->where('purpose', OtpService::PURPOSE_REGISTER)
            ->value('code'), false);
    }

    /** أوّل لقبٍ في قائمة الأدمن المجمَّعة — والقائمة إعداد لا ثابت (2.13) */
    protected function firstTitle(): string
    {
        $groups = (array) setting('onboarding.identity.titles', []);

        foreach ($groups as $titles) {
            foreach ((array) $titles as $title) {
                return (string) $title;
            }
        }

        return 'السيد';
    }

    /** كود دولةٍ ظاهرة له مفتاح هاتف — قائمة الأعلام تُبنى منها (2.5-ب) */
    protected function anyIso2(): string
    {
        return (string) Country::query()->where('is_active', true)
            ->whereNotNull('phone_code')->where('phone_code', '!=', '')
            ->value('iso2');
    }
}
