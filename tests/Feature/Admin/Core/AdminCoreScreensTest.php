<?php

namespace Tests\Feature\Admin\Core;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Position;
use App\Models\Role;
use App\Models\Track;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\AdminCoreDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * كلّ شاشة رئيسيّة في المجال تُفتَح وتُعرَض — اختبار قبول للواجهات (BUILD 6).
 */
class AdminCoreScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(AdminCoreDemoSeeder::class);

        $this->owner = User::create([
            'name' => 'مالك الشاشات',
            'email' => 'screens@test.local',
            'password' => 'secret-password',
            'code' => 'SCREEN01',
            'status' => 'active',
        ]);

        $this->owner->assignRole('platform_owner');
        app(AccessEngine::class)->forget();
    }

    /** الشاشات الأربع الرئيسيّة تفتح بلا كسر. */
    public function test_main_screens_render(): void
    {
        $this->actingAs($this->owner)->get(route('admin.dashboard'))->assertOk()->assertSee('لوحة القيادة');
        $this->actingAs($this->owner)->get(route('admin.dashboard', ['tab' => 'details']))->assertOk();
        $this->actingAs($this->owner)->get(route('admin.users.index'))->assertOk()->assertSee('المستخدمون');
        $this->actingAs($this->owner)->get(route('admin.users.approvals'))->assertOk()->assertSee('طلبات الاعتماد');
        $this->actingAs($this->owner)->get(route('admin.users.segments'))->assertOk()->assertSee('شرائح الجمهور');
        $this->actingAs($this->owner)->get(route('admin.roles.index'))->assertOk()->assertSee('الأدوار والصلاحيّات');
        $this->actingAs($this->owner)->get(route('admin.roles.assign'))->assertOk()->assertSee('إسناد دور');
        $this->actingAs($this->owner)->get(route('admin.permissions.index'))->assertOk()->assertSee('مصفوفة الصلاحيّات');
    }

    /** محرّر الدور: بحث + مجموعات + نطاق لكلّ سطر + Deny/Allow (12.2.1-ط). */
    public function test_role_editor_has_search_groups_scopes_and_effects(): void
    {
        $role = Role::where('key', 'auditor')->firstOrFail();

        $this->actingAs($this->owner)
            ->get(route('admin.roles.edit', $role))
            ->assertOk()
            ->assertSee('data-perm-search', false)
            ->assertSee('مجموعات الصلاحيّات')
            ->assertSee('النطاق')
            ->assertSee('منع');
    }

    /** كلّ تابات صفحة المستخدم تُحمَّل كسولًا وتُعرَض (12.1 · 2.15-ب). */
    public function test_user_detail_tabs_render(): void
    {
        $target = User::create([
            'name' => 'حساب التابات',
            'email' => 'tabs@test.local',
            'password' => 'secret-password',
            'code' => 'TABS0001',
            'status' => 'active',
        ]);

        foreach (['profile', 'tables', 'wallet', 'learning', 'certificates', 'security', 'admin', 'advanced', 'volunteer'] as $tab) {
            $this->actingAs($this->owner)
                ->get(route('admin.users.show', ['user' => $target, 'tab' => $tab]))
                ->assertOk();
        }
    }

    /** إسناد دور تطوّع داخل عضويّة يمرّ، ونطاقه يُشتقّ منها (12.2.1-و). */
    public function test_volunteer_role_assignment_inside_membership(): void
    {
        $target = User::create([
            'name' => 'متطوّع الشاشات',
            'email' => 'volunteer@test.local',
            'password' => 'secret-password',
            'code' => 'VOL00001',
            'status' => 'active',
        ]);

        $track = Track::where('key', 'department')->firstOrFail();
        $entity = Entity::create(['track_id' => $track->id, 'name_ar' => 'قسم الاختبار', 'status' => 'active']);
        $position = Position::where('key', 'coordinator')->firstOrFail();

        $membership = Membership::create([
            'user_id' => $target->id,
            'entity_id' => $entity->id,
            'position_id' => $position->id,
            'is_primary' => true,
            'started_at' => now(),
            'status' => 'active',
        ]);

        $this->actingAs($this->owner)
            ->post(route('admin.roles.assign.store'), [
                'user' => $target->id,
                'role' => Role::where('key', 'coordinator')->value('id'),
                'membership' => $membership->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('role_user', [
            'user_id' => $target->id,
            'membership_id' => $membership->id,
        ]);
    }
}
