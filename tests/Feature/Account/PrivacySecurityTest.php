<?php

namespace Tests\Feature\Account;

use App\Models\ConsentRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserPrivacySetting;
use App\Services\Account\PrivacyFields;
use Illuminate\Support\Str;

/**
 * حسابي ← الخصوصيّة والأمان (الدستور 13.4-م · 12.14-د · 24.5).
 */
class PrivacySecurityTest extends AccountTestCase
{
    /** متطوّعٌ بدورٍ واحدٍ من طبقة التطوّع فقط — بلا `trainee` معه أصلًا. */
    private function volunteerOnly(string $roleKey): User
    {
        $user = User::create([
            'name' => 'متطوّع تجريبيّ',
            'email' => Str::lower(Str::random(8)).'@test.local',
            'phone' => '+2010'.random_int(10000000, 99999999),
            'password' => 'secret-password',
            'code' => 'U'.Str::upper(Str::random(7)),
            'status' => 'active',
        ]);

        $user->assignRole(Role::where('key', $roleKey)->firstOrFail());

        return $user->fresh();
    }

    /**
     * ⭐ صاحب البروفايل لا يُحجَب عن شاشات إعداداته وخصوصيّته حتّى لو حمل
     * دور تطوّعٍ فقط بلا `trainee` معه — 13.4-م يبني كلّ آليّة الموافقة على
     * أنّ صاحب الحقل يضبط خصوصيّته بنفسه، فلا حارسَ يقفل عليه بابه.
     */
    public function test_a_coordinator_only_volunteer_can_reach_their_own_settings_and_privacy(): void
    {
        $user = $this->volunteerOnly('coordinator');

        $this->actingAs($user)->get(route('settings.index'))->assertOk();

        $this->actingAs($user)->get(route('settings.privacy'))->assertOk();

        $this->actingAs($user)
            ->patchJson(route('settings.privacy.field'), ['field' => 'phone', 'visibility' => 'all_volunteers'])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $this->assertSame('all_volunteers', UserPrivacySetting::where('user_id', $user->id)
            ->where('field', 'phone')->value('visibility'));

        $this->actingAs($user)
            ->patchJson(route('settings.field'), ['field' => 'name', 'value' => 'اسمٌ جديد'])
            ->assertOk();

        $this->assertSame('اسمٌ جديد', $user->fresh()->name);
    }

    /** ولا فرق بين الكوردنيتور وبقيّة السبعة أدوار — الفتح شاملٌ للطبقة كلّها. */
    public function test_other_volunteer_only_roles_also_reach_their_own_settings(): void
    {
        foreach (['team_leader', 'supervisor', 'director', 'track_supervisor', 'volunteer_gm', 'recruiter', 'academy_manager'] as $roleKey) {
            $user = $this->volunteerOnly($roleKey);

            $this->actingAs($user)->get(route('settings.privacy'))
                ->assertOk("الدور {$roleKey} لا يزال محجوبًا عن /settings/privacy.");
        }
    }

    public function test_governorate_can_never_be_hidden(): void
    {
        $user = $this->trainee();

        // ⭐ المحافظة حقل عامّ دائمًا — غير موجودة في القائمة أصلًا لا معطَّلة
        $this->assertFalse(PrivacyFields::isControllable('governorate'));
        $this->assertArrayNotHasKey('governorate', PrivacyFields::all());

        // ولا تُحفَظ ولو حاول أحدهم إرسالها مباشرةً
        $this->actingAs($user)
            ->patchJson(route('settings.privacy.field'), ['field' => 'governorate', 'visibility' => 'supervisors'])
            ->assertStatus(422);

        $this->assertSame(0, UserPrivacySetting::where('user_id', $user->id)->where('field', 'governorate')->count());

        // والصفحة لا تعرض لها أيّ كونترول
        $this->actingAs($user)->get(route('settings.privacy'))
            ->assertOk()
            ->assertViewHas('fields', fn ($fields) => ! in_array('governorate', array_column($fields, 'key'), true))
            ->assertSee('المحافظة بتفضل ظاهرة للكلّ على طول');
    }

