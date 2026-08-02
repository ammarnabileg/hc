<?php

namespace Tests\Feature\Volunteer\People;

use App\Models\Membership;
use App\Models\Position;
use App\Models\RepScore;
use App\Models\User;
use App\Services\Volunteer\People\ThanksWall;

class DebugClubTest extends PeopleTestCase
{
    public function test_debug(): void
    {
        $entity = $this->makeEntity();
        $volunteer = $this->makeUser('المتطوّعة');
        $this->makeMembership($volunteer, $entity);
        RepScore::updateOrCreate(['user_id' => $volunteer->id], ['score' => 9.7]);

        fwrite(STDERR, "threshold=".app(ThanksWall::class)->threshold()."\n");
        foreach (RepScore::query()->get() as $r) {
            $u = User::find($r->user_id);
            $m = Membership::where('user_id', $r->user_id)->get();
            fwrite(STDERR, "user {$r->user_id} ({$u?->name}) score={$r->score} memberships=".$m->count()." positions=".$m->pluck('position_id')->join(',')."\n");
        }
        foreach (app(ThanksWall::class)->members() as $m) {
            fwrite(STDERR, "MEMBER {$m->user_id} score={$m->score}\n");
        }
        $this->assertTrue(true);
    }
}
