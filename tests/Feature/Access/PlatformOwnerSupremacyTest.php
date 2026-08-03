<?php

namespace Tests\Feature\Access;

use App\Models\Permission;
use App\Models\PermissionUser;
use App\Models\Role;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ⭐ **مالك المنصّة فوق الجميع** (12.2.1-ز-5) — **و**«المنع يغلب الإذن» (12.2.1-ز-1)
 * باقيةٌ كما هي لكلّ مَن سواه. النصّان يُقرآن معًا ولا يُلغى أحدهما لأجل الآخر:
 *
 *  • المنع يحكم **تعارض المصادر على المُخوَّلين** — ويُثبَت هنا في اتّجاهيه.
 *  • والمالك ليس طرفًا في ذلك التعارض أصلًا؛ سلطتُه ليست ممنوحةً من صفٍّ حتى
 *    يُلغيها صفّ. وكان الترتيب المقلوب يجعل **صفّ منعٍ واحدًا** يقفل الحساب الجذر
 *    عن منصّته ومعه شاشةُ الأدوار التي وحدَها تفكّ القفل.
 */
class PlatformOwnerSupremacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function makeUser(string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => 'active',
        ]);
    }

    private function deny(User $user, string $permissionKey, string $scope = 'ALL'): void
    {
        PermissionUser::create([
            'permission_id' => Permission::where('key', $permissionKey)->value('id'),
            'user_id' => $user->id,
            'scope' => $scope,
            'effect' => 'deny',
        ]);

        app(AccessEngine::class)->forget($user);
    }

    // ------------------------------------------------------ ⭐ المالك فوق المنع

    /** صفّ منعٍ واحد **لا** يقفل الحساب الجذر */
    public function test_a_single_deny_row_cannot_lock_the_platform_owner(): void
    {
        $owner = $this->makeUser('مالك المنصّة');
        $owner->assignRole('platform_owner');

        $this->deny($owner, 'courses.list');
        $this->deny($owner, 'roles.edit');
        $this->deny($owner, 'permissions.assign');

        $this->assertTrue($owner->allows('courses.list'));
        $this->assertTrue($owner->allows('roles.edit'), 'قفلُ شاشة الأدوار قفلٌ لا رجعة فيه');
        $this->assertTrue($owner->allows('permissions.assign'));

        // وعلى الطلب لا على الدالّة وحدها
        $this->actingAs($owner)->get('/admin/roles')->assertOk();
    }

    /** والمنع يبقى مكتوبًا في السجلّ — لم يُحذَف، إنّما لا يعلو المالك */
    public function test_the_deny_row_is_still_recorded(): void
    {
        $owner = $this->makeUser('مالك المنصّة');
        $owner->assignRole('platform_owner');
        $this->deny($owner, 'courses.list');

        $this->assertDatabaseHas('permission_user', [
            'user_id' => $owner->id,
            'effect' => 'deny',
        ]);
    }

    // -------------------------------------- ⭐ وDeny > Allow باقية لكلّ مَن سواه

    /** أدمن عامّ: إذنٌ من دور + منعٌ فرديّ ⟵ المنع يكسب */
    public function test_deny_still_beats_allow_for_everyone_but_the_owner(): void
    {
        $admin = $this->makeUser('أدمن عامّ');
        $admin->assignRole('super_admin');

        $this->assertTrue($admin->allows('courses.list'), 'يملكها من دوره قبل المنع');

        $this->deny($admin, 'courses.list');

        $this->assertFalse($admin->allows('courses.list'), 'Deny > Allow — لكلّ من سوى المالك');
        $this->actingAs($admin)->get('/admin/courses')->assertForbidden();
    }

    /** والمنع عبر المصادر: إذنٌ من دورٍ ومنعٌ من دورٍ آخر ⟵ المنع يكسب */
    public function test_deny_from_another_role_still_wins(): void
    {
        $user = $this->makeUser('صاحب دورين');
        $user->assignRole('super_admin');

        $blocker = Role::create(['key' => 'r_block', 'name_ar' => 'دور مانع', 'layer' => 'platform']);
        DB::table('permission_role')->insert([
            'role_id' => $blocker->id,
            'permission_id' => Permission::where('key', 'courses.list')->value('id'),
            'scope' => 'ALL',
            'effect' => 'deny',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user->assignRole($blocker);
        app(AccessEngine::class)->forget();

        $this->assertFalse($user->allows('courses.list'));
    }

    /** والعزل الماليّ لا يُخترَق: منحٌ صريحٌ بنطاق ALL لا يفتح المجموعة المحميّة */
    public function test_the_isolated_group_stays_isolated(): void
    {
        $admin = $this->makeUser('أدمن عامّ');
        $admin->assignRole('super_admin');

        PermissionUser::create([
            'permission_id' => Permission::where('key', 'pricing.manage')->value('id'),
            'user_id' => $admin->id,
            'scope' => 'ALL',
            'effect' => 'allow',
        ]);
        app(AccessEngine::class)->forget($admin);

        $this->assertFalse($admin->allows('pricing.manage'));
    }
}
