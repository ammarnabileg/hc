<?php

namespace Tests\Feature\Security;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * شاشة إعادة تعيين كلمة السرّ (2.3 · 12.1-الأمان) — نصّ الدستور حرفيًّا:
 * صفحة تعرض **إيميله** · **حقلان** · **بدون شروط غير أن تكون أكثر من 6 خانات** ·
 * زرّ تأكيد **معطّل حتى تتطابق الكلمتان** · **أيقونة العين**.
 *
 * كان الرابط والنسخ يعملان، لكنّ الشاشة بلا إيميل ولا عين ولا تعطيل،
 * والحدّ الأدنى 8 بلا سندٍ من النصّ.
 */
class PasswordResetScreenTest extends SecurityTestCase
{
    /** الشاشة تعرض الإيميل وأيقونة العين وزرًّا معطّلًا حتى التطابق */
    public function test_the_screen_shows_the_email_an_eye_icon_and_a_disabled_submit(): void
    {
        $user = $this->makeUser('صاحب الرابط');
        $token = $this->issueToken($user->email);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            // ١) الإيميل ظاهر للتذكير
            ->assertSee($user->email, false)
            ->assertSee(setting('auth.password_reset.email_label'), false)
            // ٢) أيقونة العين — زرّ مرسوم بـSVG لا مكتبة
            ->assertSee('data-password-eye', false)
            ->assertSee('<svg', false)
            ->assertSee(setting('auth.password_reset.show_label'), false)
            // ٣) الزرّ معطّل حتى يتطابق الحقلان
            ->assertSee('data-password-submit disabled', false)
            // ٤) الحقلان الاثنان
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false);
    }

    /** ⭐ الحدّ الأدنى **أكثر من 6 خانات** ومن الإعدادات لا محروقًا (2.13) */
    public function test_the_minimum_is_more_than_six_and_comes_from_settings(): void
    {
        $this->assertSame(7, (int) setting('auth.password.min_length'));

        $user = $this->makeUser('بيغيّر كلمته');

        // ٦ خانات ⟵ ترفض (ليست «أكثر من 6»)
        $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => $this->issueToken($user->email),
            'password' => 'abc123',
            'password_confirmation' => 'abc123',
        ])->assertSessionHasErrors('password');

        // ٧ خانات بلا أيّ تعقيد (بلا رموز ولا أحرف كبيرة) ⟵ تُقبَل
        $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => $this->issueToken($user->email),
            'password' => 'abcdefg',
            'password_confirmation' => 'abcdefg',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('abcdefg', $user->refresh()->password));
    }

    /** الحدّ يتبع الإعداد فعلًا: غيّره المالك ⟵ التحقّق يتغيّر معه */
    public function test_changing_the_setting_changes_the_rule(): void
    {
        Setting::query()->where('key', 'auth.password.min_length')->update(['value' => '10']);
        Cache::forget('settings');

        $user = $this->makeUser('حدّ أطول');

        $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => $this->issueToken($user->email),
            'password' => 'abcdefg',
            'password_confirmation' => 'abcdefg',
        ])->assertSessionHasErrors('password');
    }

    /** الخادم هو الحكم في التطابق — التعطيل راحةٌ لا حارس */
    public function test_the_server_still_rejects_a_mismatch(): void
    {
        $user = $this->makeUser('كلمتان مختلفتان');

        $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => $this->issueToken($user->email),
            'password' => 'abcdefgh',
            'password_confirmation' => 'abcdefgz',
        ])->assertSessionHasErrors('password');
    }

    // ------------------------------------------------------------------ أدوات

    private function issueToken(string $email): string
    {
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($token), 'created_at' => now()],
        );

        return $token;
    }
}
