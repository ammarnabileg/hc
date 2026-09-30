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
        // بساعة المستخدم لا بساعة الخادم: قرب منتصف الليل يختلف اليومان (سقط الاختبار مرّةً في 21:xx UTC)
        $now = CarbonImmutable::now($streaks->timezoneFor($user));
        $streaks->checkIn($user, $now->subDays(2));
        $streaks->checkIn($user, $now->subDay());

        $week = $streaks->week($user);

        $this->assertCount(7, $week);
        $this->assertTrue($week[6]['today']);
        $this->assertSame($now->toDateString(), $week[6]['date']);
        $this->assertFalse($week[6]['active']);
        $this->assertTrue($week[5]['active']);
        $this->assertTrue($week[4]['active']);
        $this->assertFalse($week[3]['active']);
        $this->assertSame(1, count(array_filter($week, fn ($d) => $d['today'])));
    }

    public function test_each_day_state_carries_its_own_symbol_not_only_a_colour(): void
    {
        $user = $this->trainee();
        $now = CarbonImmutable::now(app(StreakService::class)->timezoneFor($user));
        \App\Models\StreakDay::create(['user_id' => $user->id, 'day' => $now->subDays(3)->toDateString(), 'club_5am' => true, 'is_freeze' => false]);
        \App\Models\StreakDay::create(['user_id' => $user->id, 'day' => $now->subDays(2)->toDateString(), 'club_5am' => false, 'is_freeze' => true]);
        \App\Models\StreakDay::create(['user_id' => $user->id, 'day' => $now->subDay()->toDateString(), 'club_5am' => false, 'is_freeze' => false]);

        $html = $this->actingAs($user)->get(route('achievements.streak'))->assertOk()->getContent();

        foreach (['club', 'freeze', 'active'] as $state) {
            $this->assertStringContainsString('data-week-state="'.$state.'"', $html);
        }
        // ثلاثة رموز مختلفة داخل الشريط: نجمة للنادي، درع للتجميد، صحّ للحضور
        preg_match('/<ol class="streak-week.*?<\\/ol>/s', $html, $m);
        $strip = $m[0] ?? '';
        $this->assertNotSame('', $strip);
        $this->assertStringContainsString('M12 3.5 14.4 9', $strip, 'رمز النجمة للنادي');
        $this->assertStringContainsString('M12 3 5 6v6', $strip, 'رمز الدرع للتجميد');
        $this->assertStringContainsString('m5 12 4 4L20 5', $strip, 'رمز الصحّ للحضور');
        $this->assertSame(1, substr_count($strip, 'm5 12 4 4L20 5'), 'الصحّ للحضور وحده');

        // والخريطة الحراريّة كذلك: علامة شكليّة داخل خليّة النادي وخليّة الدرع
        $this->assertStringContainsString('data-heat-mark="club"', $html);
        $this->assertStringContainsString('data-heat-mark="freeze"', $html);
    }

    public function test_the_streak_page_renders_the_seven_marks_with_today_flagged(): void
    {
        $user = $this->trainee();
        app(StreakService::class)->checkIn($user, CarbonImmutable::now(app(StreakService::class)->timezoneFor($user))->subDay());

        $html = $this->actingAs($user)->get(route('achievements.streak'))->assertOk()->getContent();

        $this->assertSame(7, substr_count($html, 'class="streak-week-day"'));
        $this->assertSame(1, substr_count($html, 'data-week-today'));
        $this->assertStringContainsString('aria-current="date"', $html);
        $this->assertStringContainsString('data-week-state="active"', $html);
        $this->assertStringContainsString(setting('streaks.screen.week_today', 'النهارده'), $html);
    }
}
