<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\Security\OtpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * منطقة الخطر: حذف الحساب بتأكيد OTP رباعيّ + Soft-delete (2.3).
 *
 * الفجوة المُصلَحة: `SettingsController` كان فيه `destroyEmergency` و`endSession` فقط.
 */
class DangerZoneTest extends SecurityTestCase
{
    /** القسم ظاهر داخل «الخصوصيّة والأمان» — ومعه شرحٌ لما يحدث للبيانات */
    public function test_danger_zone_appears_in_privacy_and_explains_the_data(): void
    {
        $user = $this->trainee();

        $response = $this->actingAs($user)->get(route('settings.privacy'))->assertOk();

        $response->assertSee(setting('account.delete.title'), false);

        foreach ((array) setting('account.delete.data_notes') as $note) {
            $response->assertSee($note, false);
        }
    }

    /** ⛔ بلا رمز صحيح لا حذف — الحساب يفضل شغّالًا */
    public function test_deletion_without_a_valid_code_is_refused(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->delete(route('settings.danger.destroy'), ['code' => '0000'])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->refresh()->deleted_at);
    }

    /** الدورة كاملة: رمز ⟵ حذف Soft ⟵ خروج — والحساب مايبقاش يقدر يدخل */
    public function test_deletion_with_the_otp_soft_deletes_and_logs_out(): void
    {
        Mail::fake();

        $user = $this->trainee();

        $this->actingAs($user)->post(route('settings.danger.code'))->assertRedirect();

        $code = decrypt(DB::table('security_otp_codes')
            ->where('email', $user->email)
            ->where('purpose', OtpService::PURPOSE_DELETE)
            ->value('code'), false);

        $this->actingAs($user)
            ->delete(route('settings.danger.destroy'), ['code' => $code])
            ->assertRedirect(route('home'));

        $this->assertGuest();

        // Soft-delete لا محو: السجلّ موجود بعمود deleted_at وقابل للاسترجاع
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertNotNull(User::withTrashed()->find($user->id)->deletion_requested_at);
        $this->assertDatabaseMissing('user_devices', ['user_id' => $user->id]);
    }

    private function trainee(): User
    {
        $user = $this->makeUser('هدى ماهر');
        $this->grant($user, ['user_profile.edit', 'privacy_settings.view', 'privacy_settings.edit']);

        return $user;
    }
}
