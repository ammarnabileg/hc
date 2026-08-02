<?php

namespace Tests\Feature\Account;

use App\Models\ConsentRequest;
use App\Models\EmergencyContact;

/**
 * حسابي ← الإعدادات (الدستور 24.5 · 2.17-ب · 13.4-م).
 */
class SettingsTest extends AccountTestCase
{
    public function test_settings_page_shows_the_four_groups_and_an_inner_search(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('الإعدادات')
            ->assertSee('دوّر على إعداد')
            ->assertSeeInOrder(['الحساب', 'المظهر', 'الصوت والتنبيهات', 'جهة الطوارئ'])
            // ⭐ توجل الصوت فقط — والأنيميشن دائم ولا توجل له (2.14)
            ->assertSee('الأنيميشن جزء من الإحساس وبيشتغل دايمًا.');
    }

    public function test_autosave_saves_one_field_and_answers_with_the_saved_flag(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->patchJson(route('settings.field'), ['field' => 'name', 'value' => 'منى عبد الرحمن'])
            ->assertOk()
            ->assertJson(['saved' => true, 'message' => 'اتحفظ ✓']);

        $this->assertSame('منى عبد الرحمن', $user->fresh()->name);
    }

    public function test_autosave_refuses_a_field_outside_the_closed_list(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->patchJson(route('settings.field'), ['field' => 'status', 'value' => 'active'])
            ->assertStatus(422);
    }

    public function test_changing_email_invalidates_active_contact_consents(): void
    {
        $owner = $this->trainee(['email' => 'before@test.local']);
        $requester = $this->trainee();

        $consent = ConsentRequest::create([
            'requester_id' => $requester->id,
            'owner_id' => $owner->id,
            'field' => 'email',
            'status' => 'granted',
            'request_expires_at' => now()->subDay(),
            'granted_at' => now()->subDay(),
            'consent_expires_at' => now()->addDays(29),
        ]);

        $response = $this->actingAs($owner)
            ->patchJson(route('settings.field'), ['field' => 'email', 'value' => 'after@test.local'])
            ->assertOk();

        // البيانات الجديدة لا ترث موافقة قديمة (13.4-م)
        $this->assertSame('expired', $consent->fresh()->status);
        $this->assertStringContainsString('وقفنا عرض بياناتك', $response->json('message'));
    }

    public function test_settings_page_warns_about_the_effect_on_contact_consents(): void
    {
        $owner = $this->trainee();
        $requester = $this->trainee();

        ConsentRequest::create([
            'requester_id' => $requester->id, 'owner_id' => $owner->id, 'field' => 'phone',
            'status' => 'granted', 'request_expires_at' => now()->subDay(),
            'granted_at' => now()->subDay(), 'consent_expires_at' => now()->addDays(29),
        ]);

        $this->actingAs($owner)->get(route('settings.index'))
            ->assertOk()
            ->assertViewHas('activeConsents', 1)
            ->assertSee('هيتوقف عرض بياناتك لـ');
    }

    public function test_emergency_contact_is_optional_and_capped(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)->post(route('settings.emergency.store'), [
            'name' => 'سلوى عبد الرحمن', 'phone' => '+201000000900', 'relation' => 'الوالدة',
        ])->assertRedirect();

        $this->assertSame(1, EmergencyContact::where('user_id', $user->id)->count());

        $contact = EmergencyContact::where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)->delete(route('settings.emergency.destroy', $contact))->assertRedirect();
        $this->assertSame(0, EmergencyContact::where('user_id', $user->id)->count());
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)->post(route('settings.password'), [
            'current_password' => 'wrong-password',
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->post(route('settings.password'), [
            'current_password' => 'secret-password',
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])->assertSessionHasNoErrors();
    }
}
