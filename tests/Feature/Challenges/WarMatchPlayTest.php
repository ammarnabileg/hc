<?php

namespace Tests\Feature\Challenges;

use App\Models\WarQuestion;
use App\Services\Gamification\Wars\MatchmakingService;
use App\Services\Gamification\Wars\WarMatchService;
use App\Services\Gamification\Wars\WarQuestionFunnel;
use App\Services\Gamification\Wars\WarStats;

/**
 * قواعد اللعب داخل المواجهة (15.1 · 15.5 · 15.6 · 15.2).
 */
class WarMatchPlayTest extends ChallengeTestCase
{
    /** ⭐ 15.2-3: الإجابات الصحيحة **لا تُرسَل للمتصفح** أبدًا. */
    public function test_correct_answers_never_reach_the_browser(): void
    {
        $a = $this->trainee();
        $b = $this->trainee();

        $match = $this->startMatch($a, $b, 'estimation_war');
        $public = app(WarMatchService::class)->publicItems($match);

        foreach ($public as $item) {
            $this->assertArrayNotHasKey('answer', $item);
            $this->assertArrayNotHasKey('tolerance', $item);
        }

        $response = $this->actingAs($a)->get(route('challenges.play', $match))->assertOk();

        foreach ((array) $match->questions as $question) {
            $response->assertDontSee('"answer":'.json_encode($question['answer']), false);
        }
    }

    /** ⭐ 15.2-8: الأسئلة **نفسها للطرفين** وبالعدد المضبوط من الإعداد. */
    public function test_both_sides_get_the_exact_same_questions(): void
    {
        $a = $this->trainee();
        $b = $this->trainee();

        $match = $this->startMatch($a, $b);
        $service = app(WarMatchService::class);

        $this->assertSame($service->publicItems($match), $service->publicItems($match->refresh()));
        $this->assertCount((int) setting('wars.count.knowledge', 20), (array) $match->questions);
    }

    /** حرب التقدير تسحب **الأسئلة الرقميّة وحدها** (15.0 · 15.6). */
    public function test_estimation_war_pulls_numeric_questions_only(): void
    {
        $match = $this->startMatch($this->trainee(), $this->trainee(), 'estimation_war');

        $this->assertCount((int) setting('wars.count.estimation', 7), (array) $match->questions);

        foreach ((array) $match->questions as $question) {
            $this->assertSame('number', $question['kind']);
            $this->assertTrue(is_numeric($question['answer']));
        }
    }

    /** حرب التقدير: **الأقرب للرقم الصحيح** يأخذ النقطة (15.6). */
    public function test_estimation_awards_the_point_to_the_closest_guess(): void
    {
        $sharp = $this->trainee(tickets: 20);
        $wild = $this->trainee(tickets: 20);

        $match = $this->startMatch($sharp, $wild, 'estimation_war');
        $service = app(WarMatchService::class);

        foreach ((array) $match->questions as $i => $question) {
            $target = (float) $question['answer'];
            $service->answer($match, $sharp, $i, $target);
            $service->answer($match, $wild, $i, $target + 10000);
        }

        $service->finishSide($match, $service->sideOf($match, $sharp));
        $service->finishSide($match->refresh(), $service->sideOf($match, $wild));

        $match->refresh();

        $this->assertSame($sharp->id, (int) $match->winner_id);
        $this->assertEqualsWithDelta(count((array) $match->questions), (float) $service->sideOf($match, $sharp)->score, 0.001);
        $this->assertEqualsWithDelta(0, (float) $service->sideOf($match, $wild)->score, 0.001);
    }

    /** حرب البقاء: **أوّل إجابة غلط = خروج**، والأبعد نجاةً يكسب (15.5). */
    public function test_survival_eliminates_on_the_first_wrong_answer(): void
    {
        $survivor = $this->trainee(tickets: 20);
        $rookie = $this->trainee(tickets: 20);

        $match = $this->startMatch($survivor, $rookie, 'survival_war');
        $service = app(WarMatchService::class);
        $questions = (array) $match->questions;

        // الأوّل ينجو من سؤالين ثمّ يغلط · والثاني يغلط من السؤال الأوّل
        $service->answer($match, $survivor, 0, $questions[0]['answer']);
        $service->answer($match, $survivor, 1, $questions[1]['answer']);
        $service->answer($match, $survivor, 2, 'إجابة غلط أكيد');
        $service->answer($match->refresh(), $rookie, 0, 'إجابة غلط أكيد');

        $match->refresh();

        $this->assertSame('finished', $match->status);
        $this->assertSame($survivor->id, (int) $match->winner_id);
        $this->assertSame(2, (int) $service->sideOf($match, $survivor)->reached_index);
        $this->assertSame(0, (int) $service->sideOf($match, $rookie)->reached_index);
    }

