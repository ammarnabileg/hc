<?php

namespace Tests\Feature;

use App\Models\User;
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
        $this->post('/register', [
            'name' => 'محمد أحمد',
            'email' => 'm@test.local',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('register.verify'));

        $this->post(route('register.verify.send'));

        $code = decrypt(DB::table('security_otp_codes')
            ->where('email', 'm@test.local')
            ->where('purpose', OtpService::PURPOSE_REGISTER)
            ->value('code'), false);

        $this->post(route('register.verify.confirm'), ['code' => $code])
            ->assertRedirect(route('account.pending'));

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
        ]);

        $this->actingAs($user)->get('/pending')->assertOk()->assertSee('تحت المراجعة');
    }
}
