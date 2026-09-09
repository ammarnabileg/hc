<?php

namespace Tests\Feature\Volunteer\Org;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Position;
use App\Models\Setting;
use App\Models\Track;
use App\Models\User;
use App\Services\Volunteer\Org\TrackCapacityGuard;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * ⭐ حدّ العضويّة الواحدة لكلّ مسار (23-0.2 — العضويّات المتعدّدة): «عضويّة
 * واحدة كحدّ أقصى في المسار الواحد — الحدّ إعداد». كان `volunteer.org.memberships_per_track`
 * مزروعًا بلا قارئٍ له في `app/`، فيمكن لمتطوّعٍ فتح عضويّتين فعّالتين في نفس
 * المسار (قسمين معًا، أو محافظتين، أو ملفّين) بلا حارسٍ إطلاقًا.
 */
class TrackCapacityGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        Cache::forget('settings');
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'مستخدم اختبار',
            'email' => str()->random(8).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(6)),
            'status' => 'active',
        ]);
    }

    private function makeEntity(string $trackKey, string $name = 'كيان اختبار'): Entity
    {
        return Entity::create([
            'track_id' => Track::query()->where('key', $trackKey)->value('id'),
            'name_ar' => $name,
            'status' => 'active',
        ]);
    }

    private function membershipIn(User $user, Entity $entity, string $status = 'active'): Membership
    {
        return Membership::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'position_id' => Position::query()->where('key', 'coordinator')->value('id'),
            'is_primary' => true,
            'started_at' => now(),
            'status' => $status,
        ]);
    }

    public function test_blocks_a_second_active_membership_on_the_same_track(): void
    {
        $user = $this->makeUser();
        $first = $this->makeEntity('department', 'قسم أوّل');
        $second = $this->makeEntity('department', 'قسم ثانٍ');
        $this->membershipIn($user, $first);

        $this->expectException(ValidationException::class);

        app(TrackCapacityGuard::class)->assertWithinCap($user, $second);
    }

    public function test_allows_a_membership_on_a_different_track(): void
    {
        $user = $this->makeUser();
        $department = $this->makeEntity('department');
        $governorate = $this->makeEntity('governorate');
        $this->membershipIn($user, $department);

        app(TrackCapacityGuard::class)->assertWithinCap($user, $governorate);

        $this->assertTrue(true, 'ما اترمتش استثناء — مساران مختلفان قانونيّان معًا.');
    }

    public function test_ignores_ended_memberships_when_counting_the_cap(): void
    {
        $user = $this->makeUser();
        $first = $this->makeEntity('department', 'قسم قديم');
        $second = $this->makeEntity('department', 'قسم جديد');
        $this->membershipIn($user, $first, status: 'ended');

        app(TrackCapacityGuard::class)->assertWithinCap($user, $second);

        $this->assertTrue(true, 'عضويّة منتهية لا تحجز مكانها في الحدّ.');
    }

    public function test_respects_a_configured_cap_higher_than_one(): void
    {
        Setting::updateOrCreate(
            ['key' => 'volunteer.org.memberships_per_track'],
            ['group' => 'volunteer_org', 'label_ar' => 'حدّ العضويّات لكلّ مسار', 'type' => 'number', 'default_value' => '1', 'value' => '2'],
        );
        Cache::forget('settings');

        $user = $this->makeUser();
        $first = $this->makeEntity('department', 'قسم 1');
        $second = $this->makeEntity('department', 'قسم 2');
        $third = $this->makeEntity('department', 'قسم 3');
        $this->membershipIn($user, $first);

        // الحدّ صار 2 — عضويّة ثانية تمرّ
        app(TrackCapacityGuard::class)->assertWithinCap($user, $second);
        $this->membershipIn($user, $second);

        // والثالثة تُرفَض
        $this->expectException(ValidationException::class);
        app(TrackCapacityGuard::class)->assertWithinCap($user, $third);
    }
}
