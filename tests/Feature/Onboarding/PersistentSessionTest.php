<?php

namespace Tests\Feature\Onboarding;

use App\Models\Setting;
use App\Models\UserDevice;
use App\Services\Onboarding\SessionLifetime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 2.3 — «الجلسة تُحفَظ **مدى الحياة** ولا تنتهي تلقائيًّا؛ تفضل مفتوحة لحدّ ما
 * المستخدم يعمل **تسجيل خروج** بنفسه»، ومعها زرّ **«تسجيل الخروج من كلّ الأجهزة»**.
 *
 * لماذا يهمّ إلى هذا الحدّ؟ لأنّ طردًا كلّ ساعتين يضرب **الستريكس ونادي الخامسة
 * صباحًا** مباشرةً: مَن يفتح المنصّة فجرًا فيجد نفسه مطرودًا لا يسجّل حضوره.
 */
class PersistentSessionTest extends OnboardingTestCase
{
    public function test_login_issues_a_remember_cookie_without_ticking_anything(): void
    {
        $user = $this->member(['email' => 'stay@test.local']);

        // ⭐ العطل الذي أُصلِح: الدخول بلا «فكّرني» كان لا ينتج كوكي `remember_web`
        $response = $this->post('/login', [
            'identifier' => 'stay@test.local',
            'password' => 'secret-password',
        ]);

        $this->assertAuthenticatedAs($user);

        $names = array_map(fn ($cookie) => $cookie->getName(), $response->headers->getCookies());
        $remember = array_values(array_filter($names, fn ($name) => str_starts_with($name, 'remember_')));

        $this->assertNotEmpty($remember, 'الدخول لازم يصدر Remember-me token — وإلّا الجلسة بتنتهي لوحدها.');
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_session_lifetime_comes_from_settings_not_from_a_hardcoded_number(): void
    {
        // المدّة إعدادٌ في اللوحة (2.13) — ولوحةٌ تغيّرها يجب أن يتغيّر بها السلوك
        $this->assertSame(
            (int) setting('auth.session.lifetime_minutes'),
            (int) config('session.lifetime'),
        );

        // ولا تنتهي بإغلاق المتصفّح كذلك
        $this->assertFalse((bool) config('session.expire_on_close'));

        // ولوحةٌ تغيّر الرقم يجب أن يتغيّر بها السلوك — لا أن تبقى الجلسة على حالها
        Setting::where('key', 'auth.session.lifetime_minutes')->update(['value' => '99999']);
        Cache::forget('settings');

        // نفس ما يفعله المزوّد في كلّ إقلاع، قبل بدء الجلسة
        $this->assertSame(99999, SessionLifetime::apply());
        $this->assertSame(99999, (int) config('session.lifetime'));
    }

    public function test_a_session_older_than_two_hours_still_works(): void
    {
        $user = $this->member();

        $this->actingAs($user);
        $this->travel(3)->hours();

        // ساعتان كانتا نهاية الرحلة قبل الإصلاح — والآن الحساب ما زال داخلًا
        $this->get(route('settings.index'))->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_logging_out_is_still_an_explicit_act(): void
    {
        $user = $this->member();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_log_out_of_all_devices_ends_every_session(): void
    {
        $user = $this->member();

        UserDevice::create(['user_id' => $user->id, 'session_id' => 'sess-phone', 'device_label' => 'موبايل']);
        UserDevice::create(['user_id' => $user->id, 'session_id' => 'sess-laptop', 'device_label' => 'لابتوب']);

        foreach (['sess-phone', 'sess-laptop'] as $id) {
            DB::table('sessions')->insert([
                'id' => $id, 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
                'user_agent' => 'test', 'payload' => '', 'last_activity' => time(),
            ]);
        }

        $before = $user->fresh()->remember_token;

        // الزرّ ظاهر في تاب الأمان داخل صفحة الإعدادات لا في صفحة منفصلة (2.3 · 2.15-ب)
        $this->actingAs($user)->get(route('settings.index'))
            ->assertOk()
            ->assertSee(setting('auth.logout.all_devices_label', 'تسجيل الخروج من كلّ الأجهزة'))
            ->assertSee('الجلسات النشطة')
            ->assertSee(setting('account.delete.title', 'حذف الحساب'));

        $this->actingAs($user)->delete(route('settings.devices.destroy-all'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, UserDevice::where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());

        // ⭐ وبلا تبديل الرمز الدائم كان كلّ جهاز يعود بكوكيّه بعد لحظة
        $this->assertNotSame($before, $user->fresh()->remember_token);
    }
}
