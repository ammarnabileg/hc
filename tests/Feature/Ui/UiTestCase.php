<?php

namespace Tests\Feature\Ui;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UiDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * أساس اختبارات مجال «الواجهة والبروفايل والاستوديو»:
 * مصفوفة الصلاحيّات كاملة كما في الإنتاج — فما يمرّ هنا يمرّ هناك.
 */
abstract class UiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(UiDemoSeeder::class);
    }

    protected function trainee(array $attributes = []): User
    {
        $user = User::create([
            'name' => $attributes['name'] ?? 'متدرّب تجريبيّ',
            'email' => $attributes['email'] ?? Str::lower(Str::random(8)).'@test.local',
            'phone' => $attributes['phone'] ?? '+2010'.random_int(10000000, 99999999),
            'password' => 'secret-password',
            'code' => $attributes['code'] ?? 'U'.Str::upper(Str::random(7)),
            'status' => $attributes['status'] ?? 'active',
        ] + array_diff_key($attributes, array_flip(['name', 'email', 'phone', 'code', 'status'])));

        $user->assignRole('trainee');

        return $user->fresh();
    }

    /** مستخدم يملك صلاحيّة الاستخراج كصورة (12.14-ز) — بدور مخصَّص للاختبار */
    protected function exporter(array $attributes = []): User
    {
        $user = $this->trainee($attributes);

        $role = Role::firstOrCreate(
            ['key' => 'ui_exporter_test'],
            ['name_ar' => 'مستخرِج (اختبار)', 'is_system' => false],
        );

        $permission = Permission::firstOrCreate(
            ['key' => 'image_export.use'],
            [
                'resource' => 'image_export', 'action' => 'use', 'group' => 'استوديو الصور',
                'label_ar' => 'استخدام زرّ الاستخراج', 'allowed_scopes' => ['ALL'],
            ],
        );

        \Illuminate\Support\Facades\DB::table('permission_role')->updateOrInsert(
            ['role_id' => $role->id, 'permission_id' => $permission->id, 'scope' => 'ALL'],
            ['effect' => 'allow', 'updated_at' => now(), 'created_at' => now()],
        );

        $user->assignRole($role->key);

        return $user->fresh();
    }
}
