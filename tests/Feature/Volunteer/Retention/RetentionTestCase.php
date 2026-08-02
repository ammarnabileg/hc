<?php

namespace Tests\Feature\Volunteer\Retention;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\Track;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RetentionDemoSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * أدوات مشتركة لاختبارات مجال الاحتفاظ (13.4-س · 13.4-ن · 13.4-ص).
 * كلّ اختبار هنا يقابل بندًا منصوصًا — لا سلوكًا مخترَعًا.
 */
abstract class RetentionTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(RetentionDemoSeeder::class);

        Cache::forget('settings');
        Cache::forget('rep_rules');
    }

    protected function makeUser(string $name = 'متطوّع'): User
    {
        return User::create([
            'name' => $name,
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(6)),
            'status' => 'active',
            'last_seen_at' => now(),
        ]);
    }

    protected function makeEntity(string $name = 'قسم الاختبار'): Entity
    {
        return Entity::create([
            'track_id' => Track::query()->where('key', 'department')->value('id'),
            'name_ar' => $name,
            'status' => 'active',
        ]);
    }

    protected function makeMembership(User $user, Entity $entity, ?Membership $upline = null, string $position = 'coordinator'): Membership
    {
        return Membership::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'position_id' => Position::query()->where('key', $position)->value('id'),
            'upline_id' => $upline?->id,
            'is_primary' => true,
            'started_at' => now(),
            'status' => 'active',
        ]);
    }

    /** بوزشن شرفيّ «أخوكم» — مقعد تقديريّ خارج كلّ العدّادات (13.4-ص) */
    protected function honoraryPosition(): Position
    {
        $position = Position::query()->where('is_honorary', true)->first();

        return $position ?: Position::create([
            'key' => 'honorary_'.str()->random(4),
            'name_ar' => 'أخوكم',
            'rank' => 99,
            'is_honorary' => true,
            'is_active' => true,
        ]);
    }

    /** منح صلاحيّة بنطاق محدَّد — النطاق هو قفص السلطة (12.2.1) */
    protected function grant(User $user, string $permissionKey, string $scope = 'ALL', ?Membership $membership = null): void
    {
        [$resource, $action] = explode('.', $permissionKey);

        $permission = Permission::query()->firstOrCreate(['key' => $permissionKey], [
            'resource' => $resource,
            'action' => $action,
            'group' => 'اختبار',
            'label_ar' => $permissionKey,
            'allowed_scopes' => ['SELF', 'TEAM', 'SUBTREE', 'ENTITY', 'TRACK', 'ALL'],
        ]);

        $role = Role::create(['key' => 'r_'.str()->random(8), 'name_ar' => 'دور اختبار', 'layer' => 'volunteer']);

        DB::table('permission_role')->insert([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'scope' => $scope,
            'effect' => 'allow',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user->assignRole($role, $membership);

        app(AccessEngine::class)->forget($user);
    }
}
