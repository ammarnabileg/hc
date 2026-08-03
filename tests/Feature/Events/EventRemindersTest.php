<?php

namespace Tests\Feature\Events;

use App\Models\AppNotification;
use App\Models\EventReminder;
use App\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * **تذكيرات الفعاليّات المجدولة** (13.3 · 12.11 · 24.3).
 *
 * الحارس المُثبَت سقوطه هنا هو **عدم التكرار**: تشغيلتان متتاليتان لا تُوصِلان
 * التذكير نفسه مرّتين لنفس المستلِم — وهو الفرق بين تذكيرٍ وإغراق.
 */
class EventRemindersTest extends EventsTestCase
{
    public function test_the_command_is_registered_and_scheduled(): void
    {
        // بلا أمرٍ لا مُلتقِط، وبلا جدولةٍ لا يعمل من نفسه — والاثنان شرط
        $this->assertArrayHasKey('events:remind', Artisan::all());

        $scheduled = collect(app(Schedule::class)->events())
            ->map(fn ($event) => $event->command ?? '')
            ->filter(fn (string $command) => str_contains($command, 'events:remind'));

        $this->assertTrue($scheduled->isNotEmpty(), 'events:remind مجدول في routes/console.php');
    }

    public function test_a_registrant_is_reminded_once_and_never_twice(): void
    {
        $this->resetMailbox();

        $user = $this->trainee();

        // قبل ساعة بالضبط: الموعد الثاني في القائمة الافتراضيّة (24.3)
        $event = $this->makeEvent([
            'starts_at' => now()->addMinutes(45),
            'ends_at' => now()->addMinutes(105),
        ]);

        $this->actingAs($user)->post(route('events.register', $event->slug));

        $this->artisan('events:remind')->assertSuccessful();

        $rows = EventReminder::where('event_id', $event->id)->where('user_id', $user->id)->get();
        $this->assertSame(2, $rows->count(), 'صفّ لكلّ قناة: جرس + بريد');
        $this->assertTrue($rows->every(fn ($row) => $row->status === 'sent' && $row->sent_at !== null));
        $this->assertSame([60], $rows->pluck('offset_minutes')->unique()->values()->all());

        $bell = AppNotification::where('user_id', $user->id)->count();
        $this->assertCount(1, $this->mailbox(), 'بريدٌ واحد خرج فعلًا');

        // ⭐ التشغيلة الثانية: لا صفّ جديد ولا إشعار جديد ولا بريد ثانٍ
        $this->artisan('events:remind')->assertSuccessful();

        $this->assertSame(2, EventReminder::where('event_id', $event->id)->count(), 'ما زاد صفّ');
        $this->assertSame($bell, AppNotification::where('user_id', $user->id)->count(), 'ما زاد إشعار');
        $this->assertCount(1, $this->mailbox(), 'ولا بريد ثانٍ');
    }

    public function test_a_far_event_is_not_reminded_before_its_moment(): void
    {
        $this->resetMailbox();

        $user = $this->trainee();

        // بعد ثلاثة أيّام: لا موعد تذكيرٍ حان بعد (الأبعد = قبل يوم)
        $event = $this->makeEvent([
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHour(),
        ]);

        $this->actingAs($user)->post(route('events.register', $event->slug));

        $this->artisan('events:remind')->assertSuccessful();

        $this->assertSame(0, EventReminder::count(), 'ما فيش تذكير قبل موعده');
    }

    public function test_only_registrants_are_reminded(): void
    {
        $this->resetMailbox();

        $registrant = $this->trainee();
        $bystander = $this->trainee('مش مسجّل');

        $event = $this->makeEvent([
            'starts_at' => now()->addMinutes(30),
            'ends_at' => now()->addMinutes(90),
        ]);

        $this->actingAs($registrant)->post(route('events.register', $event->slug));

        $this->artisan('events:remind')->assertSuccessful();

        $this->assertSame(1, EventReminder::where('user_id', $registrant->id)->where('channel', 'bell')->count());
        $this->assertSame(0, EventReminder::where('user_id', $bystander->id)->count());
    }

    public function test_the_reminder_offsets_are_a_setting_not_a_hardcoded_pair(): void
    {
        $this->resetMailbox();

        // الأدمن يغيّر القائمة ⟵ السلوك يتبعه فورًا (2.13)
        Setting::where('key', 'events.reminder.offsets_minutes')->update(['value' => '[15]']);
        Setting::where('key', 'events.reminder.channels')->update(['value' => '["bell"]']);
        Cache::forget('settings');

        $user = $this->trainee();

        $near = $this->makeEvent(['starts_at' => now()->addMinutes(10), 'ends_at' => now()->addHour()]);
        $mid = $this->makeEvent(['starts_at' => now()->addMinutes(50), 'ends_at' => now()->addHours(2)]);

        $this->actingAs($user)->post(route('events.register', $near->slug));
        $this->actingAs($user)->post(route('events.register', $mid->slug));

        $this->artisan('events:remind')->assertSuccessful();

        // الموعد الوحيد صار «قبل ربع ساعة»: القريبة تُذكَّر، والتي بعد 50 دقيقة لا
        $this->assertSame(1, EventReminder::where('event_id', $near->id)->count());
        $this->assertSame(0, EventReminder::where('event_id', $mid->id)->count());
        $this->assertSame(15, (int) EventReminder::where('event_id', $near->id)->value('offset_minutes'));

        // وقناة البريد مطفأة ⟵ لا بريد أصلًا
        $this->assertCount(0, $this->mailbox(), 'القناة المطفأة لا تُرسِل');
    }

    public function test_reminders_stop_when_the_event_switches_them_off(): void
    {
        $this->resetMailbox();

        $user = $this->trainee();
        $event = $this->makeEvent([
            'starts_at' => now()->addMinutes(30),
            'ends_at' => now()->addMinutes(90),
            'reminders_enabled' => false,
        ]);

        $this->actingAs($user)->post(route('events.register', $event->slug));

        $this->artisan('events:remind')->assertSuccessful();

        $this->assertSame(0, EventReminder::count(), 'الفعاليّة المُطفأ تذكيرُها لا تُذكِّر');
    }

    /** صندوق البريد الحقيقيّ لناقل `array` — إثباتٌ أنّ رسالةً خرجت فعلًا */
    private function mailbox(): array
    {
        return Mail::mailer()->getSymfonyTransport()->messages()->all();
    }

    private function resetMailbox(): void
    {
        Mail::mailer()->getSymfonyTransport()->flush();
    }
}
