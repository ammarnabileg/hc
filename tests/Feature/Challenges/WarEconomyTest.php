<?php

namespace Tests\Feature\Challenges;

use App\Models\ChallengeParticipation;
use App\Models\WarMatch;
use App\Services\Gamification\Wars\WarMatchService;

/**
 * اقتصاد الحروب (15.2) — أخطر جزء في المجال كلّه.
 *
 * كلّ اختبار هنا يقابل قاعدةً منصوصةً: البوّابة · المحصّلة الصفريّة ·
 * التعادل · الانسحاب — ومنع الفارمينج فوق ذلك كلّه.
 */
class WarEconomyTest extends ChallengeTestCase
{
    /** ⭐ 15.2-6: مجموع تذاكر النظام **لا يتغيّر** بعد أيّ مواجهة محسومة. */
    public function test_system_ticket_supply_is_unchanged_after_a_decided_match(): void
    {
        $winner = $this->trainee(tickets: 20);
        $loser = $this->trainee(tickets: 20);

        $before = $this->systemTickets();

        $match = $this->startMatch($winner, $loser);
        $service = app(WarMatchService::class);

        // الفائز يجاوب كلّ الأسئلة صحّ والخاسر لا يجاوب شيئًا
        foreach ((array) $match->questions as $i => $question) {
            $service->answer($match, $winner, $i, $question['answer']);
        }

        $service->finishSide($match, $service->sideOf($match, $winner));
        $service->finishSide($match->refresh(), $service->sideOf($match, $loser));

        $match->refresh();

        $this->assertSame('finished', $match->status);
        $this->assertSame($winner->id, (int) $match->winner_id);

        // ما كسبه الفائز هو بعينه ما خسره الخاسر — ولا تذكرة سُكّت من العدم
        $this->assertEqualsWithDelta($before, $this->systemTickets(), 0.001);
        $this->assertEqualsWithDelta(22, $this->ticketsOf($winner), 0.001);
        $this->assertEqualsWithDelta(18, $this->ticketsOf($loser), 0.001);
    }

    /** ⭐ 15.2-5: التعادل لا خصم ولا إضافة — والمجموع ثابت. */
    public function test_a_draw_moves_nothing_at_all(): void
    {
        $a = $this->trainee(tickets: 20);
        $b = $this->trainee(tickets: 20);

        $before = $this->systemTickets();
        $match = $this->startMatch($a, $b);
        $service = app(WarMatchService::class);

        // الاثنان لم يجاوبا ⟵ نفس عدد الإجابات الصحيحة = تعادل
        $service->finishSide($match, $service->sideOf($match, $a));
        $service->finishSide($match->refresh(), $service->sideOf($match, $b));

        $match->refresh();

        $this->assertSame('draw', $match->outcome);
        $this->assertNull($match->winner_id);
        $this->assertEqualsWithDelta(20, $this->ticketsOf($a), 0.001);
        $this->assertEqualsWithDelta(20, $this->ticketsOf($b), 0.001);
        $this->assertEqualsWithDelta($before, $this->systemTickets(), 0.001);
    }

    /** ⭐ 15.0: الانسحاب = خسارة (−2) + عقوبة (−10) = −12، والخصم يفوز (+2). */
    public function test_withdrawal_costs_twelve_and_gives_the_rival_the_win(): void
    {
        $quitter = $this->trainee(tickets: 20);
        $rival = $this->trainee(tickets: 20);

        $match = $this->startMatch($quitter, $rival);

        app(WarMatchService::class)->withdraw($match, $quitter);

        $match->refresh();

        $this->assertSame('withdraw', $match->outcome);
        $this->assertSame($rival->id, (int) $match->winner_id);
        $this->assertEqualsWithDelta(8, $this->ticketsOf($quitter), 0.001);
        $this->assertEqualsWithDelta(22, $this->ticketsOf($rival), 0.001);

        // العقوبة تُحرَق ولا تذهب لأحد — سحبٌ من الاقتصاد لا ضخّ فيه
        $this->assertEqualsWithDelta(10, (float) $match->settlement['penalty'], 0.001);
        $this->assertEqualsWithDelta(2, (float) $match->settlement['moved'], 0.001);
    }

