<?php

namespace Tests\Feature\Events;

use App\Models\EventRegistration;

class EventsRegistrationTest extends EventsTestCase
{
    public function test_events_index_shows_published_events_with_filters(): void
    {
        $user = $this->trainee();
        $event = $this->makeEvent(['title_ar' => 'فعاليّة الفلاتر']);

        $this->actingAs($user)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee('فعاليّة الفلاتر')
            ->assertSee('التصنيف')
            ->assertSee('النوع');

        // التقويم مرسوم بأيدينا ويعمل بلا أيّ مكتبة خارجيّة
        $this->actingAs($user)
            ->get(route('events.index', ['view' => 'calendar']))
            ->assertOk()
            ->assertSee('السبت');

        $this->assertSame('published', $event->status);
    }

    public function test_user_registers_only_once_and_gets_a_unique_ticket(): void
    {
        $user = $this->trainee();
        $event = $this->makeEvent();

        $this->actingAs($user)
            ->post(route('events.register', $event->slug))
            ->assertRedirect(route('events.show', $event->slug));

        // المحاولة الثانية لا تنشئ تسجيلًا جديدًا
        $this->actingAs($user)->post(route('events.register', $event->slug));

        $registrations = EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->get();

        $this->assertCount(1, $registrations);
        $this->assertNotEmpty($registrations->first()->ticket_code);
    }

    public function test_hybrid_event_requires_choosing_an_attendance_mode(): void
    {
        $user = $this->trainee();
        $event = $this->makeEvent(['mode' => 'hybrid', 'location' => 'القاهرة']);

        $this->actingAs($user)->post(route('events.register', $event->slug));
        $this->assertSame(0, EventRegistration::where('event_id', $event->id)->count());

        $this->actingAs($user)->post(route('events.register', $event->slug), ['attend_mode' => 'offline']);
        $this->assertSame('offline', EventRegistration::where('event_id', $event->id)->value('attend_mode'));
    }

    public function test_full_event_is_shown_with_its_state_and_blocks_registration(): void
    {
        $user = $this->trainee();
        $event = $this->makeEvent(['capacity' => 0, 'title_ar' => 'فعاليّة مكتملة']);

        $this->actingAs($user)->post(route('events.register', $event->slug));

        $this->assertSame(0, EventRegistration::where('event_id', $event->id)->count());

        $this->actingAs($user)
            ->get(route('events.show', $event->slug))
            ->assertOk()
            ->assertSee('اكتمل العدد');
    }

    public function test_paid_event_debits_the_wallet_and_blocks_when_balance_is_short(): void
    {
        $user = $this->trainee();
        $event = $this->makeEvent(['price_coins' => 150]);

        // بلا رصيد: لا تسجيل ولا خصم
        $this->actingAs($user)->post(route('events.register', $event->slug));
        $this->assertSame(0, EventRegistration::where('event_id', $event->id)->count());

        $this->giveBalance($user, 'coins', 200);
        $this->actingAs($user)->post(route('events.register', $event->slug));

        $this->assertSame(1, EventRegistration::where('event_id', $event->id)->count());
        $this->assertSame(50.0, $this->balanceOf($user, 'coins'));
    }

    public function test_calendar_file_is_generated_without_any_external_library(): void
    {
        $user = $this->trainee();
        $event = $this->makeEvent();

        $response = $this->actingAs($user)->get(route('events.ics', $event->slug));
        $response->assertOk();

        $body = $response->streamedContent();

        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('BEGIN:VEVENT', $body);
        $this->assertStringContainsString('X-WR-TIMEZONE:', $body);
    }
}
