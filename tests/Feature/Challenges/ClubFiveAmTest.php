<?php

namespace Tests\Feature\Challenges;

use App\Models\Country;
use App\Models\StreakDay;
use App\Models\StreakReward;
use App\Services\Admin\Volunteer\SettingsWriter;
use App\Services\Gamification\StreakService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * نادي الخامسة والسلاسل (الدستور 7.2 · 7.1).
 *
 * أربعة أشياء كانت غائبة ويُثبِتها هذا الملفّ:
 * النافذة بتوقيت المستخدم · سلّم XP · تذكرة المكافأة · درع التجميد.
 */
class ClubFiveAmTest extends ChallengeTestCase
{
    private function country(string $iso2, string $timezone): Country
    {
        return Country::updateOrCreate(['iso2' => $iso2], [
            'name_ar' => $iso2,
            'name_en' => $iso2,
            'timezone' => $timezone,
            'is_active' => true,
        ]);
    }

    // ---------------------------------------------------------------- النافذة بتوقيت المستخدم

    public function test_the_club_window_follows_each_user_local_time_not_the_server(): void
    {
        $cairo = $this->trainee();
        $cairo->forceFill(['country_id' => $this->country('EG', 'Africa/Cairo')->id])->save();

        $jakarta = $this->trainee();
        $jakarta->forceFill(['country_id' => $this->country('ID', 'Asia/Jakarta')->id])->save();

        $streaks = app(StreakService::class);

        // 02:00 UTC = 05:00 بالقاهرة (داخل النافذة) و09:00 بجاكرتا (خارجها)
        $moment = CarbonImmutable::parse('2026-07-01 02:00:00', 'UTC');

        $streaks->checkIn($cairo->refresh(), $moment);
        $streaks->checkIn($jakarta->refresh(), $moment);

        $this->assertSame(1, (int) $streaks->forUser($cairo)->club_5am_count);
        $this->assertSame(0, (int) $streaks->forUser($jakarta)->club_5am_count);
    }

    public function test_the_window_state_is_reported_per_user(): void
    {
        $cairo = $this->trainee();
        $cairo->forceFill(['country_id' => $this->country('EG', 'Africa/Cairo')->id])->save();

        $streaks = app(StreakService::class);
        $moment = CarbonImmutable::parse('2026-07-01 02:00:00', 'UTC'); // 05:00 بالقاهرة

        $this->assertTrue($streaks->windowIsOpenFor($cairo->refresh(), $moment));
        $this->assertSame('Africa/Cairo', $streaks->timezoneFor($cairo));
    }

    public function test_the_top_bar_appears_only_inside_the_window_of_that_user(): void
    {
        $user = $this->trainee();
        $user->forceFill(['country_id' => $this->country('EG', 'Africa/Cairo')->id])->save();

        // 05:00 بالقاهرة ⟵ الشريط ظاهر
        Carbon::setTestNow(Carbon::parse('2026-07-01 02:00:00', 'UTC'));

        $this->actingAs($user->refresh())
            ->get(route('achievements.streak'))
            ->assertOk()
            ->assertSee('نادي الخامسة مفتوح دلوقتي', false);

        // 09:00 بالقاهرة ⟵ الشريط يختفي
        Carbon::setTestNow(Carbon::parse('2026-07-01 06:00:00', 'UTC'));

        $this->actingAs($user->refresh())
            ->get(route('achievements.streak'))
            ->assertOk()
            ->assertDontSee('نادي الخامسة مفتوح دلوقتي', false);

        Carbon::setTestNow();
    }

    // ---------------------------------------------------------------- سلّم XP (100…350)

    public function test_the_xp_ladder_gives_100_then_150_up_to_350(): void
    {
        $streaks = app(StreakService::class);

        $this->assertSame(100, $streaks->xpForClubDay(1));
        $this->assertSame(100, $streaks->xpForClubDay(10));
        $this->assertSame(150, $streaks->xpForClubDay(11));
        $this->assertSame(150, $streaks->xpForClubDay(25));
        $this->assertSame(200, $streaks->xpForClubDay(26));
        $this->assertSame(250, $streaks->xpForClubDay(46));
        $this->assertSame(300, $streaks->xpForClubDay(76));
        $this->assertSame(350, $streaks->xpForClubDay(126));
        $this->assertSame(350, $streaks->xpForClubDay(1000));
    }

