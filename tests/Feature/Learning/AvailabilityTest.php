<?php

namespace Tests\Feature\Learning;

use App\Models\Country;
use App\Models\Course;
use App\Models\CourseAvailabilityPeriod;
use App\Models\User;
use App\Services\Learning\AvailabilityService;
use App\Services\Learning\UserClock;
use Illuminate\Support\Carbon;

/**
 * الإتاحة والجدولة والتوقيت (الدستور 5).
 *
 * القاعدة المختبَرة قبل كلّ شيء: **مستخدمان في نفس اللحظة وفي منطقتين
 * زمنيّتين مختلفتين يريان نفس التدريب بحالتين مختلفتين** — لأنّ النافذة
 * اليوميّة تُقاس بساعة كلٍّ منهما لا بساعة الخادم.
 */
class AvailabilityTest extends LearningTestCase
{
    private function countries(): array
    {
        return [
            Country::updateOrCreate(['iso2' => 'EG'], [
                'name_ar' => 'مصر', 'name_en' => 'Egypt', 'timezone' => 'Africa/Cairo', 'is_active' => true,
            ]),
            Country::updateOrCreate(['iso2' => 'ID'], [
                'name_ar' => 'إندونيسيا', 'name_en' => 'Indonesia', 'timezone' => 'Asia/Jakarta', 'is_active' => true,
            ]),
        ];
    }

    /** تدريب بنافذة يوميّة صباحيّة 5→7 ص — المثال الحرفيّ في الدستور 5 */
    private function dawnCourse(): Course
    {
        return $this->makeCourse(2, true, [
            'daily_open_at' => '05:00',
            'daily_close_at' => '07:00',
            // نشرٌ قديم صريح كي لا تتداخل «الجدولة» مع تثبيت الوقت في الاختبار
            'published_at' => Carbon::parse('2020-01-01'),
        ]);
    }

    public function test_daily_window_is_measured_in_each_user_local_time_not_the_server(): void
    {
        [$egypt, $indonesia] = $this->countries();

        $course = $this->dawnCourse();
        $availability = app(AvailabilityService::class);

        $cairoUser = $this->trainee('متدرّب القاهرة');
        $cairoUser->forceFill(['country_id' => $egypt->id])->save();

        $jakartaUser = $this->trainee('متدرّب جاكرتا');
        $jakartaUser->forceFill(['country_id' => $indonesia->id])->save();

        // 02:00 UTC = 05:00 بالقاهرة (داخل النافذة) و09:00 بجاكرتا (خارجها)
        Carbon::setTestNow(Carbon::parse('2026-07-15 02:00:00', 'UTC'));

        $this->assertTrue(
            $availability->forCourse($course, null, $cairoUser)['open'],
            'المتدرّب في القاهرة لازم يلاقي التدريب مفتوحًا الساعة 5 صباحًا بتوقيته.',
        );

        $this->assertFalse(
            $availability->forCourse($course, null, $jakartaUser)['open'],
            'المتدرّب في جاكرتا لازم يلاقيه مقفولًا لأنّ ساعته 9 صباحًا.',
        );

        Carbon::setTestNow();
    }

    public function test_the_closed_message_says_what_happened_and_when_it_opens_with_a_countdown(): void
    {
        [$egypt] = $this->countries();

        $course = $this->dawnCourse();
        $user = $this->trainee();
        $user->forceFill(['country_id' => $egypt->id])->save();

        // 20:00 بالقاهرة — خارج النافذة، والفتح القادم 5 ص بكرة
        Carbon::setTestNow(Carbon::parse('2026-07-15 18:00:00', 'UTC'));

        $state = app(AvailabilityService::class)->forCourse($course, null, $user);

        $this->assertFalse($state['open']);
        $this->assertStringContainsString('05:00', (string) $state['reason']);
        $this->assertNotNull($state['opens_at']);
        $this->assertGreaterThan(0, $state['opens_in']);
        $this->assertSame('Africa/Cairo', $state['timezone']);
        $this->assertSame('05:00', $state['opens_at']->format('H:i'));

        Carbon::setTestNow();
    }

