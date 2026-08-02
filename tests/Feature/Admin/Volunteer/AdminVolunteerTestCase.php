<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\AdminVolunteerDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * أساس اختبارات لوحة إدارة التطوّع — يزرع الثوابت وإعدادات المجال،
 * ويمنح الصلاحيّات فرديًّا بنطاق ALL (12.2.1) بدل زرع المصفوفة كاملة.
 */
abstract class AdminVolunteerTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        /*
         | تُزرَع المصفوفة الحقيقيّة لا صفوفٌ تُخلَق وقت المنح: الصفّ المُخترَع
         | يأتي بلا `is_owner_only` فتسقط قاعدة **عزل الحسّاس** (12.2.1-ز-3)
         | بصمت، ويمرّ الاختبارُ على شاشةٍ يجب أن تُغلَق.
         */
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(AdminVolunteerDemoSeeder::class);
    }

    protected function makeUser(string $name = 'مسؤول اختبار'): User
    {
        return User::create([
            'name' => $name,
            'email' => str()->random(8).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(6)),
            'status' => 'active',
        ]);
    }

    /**
     * مالك المنصّة — لِما هو داخل **المجموعة المحميّة** (12.2.1-ز-3).
     * منحُ صلاحيّةٍ معزولة لا يفتحها: العزل يغلب الإسناد، فالفاعل الصحيح
     * لهذه الشاشات هو المالك نفسه لا أدمنٌ ممنوح.
     */
    protected function platformOwner(string $name = 'مالك المنصّة'): User
    {
        $user = $this->makeUser($name);
        $user->assignRole(Role::query()->where('key', config('access.owner_role'))->firstOrFail());

        app(AccessEngine::class)->forget();

        return $user;
    }

    /** منح صلاحيّات بنطاق ALL لهذا المستخدم وحده */
    protected function grant(User $user, string ...$keys): User
    {
        foreach ($keys as $key) {
            [$resource, $action] = explode('.', $key);

            $permission = Permission::firstOrCreate(['key' => $key], [
                'resource' => $resource,
                'action' => $action,
                'group' => 'اختبار',
                'label_ar' => $key,
                'allowed_scopes' => ['SELF', 'TEAM', 'SUBTREE', 'ENTITY', 'TRACK', 'ALL'],
            ]);

            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => $permission->id,
                'user_id' => $user->id,
                'membership_id' => null,
                'scope' => 'ALL',
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(AccessEngine::class)->forget($user);

        return $user;
    }
}
