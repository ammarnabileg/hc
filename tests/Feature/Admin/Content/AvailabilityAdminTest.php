<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Course;
use App\Models\CourseAvailabilityPeriod;

/**
 * شاشة إدارة الإتاحة الزمنيّة (الدستور 5 · 12.4):
 * الفترات المتعدّدة + أوقات التشغيل اليوميّة، وكلاهما إعداد لا رقم محروق.
 */
class AvailabilityAdminTest extends AdminContentTestCase
{
    public function test_the_screen_lists_courses_with_their_schedule(): void
    {
        $admin = $this->admin();
        $course = Course::query()->first();

        $this->actingAs($admin)
            ->get(route('admin.availability.index', ['course' => $course->id]))
            ->assertOk()
            ->assertSee('الإتاحة والتوقيت', false)
            ->assertSee($course->name_ar, false);
    }

    /** ⭐ 24.2: بحثٌ بلا نتائج يقول كده صراحةً بدل رسالة تُوهم بأنّ المنصّة بلا تدريبات. */
    public function test_a_search_with_no_matches_shows_a_filtered_empty_message_not_the_start_message(): void
    {
        $this->assertGreaterThan(0, Course::query()->count(), 'لازم يكون في تدريبات فعليّة قبل الاختبار');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.availability.index', ['q' => 'zzzznotexist']))
            ->assertOk();

        $response->assertSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
        $response->assertDontSee(
            setting('admin.availability.index.lsh_mfysh_tdrybat_fy_almnsa_asla', 'لسّه مفيش تدريبات في المنصّة أصلًا.'),
            false,
        );
    }

    /** وشاشةٌ فارغةٌ فعليًّا (بلا فلتر) تعرض رسالة «مفيش تدريبات أصلًا» لا رسالة الفلتر. */
    public function test_an_actually_empty_screen_without_filters_keeps_the_original_start_message(): void
    {
        Course::query()->delete();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.availability.index'))
            ->assertOk();

        $response->assertSee(
            setting('admin.availability.index.lsh_mfysh_tdrybat_fy_almnsa_asla', 'لسّه مفيش تدريبات في المنصّة أصلًا.'),
            false,
        );
        $response->assertDontSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
    }

    public function test_an_admin_adds_a_period_and_a_daily_window(): void
    {
        $admin = $this->admin();
        $course = Course::query()->first();

        $this->actingAs($admin)
            ->post(route('admin.availability.periods.store', $course), [
                'starts_on' => '2027-01-01',
                'ends_on' => '2027-01-07',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('course_availability_periods', [
            'course_id' => $course->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.availability.daily.save', $course), [
                'daily_open_at' => '05:00',
                'daily_close_at' => '07:00',
            ])
            ->assertRedirect();

        $this->assertSame('05:00', substr((string) $course->fresh()->daily_open_at, 0, 5));
    }

    public function test_a_reversed_period_is_refused_with_a_clear_message(): void
    {
        $admin = $this->admin();
        $course = Course::query()->first();

        $this->actingAs($admin)
            ->from(route('admin.availability.index'))
            ->post(route('admin.availability.periods.store', $course), [
                'starts_on' => '2027-01-10',
                'ends_on' => '2027-01-01',
            ])
            ->assertSessionHasErrors('ends_on');

        $this->assertDatabaseCount('course_availability_periods', 0);
    }

    public function test_a_period_can_be_paused_and_deleted(): void
    {
        $admin = $this->admin();
        $course = Course::query()->first();

        $period = CourseAvailabilityPeriod::create([
            'course_id' => $course->id,
            'starts_on' => '2027-03-01',
            'ends_on' => '2027-03-07',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.availability.periods.toggle', $period))->assertRedirect();
        $this->assertFalse((bool) $period->fresh()->is_active);

        $this->actingAs($admin)->delete(route('admin.availability.periods.destroy', $period))->assertRedirect();
        $this->assertDatabaseCount('course_availability_periods', 0);
    }
}
