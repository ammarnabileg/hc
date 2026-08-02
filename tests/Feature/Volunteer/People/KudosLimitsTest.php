<?php

namespace Tests\Feature\Volunteer\People;

use App\Models\Kudos;
use App\Models\Setting;
use App\Services\Volunteer\People\KudosService;
use Illuminate\Support\Facades\Cache;

/** حدود Kudos (13.4-ي): سبب إلزاميّ · حدّ يوميّ · أفراد مختلفون أسبوعيًّا. */
class KudosLimitsTest extends PeopleTestCase
{
    public function test_reason_is_required(): void
    {
        $service = app(KudosService::class);
        $sender = $this->makeUser('مرسِل');
        $receiver = $this->makeUser('مستقبِل');

        $this->expectException(\RuntimeException::class);
        $service->send($sender, $receiver, '   ');
    }

    public function test_daily_limit_comes_from_settings_and_blocks_politely(): void
    {
        $this->setSetting('kudos.daily_limit', '2');

        $service = app(KudosService::class);
        $sender = $this->makeUser('مرسِل');

        $service->send($sender, $this->makeUser('أ'), 'ساعدني في تسليم المهمّة قبل الموعد.');
        $service->send($sender, $this->makeUser('ب'), 'راجع الملفّ معي بعد ساعات العمل.');

        $blocked = $service->blockedReason($sender, $this->makeUser('ج'), 'سبب واضح');

        $this->assertSame((string) setting('kudos.daily_limit.message'), $blocked);
        $this->assertSame(2, Kudos::where('sender_id', $sender->id)->count());
    }

    public function test_same_person_cannot_be_thanked_twice_in_the_same_week(): void
    {
        $service = app(KudosService::class);
        $sender = $this->makeUser('مرسِل');
        $receiver = $this->makeUser('مستقبِل');

        $service->send($sender, $receiver, 'شرح لي دورة العمل بصبر.');

        $this->assertNotNull($service->blockedReason($sender, $receiver, 'سبب تاني'));
        $this->assertSame(1, Kudos::where('sender_id', $sender->id)->count());
    }

    public function test_weekly_people_limit_counts_distinct_people(): void
    {
        $this->setSetting('kudos.daily_limit', '20');
        $this->setSetting('kudos.weekly_people_limit', '3');

        $service = app(KudosService::class);
        $sender = $this->makeUser('مرسِل');

        foreach (['أ', 'ب', 'ج'] as $name) {
            $service->send($sender, $this->makeUser($name), 'سبب مكتوب لـ'.$name);
        }

        $this->assertSame(3, $service->peopleThisWeek($sender));
        $this->assertSame((string) setting('kudos.weekly_limit.message'),
            $service->blockedReason($sender, $this->makeUser('د'), 'سبب رابع'));
    }

    public function test_cannot_thank_self(): void
    {
        $service = app(KudosService::class);
        $user = $this->makeUser('نفسه');

        $this->assertNotNull($service->blockedReason($user, $user, 'سبب'));
    }

    private function setSetting(string $key, string $value): void
    {
        Setting::updateOrCreate(['key' => $key], [
            'group' => 'kudos', 'label_ar' => $key, 'type' => 'number',
            'default_value' => $value, 'value' => $value,
        ]);

        Cache::forget('settings');
    }
}
