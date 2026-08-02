<?php

namespace Tests\Feature\Security;

use App\Models\AppNotification;
use App\Models\MaintenanceWindow;
use App\Services\Security\MaintenanceAlert;
use Illuminate\Support\Facades\Cache;

/**
 * وضع الصيانة **يقفل المنصّة فعلًا** (12.7-و-1).
 *
 * الفجوة المُصلَحة: المنطق كان موجودًا لكن بلا حارس ولا صفحة — الموقع يفضل مفتوحًا.
 */
class MaintenanceLockTest extends SecurityTestCase
{
    /** ⭐ الشرط الأوّل: تحجب مستخدمًا عاديًّا وتسمح لمالك المنصّة */
    public function test_maintenance_blocks_a_normal_user_and_lets_the_platform_owner_in(): void
    {
        $this->startMaintenance();

        $trainee = $this->makeUser('متدرّب عاديّ');

        $this->actingAs($trainee)->get('/help')
            ->assertStatus(503)
            ->assertSee('بنطوّر حاجة حلوة — هنرجع قريب.', false);

        // مالك المنصّة يكمل شغله والموقع مقفول
        $this->actingAs($this->owner())->get('/help')->assertOk();
    }

    /** مَن له `maintenance.manage` يعدّي — وإلّا اتقفل على نفسه بره الموقع */
    public function test_maintenance_manager_passes_through_the_wall(): void
    {
        $this->startMaintenance();

        $this->actingAs($this->admin(['maintenance.manage']))
            ->get('/help')
            ->assertOk();
    }

    /** الدخول والخروج مستثنيان دائمًا — وإلّا مايقدرش الأدمن يدخل أصلًا */
    public function test_login_and_logout_stay_reachable_under_maintenance(): void
    {
        $this->startMaintenance();

        $this->get('/login')->assertOk();
        $this->actingAs($this->makeUser('خارج'))->post('/logout')->assertRedirect();
    }

    /** ⭐ `system.maintenance.exempt_ips` — الجهاز المستثنى يعدّي بلا حساب أصلًا */
    public function test_exempt_ip_passes_without_any_account(): void
    {
        $this->startMaintenance();

        \App\Models\Setting::query()->where('key', 'system.maintenance.exempt_ips')->update(['value' => '10.0.0.7']);
        Cache::forget('settings');

        $trainee = $this->makeUser('متدرّب');

        $this->actingAs($trainee)
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.7'])
            ->get('/help')
            ->assertOk();
    }

    /** الردّ على الطلبات غير المتزامنة 503 بجسم JSON لا صفحة HTML */
    public function test_json_requests_get_a_503_payload(): void
    {
        $this->startMaintenance();

        $this->actingAs($this->makeUser('متدرّب'))
            ->getJson('/help')
            ->assertStatus(503)
            ->assertJson(['maintenance' => true]);
    }

    /** ⭐ عند الصفر: «قرّبنا ننتهي» بدل عدّاد سالب — وبنفس مساحة العدّاد */
    public function test_at_zero_the_message_switches_instead_of_a_negative_countdown(): void
    {
        $this->startMaintenance();

        MaintenanceWindow::query()->whereNull('ended_at')->update([
            'started_at' => now()->subHours(4),
            'expected_end_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->makeUser('متدرّب'))->get('/help');

        $response->assertStatus(503)
            ->assertSee(setting('system.maintenance.overrun_text'), false)
            // الصندوق واحد بمساحة ثابتة فلا تقفز الصفحة عند التبديل
            ->assertSee('data-countdown-box', false)
            ->assertSee('min-block-size: 104px', false);
    }

    /** ⭐ صمّام أمان: عند تجاوز المدّة يصل تنبيه لمن يديرون الصيانة — مرّة واحدة */
    public function test_overrun_alerts_the_admins_once_per_window(): void
    {
        $owner = $this->owner();
        $this->startMaintenance();

        MaintenanceWindow::query()->whereNull('ended_at')->update([
            'started_at' => now()->subHours(4),
            'expected_end_at' => now()->subHour(),
        ]);

        $trainee = $this->makeUser('متدرّب');
        $this->actingAs($trainee)->get('/help')->assertStatus(503);
        $this->actingAs($trainee)->get('/help')->assertStatus(503);

        $this->assertSame(1, AppNotification::query()
            ->where('category', MaintenanceAlert::CATEGORY)
            ->where('user_id', $owner->id)
            ->count());
    }

    /** تحديث تلقائيّ كلّ دقيقتين — والقيمة إعداد لا رقم محروق */
    public function test_page_auto_refreshes_from_settings(): void
    {
        $this->startMaintenance();

        $this->actingAs($this->makeUser('متدرّب'))
            ->get('/help')
            ->assertSee((string) ((int) setting('system.maintenance.refresh_seconds', 120) * 1000), false);
    }

    /** بلا صيانة لا يتغيّر شيء — الحارس صامت تمامًا */
    public function test_platform_is_open_when_maintenance_is_off(): void
    {
        $this->actingAs($this->makeUser('متدرّب'))->get('/help')->assertOk();
    }
}