    public function test_field_privacy_is_saved_per_field(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->patchJson(route('settings.privacy.field'), ['field' => 'phone', 'visibility' => 'all_volunteers'])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $this->assertSame('all_volunteers', UserPrivacySetting::where('user_id', $user->id)
            ->where('field', 'phone')->value('visibility'));
    }

    public function test_sensitive_fields_default_to_supervisors_only(): void
    {
        foreach (PrivacyFields::SENSITIVE as $field) {
            $this->assertSame('supervisors', PrivacyFields::defaultVisibility($field));
        }
    }

    public function test_who_sees_my_data_lists_consents_and_revoke_works_silently(): void
    {
        $owner = $this->trainee();
        $requester = $this->trainee(['name' => 'أحمد سيف الدين']);

        $consent = ConsentRequest::create([
            'requester_id' => $requester->id, 'owner_id' => $owner->id, 'field' => 'phone',
            'status' => 'granted', 'request_expires_at' => now()->subDay(),
            'granted_at' => now()->subDay(), 'consent_expires_at' => now()->addDays(29),
        ]);

        $this->actingAs($owner)->get(route('settings.privacy'))
            ->assertOk()
            ->assertSee('مَن يرى بياناتي')
            ->assertSee('أحمد سيف الدين');

        $this->actingAs($owner)->post(route('settings.privacy.revoke', $consent))->assertRedirect();

        $consent->refresh();
        $this->assertSame('revoked', $consent->status);
        $this->assertNotNull($consent->revoked_at);

        // السحب بلا إشعار للطرف الآخر (13.4-م)
        $this->assertSame(0, $requester->notificationsFeed()->count());

        $this->actingAs($owner)->get(route('settings.privacy'))
            ->assertOk()
            ->assertSee('مفيش حدّ بيشوف بياناتك دلوقتي');
    }

    public function test_ending_an_active_session_works(): void
    {
        $user = $this->trainee();

        $device = UserDevice::create([
            'user_id' => $user->id,
            'session_id' => 'other-session-id',
            'ip' => '41.32.88.10',
            'device_label' => 'لابتوب — Chrome',
            'last_active_at' => now()->subHour(),
        ]);

        $this->actingAs($user)->get(route('settings.privacy'))
            ->assertOk()
            ->assertSee('الجلسات النشطة')
            ->assertSee('لابتوب — Chrome');

        $this->actingAs($user)->delete(route('settings.devices.destroy', $device))->assertRedirect();

        $this->assertSame(0, UserDevice::where('user_id', $user->id)->count());
    }

    public function test_a_user_cannot_end_someone_elses_session(): void
    {
        $user = $this->trainee();
        $other = $this->trainee();

        $device = UserDevice::create([
            'user_id' => $other->id, 'session_id' => 'x', 'device_label' => 'جهاز غيري',
        ]);

        $this->actingAs($user)->delete(route('settings.devices.destroy', $device))->assertForbidden();
        $this->assertSame(1, UserDevice::where('user_id', $other->id)->count());
    }

    public function test_download_my_data_returns_a_json_file(): void
    {
        $user = $this->trainee(['name' => 'نورهان مصطفى']);

        $response = $this->actingAs($user)->get(route('settings.export'));

        $response->assertOk()
            ->assertHeader('content-type', 'application/json; charset=utf-8');

        $this->assertStringContainsString('attachment;', $response->headers->get('content-disposition'));

        $payload = json_decode($response->getContent(), true);

        $this->assertSame('نورهان مصطفى', $payload['account']['name']);
        // ⭐ الملفّ يصرّح أنّ المحافظة حقل عامّ دائمًا (12.14-د)
        $this->assertContains('governorate', $payload['always_public_fields']);
    }
}