    public function test_a_course_outside_all_its_periods_is_closed_even_inside_the_daily_window(): void
    {
        [$egypt] = $this->countries();

        $course = $this->dawnCourse();
        $user = $this->trainee();
        $user->forceFill(['country_id' => $egypt->id])->save();

        // فترة إتاحة في يناير وحده — ونحن في يوليو
        CourseAvailabilityPeriod::create([
            'course_id' => $course->id,
            'starts_on' => '2027-01-01',
            'ends_on' => '2027-01-07',
            'is_active' => true,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-07-15 02:00:00', 'UTC')); // 5 ص بالقاهرة

        $state = app(AvailabilityService::class)->forCourse($course, null, $user);

        $this->assertFalse($state['open'], 'الساعة داخل النافذة اليوميّة لكن التاريخ خارج كلّ الفترات.');
        $this->assertSame('2027-01-01', $state['opens_at']->toDateString());

        Carbon::setTestNow();
    }

    public function test_inside_a_period_and_inside_the_daily_window_the_course_opens(): void
    {
        [$egypt] = $this->countries();

        $course = $this->dawnCourse();
        $user = $this->trainee();
        $user->forceFill(['country_id' => $egypt->id])->save();

        CourseAvailabilityPeriod::create([
            'course_id' => $course->id,
            'starts_on' => '2026-07-10',
            'ends_on' => '2026-07-20',
            'is_active' => true,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-07-15 02:00:00', 'UTC')); // 5 ص بالقاهرة

        $this->assertTrue(app(AvailabilityService::class)->forCourse($course, null, $user)['open']);

        Carbon::setTestNow();
    }

    public function test_a_course_whose_periods_are_all_over_is_closed_for_good(): void
    {
        $course = $this->dawnCourse();
        $user = $this->trainee();

        CourseAvailabilityPeriod::create([
            'course_id' => $course->id,
            'starts_on' => '2020-01-01',
            'ends_on' => '2020-01-07',
            'is_active' => true,
        ]);

        $state = app(AvailabilityService::class)->forCourse($course, null, $user);

        $this->assertFalse($state['open']);
        $this->assertSame('danger', $state['state']);
        $this->assertNull($state['opens_at']);
    }

    /** الحجب على الخادم لا في الواجهة: الدرس نفسه لا يُفتَح خارج النافذة (5) */
    public function test_the_lesson_screen_is_blocked_server_side_outside_the_window(): void
    {
        [$egypt] = $this->countries();

        $course = $this->dawnCourse();
        $user = $this->trainee();
        $user->forceFill(['country_id' => $egypt->id])->save();
        $this->enroll($user, $course);

        $lesson = $this->lessonsOf($course)->first();

        Carbon::setTestNow(Carbon::parse('2026-07-15 18:00:00', 'UTC')); // 20:00 بالقاهرة

        $this->actingAs($user)
            ->get(route('learning.lesson', [$course, $lesson]))
            ->assertRedirect(route('learning.course', $course));

        // وحتى الطلب المباشر لإكمال الدرس يُرفَض — القرار في الخادم
        $this->actingAs($user)
            ->post(route('learning.lesson.complete', [$course, $lesson]))
            ->assertRedirect();

        $this->assertDatabaseMissing('lesson_completions', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_the_course_screen_shows_the_reason_and_the_local_timezone(): void
    {
        [$egypt] = $this->countries();

        $course = $this->dawnCourse();
        $user = $this->trainee();
        $user->forceFill(['country_id' => $egypt->id])->save();
        $this->enroll($user, $course);

        Carbon::setTestNow(Carbon::parse('2026-07-15 18:00:00', 'UTC'));

        $this->actingAs($user)
            ->get(route('learning.course', $course))
            ->assertOk()
            ->assertSee('Africa/Cairo', false)
            ->assertSee(setting('learning.lock.opens_at_prefix'), false);

        Carbon::setTestNow();
    }

    // ---------------------------------------------------------------- التوقيت

    public function test_the_manual_timezone_wins_over_the_detected_one(): void
    {
        [$egypt] = $this->countries();

        $user = $this->trainee();
        $user->forceFill([
            'country_id' => $egypt->id,
            'auto_timezone' => 'Asia/Jakarta',
            'timezone' => 'Europe/London',
        ])->save();

        $this->assertSame('Europe/London', app(UserClock::class)->timezoneFor($user->refresh()));
        $this->assertSame('manual', app(UserClock::class)->sourceFor($user));
    }

    public function test_the_detected_timezone_wins_over_the_country_default(): void
    {
        [$egypt] = $this->countries();

        $user = $this->trainee();
        $user->forceFill(['country_id' => $egypt->id, 'auto_timezone' => 'Asia/Jakarta'])->save();

        $this->assertSame('Asia/Jakarta', app(UserClock::class)->timezoneFor($user->refresh()));
    }

    public function test_the_client_hint_is_validated_on_the_server_before_it_is_stored(): void
    {
        $user = $this->trainee();

        // منطقة غير صالحة تُرفَض بلا أثر — المتصفّح يقترح والخادم يقرّر
        $this->actingAs($user)
            ->postJson(route('timezone.detect'), ['timezone' => 'Mars/Olympus'])
            ->assertOk();

        $this->assertNull($user->refresh()->auto_timezone);

        $this->actingAs($user)
            ->postJson(route('timezone.detect'), ['timezone' => 'Asia/Riyadh'])
            ->assertOk()
            ->assertJson(['changed' => true, 'timezone' => 'Asia/Riyadh']);

        $this->assertSame('Asia/Riyadh', $user->refresh()->auto_timezone);
    }

    public function test_the_user_can_set_and_clear_the_manual_timezone(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->from(route('learning.courses'))
            ->post(route('timezone.update'), ['timezone' => 'Asia/Dubai'])
            ->assertRedirect(route('learning.courses'));

        $this->assertSame('Asia/Dubai', $user->refresh()->timezone);

        // «تلقائيًّا» = مسح الاختيار لا تثبيت قيمة
        $this->actingAs($user)
            ->from(route('learning.courses'))
            ->post(route('timezone.update'), ['timezone' => '']);

        $this->assertNull($user->refresh()->timezone);
    }

    public function test_the_geo_header_detects_the_country_timezone_on_login(): void
    {
        $this->countries();

        $user = User::create([
            'name' => 'داخل من الرياض',
            'email' => 'geo@test.local',
            'password' => 'secret-password',
            'code' => 'GEO12345',
            'status' => 'active',
        ]);

        Country::updateOrCreate(['iso2' => 'SA'], [
            'name_ar' => 'السعوديّة', 'name_en' => 'Saudi Arabia', 'timezone' => 'Asia/Riyadh', 'is_active' => true,
        ]);

        $this->post(route('login'), [
            'identifier' => 'geo@test.local',
            'password' => 'secret-password',
        ], ['CF-IPCountry' => 'SA']);

        $this->assertSame('Asia/Riyadh', $user->refresh()->auto_timezone);
    }
}
