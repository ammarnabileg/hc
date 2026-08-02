<?php

namespace Tests\Feature\Volunteer\Goals;

use App\Models\Entity;
use App\Models\Goal;
use App\Models\LeadershipCriterion;
use App\Models\Membership;
use App\Models\Milestone;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Track;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkPackage;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * أدوات مشتركة لاختبارات مجال الأهداف والأداء.
 * كلّ اختبار هنا يقابل قاعدةً منصوصةً في الدستور — لا سلوكًا مخترَعًا.
 */
abstract class GoalsTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);

        // الكاش يُمسَح بعد السيدر كي تُقرَأ القيم الحقيقيّة لا الفارغة
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

    /** منح صلاحيّة بنطاق — تُنشأ الصلاحيّة إن لم تكن موجودة */
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
    }

    /** شجرة كاملة: هدف ⟵ مَعلَم ⟵ حزمة ⟵ بند */
    protected function makeTree(Entity $entity, string $status = 'sent_to_execution'): array
    {
        $goal = Goal::create([
            'name' => 'هدف الاختبار',
            'verification_type' => 'numeric',
            'target_from' => 0,
            'target_to' => 100,
            'end_date' => now()->addMonth()->toDateString(),
            'status' => $status,
            'sent_to_execution_at' => $status === 'draft' ? null : now(),
        ]);

        $milestone = Milestone::create(['goal_id' => $goal->id, 'name' => 'مَعلَم الاختبار']);

        $package = WorkPackage::create([
            'milestone_id' => $milestone->id,
            'entity_id' => $entity->id,
            'name' => 'حزمة الاختبار',
        ]);

        $item = WorkItem::create([
            'work_package_id' => $package->id,
            'name' => 'بند الاختبار',
            'vxp_pool' => 100,
        ]);

        return compact('goal', 'milestone', 'package', 'item');
    }

    protected function makeTask(WorkItem $item, string $status, ?User $owner = null, float $vxp = 0, ?Task $parent = null): Task
    {
        return Task::create([
            'title' => 'مهمّة '.str()->random(4),
            'work_item_id' => $item->id,
            'owner_id' => $owner?->id,
            'parent_task_id' => $parent?->id,
            'vxp_value' => $vxp,
            'status' => $status,
            'source' => 'assigned',
        ]);
    }

    protected function makeOperationalProject(Entity $entity): array
    {
        $project = Project::create([
            'entity_id' => $entity->id,
            'name' => 'المشروع التشغيليّ',
            'type' => 'operational',
            'is_permanent' => true,
            'status' => 'active',
        ]);

        $package = WorkPackage::create([
            'project_id' => $project->id,
            'entity_id' => $entity->id,
            'name' => 'الإيقاع اليوميّ',
        ]);

        return compact('project', 'package');
    }

    protected function makeCriteria(int $count = 2): void
    {
        foreach (range(1, $count) as $i) {
            LeadershipCriterion::create([
                'key' => 'c'.$i,
                'label_ar' => 'معيار '.$i,
                'weight' => 1,
                'sort_order' => $i,
            ]);
        }
    }
}