    public function test_club_attendance_awards_ladder_xp_once_per_day(): void
    {
        $user = $this->trainee();
        $user->forceFill(['country_id' => $this->country('EG', 'Africa/Cairo')->id, 'xp' => 0])->save();

        $streaks = app(StreakService::class);
        $inside = CarbonImmutable::parse('2026-07-01 05:00:00', 'Africa/Cairo');

        $first = $streaks->checkIn($user->refresh(), $inside);

        $this->assertSame(100, $first['xp']);
        $this->assertSame(100, (int) $user->refresh()->xp);

        // إعادة الضغط في نفس اليوم لا تكرّر المنحة
        $again = $streaks->checkIn($user->refresh(), $inside->addMinutes(5));

        $this->assertSame(0, $again['xp']);
        $this->assertSame(100, (int) $user->refresh()->xp);
    }

    public function test_attendance_outside_the_window_earns_no_club_xp(): void
    {
        $user = $this->trainee();
        $user->forceFill(['country_id' => $this->country('EG', 'Africa/Cairo')->id, 'xp' => 0])->save();

        $result = app(StreakService::class)->checkIn(
            $user->refresh(),
            CarbonImmutable::parse('2026-07-01 09:00:00', 'Africa/Cairo'),
        );

        $this->assertSame(0, $result['xp']);
        $this->assertFalse($result['club']);
        $this->assertSame(0, (int) $user->refresh()->xp);
    }

    // ---------------------------------------------------------------- تذكرة مكافأة السلسلة

    public function test_completing_the_cycle_grants_one_gift_ticket_only_once(): void
    {
        $user = $this->trainee(0);
        $streaks = app(StreakService::class);

        $start = CarbonImmutable::now('UTC')->subDays(6)->setTime(9, 0);

        for ($i = 0; $i < 7; $i++) {
            $streaks->checkIn($user->refresh(), $start->addDays($i));
        }

        $this->assertSame(7, (int) $streaks->forUser($user->refresh())->current_days);
        $this->assertTrue($streaks->rewardIsDue($user->refresh()));

        $first = $streaks->claimReward($user->refresh());

        $this->assertTrue($first['ok']);
        $this->assertSame(1.0, $this->ticketsOf($user->refresh()));
        $this->assertDatabaseCount('streak_rewards', 1);

        // الضغط مرّتين لا يصرف تذكرتين — السجلّ هو المانع لا الواجهة
        $second = $streaks->claimReward($user->refresh());

        $this->assertFalse($second['ok']);
        $this->assertSame(1.0, $this->ticketsOf($user->refresh()));
        $this->assertDatabaseCount('streak_rewards', 1);
    }

    public function test_the_reward_button_is_refused_before_the_cycle_completes(): void
    {
        $user = $this->trainee(0);
        $streaks = app(StreakService::class);

        $streaks->checkIn($user->refresh(), CarbonImmutable::now('UTC')->setTime(9, 0));

        $this->assertFalse($streaks->rewardIsDue($user->refresh()));
        $this->assertFalse($streaks->claimReward($user->refresh())['ok']);
        $this->assertSame(0.0, $this->ticketsOf($user->refresh()));
    }

    public function test_the_reward_route_is_guarded_and_answers_immediately(): void
    {
        $user = $this->trainee(0);

        $this->actingAs($user)
            ->from(route('achievements.streak'))
            ->post(route('achievements.streak.reward'))
            ->assertRedirect(route('achievements.streak'))
            ->assertSessionHas('status');

        $this->assertSame(0, StreakReward::query()->count());
    }

    // ---------------------------------------------------------------- درع التجميد