    /** ⭐ 15.2-4: بوّابة ≥ 12 تذكرة — لا استعداد بأقلّ منها. */
    public function test_readiness_requires_twelve_tickets(): void
    {
        $poor = $this->trainee(tickets: 11);
        $challenge = $this->challenge();

        $this->actingAs($poor)
            ->from(route('challenges.arena', $challenge))
            ->post(route('challenges.ready', $challenge))
            ->assertRedirect(route('challenges.arena', $challenge))
            ->assertSessionHas('topup_needed');

        $this->assertDatabaseCount('war_readiness', 0);

        // وبرصيد 12 بالضبط يُقبَل
        $rich = $this->trainee(tickets: 12);

        $this->actingAs($rich)->post(route('challenges.ready', $challenge))->assertRedirect();

        $this->assertDatabaseHas('war_readiness', ['user_id' => $rich->id]);
    }

    /** الاستعداد **حصريّ**: نوع واحد في اللحظة الواحدة (15.0). */
    public function test_readiness_is_exclusive_to_one_war_at_a_time(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)->post(route('challenges.ready', $this->challenge('knowledge_war')));
        $this->actingAs($user)->post(route('challenges.ready', $this->challenge('survival_war')));

        $this->assertDatabaseCount('war_readiness', 1);
        $this->assertDatabaseHas('war_readiness', [
            'user_id' => $user->id,
            'challenge_id' => $this->challenge('survival_war')->id,
        ]);
    }

    /** إلغاء الاستعداد أثناء مواجهة نشطة = انسحاب صريح بكامل تكلفته (15.0). */
    public function test_cancelling_readiness_during_a_live_match_is_a_withdrawal(): void
    {
        $quitter = $this->trainee(tickets: 20);
        $rival = $this->trainee(tickets: 20);

        $match = $this->startMatch($quitter, $rival);

        // الشريط العائم يعيد الاستعداد ثمّ يُلغى — نحاكي وجود صفّ استعداد
        $this->actingAs($quitter)->post(route('challenges.unready'))->assertRedirect();

        $this->assertSame('withdraw', $match->refresh()->outcome);
        $this->assertEqualsWithDelta(8, $this->ticketsOf($quitter), 0.001);
        $this->assertEqualsWithDelta(22, $this->ticketsOf($rival), 0.001);
    }

    /** ⭐ 15.2-1: القفل الذرّيّ — لا يدخل لاعبٌ مواجهتين في نفس اللحظة. */
    public function test_atomic_lock_prevents_a_second_match_on_a_busy_fighter(): void
    {
        $target = $this->trainee();
        $first = $this->trainee();
        $second = $this->trainee();
        $challenge = $this->challenge();

        foreach ([$target, $first, $second] as $user) {
            $this->actingAs($user)->post(route('challenges.ready', $challenge));
        }

        // الأوّل يكسب السباق
        $this->actingAs($first)->post(route('challenges.duel', [$challenge, $target]))->assertRedirect();
        // والثاني يجد صفّ الاستعداد قد اختفى ⟵ يُرفَض بلا مواجهة ثانية
        $this->actingAs($second)->post(route('challenges.duel', [$challenge, $target]))->assertRedirect();

        $this->assertSame(1, WarMatch::query()->count());
        $this->assertSame(
            1,
            ChallengeParticipation::query()->where('user_id', $target->id)->whereNotNull('war_match_id')->count(),
        );
    }

    /** المواجهة تُخرِج الطرفين من بركة الاستعداد فورًا (15.2-1). */
    public function test_starting_a_match_removes_both_from_the_ready_pool(): void
    {
        $a = $this->trainee();
        $b = $this->trainee();

        $this->startMatch($a, $b);

        $this->assertDatabaseCount('war_readiness', 0);
    }

    /** الاستئناف: إعادة التحميل ترجع لنفس المواجهة بلا خصمٍ ثانٍ (15.2-7). */
    public function test_reload_resumes_the_same_match_with_no_second_charge(): void
    {
        $a = $this->trainee(tickets: 20);
        $b = $this->trainee(tickets: 20);

        $match = $this->startMatch($a, $b);

        $this->actingAs($a)->postJson(route('challenges.answer', $match), ['index' => 0, 'value' => '0']);
        $this->actingAs($a)->get(route('challenges.play', $match))->assertOk();

        $this->assertEqualsWithDelta(20, $this->ticketsOf($a), 0.001);
        $this->assertSame(1, ChallengeParticipation::query()->where('user_id', $a->id)->count());
    }
}
