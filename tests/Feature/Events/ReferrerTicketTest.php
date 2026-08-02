<?php

namespace Tests\Feature\Events;

use App\Models\Referral;
use App\Models\Transaction;
use App\Services\Referral\ReferralService;

/**
 * 7.6: «عند نجاح الدعوة يحصل **كلٌ من الداعي والمدعو** على تذكرة» —
 * وشرط الصرف: استكمال المدعوّ لبياناته + موافقة الأدمن (= صار حسابه مفعَّلًا).
 *
 * وكانت تذكرة **الداعي** مفقودة تمامًا: المدعوّ يأخذ والداعي لا يأخذ شيئًا.
 */
class ReferrerTicketTest extends EventsTestCase
{
    public function test_both_sides_get_a_ticket_after_the_invited_account_is_activated(): void
    {
        [$referrer, $invited] = $this->pair();
        $service = app(ReferralService::class);

        // قبل التفعيل: لا تذكرة لأيّ طرف
        $this->assertFalse($service->grantReferrerTicket($invited));
        $this->assertSame(0.0, $this->balanceOf($referrer, 'tickets'));

        $invited->forceFill(['status' => 'active', 'activated_at' => now()])->save();

        $granted = $service->settleRewards($invited->fresh());

        $this->assertTrue($granted['invited'], 'المدعوّ يأخذ تذكرته.');
        $this->assertTrue($granted['referrer'], 'والداعي يأخذ تذكرته كذلك (7.6).');

        $this->assertSame((float) setting('referral.welcome_tickets'), $this->balanceOf($invited, 'tickets'));
        $this->assertSame((float) setting('referral.referrer_tickets'), $this->balanceOf($referrer, 'tickets'));
    }

    public function test_the_referrer_ticket_is_granted_only_once(): void
    {
        [$referrer, $invited] = $this->pair();
        $invited->forceFill(['status' => 'active', 'activated_at' => now()])->save();

        $service = app(ReferralService::class);

        $this->assertTrue($service->grantReferrerTicket($invited->fresh()));
        $this->assertFalse($service->grantReferrerTicket($invited->fresh()));
        $this->assertFalse($service->grantReferrerTicket($invited->fresh()));

        $this->assertSame((float) setting('referral.referrer_tickets'), $this->balanceOf($referrer, 'tickets'));
        $this->assertTrue((bool) Referral::where('referred_id', $invited->id)->value('referrer_ticket_granted'));

        $this->assertSame(1, Transaction::query()
            ->where('user_id', $referrer->id)
            ->where('source', 'referral')
            ->count());
    }

    /** حارس كلّ طرف مستقلّ: منح المدعوّ أوّلًا لا يُسقِط حقّ الداعي */
    public function test_granting_the_invited_side_first_does_not_swallow_the_referrer_ticket(): void
    {
        [$referrer, $invited] = $this->pair();
        $invited->forceFill(['status' => 'active', 'activated_at' => now()])->save();

        $service = app(ReferralService::class);

        $this->assertTrue($service->grantWelcomeTicket($invited->fresh()));
        $this->assertTrue($service->grantReferrerTicket($invited->fresh()));

        $this->assertGreaterThan(0, $this->balanceOf($referrer, 'tickets'));
    }

    /** صفحة الدعوات تُسوّي المعلّق للداعي — نداءٌ آمن للتكرار */
    public function test_the_invitations_page_settles_pending_referrer_tickets(): void
    {
        [$referrer, $invited] = $this->pair();
        $invited->forceFill(['status' => 'active', 'activated_at' => now()])->save();

        $this->actingAs($referrer)->get(route('referral.index'))->assertOk();

        $this->assertSame((float) setting('referral.referrer_tickets'), $this->balanceOf($referrer, 'tickets'));

        // زيارة ثانية لا تكرّر الصرف
        $this->actingAs($referrer)->get(route('referral.index'))->assertOk();

        $this->assertSame((float) setting('referral.referrer_tickets'), $this->balanceOf($referrer, 'tickets'));
    }

    /** @return array{0:\App\Models\User,1:\App\Models\User} */
    private function pair(): array
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

        return [$referrer, $invited->fresh()];
    }
}
