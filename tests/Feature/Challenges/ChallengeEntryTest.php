<?php

namespace Tests\Feature\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeParticipation;

/**
 * دخول التحدّي — كلّ اختبار يقابل قاعدةً منصوصةً في الدستور 15.
 */
class ChallengeEntryTest extends ChallengeTestCase
{
    public function test_entering_a_challenge_charges_the_cost_exactly_once(): void
    {
        $user = $this->trainee(tickets: 20);
        $challenge = Challenge::where('key', 'knowledge_war')->firstOrFail();
        $cost = (float) $challenge->entry_cost;

        $this->actingAs($user)->post(route('challenges.enter', $challenge))->assertRedirect();

        // قفل ذرّيّ: الدخول تاني وهو في نفس الحرب لا يفتح مشاركةً ولا يخصم مرّة ثانية (15.2-1)
        $this->actingAs($user)->post(route('challenges.enter', $challenge))->assertRedirect();

        $this->assertSame(1, ChallengeParticipation::where('user_id', $user->id)->count());
        $this->assertEqualsWithDelta(20 - $cost, $this->ticketsOf($user), 0.001);
    }

    public function test_entry_redirects_to_the_focus_screen(): void
    {
        $user = $this->trainee();
        $challenge = Challenge::where('key', 'knowledge_war')->firstOrFail();

        $this->actingAs($user)->post(route('challenges.enter', $challenge));

        $participation = ChallengeParticipation::where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)
            ->get(route('challenges.play', $participation))
            ->assertOk()
            ->assertSee('باقي من وقتك');
    }

    public function test_insufficient_balance_blocks_entry_and_offers_topup(): void
    {
        $user = $this->trainee(tickets: 0);
        $challenge = Challenge::where('key', 'focus_war')->firstOrFail();

        $this->actingAs($user)
            ->from(route('challenges.index'))
            ->post(route('challenges.enter', $challenge))
            ->assertRedirect(route('challenges.index'))
            ->assertSessionHas('topup_needed');

        $this->assertSame(0, ChallengeParticipation::where('user_id', $user->id)->count());
        $this->assertEqualsWithDelta(0, $this->ticketsOf($user), 0.001);
    }

    public function test_settings_are_locked_while_a_war_is_running_and_unlocked_after(): void
    {
        $user = $this->trainee();
        $challenge = Challenge::where('key', 'knowledge_war')->firstOrFail();

        $this->actingAs($user)->post(route('challenges.enter', $challenge));
        $this->assertTrue($challenge->refresh()->settings_locked);

        $participation = ChallengeParticipation::where('user_id', $user->id)->firstOrFail();
        $this->actingAs($user)->post(route('challenges.submit', $participation));

        $this->assertFalse($challenge->refresh()->settings_locked);
    }

    public function test_correct_answers_never_reach_the_browser(): void
    {
        $user = $this->trainee();
        $challenge = Challenge::where('key', 'estimation_war')->firstOrFail();

        $this->actingAs($user)->post(route('challenges.enter', $challenge));
        $participation = ChallengeParticipation::where('user_id', $user->id)->firstOrFail();

        // التصحيح Server-side والإجابات الصحيحة لا تُرسَل للمتصفح (15.2-3)
        $this->actingAs($user)
            ->get(route('challenges.play', $participation))
            ->assertOk()
            ->assertDontSee('1440')
            ->assertDontSee('tolerance');
    }
}
