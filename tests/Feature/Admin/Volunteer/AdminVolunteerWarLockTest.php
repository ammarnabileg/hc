<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Services\Admin\Volunteer\WarSettingsService;
use RuntimeException;

/**
 * إعدادات الحروب (12.10-ج): ⭐ **قفل الإعدادات أثناء حرب نشطة** —
 * فلا تتغيّر قواعد اللعبة على لاعبٍ في منتصف جولته.
 */
class AdminVolunteerWarLockTest extends AdminVolunteerTestCase
{
    /** الحرب الساكنة تُحفَظ عادي، والـOverride يُسجَّل. */
    public function test_war_settings_save_when_no_round_is_running(): void
    {
        $admin = $this->grant($this->makeUser(), 'wars_settings.view', 'wars_settings.edit');
        $war = Challenge::where('key', 'knowledge_war')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.gamification.wars.save', $war), [
                'rewards' => ['win' => 3],
                'timers' => ['question_seconds' => 20],
            ])
            ->assertRedirect();

        $this->assertSame(3, (int) WarSettingsService::overrideOf($war->fresh(), 'rewards')['win']);
        $this->assertSame(20, (int) WarSettingsService::overrideOf($war->fresh(), 'timers')['question_seconds']);
    }

    /** ⭐ جولة جارية ⟵ القفل يمنع الحفظ ويشرح السبب بلا لوم. */
    public function test_war_settings_are_locked_while_a_round_is_running(): void
    {
        $admin = $this->grant($this->makeUser(), 'wars_settings.view', 'wars_settings.edit');
        $war = Challenge::where('key', 'focus_war')->firstOrFail();

        ChallengeParticipation::create([
            'challenge_id' => $war->id,
            'user_id' => $this->makeUser('محارب')->id,
            'started_at' => now(),
            'status' => 'running',
        ]);

        $this->assertTrue(WarSettingsService::isLocked($war));

        $this->actingAs($admin)
            ->post(route('admin.gamification.wars.save', $war), ['rewards' => ['win' => 99]])
            ->assertRedirect()
            ->assertSessionHas('status', WarSettingsService::lockMessage());

        $this->assertSame([], WarSettingsService::overrideOf($war->fresh(), 'rewards'));
    }

    /** علم `settings_locked` وحده يكفي للقفل ولو لم تكن هناك مشاركات. */
    public function test_settings_locked_flag_alone_blocks_the_save(): void
    {
        $war = Challenge::where('key', 'survival_war')->firstOrFail();
        $war->forceFill(['settings_locked' => true])->save();

        $this->expectException(RuntimeException::class);

        WarSettingsService::save($war, ['rewards' => ['win' => 5]]);
    }

    /** ↺ Reset يمسح كلّ الـOverrides فتعود الحرب للقواعد العامّة. */
    public function test_reset_clears_all_overrides_for_a_war(): void
    {
        // `wars_settings.manage` منصوصة «مالك المنصّة فقط» (12.2.2) — والعزل يغلب الإسناد
        $admin = $this->platformOwner();
        $war = Challenge::where('key', 'knowledge_war')->firstOrFail();

        WarSettingsService::save($war, ['rewards' => ['win' => 7]], $admin);
        $this->assertNotEmpty(WarSettingsService::overrideOf($war->fresh(), 'rewards'));

        $this->actingAs($admin)
            ->post(route('admin.gamification.wars.reset', $war))
            ->assertRedirect();

        $this->assertSame([], WarSettingsService::overrideOf($war->fresh(), 'rewards'));
    }
}
