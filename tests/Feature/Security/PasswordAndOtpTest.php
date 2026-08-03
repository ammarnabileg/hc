<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\Onboarding\OnboardingJourney;
use App\Services\Security\OtpService;
use App\Services\Security\RequireVerifiedEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * استرجاع كلمة السرّ (2.3) وتحقّق البريد بـOTP (2.5-ب).
 *
 * الفجوة المُصلَحة: `route:list` كان بلا أيّ `password.*`، والتسجيل بسيط بلا OTP.
 */
class PasswordAndOtpTest extends SecurityTestCase
{
    // ------------------------------------------------------- استرجاع كلمة السرّ

    public function test_forgot_password_screen_opens_and_is_linked_from_login(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee(setting('auth.password_reset.request_title'), false);
    }

    /** ⭐ الردّ محايد دائمًا — الشاشة مش أداة تعداد حسابات */
    public function test_request_never_reveals_whether_the_email_exists(): void
    {
        Mail::fake();

        $known = $this->makeUser('صاحب حساب');

        $first = $this->post(route('password.email'), ['email' => $known->email]);
        $second = $this->post(route('password.email'), ['email' => 'nobody@test.local']);

        $this->assertSame(
            $first->getSession()->get('status'),
            $second->getSession()->get('status'),
        );
    }

    /** الدورة كاملة: طلب ⟵ رابط ⟵ إعادة تعيين — والدخول بالجديدة يشتغل */
    public function test_full_reset_cycle_by_link_changes_the_password(): void
    {
        Mail::fake();

        $user = $this->makeUser('نهى فتحي');

        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect();

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);

        // نلتقط التوكن الخام من الرابط المرسَل تمامًا كما يفعل المستخدم
        $token = $this->capturedToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();

        $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-secret-password', $user->refresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    /** المسار الموازي: رمز رباعيّ بدل الرابط — نفس الطلب ونفس الشاشة */
    public function test_reset_by_four_digit_code_works_too(): void
    {
        Mail::fake();

        $user = $this->makeUser('كريم منير');
        $this->post(route('password.email'), ['email' => $user->email]);

        $code = $this->plainCode($user->email, OtpService::PURPOSE_PASSWORD);

        $this->post(route('password.code'), ['email' => $user->email, 'code' => $code])
            ->assertRedirect();

        $this->followingRedirects()
            ->post(route('password.code'), ['email' => $user->email, 'code' => $code])
            ->assertOk();
    }

    /** رابط منتهي لا يفتح شاشة التعيين — ورسالة تقول ماذا يفعل (2.17-ب) */
    public function test_expired_token_is_refused_with_a_helpful_message(): void
    {
        Mail::fake();

        $user = $this->makeUser('مروة');
        $this->post(route('password.email'), ['email' => $user->email]);

        DB::table('password_reset_tokens')->where('email', $user->email)->update([
            'created_at' => now()->subMinutes(setting('auth.password_reset.ttl_minutes', 60) + 10),
        ]);

        $this->get(route('password.reset', ['token' => $this->capturedToken($user), 'email' => $user->email]))
            ->assertRedirect(route('password.request'));
    }

    // -------------------------------------------------------- تحقّق البريد (OTP)

    /** ⭐ لا حساب يُنشَأ قبل تأكيد البريد — الطلب يتحوّل لشاشة الرمز */
    public function test_registration_requires_email_otp_first(): void
    {
        Mail::fake();

        $this->post('/register', $this->registrationPayload('ياسمين طارق سعيد', 'yasmin@test.local'))->assertRedirect(route('register.verify'));

        $this->assertDatabaseMissing('users', ['email' => 'yasmin@test.local']);

        $this->get(route('register.verify'))->assertOk()->assertSee('yasmin@test.local', false);
    }

    /** بعد الرمز الصحيح يكتمل التسجيل بنفس المدخلات بلا إعادة كتابة */
    public function test_correct_otp_completes_the_registration(): void
    {
        Mail::fake();

        $this->post('/register', $this->registrationPayload('ياسمين طارق سعيد', 'yasmin@test.local'));

        $this->post(route('register.verify.send'))->assertRedirect();

        $code = $this->plainCode('yasmin@test.local', OtpService::PURPOSE_REGISTER);

        $response = $this->post(route('register.verify.confirm'), ['code' => $code])
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'yasmin@test.local')->firstOrFail();

        /*
         | الوجهة تُقرأ من رحلة 2.5-د نفسها لا تُثبَّت على خطوةٍ بعينها: تعليمات
         | (د-1) ثمّ اختبار تمهيديّ (د-2) ثمّ «تحت المراجعة» (د-3) — وإلّا كسر
         | الاختبارُ نفسَه كلّما فعّل المالك خطوةً أو أطفأها من لوحته (2.13).
         */
        $response->assertRedirect(route(app(OnboardingJourney::class)->routeFor($user)));

        $this->assertSame('pending', $user->status);

