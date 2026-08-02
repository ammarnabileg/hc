<?php

namespace Tests\Feature\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\User;

/**
 * اختبار Feature لكلّ شاشة رئيسيّة في المجال (قاعدة البناء 6).
 */
class ChallengeScreensTest extends ChallengeTestCase
{
    public function test_available_challenges_screen_shows_cards_with_cost_and_reward(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->get(route('challenges.index'))
            ->assertOk()
            ->assertSee('حرب المعلومات', false)
            ->assertSee('تكلفة الدخول', false)
            ->assertSee('ادخل التحدّي', false)
            ->assertSee('رصيدك قبل', false)
            ->assertSee('رصيدك بعد', false);
    }

    public function test_available_challenges_screen_filters_by_type(): void
    {
        $user = $this->trainee();

        // نفحص وصف الكارت لا اسم النوع، لأنّ أسماء الأنواع كلّها موجودة في قائمة الفلتر
        $this->actingAs($user)
            ->get(route('challenges.index', ['type' => 'focus']))
            ->assertOk()
            ->assertSee('عمل عميق بلا مقاطعة', false)
            ->assertDontSee('أوّل غلطة تخرجك', false);
    }

    public function test_paused_war_is_shown_with_its_state_not_hidden(): void
    {
        $user = $this->trainee();
        Challenge::where('key', 'survival_war')->update(['is_active' => false]);

        $this->actingAs($user)
            ->get(route('challenges.index'))
            ->assertOk()
            ->assertSee('حرب البقاء', false)
            ->assertSee('موقوفة مؤقّتًا', false);
    }

    public function test_my_challenges_screen_has_running_and_finished_tabs(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)->get(route('challenges.mine'))->assertOk()->assertSee('جارية', false);
        $this->actingAs($user)->get(route('challenges.mine', ['tab' => 'done']))->assertOk()->assertSee('منتهية', false);
    }

    public function test_champions_board_pins_my_row_at_the_bottom(): void
    {
        $user = $this->trainee();
        $challenge = Challenge::where('key', 'knowledge_war')->firstOrFail();

        $this->actingAs($user)->post(route('challenges.enter', $challenge));
        $participation = ChallengeParticipation::where('user_id', $user->id)->firstOrFail();
        $this->actingAs($user)->post(route('challenges.submit', $participation));

        $this->actingAs($user)
            ->get(route('challenges.leaderboard'))
            ->assertOk()
            ->assertSee('ده إنت', false);
    }

    public function test_xp_leaderboard_pins_my_row_and_shows_period_delta(): void
    {
        $me = $this->trainee();
        $me->forceFill(['xp' => 500])->save();

        $other = $this->trainee();
        $other->forceFill(['xp' => 900])->save();

        $this->actingAs($me)
            ->get(route('achievements.leaderboard'))
            ->assertOk()
            ->assertSee('ده إنت', false)
            ->assertSee('XP', false);
    }

    public function test_streak_screen_explains_the_five_am_club_clearly(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->get(route('achievements.streak'))
            ->assertOk()
            ->assertSee('نادي الخامسة صباحًا', false)
            ->assertSee('أطول ستريك', false)
            ->assertSee('04:50', false);
    }

    public function test_games_screen_shows_the_coming_soon_empty_state(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->get(route('achievements.games'))
            ->assertOk()
            ->assertSee('قريبًا', false);
    }

    public function test_screens_require_permission(): void
    {
        $stranger = User::create([
            'name' => 'زائر', 'email' => 'no-perm@test.local', 'password' => 'secret-password',
            'code' => 'UNOPERM1', 'status' => 'active',
        ]);

        $this->actingAs($stranger)->get(route('challenges.index'))->assertForbidden();
        $this->actingAs($stranger)->get(route('achievements.badges'))->assertForbidden();
    }
}
