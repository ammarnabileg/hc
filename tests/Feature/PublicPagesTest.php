<?php

namespace Tests\Feature;

use App\Models\Offboarding;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\Volunteer\SettingsWriter;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
    }

    private function trainee(): User
    {
        $u = User::create([
            'name' => 'زائر', 'email' => uniqid().'@t.local', 'password' => 'secret-password',
            'code' => strtoupper(uniqid()), 'status' => 'active',
        ]);
        $u->assignRole('trainee');

        return $u;
    }

    /** ⭐ الزرّ الرئيسيّ نصُّه **ما كتبه الأدمن** في `volunteer_page.cta_label` (13.4-أ) */
    public function test_landing_shows_the_start_action_for_a_non_volunteer(): void
    {
        SettingsWriter::put('volunteer_page.cta_label', 'ابدأ المسار التأهيليّ');

        $this->actingAs($this->trainee())->get(route('volunteering.landing'))
            ->assertOk()
            ->assertSee('ابدأ المسار التأهيليّ');
    }

    /** داخل التبريد لا يُفتَح الزرّ أصلًا ويظهر موعد الإتاحة (13.4-س) */
    public function test_cooldown_hides_the_start_action_and_shows_the_date(): void
    {
        SettingsWriter::put('volunteer_page.cta_label', 'ابدأ المسار التأهيليّ');

        $user = $this->trainee();

        Offboarding::create([
            'user_id' => $user->id, 'type' => 'resignation',
            'cooldown_until' => now()->addDays(20), 'completed_at' => now(),
        ]);

        $this->actingAs($user)->get(route('volunteering.landing'))
            ->assertOk()
            ->assertSee('أهلًا بعودتك')
            ->assertDontSee('ابدأ المسار التأهيليّ');
    }

    /** بعد الإقصاء: العودة بقرار مشرف عام التطوّع وحده (13.4-ق) */
    public function test_exclusion_requires_a_general_supervisor_decision(): void
    {
        SettingsWriter::put('volunteer_page.cta_label', 'ابدأ المسار التأهيليّ');

        $user = $this->trainee();

        Offboarding::create([
            'user_id' => $user->id, 'type' => 'exclusion', 'completed_at' => now(),
        ]);

        $this->actingAs($user)->get(route('volunteering.landing'))
            ->assertOk()
            ->assertSee('مشرف عام التطوّع')
            ->assertDontSee('ابدأ المسار التأهيليّ');
    }

    /** بانر الموافقة لا يظهر إن كان التتبّع مطفأً كلّيًّا (21.3-د) */
    public function test_consent_banner_hidden_when_tracking_is_off(): void
    {
        $this->actingAs($this->trainee())->get(route('volunteering.landing'))
            ->assertOk()
            ->assertDontSee('نستخدم ملفّات تعريف الارتباط');
    }

    public function test_consent_banner_appears_and_records_the_choice(): void
    {
        Setting::where('key', 'ads.tracking.enabled')->update(['value' => '1']);
        cache()->forget('settings');

        $user = $this->trainee();

        $this->actingAs($user)->get(route('volunteering.landing'))
            ->assertOk()
            ->assertSee('أرفض');

        $this->actingAs($user)->post(route('consent.tracking'), ['choice' => 'rejected']);

        // الرفض يُسجَّل فعليًّا فيتوقّف التتبّع — لا شكليًّا
        $this->assertSame('rejected', $user->fresh()->tracking_consent);
    }

    /** إشعارات التطوّع تُوجَّه لمركز الإشعارات على تاب التطوّع — مصدر واحد (2.8) */
    public function test_volunteer_notifications_redirects_to_the_single_center(): void
    {
        $this->actingAs($this->trainee())->get(route('volunteer.notifications'))
            ->assertRedirect(route('notifications.index', ['layer' => 'volunteer']));
    }
}
