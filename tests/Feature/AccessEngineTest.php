<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\Task;
use App\Models\Track;
use App\Models\User;
use App\Support\Access\AccessEngine;
use App\Support\Access\PermissionExpander;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * اختبارات محرّك الصلاحيّات — كلّ اختبار يقابل قاعدةً منصوصةً في الدستور 12.2.1.
 */
class AccessEngineTest extends TestCase
{
    use RefreshDatabase;

    private AccessEngine $access;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->access = app(AccessEngine::class);

        Permission::create([
            'key' => 'tasks.edit', 'resource' => 'tasks', 'action' => 'edit',
            'group' => 'دورة العمل', 'label_ar' => 'المهامّ',
            'allowed_scopes' => ['SELF', 'TEAM', 'SUBTREE', 'ENTITY', 'TRACK', 'ALL'],
        ]);

        Permission::create([
            'key' => 'finance.view', 'resource' => 'finance', 'action' => 'view',
            'group' => 'المال', 'label_ar' => 'الماليّات',
            'allowed_scopes' => ['ALL'], 'is_sensitive' => true, 'is_owner_only' => true,
        ]);
    }

    // ------------------------------------------------------------ أدوات

    private function makeUser(string $name = 'مستخدم'): User
    {
        return User::create([
            'name' => $name,
            'email' => str()->random(8).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(6)),
            'status' => 'active',
        ]);
    }

    private function makeRoleWith(string $permissionKey, string $scope, string $effect = 'allow'): Role
    {
        $role = Role::create(['key' => 'r_'.str()->random(6), 'name_ar' => 'دور', 'layer' => 'volunteer']);

        DB::table('permission_role')->insert([
            'role_id' => $role->id,
            'permission_id' => Permission::where('key', $permissionKey)->value('id'),
            'scope' => $scope,
            'effect' => $effect,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $role;
    }

    private function makeEntity(?Entity $parent = null): Entity
    {
        $track = Track::where('key', 'department')->first();

        return Entity::create([
            'track_id' => $track->id,
            'parent_id' => $parent?->id,
            'name_ar' => 'كيان '.str()->random(4),
            'status' => 'active',
        ]);
    }

    private function makeMembership(User $user, Entity $entity, ?Membership $upline = null, string $positionKey = 'coordinator'): Membership
    {
        return Membership::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'position_id' => Position::where('key', $positionKey)->value('id'),
            'upline_id' => $upline?->id,
            'is_primary' => true,
            'started_at' => now(),
            'status' => 'active',
        ]);
    }

    private function makeTask(User $owner, Entity $entity): Task
    {
        return Task::create([
            'title' => 'مهمّة اختبار',
            'owner_id' => $owner->id,
            'entity_id' => $entity->id,
            'status' => 'in_progress',
        ]);
    }

    // ------------------------------------------------------------ الاختبارات

    /** النطاق SELF: على سجلّاته هو فقط */
    public function test_self_scope_covers_only_own_records(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);

        $user->assignRole($this->makeRoleWith('tasks.edit', 'SELF'));

        $this->assertTrue($this->access->allows($user, 'tasks.edit', $this->makeTask($user, $entity)));
        $this->assertFalse($this->access->allows($user, 'tasks.edit', $this->makeTask($other, $entity)));
    }

    /** النطاق TEAM: الداونلاين المباشر داخل العضويّة النشطة */
    public function test_team_scope_covers_direct_downline_only(): void
    {
        $leader = $this->makeUser('قائد');
        $member = $this->makeUser('عضو');
        $grandchild = $this->makeUser('حفيد');
        $entity = $this->makeEntity();

        $leaderM = $this->makeMembership($leader, $entity, null, 'team_leader');
        $memberM = $this->makeMembership($member, $entity, $leaderM);
        $this->makeMembership($grandchild, $entity, $memberM);

        $leader->assignRole($this->makeRoleWith('tasks.edit', 'TEAM'), $leaderM);

        $this->assertTrue($this->access->allows($leader, 'tasks.edit', $this->makeTask($member, $entity), $leaderM));
        $this->assertFalse($this->access->allows($leader, 'tasks.edit', $this->makeTask($grandchild, $entity), $leaderM));
    }

    /** النطاق SUBTREE: كلّ الشجرة تحته */
    public function test_subtree_scope_covers_whole_downline(): void
    {
        $head = $this->makeUser();
        $mid = $this->makeUser();
        $leaf = $this->makeUser();
        $entity = $this->makeEntity();

        $headM = $this->makeMembership($head, $entity, null, 'supervisor');
        $midM = $this->makeMembership($mid, $entity, $headM);
        $this->makeMembership($leaf, $entity, $midM);

        $head->assignRole($this->makeRoleWith('tasks.edit', 'SUBTREE'), $headM);

        $this->assertTrue($this->access->allows($head, 'tasks.edit', $this->makeTask($leaf, $entity), $headM));
    }

    /** قفص العضويّة: لا سلطة عابرة للكيانات */
    public function test_entity_scope_does_not_cross_entities(): void
    {
        $director = $this->makeUser();
        $stranger = $this->makeUser();
        $mine = $this->makeEntity();
        $theirs = $this->makeEntity();

        $membership = $this->makeMembership($director, $mine, null, 'director');
        $director->assignRole($this->makeRoleWith('tasks.edit', 'ENTITY'), $membership);

        $this->assertTrue($this->access->allows($director, 'tasks.edit', $this->makeTask($stranger, $mine), $membership));
        $this->assertFalse($this->access->allows($director, 'tasks.edit', $this->makeTask($stranger, $theirs), $membership));
    }

    /** ENTITY يشمل الكيانات الفرعيّة تحته */
    public function test_entity_scope_includes_sub_entities(): void
    {
        $director = $this->makeUser();
        $member = $this->makeUser();
        $parent = $this->makeEntity();
        $child = $this->makeEntity($parent);

        $membership = $this->makeMembership($director, $parent, null, 'director');
        $director->assignRole($this->makeRoleWith('tasks.edit', 'ENTITY'), $membership);

        $this->assertTrue($this->access->allows($director, 'tasks.edit', $this->makeTask($member, $child), $membership));
    }

    /** ⭐ Deny > Allow دائمًا */
    public function test_deny_always_beats_allow(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);

        $user->assignRole($this->makeRoleWith('tasks.edit', 'ALL', 'allow'));
        $user->assignRole($this->makeRoleWith('tasks.edit', 'ALL', 'deny'));

        $this->assertFalse($this->access->allows($user, 'tasks.edit', $this->makeTask($user, $entity)));
    }

    /** حمل أكثر من دور = اتّحاد الصلاحيّات */
    public function test_multiple_roles_union(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $entity = $this->makeEntity();
        $membership = $this->makeMembership($user, $entity);

        $user->assignRole($this->makeRoleWith('tasks.edit', 'SELF'));
        $this->assertFalse($this->access->allows($user, 'tasks.edit', $this->makeTask($other, $entity), $membership));

        $user->assignRole($this->makeRoleWith('tasks.edit', 'ENTITY'), $membership);
        $this->assertTrue($this->access->allows($user, 'tasks.edit', $this->makeTask($other, $entity), $membership));
    }

    /** ⭐ عزل الحسّاس: الماليّ لمالك المنصّة وحده مهما كان الدور */
    public function test_owner_only_permission_is_isolated(): void
    {
        $admin = $this->makeUser();
        $admin->assignRole($this->makeRoleWith('finance.view', 'ALL'));

        $this->assertFalse($this->access->allows($admin, 'finance.view'));

        $owner = $this->makeUser();
        $ownerRole = Role::create(['key' => config('access.owner_role'), 'name_ar' => 'مالك المنصّة', 'layer' => 'platform']);
        $owner->assignRole($ownerRole);

        $this->assertTrue($this->access->allows($owner, 'finance.view'));
    }

    /** ⭐ منع تصعيد الامتياز: لا يمنح أحدٌ نطاقًا أوسع ممّا يملك */
    public function test_no_privilege_escalation(): void
    {
        $granter = $this->makeUser();
        $entity = $this->makeEntity();
        $membership = $this->makeMembership($granter, $entity, null, 'team_leader');

        $granter->assignRole($this->makeRoleWith('tasks.edit', 'TEAM'), $membership);

        $this->assertTrue($this->access->canGrant($granter, 'tasks.edit', 'SELF', $membership));
        $this->assertTrue($this->access->canGrant($granter, 'tasks.edit', 'TEAM', $membership));
        $this->assertFalse($this->access->canGrant($granter, 'tasks.edit', 'ENTITY', $membership));
        $this->assertFalse($this->access->canGrant($granter, 'tasks.edit', 'ALL', $membership));
    }

    /** لا يمنح أحدٌ صلاحيّةً لا يملكها أصلًا */
    public function test_cannot_grant_unowned_permission(): void
    {
        $granter = $this->makeUser();

        $this->assertFalse($this->access->canGrant($granter, 'tasks.edit', 'SELF'));
    }

    /** ⭐ «manage» تُفرَد ظاهرةً عند الحفظ — لا وراثة صامتة */
    public function test_manage_expands_visibly_on_save(): void
    {
        Permission::create(['key' => 'meetings.manage', 'resource' => 'meetings', 'action' => 'manage', 'group' => 'g', 'label_ar' => 'اجتماعات', 'allowed_scopes' => ['ENTITY']]);

        foreach (config('access.manage_expands_to') as $action) {
            Permission::create(['key' => "meetings.{$action}", 'resource' => 'meetings', 'action' => $action, 'group' => 'g', 'label_ar' => 'اجتماعات', 'allowed_scopes' => ['ENTITY']]);
        }

        $role = Role::create(['key' => 'r_manage', 'name_ar' => 'دور', 'layer' => 'volunteer']);
        $written = app(PermissionExpander::class)->attachToRole($role, 'meetings.manage', 'ENTITY');

        /*
         | الصلاحيّة نفسها + الأفعال الخمسة التي ينصّ عليها 12.2.1-د:
         | `create/edit/delete/archive/assign` — لا اثنا عشر. التوسيع الأوسع
         | كان يمنح صامتًا `export` و`approve` و`reject` و`import` و`restore`،
         | أي توسيعَ صلاحيّاتٍ بلا سندٍ في النصّ.
         */
        $expected = count(config('access.manage_expands_to')) + 1;

        $this->assertSame(6, $expected, 'manage تشمل خمسة أفعال لا أكثر (12.2.1-د).');
        $this->assertSame($expected, $written);
        $this->assertSame($expected, DB::table('permission_role')->where('role_id', $role->id)->count());
    }

    /** الإسناد المرتبط بعضويّة لا يسري خارجها */
    public function test_membership_bound_grant_does_not_leak(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $entityA = $this->makeEntity();
        $entityB = $this->makeEntity();

        $membershipA = $this->makeMembership($user, $entityA, null, 'director');
        $membershipB = $this->makeMembership($user, $entityB, null, 'coordinator');

        $user->assignRole($this->makeRoleWith('tasks.edit', 'ENTITY'), $membershipA);

        $this->assertTrue($this->access->allows($user, 'tasks.edit', $this->makeTask($other, $entityA), $membershipA));
        $this->assertFalse($this->access->allows($user, 'tasks.edit', $this->makeTask($other, $entityB), $membershipB));
    }

    /** مالك المنصّة يعلو الجميع */
    public function test_platform_owner_allows_everything(): void
    {
        $owner = $this->makeUser();
        $owner->assignRole(Role::create(['key' => config('access.owner_role'), 'name_ar' => 'مالك', 'layer' => 'platform']));

        $this->assertTrue($this->access->allows($owner, 'tasks.edit'));
        $this->assertTrue($this->access->isPlatformOwner($owner));
        $this->assertSame('ALL', $this->access->widestScope($owner, 'tasks.edit'));
    }

    /** بلا أيّ إسناد = ممنوع (Fail closed) */
    public function test_default_is_deny(): void
    {
        $this->assertFalse($this->access->allows($this->makeUser(), 'tasks.edit'));
    }

    /** اسم العرض المختصر: أدوات الاسم جزءٌ من الكلمة التالية (12.14-ج) */
    public function test_short_name_treats_particles_as_part_of_next_word(): void
    {
        $u = $this->makeUser('عبد الرحمن محمد علي');
        $this->assertSame('عبد الرحمن محمد', $u->shortName());

        $u2 = $this->makeUser('محمد أحمد علي');
        $this->assertSame('محمد أحمد', $u2->shortName());

        $u3 = $this->makeUser('أبو بكر الصديق');
        $this->assertSame('أبو بكر الصديق', $u3->shortName());
    }
}
