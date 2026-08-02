<?php

namespace Tests\Feature\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Services\Gamification\ChallengeService;

/**
 * شاشة التحدّي: الحفظ اللحظيّ · انتهاء الوقت · الاستئناف بعد الانقطاع.
 */
class ChallengePlayTest extends ChallengeTestCase
{
    public function test_answers_are_saved_on_the_server_the_moment_they_are_given(): void
    {
        $user = $this->trainee();
        $participation = $this->start($user, 'knowledge_war');

        $this->actingAs($user)
            ->postJson(route('challenges.answer', $participation), ['index' => 0, 'value' => '0'])
            ->assertOk()
            ->assertJson(['saved' => true, 'answered' => 1, 'status' => 'running']);

        // Autosave: الإجابة محفوظة قبل أيّ تسليم (15.1)
        $this->assertSame('0', $participation->refresh()->progress['answers']['0']);
        $this->assertEqualsWithDelta(1.0, (float) $participation->score, 0.001);
    }

    public function test_time_up_submits_automatically_on_the_server(): void
    {
        $user = $this->trainee();
        $participation = $this->start($user, 'knowledge_war');

        $this->actingAs($user)
            ->postJson(route('challenges.answer', $participation), ['index' => 0, 'value' => '0'])
            ->assertOk();

        // نرجّع البداية لما بعد المدّة: أيّ لمسة بعدها = تسليم تلقائيّ
        $minutes = (int) $participation->challenge->duration_minutes;
        $participation->forceFill(['started_at' => now()->subMinutes($minutes + 1)])->save();

        $this->actingAs($user)
            ->get(route('challenges.play', $participation))
            ->assertRedirect(route('challenges.result', $participation));

        $participation->refresh();

        $this->assertSame('finished', $participation->status);
        $this->assertTrue($participation->progress['auto_submitted']);
        $this->assertNotNull($participation->finished_at);
        // إجاباته المحفوظة اتحسبت — الانقطاع/انتهاء الوقت لا يلغي المجهود (15.2-2)
        $this->assertEqualsWithDelta(1.0, (float) $participation->score, 0.001);
    }

    public function test_answers_after_the_deadline_are_rejected_without_losing_progress(): void
    {
        $user = $this->trainee();
        $participation = $this->start($user, 'knowledge_war');

        $minutes = (int) $participation->challenge->duration_minutes;
        $participation->forceFill(['started_at' => now()->subMinutes($minutes + 1)])->save();

        $this->actingAs($user)
            ->postJson(route('challenges.answer', $participation), ['index' => 0, 'value' => '0'])
            ->assertOk()
            ->assertJson(['saved' => false, 'status' => 'finished']);

        $this->assertSame('finished', $participation->refresh()->status);
    }

    public function test_reload_resumes_the_same_participation_with_saved_progress(): void
    {
        $user = $this->trainee();
        $participation = $this->start($user, 'knowledge_war');

        $this->actingAs($user)->postJson(route('challenges.answer', $participation), ['index' => 0, 'value' => '1']);
        $this->actingAs($user)->postJson(route('challenges.answer', $participation), ['index' => 1, 'value' => '1']);

        // Refresh أثناء تحدٍّ نشط ⟵ نفس التحدّي بإجاباته المحفوظة (15.2-7)
        $this->actingAs($user)->get(route('challenges.play', $participation))->assertOk();

        $progress = $participation->refresh()->progress;
        $this->assertSame('1', $progress['answers']['0']);
        $this->assertSame('1', $progress['answers']['1']);
    }

    public function test_another_user_cannot_open_my_focus_screen(): void
    {
        $mine = $this->start($this->trainee(), 'knowledge_war');
        $stranger = $this->trainee();

        $this->actingAs($stranger)->get(route('challenges.play', $mine))->assertForbidden();
    }

    public function test_winning_pays_the_reward_and_records_an_active_day(): void
    {
        $user = $this->trainee();
        $participation = $this->start($user, 'estimation_war');

        // كلّ التقديرات مضبوطة ⟵ فوز
        $this->actingAs($user)->postJson(route('challenges.answer', $participation), ['index' => 0, 'value' => 1440]);
        $this->actingAs($user)->postJson(route('challenges.answer', $participation), ['index' => 1, 'value' => 365]);
        $this->actingAs($user)->postJson(route('challenges.answer', $participation), ['index' => 2, 'value' => 56]);

        $this->actingAs($user)->post(route('challenges.submit', $participation))
            ->assertRedirect(route('challenges.result', $participation));

        $participation->refresh();
        $this->assertSame('win', $participation->result);

        $rewardXp = (int) $participation->challenge->rewards['xp'];
        $this->assertSame($rewardXp, (int) $user->refresh()->xp);
        $this->assertDatabaseHas('streak_days', ['user_id' => $user->id]);
    }

    private function start($user, string $key): ChallengeParticipation
    {
        $challenge = Challenge::where('key', $key)->firstOrFail();

        return app(ChallengeService::class)->enter($user, $challenge);
    }
}
