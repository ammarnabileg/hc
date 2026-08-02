<?php

namespace Tests\Feature\Challenges;

use App\Models\Badge;
use App\Models\BadgeUser;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\StreakService;
use Carbon\CarbonImmutable;

/**
 * الستريك ونادي الخامسة (7.2) والشارات (7.4).
 */
class StreakAndBadgeTest extends ChallengeTestCase
{
    public function test_streak_counts_consecutive_days_and_keeps_the_best(): void
    {
        $user = $this->trainee();
        $streaks = app(StreakService::class);
        $tz = 'Africa/Cairo';

        $streaks->record($user, CarbonImmutable::parse('2026-07-01 09:00', $tz));
        $streaks->record($user, CarbonImmutable::parse('2026-07-02 20:00', $tz));
        // نفس اليوم مرّة تانية لا يزيد العدّاد
        $streak = $streaks->record($user, CarbonImmutable::parse('2026-07-02 22:00', $tz));

        $this->assertSame(2, (int) $streak->current_days);
        $this->assertSame(2, (int) $streak->best_days);

        // انقطاع ⟵ العدّ يبدأ من جديد، وأطول ستريك يفضل محفوظًا
        $streak = $streaks->record($user, CarbonImmutable::parse('2026-07-06 12:00', $tz));

        $this->assertSame(1, (int) $streak->current_days);
        $this->assertSame(2, (int) $streak->best_days);
        $this->assertDatabaseCount('streak_days', 3);
    }

    public function test_streak_is_broken_only_when_the_last_active_day_is_older_than_yesterday(): void
    {
        $streaks = app(StreakService::class);

        $fresh = $this->trainee();
        $streaks->record($fresh, CarbonImmutable::now('Africa/Cairo')->subDay());
        $this->assertFalse($streaks->isBroken($streaks->forUser($fresh)));

        $stale = $this->trainee();
        $streaks->record($stale, CarbonImmutable::now('Africa/Cairo')->subDays(5));
        $this->assertTrue($streaks->isBroken($streaks->forUser($stale)));
    }

    public function test_five_am_club_counts_only_inside_the_window(): void
    {
        $user = $this->trainee();
        $streaks = app(StreakService::class);
        $tz = 'Africa/Cairo';

        $streaks->record($user, CarbonImmutable::parse('2026-07-01 05:00', $tz)); // داخل النافذة
        $streaks->record($user, CarbonImmutable::parse('2026-07-02 07:30', $tz)); // برّه النافذة
        $streak = $streaks->record($user, CarbonImmutable::parse('2026-07-03 04:55', $tz)); // داخل النافذة

        $this->assertSame(2, (int) $streak->club_5am_count);
        $this->assertDatabaseHas('streak_days', ['user_id' => $user->id, 'club_5am' => true]);
    }

    public function test_broken_streak_shows_an_encouraging_neutral_message(): void
    {
        $user = $this->trainee();
        app(StreakService::class)->record($user, CarbonImmutable::now()->subDays(9));

        $this->actingAs($user)
            ->get(route('achievements.streak'))
            ->assertOk()
            ->assertSee('ابدأ من جديد النهارده', false);
    }

    public function test_check_in_records_today_and_answers_immediately(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->from(route('achievements.streak'))
            ->post(route('achievements.streak.checkin'))
            ->assertRedirect(route('achievements.streak'))
            ->assertSessionHas('status');

        $this->assertTrue(app(StreakService::class)->recordedToday($user));
    }

    public function test_badges_are_awarded_automatically_by_their_condition(): void
    {
        $user = $this->trainee();
        $user->forceFill(['xp' => 1200])->save();

        app(BadgeService::class)->evaluate($user);

        $badge = Badge::where('key', 'xp_1000')->firstOrFail();
        $this->assertTrue(BadgeUser::where('user_id', $user->id)->where('badge_id', $badge->id)->exists());

        // المقفولة تفضل مقفولة لحدّ ما شرطها يتحقّق
        $locked = Badge::where('key', 'xp_10000')->firstOrFail();
        $this->assertFalse(BadgeUser::where('user_id', $user->id)->where('badge_id', $locked->id)->exists());
    }

    public function test_badges_screen_writes_the_unlock_condition_explicitly(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->get(route('achievements.badges'))
            ->assertOk()
            ->assertSee('اجمع 1000 XP.', false)
            ->assertSee('خلّص أوّل تحدّي واحد.', false);
    }
}
