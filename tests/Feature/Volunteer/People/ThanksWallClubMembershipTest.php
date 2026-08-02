<?php

namespace Tests\Feature\Volunteer\People;

use App\Models\Position;
use App\Models\RepScore;
use App\Models\User;
use App\Services\Volunteer\People\ThanksWall;

/**
 * نادي +9.5 **بين المتطوّعين** وحدهم (13.4-ي)، و«أخوكم» الشرفيّ **بلا Rep ولا
 * VXP وبلا أثر على أيّ شيء** (13.4-ص).
 *
 * كان الحائط يستعلم `rep_scores` مباشرةً بلا استبعاد الشرفيّ وبلا اشتراط عضويّة
 * نشطة: فشرفيٌّ بـ9.9 يتصدّر النادي بإطار ذهبيّ، ومتدرّبٌ بلا أيّ عضويّة تطوّع
 * بـ9.8 يدخله.
 */
class ThanksWallClubMembershipTest extends PeopleTestCase
{
    private function wall(): ThanksWall
    {
        return app(ThanksWall::class);
    }

    private function scoreFor(User $user, float $score): void
    {
        RepScore::updateOrCreate(['user_id' => $user->id], ['score' => $score]);
    }

    /** ⭐ الشرفيّ وغير المتطوّع خارج النادي، والمتطوّع وحده داخله */
    public function test_honorary_and_non_volunteer_are_kept_out_of_the_club(): void
    {
        $entity = $this->makeEntity();

        $volunteer = $this->makeUser('المتطوّعة');
        $this->makeMembership($volunteer, $entity);
        $this->scoreFor($volunteer, 9.7);

        // «أخوكم»: عضويّة نشطة لكن ببوزشن شرفيّ
        $honorary = $this->makeUser('أخوكم');
        $this->makeMembership($honorary, $entity, $this->honoraryPositionKey());
        $this->scoreFor($honorary, 9.9);

        // متدرّب بلا أيّ عضويّة تطوّع
        $trainee = $this->makeUser('متدرّب');
        $this->scoreFor($trainee, 9.8);

        $members = $this->wall()->members()->pluck('user_id')->all();

        $this->assertContains($volunteer->id, $members);
        $this->assertNotContains($honorary->id, $members, 'الشرفيّ دخل نادي +9.5');
        $this->assertNotContains($trainee->id, $members, 'غير المتطوّع دخل نادي +9.5');

        // والترتيب #1 للمتطوّعة لا للشرفيّ
        $this->assertSame($volunteer->id, $this->wall()->members()->first()->user_id);
    }

    /** وبلوك «اقتربت» بابُ النادي نفسه — فشرطه شرطه */
    public function test_the_approaching_block_uses_the_same_gate(): void
    {
        $entity = $this->makeEntity();
        $gap = (float) setting('thanks_wall.approaching_gap', 1.5);
        $near = $this->wall()->threshold() - ($gap / 2);

        $volunteer = $this->makeUser('قريبة');
        $this->makeMembership($volunteer, $entity);
        $this->scoreFor($volunteer, $near);

        $honorary = $this->makeUser('أخوكم القريب');
        $this->makeMembership($honorary, $entity, $this->honoraryPositionKey());
        $this->scoreFor($honorary, $near);

        $trainee = $this->makeUser('متدرّب قريب');
        $this->scoreFor($trainee, $near);

        $ids = $this->wall()->approaching()->pluck('user_id')->all();

        $this->assertContains($volunteer->id, $ids);
        $this->assertNotContains($honorary->id, $ids);
        $this->assertNotContains($trainee->id, $ids);
    }

    /** ولا احتفال دخولٍ لمن هو خارج السباق أصلًا */
    public function test_no_club_celebration_for_someone_outside_the_race(): void
    {
        $trainee = $this->makeUser('متدرّب متحمّس');
        $this->scoreFor($trainee, 10.0);

        $this->assertFalse($this->wall()->personalProgress($trainee)['is_member']);
        $this->assertNull($this->wall()->celebrateEntry($trainee));

        $volunteer = $this->makeUser('متطوّعة متميّزة');
        $this->makeMembership($volunteer, $this->makeEntity('قسم آخر'));
        $this->scoreFor($volunteer, 10.0);

        $this->assertTrue($this->wall()->personalProgress($volunteer)['is_member']);
    }

    /** بوزشن شرفيّ موجود أو يُنشَأ — «أخوكم» مقعدٌ تقديريّ خارج العدّادات (13.4-ص) */
    private function honoraryPositionKey(): string
    {
        $position = Position::query()->where('is_honorary', true)->first();

        return $position?->key ?? Position::create([
            'key' => 'honorary_test',
            'name_ar' => 'أخوكم',
            'rank' => 99,
            'is_honorary' => true,
            'is_active' => true,
        ])->key;
    }
}
