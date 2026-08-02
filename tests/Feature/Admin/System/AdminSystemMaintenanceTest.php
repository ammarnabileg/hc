<?php

namespace Tests\Feature\Admin\System;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\MaintenanceWindow;
use App\Services\Admin\System\MaintenanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * وضع الصيانة العامّ (12.7-و-1) — **ولا صيانة جزئيّة إطلاقًا**.
 * ⭐ التفعيل يجمّد كلّ المهل، والرفع يعيد حسابها دفعةً واحدة (استئناف لا إلغاء).
 */
class AdminSystemMaintenanceTest extends SystemTestCase
{
    private const ADMIN = ['maintenance.view', 'maintenance.manage', 'settings_general.view', 'settings_general.edit'];

    public function test_activation_records_a_window_and_flags_the_platform(): void
    {
        $admin = $this->admin(self::ADMIN);

        $this->actingAs($admin)->post(route('admin.settings.maintenance.start'), [
            'message' => 'بنطوّر حاجة حلوة — هنرجع قريب.',
            'hours' => 2,
        ])->assertRedirect();

        Cache::forget('settings');

        $this->assertDatabaseCount('maintenance_windows', 1);
        $this->assertTrue(app(MaintenanceService::class)->isActive());
    }

    /** ⭐ الديدلاين يُزاح بالفارق عند الرفع — والمهلة تُستأنف من حيث وقفت */
    public function test_lifting_maintenance_shifts_frozen_deadlines_in_one_batch(): void
    {
        $admin = $this->admin(self::ADMIN);
        $enrollment = $this->enrollment(CarbonImmutable::now()->addDays(3));
        $before = CarbonImmutable::parse($enrollment->deadline_at);

        // فترة صيانة بدأت من ساعتين — نكتبها مباشرةً لنضبط المدّة بالضبط
        MaintenanceWindow::create([
            'message' => 'صيانة',
            'planned_hours' => 2,
            'started_at' => now()->subHours(2),
            'expected_end_at' => now(),
            'started_by' => $admin->id,
        ]);

        $result = app(MaintenanceService::class)->lift($admin);

        $enrollment->refresh();
        $after = CarbonImmutable::parse($enrollment->deadline_at);

        $this->assertGreaterThanOrEqual(1, $result['rows']);
        $this->assertEqualsWithDelta(7200, $result['seconds'], 60);
        $this->assertEqualsWithDelta(7200, $before->diffInSeconds($after), 60);
    }

    /** ما فات قبل بدء الصيانة لا يُمدَّد — التجميد استئنافٌ لا مكافأة */
    public function test_deadlines_already_missed_before_the_window_are_not_extended(): void
    {
        $admin = $this->admin(self::ADMIN);
        $enrollment = $this->enrollment(CarbonImmutable::now()->subDays(5));
        $before = CarbonImmutable::parse($enrollment->deadline_at);

        MaintenanceWindow::create([
            'message' => 'صيانة',
            'planned_hours' => 2,
            'started_at' => now()->subHours(2),
            'expected_end_at' => now(),
            'started_by' => $admin->id,
        ]);

        app(MaintenanceService::class)->lift($admin);

        $this->assertEqualsWithDelta(
            0,
            $before->diffInSeconds(CarbonImmutable::parse($enrollment->refresh()->deadline_at)),
            2,
        );
    }

    /** ⭐ الفترات المتداخلة تتراكم **بلا ازدواج حساب** (12.7-و-1) */
    public function test_overlapping_windows_are_merged_without_double_counting(): void
    {
        MaintenanceWindow::create([
            'message' => 'أولى', 'planned_hours' => 4,
            'started_at' => now()->subHours(6), 'expected_end_at' => now()->subHours(2),
            'ended_at' => now()->subHours(2),
        ]);

        MaintenanceWindow::create([
            'message' => 'ثانية', 'planned_hours' => 3,
            'started_at' => now()->subHours(3), 'expected_end_at' => now()->subHour(),
            'ended_at' => now()->subHour(),
        ]);

        $seconds = app(MaintenanceService::class)->frozenSeconds(CarbonImmutable::now()->subHours(24));

        // 6→2 ثمّ 3→1: الاتّحاد خمس ساعات لا سبع
        $this->assertEqualsWithDelta(5 * 3600, $seconds, 60);
    }

    /** ⭐ عند الصفر: رسالة «قرّبنا ننتهي» بدل عدّاد سالب — وبنفس المساحة */
    public function test_countdown_never_goes_negative_and_switches_message(): void
    {
        $admin = $this->admin(self::ADMIN);

        MaintenanceWindow::create([
            'message' => 'صيانة',
            'planned_hours' => 1,
            'started_at' => now()->subHours(3),
            'expected_end_at' => now()->subHour(),
            'started_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.settings.maintenance.state'));

        $response->assertOk();
        $this->assertTrue($response->json('overrun'));
        $this->assertSame(0, $response->json('seconds_left'));
        $this->assertSame(setting('system.maintenance.overrun_text'), $response->json('message'));
    }

    /** التمديد بضغطة: +1 · +3 · مخصّص — والحدّ إعداد لا رقم محروق */
    public function test_extending_pushes_the_expected_end(): void
    {
        $admin = $this->admin(self::ADMIN);

        $window = MaintenanceWindow::create([
            'message' => 'صيانة', 'planned_hours' => 1,
            'started_at' => now(), 'expected_end_at' => now()->addHour(),
            'started_by' => $admin->id,
        ]);

        $expected = CarbonImmutable::parse($window->expected_end_at);

        $this->actingAs($admin)->post(route('admin.settings.maintenance.extend'), ['hours' => 3])->assertRedirect();

        $this->assertEqualsWithDelta(
            3 * 3600,
            $expected->diffInSeconds(CarbonImmutable::parse($window->refresh()->expected_end_at)),
            5,
        );
    }

    /** ⛔ لا صيانة جزئيّة: الشاشة تقولها صراحةً وتحيل لمفاتيح المزايا */
    public function test_partial_maintenance_is_explicitly_ruled_out_on_screen(): void
    {
        $admin = $this->admin(self::ADMIN);

        $this->actingAs($admin)->get(route('admin.settings.index', ['tab' => 'maintenance']))
            ->assertOk()
            ->assertSee('الصيانة عامّة للمنصّة كلّها فقط', false)
            ->assertSee('مفاتيح المزايا', false);
    }

    private function enrollment(CarbonImmutable $deadline): Enrollment
    {
        $course = Course::create([
            'slug' => 'course-'.str()->lower(str()->random(6)),
            'name_ar' => 'تدريب تجريبيّ',
            'status' => 'published',
        ]);

        return Enrollment::create([
            'user_id' => $this->makeUser('متدرّب')->id,
            'course_id' => $course->id,
            'started_at' => now()->subDay(),
            'deadline_at' => $deadline,
            'status' => 'active',
        ]);
    }
}
