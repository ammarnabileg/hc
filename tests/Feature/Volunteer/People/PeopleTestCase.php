<?php

namespace Tests\Feature\Volunteer\People;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\Track;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\VolunteerPeopleDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * أساس اختبارات المجال: يجهّز الثوابت والصلاحيّات وإعدادات المجال،
 * ويمنح كلّ اختبار أدوات صغيرة لصناعة مستخدم بصلاحيّة بعينها.
 */
abstract class PeopleTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);

        // إعدادات المجال تأتي من سيدر المجال نفسه (BUILD §3)
        $this->seed(VolunteerPeopleDemoSeeder::class);

        Cache::forget('settings');
        Cache::forget('rep_rules');
    }

    protected function makeUser(string $name = 'مستخدم'): User
    {
        return User::create([
            'name' => $name,
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => 'active',
        ]);
    }

    /** مستخدم يملك هذه الصلاحيّات بنطاق ALL — أسرع طريق لاختبار مسار محميّ */
    protected function userWith(array $permissionKeys, string $scope = 'ALL'): User
    {
        $user = $this->makeUser();
        $role = Role::create(['key' => 'r_'.str()->random(8), 'name_ar' => 'دور اختبار', 'layer' => 'volunteer']);

        foreach ($permissionKeys as $key) {
            $permission = Permission::firstOrCreate(['key' => $key], [
                'resource' => explode('.', $key)[0],
                'action' => explode('.', $key)[1] ?? 'view',
                'group' => 'اختبار',
                'label_ar' => $key,
                'allowed_scopes' => ['SELF', 'TEAM', 'SUBTREE', 'ENTITY', 'TRACK', 'ALL'],
            ]);

            DB::table('permission_role')->insert([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
                'scope' => $scope,
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $user->roles()->attach($role->id, ['assigned_at' => now()]);
        app(AccessEngine::class)->forget($user);

        return $user;
    }

    protected function makeEntity(string $name = 'قسم اختبار', ?Entity $parent = null): Entity
    {
        return Entity::create([
            'track_id' => (Track::firstWhere('key', 'department') ?? Track::first())->id,
            'parent_id' => $parent?->id,
            'name_ar' => $name,
            'status' => 'active',
        ]);
    }

    protected function makeMembership(User $user, Entity $entity, string $positionKey = 'coordinator'): Membership
    {
        return Membership::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'position_id' => Position::firstWhere('key', $positionKey)->id,
            'started_at' => now(),
            'status' => 'active',
            'is_primary' => true,
        ]);
    }
}
