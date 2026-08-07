<?php

namespace Tests\Feature\Access;

use App\Http\Controllers\Admin\PermissionController;
use App\Models\Permission;
use App\Models\PermissionUser;
use App\Models\User;
use Database\Seeders\AdminCoreDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * شاشة «منح صلاحيّة فرديّة» (12.2.2 `permissions.assign`) — الباك-إند
 * (`PermissionController::update()`) كان يعمل بلا واجهة قطّ، ونُفِّذ بـ`curl`
 * مباشرةً وقت الأوديت. هذا الملفّ يغطّي الشاشة نفسها: فتحها محروسًا،
 * عرض الاستثناءات القائمة، وسحبها (`destroy()` الجديد).
 */
class PermissionAssignScreenTest extends TestCase
{
    use RefreshDatabase;

    /** صلاحيّة معزولة لمالك المنصّة وحده — تختبر عزل الحسّاس على السحب أيضًا */
    private const OWNER_ONLY_KEY = 'manual_rewards.list';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(AdminCoreDemoSeeder::class);

        $this->assertTrue(
            (bool) Permission::where('key', self::OWNER_ONLY_KEY)->value('is_owner_only'),
            'الاختبار قائم على مفتاحٍ معزول لمالك المنصّة وحده',
        );
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

    private function owner(): User
    {
        $owner = $this->makeUser('مالك المنصّة');
        $owner->assignRole('platform_owner');

        return $owner;
    }

    public function test_screen_is_forbidden_without_the_permission(): void
    {
        $stranger = $this->makeUser('بلا صلاحيّة');
        $target = $this->makeUser('هدف');

        $this->actingAs($stranger)
            ->get(route('admin.permissions.assign', ['user' => $target->id]))
            ->assertForbidden();
    }

    public function test_screen_opens_for_the_platform_owner_and_lists_existing_overrides(): void
    {
        $owner = $this->owner();
        $target = $this->makeUser('هدف');

        $permission = Permission::where('key', 'org_chart.view')->firstOrFail();

        PermissionUser::create([
            'permission_id' => $permission->id,
            'user_id' => $target->id,
            'scope' => 'ENTITY',
            'effect' => 'allow',
            'assigned_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->get(route('admin.permissions.assign', ['user' => $target->id]));

        $response->assertOk();
        $response->assertSee($permission->label_ar, false);
        $response->assertSee('ENTITY', false);
    }

    public function test_owner_can_revoke_an_individual_override(): void
    {
        $owner = $this->owner();
        $target = $this->makeUser('هدف');

        $permission = Permission::where('key', 'org_chart.view')->firstOrFail();

        $override = PermissionUser::create([
            'permission_id' => $permission->id,
            'user_id' => $target->id,
            'scope' => 'ENTITY',
            'effect' => 'allow',
            'assigned_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->delete(route('admin.permissions.destroy', $override))
            ->assertRedirect();

        $this->assertSame(0, PermissionUser::where('id', $override->id)->count());
    }

    /**
     * ⭐ عزل الحسّاس يحكم السحب كما يحكم المنح — الفحص المباشر على الكنترولر
     * (بلا مرور بميدلوير المسار) لأنّ `permissions.assign` نفسها معزولة، فمن
     * الناحية العمليّة لا يصل لهذا الفرع إلّا مالك المنصّة أصلًا؛ والحارس هنا
     * دفاعٌ إضافيّ لا يجوز أن يسقط لو تغيّر تصنيف `permissions.assign` لاحقًا.
     */
    public function test_revoking_an_owner_only_permissions_override_is_refused_to_a_non_owner(): void
    {
        $owner = $this->owner();
        $target = $this->makeUser('هدف');
        $nonOwner = $this->makeUser('غير مالك');

        $permission = Permission::where('key', self::OWNER_ONLY_KEY)->firstOrFail();

        $override = PermissionUser::create([
            'permission_id' => $permission->id,
            'user_id' => $target->id,
            'scope' => 'ALL',
            'effect' => 'allow',
            'assigned_by' => $owner->id,
        ]);

        $response = app(PermissionController::class)
            ->destroy(Request::create('/')->setUserResolver(fn () => $nonOwner), $override);

        $this->assertSame(1, PermissionUser::where('id', $override->id)->count(), 'العزل يمنع السحب — المالك وحده يملكه');
        $this->assertNotNull($response);
    }
}
