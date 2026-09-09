<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Event;
use App\Models\User;

/**
 * ⭐ [2026-09-10] «عرض تقويم + جدول (تبديل)» (12.11) — كان `view=` يُقرَأ في
 * المتحكّم بلا زرّ تبديلٍ في الواجهة ولا فرعٍ يستهلكه: لا تقويم ولا مبدِّل.
 */
class AdminEventsCalendarViewTest extends AdminVolunteerTestCase
{
    private function admin(): User
    {
        return $this->grant($this->makeUser(), 'events.list');
    }

    public function test_the_table_view_is_the_default(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.events.index'))
            ->assertOk()
            ->assertSee('data-event-edit', false);
    }

    /** ⭐ زرّ التبديل موجودٌ فعليًّا — لا لافتةً بلا أثر */
    public function test_the_toggle_buttons_exist_and_switch_the_view(): void
    {
        $admin = $this->admin();

        $tableHtml = $this->actingAs($admin)
            ->get(route('admin.events.index', ['view' => 'table']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('view=calendar', $tableHtml, 'زرّ التبديل للتقويم غير موجود في الجدول.');

        $calendarHtml = $this->actingAs($admin)
            ->get(route('admin.events.index', ['view' => 'calendar']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('view=table', $calendarHtml, 'زرّ التبديل للجدول غير موجود في التقويم.');
        // شبكة الأيّام السبعة — دليلٌ على تقويمٍ حقيقيّ لا مجرّد لافتة
        $this->assertStringContainsString('grid-cols-7', $calendarHtml);
    }

    /** والفعاليّة تظهر فعليًّا في يوم شهرها الصحيح — لا تقويمًا فارغًا دائمًا */
    public function test_an_event_actually_appears_on_its_day_in_the_calendar(): void
    {
        $admin = $this->admin();
        $when = now()->addDays(3)->setTime(14, 0);

        $event = Event::create([
            'title_ar' => 'فعاليّة التقويم',
            'title_en' => 'Calendar Event',
            'slug' => 'calendar-event-test',
            'mode' => 'online',
            'starts_at' => $when,
            'status' => 'published',
            'attendance_code' => 'CAL12345',
        ]);

        $html = $this->actingAs($admin)
            ->get(route('admin.events.index', ['view' => 'calendar', 'month' => $when->format('Y-m')]))
            ->assertOk()->getContent();

        $this->assertStringContainsString($event->title_ar, $html, 'الفعاليّة غير ظاهرة في تقويم شهرها.');
    }

    /** وشهرٌ آخر لا يحمل فعاليّات شهرٍ غيره */
    public function test_an_event_does_not_leak_into_a_different_month(): void
    {
        $admin = $this->admin();
        $when = now()->addMonths(2)->setTime(10, 0);

        $event = Event::create([
            'title_ar' => 'فعاليّة شهر بعيد',
            'title_en' => 'Distant Month Event',
            'slug' => 'distant-month-event-test',
            'mode' => 'online',
            'starts_at' => $when,
            'status' => 'published',
            'attendance_code' => 'CAL54321',
        ]);

        $html = $this->actingAs($admin)
            ->get(route('admin.events.index', ['view' => 'calendar', 'month' => now()->format('Y-m')]))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString($event->title_ar, $html, 'فعاليّة شهرٍ آخر ظهرت في تقويم هذا الشهر.');
    }
}
