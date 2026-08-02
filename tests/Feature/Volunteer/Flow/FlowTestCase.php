<?php

namespace Tests\Feature\Volunteer\Flow;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\Task;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\VolunteerFlowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * أساس اختبارات دورة العمل: هيكل صغير بسلسلة أبلاين حقيقيّة
 * (كوردنيتور ⟵ تيم ليدر ⟵ سوبرفايزر ⟵ السقف) لأنّ كلّ شيء هنا يقاس بالسلسلة.
 */
abstract class FlowTestCase extends TestCase
{
    use RefreshDatabase;

    protected Entity $entity;

    protected User $contributor;   // كوردنيتور — المساهم

    protected User $owner;         // تيم ليدر — مالك المهمّة

    protected User $reviewer;      // سوبرفايزر — المراجِع (صاحب النافذة الأولى)

    protected User $top;           // مشرف عام التطوّع — السقف بنافذة 48

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(SettingSeeder::class);
        (new VolunteerFlowDemoSeeder)->settings();

        Cache::forget('settings');
        Cache::forget('rep_rules');

        $this->entity = $this->makeEntity();

        $this->top = $this->makeUser('مشرف عام');
        $this->reviewer = $this->makeUser('سوبرفايزر');
        $this->owner = $this->makeUser('مالك المهمّة');
        $this->contributor = $this->makeUser('المساهم');

        $topMembership = $this->makeMembership($this->top, 'volunteer_gm', null);
        $reviewerMembership = $this->makeMembership($this->reviewer, 'supervisor', $topMembership);
        $ownerMembership = $this->makeMembership($this->owner, 'team_leader', $reviewerMembership);
        $this->makeMembership($this->contributor, 'coordinator', $ownerMembership);
    }

    // ------------------------------------------------------------------ أدوات

    protected function makeEntity(): Entity
    {
        $track = Track::query()->where('key', 'department')->firstOrFail();

        return Entity::create([
            'track_id' => $track->id,
            'name_ar' => 'قسم الاختبار',
            'status' => 'active',
        ]);
    }

    protected function makeUser(string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(6)),
            'phone' => '0100'.random_int(1000000, 9999999),
            'status' => 'active',
        ]);
    }

    protected function makeMembership(User $user, string $positionKey, ?Membership $upline): Membership
    {
        return Membership::create([
            'user_id' => $user->id,
            'entity_id' => $this->entity->id,
            'position_id' => Position::query()->where('key', $positionKey)->value('id'),
            'upline_id' => $upline?->id,
            'is_primary' => true,
            'started_at' => now()->subMonth(),
            'status' => 'active',
        ]);
    }

    protected function makeTask(?User $owner = null, array $attributes = []): Task
    {
        return Task::create(array_merge([
            'title' => 'مهمّة اختبار',
            'entity_id' => $this->entity->id,
            'owner_id' => ($owner ?? $this->owner)->id,
            'reviewer_id' => $this->reviewer->id,
            'deliverable_spec' => 'ملفّ واحد بثلاثة أقسام.',
            'vxp_value' => 200,
            'deadline_at' => now()->addDays(5),
            'status' => 'in_progress',
        ], $attributes));
    }

    /** منح صلاحيّة بنطاق ALL لاختبارات الشاشات (12.2.1) */
    protected function grant(User $user, string ...$permissionKeys): void
    {
        $role = Role::create(['key' => 'r_'.str()->random(8), 'name_ar' => 'دور اختبار', 'layer' => 'volunteer']);

        foreach ($permissionKeys as $key) {
            [$resource, $action] = explode('.', $key);

            $permission = Permission::firstOrCreate(['key' => $key], [
                'resource' => $resource,
                'action' => $action,
                'group' => 'دورة العمل',
                'label_ar' => $key,
                'allowed_scopes' => ['SELF', 'TEAM', 'SUBTREE', 'ENTITY', 'TRACK', 'ALL'],
            ]);

            DB::table('permission_role')->insert([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
                'scope' => 'ALL',
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $user->assignRole($role);
    }
}