    public function test_a_freeze_shield_costs_a_ticket_and_saves_the_streak(): void
    {
        $user = $this->trainee(5);
        $streaks = app(StreakService::class);

        $now = CarbonImmutable::now('UTC')->setTime(9, 0);

        // حضر أوّل أمس واليوم، وفاته أمس ⟵ السلسلة تساوي 1
        $streaks->checkIn($user->refresh(), $now->subDays(2));
        $streaks->checkIn($user->refresh(), $now);

        $this->assertSame(1, (int) $streaks->forUser($user->refresh())->current_days);

        $result = $streaks->buyFreeze($user->refresh());

        $this->assertTrue($result['ok']);
        $this->assertSame(4.0, $this->ticketsOf($user->refresh()));
        $this->assertSame(3, (int) $streaks->forUser($user->refresh())->current_days);

        $this->assertDatabaseHas('streak_days', [
            'user_id' => $user->id,
            'is_freeze' => true,
        ]);
    }

    public function test_a_freeze_is_refused_when_no_missed_day_breaks_the_chain(): void
    {
        $user = $this->trainee(5);
        $streaks = app(StreakService::class);

        $now = CarbonImmutable::now('UTC')->setTime(9, 0);
        $streaks->checkIn($user->refresh(), $now->subDay());
        $streaks->checkIn($user->refresh(), $now);

        // السلسلة متّصلة — والدرع يحمي يومًا فايتًا لا يشتري ماضيًا لم يحضره
        $result = $streaks->buyFreeze($user->refresh());

        $this->assertFalse($result['ok']);
        $this->assertSame(5.0, $this->ticketsOf($user->refresh()));
    }

    public function test_a_freeze_is_refused_without_enough_tickets(): void
    {
        $user = $this->trainee(0);
        $streaks = app(StreakService::class);

        $now = CarbonImmutable::now('UTC')->setTime(9, 0);
        $streaks->checkIn($user->refresh(), $now->subDays(2));
        $streaks->checkIn($user->refresh(), $now);

        $result = $streaks->buyFreeze($user->refresh());

        $this->assertFalse($result['ok']);
        $this->assertSame(0, StreakDay::query()->where('user_id', $user->id)->where('is_freeze', true)->count());
    }

    public function test_the_monthly_freeze_cap_is_enforced(): void
    {
        $user = $this->trainee(20);
        $streaks = app(StreakService::class);

        SettingsWriter::put('streaks.max_freezes_per_month', 1);
        SettingsWriter::put('streaks.freeze_max_age_days', 3);

        // نثبّت اليوم في منتصف الشهر كي تقع كلّ الأيّام في شهر واحد
        Carbon::setTestNow(Carbon::parse('2026-07-20 09:00:00', 'UTC'));

        $now = CarbonImmutable::parse('2026-07-20 09:00:00', 'UTC');
        $streaks->checkIn($user->refresh(), $now->subDays(4)); // حضورٌ قديم يتّصل به الدرع
        $streaks->checkIn($user->refresh(), $now);

        $this->assertTrue($streaks->buyFreeze($user->refresh())['ok']);

        // اليوم التالي يستحقّ الحماية لكنّ السقف الشهريّ استُهلك
        $this->assertNotNull($streaks->freezableDay($user->refresh()));

        $second = $streaks->buyFreeze($user->refresh());

        $this->assertFalse($second['ok']);
        $this->assertStringContainsString('1', $second['message']);

        Carbon::setTestNow();
    }

    // ---------------------------------------------------------------- توحيد المفاتيح

    public function test_the_admin_screen_and_the_engine_read_the_very_same_setting_keys(): void
    {
        $this->assertDatabaseHas('settings', ['key' => 'streaks.club5am.window_start']);
        $this->assertDatabaseHas('settings', ['key' => 'streaks.club5am.window_end']);
        $this->assertDatabaseHas('settings', ['key' => 'streaks.reward_days']);

        // ولا مفتاح يتيم بالصيغة القديمة
        $this->assertDatabaseMissing('settings', ['key' => 'streaks.club_5am.window_start']);
        $this->assertDatabaseMissing('settings', ['key' => 'streaks.club_5am.window_end']);
        $this->assertDatabaseMissing('settings', ['key' => 'streaks.reward.every_days']);

        // وما يكتبه الأدمن هو ما يقرؤه المحرّك بالضبط
        SettingsWriter::put('streaks.club5am.window_start', '04:00');

        $this->assertSame('04:00', app(StreakService::class)->clubWindow()['start']);
    }
}