        /*
         | ⭐ وعلامةُ «هذا البريد مؤكَّد» **تُمحى** بعد إنشاء الحساب.
         |
         | كانت تبقى في الجلسة، وهي رخصةُ مرورٍ لبريدٍ بعينه: تبقى بعد اكتمال
         | مهمّتها فيمرّ بها من يعود لصفحة التسجيل في نفس الجلسة. والصحيح أنّ
         | الرخصة تُستهلَك مرّةً وتنتهي — ومعها حمولةُ الشاشة الأولى وفيها كلمة
         | السرّ، فلا تعيش في السيشن بعد أن صار لها حسابٌ حقيقيّ.
         */
        $this->assertNull(session(RequireVerifiedEmail::SESSION_VERIFIED));
        $this->assertNull(session(RequireVerifiedEmail::SESSION_PENDING));
    }

    /** رمز غلط لا يُنشئ حسابًا ويقول ماذا حدث وماذا يفعل */
    public function test_wrong_otp_refuses_and_keeps_the_form_data(): void
    {
        Mail::fake();

        $this->post('/register', $this->registrationPayload('حسن محمود إبراهيم', 'hassan@test.local'));

        $this->post(route('register.verify.send'));

        $this->post(route('register.verify.confirm'), ['code' => '0000'])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseMissing('users', ['email' => 'hassan@test.local']);
        // المدخلات محفوظة فالمستخدم مايعيدش كتابتها (2.17-ب)
        $this->assertNotNull(session(RequireVerifiedEmail::SESSION_PENDING));
    }

    /** ⭐ قاعدة 2.5-ب: الرمز **لا يتغيّر أبدًا** لنفس البريد مهما تكرّر الطلب */
    public function test_the_register_otp_never_changes_for_the_same_email(): void
    {
        Mail::fake();

        $otp = app(OtpService::class);

        $otp->send('same@test.local', OtpService::PURPOSE_REGISTER);
        $first = $this->plainCode('same@test.local', OtpService::PURPOSE_REGISTER);

        // نُنهي مهلة إعادة الإرسال ثمّ نطلب تاني
        DB::table('security_otp_codes')->where('email', 'same@test.local')
            ->update(['sent_at' => now()->subHour()]);

        $otp->send('same@test.local', OtpService::PURPOSE_REGISTER);

        $this->assertSame($first, $this->plainCode('same@test.local', OtpService::PURPOSE_REGISTER));
    }

    /** إعادة الإرسال ممنوعة قبل مهلة الإعدادات — والعدّاد يعرف كم بقي */
    public function test_resend_is_throttled_by_the_configured_window(): void
    {
        Mail::fake();

        $otp = app(OtpService::class);

        $this->assertTrue($otp->send('wait@test.local', OtpService::PURPOSE_REGISTER)['sent']);

        $second = $otp->send('wait@test.local', OtpService::PURPOSE_REGISTER);

        $this->assertFalse($second['sent']);
        $this->assertGreaterThan(0, $second['wait']);
        $this->assertGreaterThan(0, $otp->secondsUntilResend('wait@test.local', OtpService::PURPOSE_REGISTER));
    }

    /** بريد مستعمَل بالفعل يعدّي للمتحكّم ليقول رسالته — ولا نبعت رمزًا لبريد مرفوض */
    public function test_taken_email_skips_the_otp_step_and_shows_the_real_error(): void
    {
        $existing = $this->makeUser('حساب قائم');

        $this->post('/register', [
            'name' => 'مكرّر',
            'email' => $existing->email,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('security_otp_codes', ['email' => $existing->email]);
    }

    // ------------------------------------------------------------------ مساعدات

    private function plainCode(string $email, string $purpose): string
    {
        $row = DB::table('security_otp_codes')->where('email', $email)->where('purpose', $purpose)->firstOrFail();

        return decrypt($row->code, false);
    }

    private function capturedToken(User $user): string
    {
        // التوكن الخام لا يُخزَّن (مُجزَّأ) — فنولّد واحدًا ونثبّته كما يفعل البريد
        $token = 'token-'.$user->id.'-'.uniqid();

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => DB::table('password_reset_tokens')->where('email', $user->email)->value('created_at') ?? now()],
        );

        return $token;
    }

    /**
     * حمولة تسجيلٍ كاملة ببيانات **2.5-ج** (اللقب · الاسم بالعربيّ والإنجليزيّ ·
     * النوع · العنوان) — وبدونها لا تُصدَر شهادةٌ صحيحة، فهي جزءٌ من التسجيل
     * نفسه لا تفصيلٌ لاحق، والتحقّق يرفض ما دونها.
     *
     * والاسمان **ثلاثيّان** كما نصّ البند حرفيًّا — واسمٌ ثنائيّ هنا كان يمرّ من
     * الحارس ثمّ يسقط في الإعادة، فيبدو العطل في الإعادة وهو في الحمولة.
     *
     * @return array<string, string>
     */
    private function registrationPayload(string $nameAr, string $email): array
    {
        $groups = setting('onboarding.identity.titles', []);
        $title = 'أستاذ';

        foreach (is_array($groups) ? $groups : [] as $titles) {
            foreach ((array) $titles as $one) {
                $title = (string) $one;
                break 2;
            }
        }

        return [
            'title' => $title,
            'name_ar' => $nameAr,
            'name_en' => 'Test User Name',
            'gender' => 'female',
            'address_line' => 'شارع التحرير',
            'email' => $email,
            'phone' => '+2010'.random_int(10000000, 99999999),
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ];
    }
}