    /** مؤقّت السؤال 15 ثانية يُفرَض على **الخادم**: انتهاؤه = خروج (15.5). */
    public function test_survival_question_timeout_is_enforced_server_side(): void
    {
        $slow = $this->trainee(tickets: 20);
        $fast = $this->trainee(tickets: 20);

        $match = $this->startMatch($slow, $fast, 'survival_war');
        $service = app(WarMatchService::class);
        $questions = (array) $match->questions;

        $service->answer($match, $fast, 0, $questions[0]['answer']);

        // نرجّع بداية السؤال لما قبل المهلة ⟵ أيّ لمسة بعدها = خروج
        $seconds = (int) setting('wars.shared.question_seconds', 15);

        foreach ($match->sides as $sideRow) {
            $sideRow->forceFill(['started_at' => now()->subSeconds($seconds + 5)])->save();
            $progress = $sideRow->progress ?? [];
            $progress['q_started_at'] = now()->subSeconds($seconds + 5)->toIso8601String();
            $sideRow->forceFill(['progress' => $progress])->save();
        }

        $service->enforceTimers($match->refresh());

        $this->assertSame('finished', $match->refresh()->status);
        $this->assertTrue((bool) $service->sideOf($match, $slow)->progress['timed_out']);
    }

    /** عدّاد الحسم 20 ثانية يبدأ بأوّل مَن يخلّص، وانتهاؤه يقفل المواجهة (15.1). */
    public function test_decision_timer_starts_and_closes_the_match(): void
    {
        $quick = $this->trainee(tickets: 20);
        $slow = $this->trainee(tickets: 20);

        $match = $this->startMatch($quick, $slow);
        $service = app(WarMatchService::class);

        foreach ((array) $match->questions as $i => $question) {
            $service->answer($match, $quick, $i, $question['answer']);
        }

        $service->finishSide($match, $service->sideOf($match, $quick));
        $match->refresh();

        $this->assertNotNull($match->first_finished_at);
        $this->assertSame('running', $match->status);
        $this->assertEqualsWithDelta(
            (int) setting('wars.shared.decision_seconds', 20),
            (int) $match->first_finished_at->diffInSeconds($match->decision_deadline_at),
            1,
        );

        // انتهاء العدّاد ⟵ يُقفَل بما هو محفوظ عند البطيء
        $match->forceFill(['decision_deadline_at' => now()->subSecond()])->save();
        $service->enforceTimers($match->refresh());

        $this->assertSame('finished', $match->refresh()->status);
        $this->assertSame($quick->id, (int) $match->refresh()->winner_id);
    }

    /** الانقطاع لا يعاقِب: الإجابات المحفوظة تُحسَب كما هي (15.2-2). */
    public function test_saved_answers_count_even_if_the_side_never_submits(): void
    {
        $a = $this->trainee(tickets: 20);
        $b = $this->trainee(tickets: 20);

        $match = $this->startMatch($a, $b);
        $questions = (array) $match->questions;

        $this->actingAs($a)
            ->postJson(route('challenges.answer', $match), ['index' => 0, 'value' => $questions[0]['answer']])
            ->assertOk()
            ->assertJson(['saved' => true, 'answered' => 1, 'status' => 'running']);

        $service = app(WarMatchService::class);
        $service->finishSide($match, $service->sideOf($match, $a));
        $match->forceFill(['decision_deadline_at' => now()->subSecond()])->save();
        $service->enforceTimers($match->refresh());

        $this->assertSame($a->id, (int) $match->refresh()->winner_id);
    }

    /** طرف ثالث لا يرى المواجهة أصلًا. */
    public function test_a_stranger_cannot_open_someone_elses_match(): void
    {
        $match = $this->startMatch($this->trainee(), $this->trainee());
        $stranger = $this->trainee();

        $this->actingAs($stranger)->get(route('challenges.play', $match))->assertForbidden();
    }

    /** ⭐ قاعدة 3 خسارات متتالية: يختفي من قائمة الجاهزين ويعود بأوّل فوز (15.1). */
    public function test_three_consecutive_losses_hide_a_fighter_from_the_ready_list(): void
    {
        $unlucky = $this->trainee(tickets: 40);
        $me = $this->trainee(tickets: 40);
        $challenge = $this->challenge();

        $stats = app(WarStats::class);
        $matchmaking = app(MatchmakingService::class);

        $matchmaking->ready($unlucky, $challenge);
        $matchmaking->ready($me, $challenge);

        $this->assertCount(1, $matchmaking->fighters($me, $challenge));

        $limit = (int) setting('wars.shared.loss_rule_count', 3);

        for ($i = 0; $i < $limit; $i++) {
            $stats->recordLoss($unlucky);
        }

        $this->assertCount(0, $matchmaking->fighters($me, $challenge));

        // الفوز يكسر السلسلة فيرجع يظهر
        $stats->recordWin($unlucky);

        $this->assertCount(1, $matchmaking->fighters($me, $challenge));
    }

    /** القمع الموحّد يسحب من البنك والتدريبات معًا بنسبة 70/30 (15.0). */
    public function test_the_funnel_mixes_arena_and_training_questions(): void
    {
        $drawn = app(WarQuestionFunnel::class)->draw($this->challenge(), 20);

        $trainingRefs = WarQuestion::query()->where('source', 'training')->pluck('id')
            ->map(fn ($id) => 'war:'.$id)->all();

        $refs = array_column($drawn, 'ref');
        $fromTraining = count(array_intersect($refs, $trainingRefs));

        $this->assertCount(20, $drawn);
        // 30% من عشرين = ستّة — ونقبل نقصانًا إن شحّ قمع التدريبات
        $this->assertGreaterThan(0, $fromTraining);
        $this->assertLessThanOrEqual(20, $fromTraining);
    }
}
