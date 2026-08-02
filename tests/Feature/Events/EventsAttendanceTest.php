<?php

namespace Tests\Feature\Events;

use App\Models\Certificate;
use App\Models\EventRegistration;
use App\Models\Transaction;

class EventsAttendanceTest extends EventsTestCase
{
    public function test_join_link_is_hidden_before_its_window_and_shown_inside_it(): void
    {
        $user = $this->trainee();

        // الموعد بعيد: الرابط لا يُرسَل للواجهة أصلًا (13.3)
        $far = $this->makeEvent([
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHour(),
            'join_link' => 'https://meet.example.com/hidden-room',
        ]);

        $this->actingAs($user)->post(route('events.register', $far->slug));

        $this->actingAs($user)
            ->get(route('events.show', $far->slug))
            ->assertOk()
            ->assertDontSee('https://meet.example.com/hidden-room')
            ->assertSee('رابط الانضمام بيفتح');

        // داخل النافذة: الرابط يظهر
        $soon = $this->makeEvent([
            'starts_at' => now()->addMinutes(5),
            'ends_at' => now()->addHour(),
            'join_link' => 'https://meet.example.com/open-room',
        ]);

        $this->actingAs($user)->post(route('events.register', $soon->slug));

        $this->actingAs($user)
            ->get(route('events.show', $soon->slug))
            ->assertOk()
            ->assertSee('https://meet.example.com/open-room');
    }

    public function test_reward_is_granted_only_with_the_correct_code(): void
    {
        $user = $this->trainee();
        $event = $this->makeEvent([
            'starts_at' => now()->subMinutes(30),
            'ends_at' => now()->addMinutes(30),
            'attendance_code' => '135791',
        ]);

        $this->actingAs($user)->post(route('events.register', $event->slug));

        // كود غلط: لا حضور ولا مكافأة ولا شهادة
        $this->actingAs($user)->post(route('events.checkin', $event->slug), ['code' => '000000']);

        $registration = EventRegistration::where('event_id', $event->id)->firstOrFail();

        $this->assertFalse((bool) $registration->attended);
        $this->assertSame(0.0, $this->balanceOf($user, 'xp'));
        $this->assertSame(0, Certificate::where('user_id', $user->id)->count());

        // الكود الصحيح يفتح الشهادة والمكافأة
        $this->actingAs($user)->post(route('events.checkin', $event->slug), ['code' => '135791']);

        $registration->refresh();

        $this->assertTrue((bool) $registration->attended);
        $this->assertSame(100.0, $this->balanceOf($user, 'xp'));
        $this->assertSame(1.0, $this->balanceOf($user, 'tickets'));
        $this->assertNotNull($registration->certificate_id);
    }

    public function test_reward_is_paid_only_once_even_with_repeated_correct_codes(): void
    {
        $user = $this->trainee();
        $event = $this->makeEvent([
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addMinutes(50),
            'attendance_code' => '864209',
        ]);

        $this->actingAs($user)->post(route('events.register', $event->slug));
        $this->actingAs($user)->post(route('events.checkin', $event->slug), ['code' => '864209']);
        $this->actingAs($user)->post(route('events.checkin', $event->slug), ['code' => '864209']);
        $this->actingAs($user)->post(route('events.checkin', $event->slug), ['code' => '864209']);

        $this->assertSame(100.0, $this->balanceOf($user, 'xp'));
        $this->assertSame(1.0, $this->balanceOf($user, 'tickets'));

        $rewardRows = Transaction::query()
            ->where('user_id', $user->id)
            ->where('source', 'event')
            ->where('amount', '>', 0)
            ->count();

        $this->assertSame(2, $rewardRows); // XP وتذكرة — سطر واحد لكلّ عملة
        $this->assertSame(1, Certificate::where('user_id', $user->id)->count());
    }

    public function test_check_in_is_closed_before_the_event_starts(): void
    {
        $user = $this->trainee();
        $event = $this->makeEvent([
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
            'attendance_code' => '777777',
        ]);

        $this->actingAs($user)->post(route('events.register', $event->slug));
        $this->actingAs($user)->post(route('events.checkin', $event->slug), ['code' => '777777']);

        $this->assertFalse((bool) EventRegistration::where('event_id', $event->id)->value('attended'));
        $this->assertSame(0.0, $this->balanceOf($user, 'xp'));
    }

    public function test_recording_link_appears_only_after_the_event_ends(): void
    {
        $user = $this->trainee();
        $event = $this->makeEvent([
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addHour(),
            'recording_link' => 'https://drive.example.com/recording-abc',
        ]);

        $this->actingAs($user)
            ->get(route('events.show', $event->slug))
            ->assertOk()
            ->assertSee('https://drive.example.com/recording-abc');
    }
}
