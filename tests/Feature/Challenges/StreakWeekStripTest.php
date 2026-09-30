<?php

namespace Tests\Feature\Challenges;

use App\Services\Gamification\StreakService;
use Carbon\CarbonImmutable;

/**
 * أسبوع الاستمراريّة (الفكرة #26): سبع علامات لآخر سبعة أيّام، النهارده معلَّم،
 * وكلّ يوم بحاله الحقيقيّ من سجلّ الستريك لا بتخمين.
 */
class StreakWeekStripTest extends ChallengeTestCase
{
    public function test_week_returns_seven_days_ending_today_with_their_states(): void
    {
        $user = $this->trainee();
        $streaks = app(StreakService::class);
        $streaks->checkIn($user, CarbonImmutable::now()->subDays(2));
        $streaks->checkIn($user, CarbonImmutable::now()->subDay());

        $week = $streaks->week($user);

        $this->assertCount(7, $week);
        $this->assertTrue($week[6]['today']);
        $this->assertSame(CarbonImmutable::now()->toDateString(), $week[6]['date']);
        $this->assertFalse($week[6]['active']);
        $this->assertTrue($week[5]['active']);
        $this->assertTrue($week[4]['active']);
        $this->assertFalse($week[3]['active']);
        $this->assertSame(1, count(array_filter($week, fn ($d) => $d['today'])));
    }

    public function test_the_streak_page_renders_the_seven_marks_with_today_flagged(): void
    {
        $user = $this->trainee();
        app(StreakService::class)->checkIn($user, CarbonImmutable::now()->subDay());

        $html = $this->actingAs($user)->get(route('achievements.streak'))->assertOk()->getContent();

        $this->assertSame(7, substr_count($html, 'class="streak-week-day"'));
        $this->assertSame(1, substr_count($html, 'data-week-today'));
        $this->assertStringContainsString('aria-current="date"', $html);
        $this->assertStringContainsString('data-week-state="active"', $html);
        $this->assertStringContainsString(setting('streaks.screen.week_today', 'النهارده'), $html);
    }
}
