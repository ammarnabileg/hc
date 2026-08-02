<?php

namespace Tests\Feature\Volunteer\Core;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Project;
use App\Models\Task;
use App\Models\Track;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkPackage;
use App\Support\Access\AccessEngine;
use Database\Seeders\CoreSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\VolunteerCoreDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * أدوات مشتركة لاختبارات مجال «لوحة التطوّع: النظرة العامّة والمهام».
 * كلّ اختبار يقابل قاعدةً منصوصةً في الدستور (23 · 24.4 · 13.4-ح).
 */
abstract class VolunteerCoreTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // البوزشنز وقيم Rep والإعدادات — لا رقم محروق في الكود
        $this->seed(CoreSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seedAreaSettings();
    }

    /** إعدادات هذا المجال من سيدره — بلا تشغيل بياناته التجريبيّة */
    protected function seedAreaSettings(): void
    {
        $seeder = new VolunteerCoreDemoSeeder;
        $method = new \ReflectionMethod($seeder, 'settings');
        $method->setAccessible(true);
        $method->invoke($seeder);
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

    protected function makeEntity(): Entity
    {
        return Entity::create([
            'track_id' => Track::where('key', 'department')->value('id'),
            'name_ar' => 'كيان '.str()->random(4),
            'icon' => '🏛️',
            'status' => 'active',
        ]);
    }

    protected function makeMembership(User $user, Entity $entity, string $positionKey = 'coordinator', ?Membership $upline = null): Membership
    {
        return Membership::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'position_id' => Position::where('key', $positionKey)->value('id'),
            'upline_id' => $upline?->id,
            'is_primary' => true,
            'started_at' => now()->subMonth(),
            'status' => 'active',
        ]);
    }

    /** منح صلاحيّات مباشرةً للمستخدم بنطاق ALL — لعزل الاختبار عن سيدر الأدوار */
    protected function grant(User $user, array $keys, string $scope = 'ALL'): void
    {
        foreach ($keys as $key) {
            [$resource, $action] = explode('.', $key);

            $permission = Permission::firstOrCreate(['key' => $key], [
                'resource' => $resource,
                'action' => $action,
                'group' => 'دورة العمل',
                'label_ar' => $key,
                'allowed_scopes' => ['SELF', 'TEAM', 'SUBTREE', 'ENTITY', 'TRACK', 'ALL'],
            ]);

            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => $permission->id,
                'user_id' => $user->id,
                'membership_id' => null,
                'scope' => $scope,
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(AccessEngine::class)->forget($user);
    }

    /** بندٌ حقيقيّ لأنّ ربط كلّ مهمّة ببند إلزاميّ (23-3.1) */
    protected function makeWorkItem(Entity $entity): WorkItem
    {
        $project = Project::create([
            'entity_id' => $entity->id,
            'name' => 'مشروع تشغيليّ',
            'type' => 'operational',
            'status' => 'active',
        ]);

        $package = WorkPackage::create([
            'project_id' => $project->id,
            'entity_id' => $entity->id,
            'name' => 'حزمة',
        ]);

        return WorkItem::create([
            'work_package_id' => $package->id,
            'name' => 'بند تجريبيّ',
            'vxp_pool' => 100,
        ]);
    }

    protected function makeTask(User $owner, Entity $entity, array $attributes = []): Task
    {
        return Task::create(array_merge([
            'title' => 'مهمّة '.str()->random(4),
            'owner_id' => $owner->id,
            'reviewer_id' => $owner->id,
            'created_by' => $owner->id,
            'entity_id' => $entity->id,
            'status' => 'in_progress',
            'deadline_at' => now()->addDays(3),
            'deliverable_spec' => 'ملفّ نهائيّ',
        ], $attributes));
    }
}
