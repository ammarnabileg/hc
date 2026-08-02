<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * أساس اختبارات مجال «account»: مصفوفة الصلاحيّات كاملة كما في الإنتاج،
 * فما يمرّ هنا يمرّ هناك — ولا نخترع صلاحيّات للاختبار.
 */
abstract class AccountTestCase extends TestCase
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
}
