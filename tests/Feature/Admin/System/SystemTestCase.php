<?php

namespace Tests\Feature\Admin\System;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\AdminSystemDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * أساس اختبارات مجال «المتجر والماليّات والإحصائيّات والإعدادات والنظام».
 * كلّ اختبار هنا يقابل **قاعدةً منصوصةً** في الدستور لا مجرّد شاشة تفتح.
 */
abstract class SystemTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(AdminSystemDemoSeeder::class);
    }

    /** مالك المنصّة — يعلو الجميع ويملك المجموعة المحميّة (12.2.1) */
    protected function owner(): User
    {
        $user = $this->makeUser('مالك المنصّة');
        $user->assignRole(Role::query()->where('key', config('access.owner_role'))->firstOrFail());

        app(AccessEngine::class)->forget($user);

        return $user;
    }

    /** أدمن عامّ بصلاحيّات محدَّدة — وبلا أيّ صلاحيّة ماليّة */
    protected function admin(array $permissionKeys, string $name = 'أدمن عامّ'): User
    {
        $user = $this->makeUser($name);
        $this->grant($user, $permissionKeys);

        return $user;
    }

    protected function grant(User $user, array $permissionKeys): void
    {
        foreach ($permissionKeys as $key) {
            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => $this->permission($key)->id,
                'user_id' => $user->id,
                'membership_id' => null,
                'scope' => 'ALL',
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(AccessEngine::class)->forget($user);
    }

    /** الصلاحيّات المشتركة تُنشَأ عند الحاجة — أخفّ من تحميل المصفوفة كاملةً */
    protected function permission(string $key): Permission
    {
        [$resource, $action] = array_pad(explode('.', $key, 2), 2, 'view');

        return Permission::firstOrCreate(
            ['key' => $key],
            [
                'resource' => $resource,
                'action' => $action,
                'group' => 'اختبار',
                'label_ar' => $key,
                'allowed_scopes' => ['ALL'],
            ],
        );
    }

    protected function makeUser(string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => Str::lower(Str::random(10)).'@test.local',
            'password' => 'secret-password',
            'code' => Str::upper(Str::random(8)),
            'status' => 'active',
        ]);
    }
}
