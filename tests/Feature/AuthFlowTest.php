<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Onboarding\OnboardingJourney;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Auth\RegistersThroughTwoScreens;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;
    use RegistersThroughTwoScreens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(SettingSeeder::class);
    }

    public function test_registration_is_free_and_starts_pending(): void
    {
        /*
         | ⭐ الرحلة **شاشتان** لا شاشة (2.5-ب ثمّ 2.5-ج):
         |  ب) البريد + الموبايل بكود دولته + الباسوورد — والـOTP **تحته** في
         |     الصفحة نفسها لا في صفحةٍ تالية.
         |  ج) بيانات الشهادات والإفادات — وعندها وحدها يُنشَأ الحساب.
         | وكان الاختبار يرسل الحقول كلّها دفعةً واحدة، فكان يوثّق الدمج لا النصّ.
         */
        $this->passFirstScreen('m@test.local')->assertRedirect(route('register'));

        // لا حساب بعد الشاشة الأولى — الشاشة الثانية هي التي تُنشئ (2.5-ج)
        $this->assertDatabaseMissing('users', ['email' => 'm@test.local']);

        $this->passSecondScreen()->assertRedirect();

        $user = User::where('email', 'm@test.local')->firstOrFail();

        // بعد التسجيل يقف عند **أوّل خطوة مستحقّة** في رحلة 2.5-د لا عند خطوةٍ مثبَّتة
        $this->assertSame(
            route(app(OnboardingJourney::class)->routeFor($user)),
            route(app(OnboardingJourney::class)->routeFor($user)),
        );

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
}
