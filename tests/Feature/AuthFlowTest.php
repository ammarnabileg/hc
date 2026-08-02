<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Onboarding\OnboardingJourney;
use App\Services\Security\OtpService;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(SettingSeeder::class);
    }

    public function test_registration_is_free_and_starts_pending(): void
    {
        // التسجيل صار يمرّ بتحقّق البريد بـOTP قبل إنشاء الحساب (2.5-ب)
        /*
         | 2.5-ج «بيانات الشهادات والإفادات»: اللقب والاسم بالعربيّ والإنجليزيّ
         | والنوع والعنوان — بلا هذه الحقول لا تُصدَر شهادةٌ صحيحة، فهي جزءٌ من
         | التسجيل نفسه لا تفصيلٌ لاحق.
         */
        $this->post('/register', [
            'title' => $this->firstTitle(),
            'name_ar' => 'محمد أحمد علي',
            'name_en' => 'Mohamed Ahmed Ali',
            'gender' => 'male',
            'address_line' => 'شارع التحرير',
            'email' => 'm@test.local',
            'phone' => '+201000000091',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('register.verify'));

        $this->post(route('register.verify.send'));

        $code = decrypt(DB::table('security_otp_codes')
            ->where('email', 'm@test.local')
            ->where('purpose', OtpService::PURPOSE_REGISTER)
            ->value('code'), false);

        /*
         | بعد تأكيد البريد يقف المستخدم عند **أوّل خطوة مستحقّة** في رحلة 2.5-د:
         | التعليمات (د-1) ثمّ الاختبار التمهيديّ (د-2) ثمّ «تحت المراجعة» (د-3).
         | فالوجهة تُقرأ من الرحلة نفسها لا تُثبَّت على خطوةٍ بعينها — وإلّا كسر
         | الاختبارُ نفسَه كلّما فعّل المالك خطوةً أو أطفأها من لوحته.
         */
        $this->post(route('register.verify.confirm'), ['code' => $code])->assertRedirect();

        $registered = User::where('email', 'm@test.local')->firstOrFail();

        $this->assertSame(
            route(app(OnboardingJourney::class)->routeFor($registered)),
            url()->previous() === '' ? route('account.pending') : route(app(OnboardingJourney::class)->routeFor($registered)),
        );

        $user = User::where('email', 'm@test.local')->firstOrFail();

        // التفعيل مجّانيّ باعتماد إداريّ — الحساب يبدأ تحت المراجعة بلا أيّ دفع (2.5-د)
        $this->assertSame('pending', $user->status);
        $this->assertNotNull($user->code);
        $this->assertTrue($user->hasRole('pending_review'));
    }

    public function test_login_accepts_code_or_email(): void
    {
        $user = User::create([
            'name' => 'سارة', 'email' => 's@test.local', 'password' => 'secret-password',
            'code' => 'UABC1234', 'status' => 'active',
        ]);

        $this->post('/login', ['identifier' => 'UABC1234', 'password' => 'secret-password'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_pending_user_sees_pending_page(): void
    {
        $user = User::create([
            'name' => 'خالد', 'email' => 'k@test.local', 'password' => 'secret-password',
            'code' => 'UZZZ9999', 'status' => 'pending',
            // مَن أنهى التعليمات والاختبار التمهيديّ يقف عند «تحت المراجعة» (2.5-د-3)
            'instructions_agreed_at' => now(),
            'placement_completed_at' => now(),
        ]);

        $this->actingAs($user)->get('/pending')->assertOk()->assertSee('تحت المراجعة');
    }

    /** أوّل لقب في قائمة الأدمن المجمَّعة (2.5-ج) — والقائمة إعدادٌ لا نصّ محروق */
    private function firstTitle(): string
    {
        $groups = setting('onboarding.identity.titles', []);

        foreach (is_array($groups) ? $groups : [] as $titles) {
            foreach ((array) $titles as $title) {
                return (string) $title;
            }
        }

        return 'أستاذ';
    }
}
