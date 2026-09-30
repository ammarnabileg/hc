<?php

namespace Tests\Feature\Events;

use App\Models\Country;
use App\Models\Event;
use App\Models\User;
use App\Services\Learning\UserClock;
use App\Services\Events\EventPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⛔ ما قبله: مواعيد الفعاليّات كانت بمنطقة دولة المستخدم وحدها، متجاهلةً منطقته
 * المختارة أو المكتشَفة تلقائيًّا التي تعتمدها ساعة التعلّم (UserClock)؛ فالمسافر أو
 * مَن اختار منطقةً يرى وقتًا خاطئًا. الآن سُلّم واحد للمنصّة كلّها (الفكرة #20).
 */
class EventTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private function eventAt(string $utc): Event
    {
        $event = new Event;
        $event->starts_at = $utc;

        return $event;
    }

    public function test_the_users_own_timezone_wins_over_the_country_and_the_platform(): void
    {
        $presenter = app(EventPresenter::class);
        $event = $this->eventAt('2026-01-10 10:00:00');

        $manual = new User;
        $manual->timezone = 'Asia/Riyadh';
        $this->assertSame('13:00', $presenter->localStart($event, $manual)->format('H:i'));

        $auto = new User;
        $auto->auto_timezone = 'Europe/Berlin';
        $this->assertSame('11:00', $presenter->localStart($event, $auto)->format('H:i'));

        // بلا مستخدم: نفس سُلّم ساعة التعلّم (إعداد المنصّة وإلّا منطقة التطبيق) لا قيمة خاصّة بالفعاليّات
        $this->assertSame(app(UserClock::class)->timezoneFor(null), $presenter->timezone(null));

        $country = new User;
        $country->setRelation('country', new Country(['timezone' => 'Asia/Dubai']));
        $this->assertSame('14:00', $presenter->localStart($event, $country)->format('H:i'), 'بلا اختيار ولا اكتشاف: منطقة الدولة');
    }
}
