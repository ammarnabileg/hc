<?php

namespace Tests\Feature\Challenges;

use App\Models\CelebrationConsumption;
use App\Models\CelebrationEvent;
use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Models\Setting;
use App\Models\WarMatch;
use App\Services\Gamification\CelebrationService;
use App\Services\Gamification\Wars\WarMatchService;
use Illuminate\Support\Facades\Cache;

/**
 * نظام الاحتفالات (2.14): مرّة واحدة لكلّ حدث Server-side + الحدّ اليوميّ للذروة.
 */
class CelebrationTest extends ChallengeTestCase
{
    public function test_the_same_event_never_celebrates_twice(): void
    {
        $user = $this->trainee();
        $participation = $this->finishedWin($user);

        $first = app(CelebrationService::class)->fire($user, 'challenge.won', $participation);
        $second = app(CelebrationService::class)->fire($user, 'challenge.won', $participation);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, $this->consumptionsOf($user, 'challenge.won'));
    }

    public function test_reloading_the_result_screen_does_not_repeat_the_celebration(): void
    {
        $user = $this->trainee();
        $participation = $this->finishedWin($user);

        $this->actingAs($user)->get(route('challenges.result', $this->match))->assertOk();
        $countAfterFirst = CelebrationConsumption::where('user_id', $user->id)->count();

        $this->actingAs($user)->get(route('challenges.result', $this->match))->assertOk();

        $this->assertSame($countAfterFirst, CelebrationConsumption::where('user_id', $user->id)->count());
    }

    public function test_peak_celebrations_respect_the_daily_cap(): void
    {
        $cap = 2;
        Setting::where('key', 'celebrations.peak.daily_cap')->update(['value' => (string) $cap]);
        Cache::forget('settings');

        $user = $this->trainee();
        $service = app(CelebrationService::class);
        $peakEvent = CelebrationEvent::where('key', 'challenge.first_win')->firstOrFail();

        $tiers = [];

        // نطلق نفس الحدث بمراجع مختلفة: أوّل «cap» ذروة، وما بعده ينزل لمتوسّط
        for ($i = 0; $i < $cap + 1; $i++) {
            $reference = ChallengeParticipation::create([
                'challenge_id' => Challenge::where('key', 'knowledge_war')->value('id'),
                'user_id' => $user->id,
                'started_at' => now(),
                'status' => 'finished',
                'result' => 'win',
            ]);

            $tiers[] = $service->fire($user, $peakEvent->key, $reference)['tier'];
        }

        $this->assertSame(array_fill(0, $cap, 3) + [$cap => 2], $tiers);
    }

    public function test_result_screen_shows_the_congratulation(): void
    {
        $user = $this->trainee();
        $participation = $this->finishedWin($user);

        $this->actingAs($user)
            ->get(route('challenges.result', $this->match))
            ->assertOk()
            ->assertSee('كسبت المواجهة', false);
    }

    private function consumptionsOf($user, string $eventKey): int
    {
        return CelebrationConsumption::where('user_id', $user->id)
            ->where('celebration_event_id', CelebrationEvent::where('key', $eventKey)->value('id'))
            ->count();
    }

    /** مواجهة حقيقيّة ينتصر فيها المستخدم — الفوز لا يُزوَّر في الاختبار كذلك */
    private function finishedWin($user): ChallengeParticipation
    {
        $rival = $this->trainee();
        $match = $this->startMatch($user, $rival, 'estimation_war');
        $service = app(WarMatchService::class);

        foreach ((array) $match->questions as $i => $question) {
            $service->answer($match, $user, $i, $question['answer']);
        }

        $service->finishSide($match, $service->sideOf($match, $user));
        $service->finishSide($match->refresh(), $service->sideOf($match, $rival));

        $this->match = $match->refresh();

        return $service->sideOf($this->match, $user);
    }

    /** المواجهة الأخيرة — شاشة النتيجة تُفتَح بها لا بالمشاركة */
    private ?WarMatch $match = null;
}
