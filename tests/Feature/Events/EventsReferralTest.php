<?php

namespace Tests\Feature\Events;

use App\Models\Referral;
use App\Models\Transaction;
use App\Services\Referral\ReferralService;

class EventsReferralTest extends EventsTestCase
{
    public function test_referral_page_shows_the_invite_link_and_the_commission(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->get(route('referral.index'))
            ->assertOk()
            ->assertSee('offer='.$user->code)
            ->assertSee('ادعُ أصدقاءك')
            ->assertSee((string) (int) setting('referral.commission_percent'));
    }

    public function test_deep_link_stores_its_landing_and_opens_the_same_page_after_signup(): void
    {
        $referrer = $this->trainee('الداعي');
        $event = $this->makeEvent(['title_ar' => 'فعاليّة الدعوة العميقة']);

        // الزائر يفتح رابط الدعوة لهذه الفعاليّة تحديدًا
        $this->get('/i/'.$referrer->code.'?type=event&id='.$event->id)
            ->assertRedirect(route('register', ['offer' => $referrer->code]));

        $pending = Referral::query()->whereNull('referred_id')->firstOrFail();

        $this->assertSame('event', $pending->landing_type);
        $this->assertSame($event->id, (int) $pending->landing_id);

        // بعد تسجيله يُنقَل الهدف لسطر دعوته، فتُفتَح له نفس الصفحة
        $invited = $this->trainee('المدعوّ');
        $referral = Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $invited->id,
            'code' => $referrer->code,
            'commission_percent' => setting('referral.commission_percent'),
        ]);

        app(ReferralService::class)->claimLanding($invited, $pending->id);

        $this->assertSame('event', $referral->refresh()->landing_type);
        $this->assertSame(
            route('events.show', $event->slug),
            app(ReferralService::class)->landingUrlFor($invited),
        );
    }

    public function test_welcome_ticket_is_granted_once_and_only_after_activation(): void
    {
        $referrer = $this->trainee('الداعي');
        $invited = $this->trainee('المدعوّ');
        $invited->forceFill(['status' => 'pending'])->save();

        Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $invited->id,
            'code' => $referrer->code,
            'commission_percent' => setting('referral.commission_percent'),
        ]);

        $service = app(ReferralService::class);

        // قبل التفعيل: لا تذكرة ترحيب
        $this->assertFalse($service->grantWelcomeTicket($invited));
        $this->assertSame(0.0, $this->balanceOf($invited, 'tickets'));

        $invited->forceFill(['status' => 'active', 'activated_at' => now()])->save();

        $this->assertTrue($service->grantWelcomeTicket($invited->fresh()));
        $this->assertFalse($service->grantWelcomeTicket($invited->fresh()));
        $this->assertFalse($service->grantWelcomeTicket($invited->fresh()));

        $expected = (float) setting('referral.welcome_tickets');

        $this->assertSame($expected, $this->balanceOf($invited, 'tickets'));
        $this->assertTrue((bool) Referral::where('referred_id', $invited->id)->value('welcome_ticket_granted'));

        $this->assertSame(1, Transaction::query()
            ->where('user_id', $invited->id)
            ->where('source', 'referral')
            ->count());
    }

    public function test_invited_list_shows_who_joined_with_status_and_commission(): void
    {
        $referrer = $this->trainee('الداعي');
        $invited = $this->trainee('سعاد إبراهيم');

        Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $invited->id,
            'code' => $referrer->code,
            'commission_percent' => setting('referral.commission_percent'),
            'commission_earned' => 12.5,
        ]);

        $this->actingAs($referrer)
            ->get(route('referral.index'))
            ->assertOk()
            ->assertSee('سعاد')
            ->assertSee('12.5')
            ->assertSee('مكتمل');
    }

    public function test_event_page_offers_a_deep_invite_link_for_that_event(): void
    {
        $user = $this->trainee();
        $event = $this->makeEvent();

        $this->actingAs($user)
            ->get(route('events.show', $event->slug))
            ->assertOk()
            ->assertSee('/i/'.$user->code)
            ->assertSee('ادعُ صديقك للفعاليّة دي');
    }
}
