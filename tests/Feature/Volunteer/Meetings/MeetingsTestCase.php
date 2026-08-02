<?php

namespace Tests\Feature\Volunteer\Meetings;

use App\Models\Entity;
use App\Models\Meeting;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\Track;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * أساس اختبارات مجال الاجتماعات: عضويّات وصلاحيّات حقيقيّة،
 * فما يُختبَر هو المسار كما يمرّ به المستخدم لا نسخةٌ مبسّطة منه.
 */
abstract class MeetingsTestCase extends TestCase
{
    use RefreshDatabase;

    protected Entity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->entity = $this->makeEntity();
    }

    protected function makeEntity(?Entity $parent = null): Entity
    {
        return Entity::create([
            'track_id' => Track::where('key', 'department')->value('id'),
            'parent_id' => $parent?->id,
            'name_ar' => 'قسم '.str()->random(4),
            'status' => 'active',
        ]);
    }

    protected function makeUser(string $name = 'متطوّع'): User
    {
        return User::create([
            'name' => $name,
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(6)),
            'status' => 'active',
        ]);
    }

    protected function makeMembership(User $user, ?Membership $upline = null, string $position = 'coordinator', ?Entity $entity = null): Membership
    {
        return Membership::create([
            'user_id' => $user->id,
            'entity_id' => ($entity ?? $this->entity)->id,
            'position_id' => Position::where('key', $position)->value('id'),
            'upline_id' => $upline?->id,
            'is_primary' => true,
            'started_at' => now(),
            'status' => 'active',
        ]);
    }

    /** متطوّع مُسكَّن بصلاحيّات شاشات المجال */
    protected function volunteer(string $name = 'متطوّع', ?Membership $upline = null, array $extra = []): User
    {
        $user = $this->makeUser($name);
        $this->makeMembership($user, $upline);
        $this->grant($user, array_merge($this->baseGrants(), $extra));

        return $user;
    }

    protected function baseGrants(): array
    {
        return [
            'meetings.list' => 'ENTITY',
            'meetings.view' => 'ENTITY',
            'meeting_attendance.create' => 'SELF',
            'meeting_attendance.view' => 'SELF',
            'meeting_minutes.view' => 'ENTITY',
            'meeting_posts.create' => 'ENTITY',
            'rep_transactions.list' => 'SELF',
            'rep_transactions.view' => 'SELF',
            'rep_transactions.export' => 'SELF',
            'objections.create' => 'SELF',
            'objections.view' => 'SELF',
        ];
    }

    /** إسناد صلاحيّات مباشرةً للمستخدم (استثناءات فرديّة فوق الأدوار) */
    protected function grant(User $user, array $keys): void
    {
        foreach ($keys as $key => $scope) {
            $permissionId = Permission::query()->where('key', $key)->value('id');

            if (! $permissionId) {
                continue;
            }

            DB::table('permission_user')->updateOrInsert(
                ['user_id' => $user->id, 'permission_id' => $permissionId, 'scope' => $scope],
                ['effect' => 'allow', 'created_at' => now(), 'updated_at' => now()],
            );
        }

        app(AccessEngine::class)->forget($user);
    }

    protected function makeRole(): Role
    {
        return Role::create(['key' => 'r_'.str()->random(6), 'name_ar' => 'دور', 'layer' => 'volunteer']);
    }

    /** اجتماع منتهٍ ونافذته مفتوحة — الحالة التي تُقاس فيها قيمة التسجيل */
    protected function endedMeeting(User $owner, array $attributes = []): Meeting
    {
        return Meeting::create(array_merge([
            'title' => 'اجتماع اختبار',
            'entity_id' => $this->entity->id,
            'audience' => 'entity',
            'owner_id' => $owner->id,
            'scheduled_at' => now()->subHours(3),
            'status' => 'ended',
            'ended_at' => now()->subHour(),
            'attendance_window_hours' => 12,
            'attendance_closes_at' => now()->addHours(11),
            'attendance_code' => 'HC-CODE',
            'minutes' => 'محضر موثَّق.',
        ], $attributes));
    }
}
