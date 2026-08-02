<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Role;
use App\Models\RoleUser;
use App\Models\User;
use Database\Seeders\AdminContentDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * أساس اختبارات مجال «إدارة التدريب والشهادات والتوجيه».
 * الصلاحيّة إلزاميّة على كلّ مسار (12.2.1) — فالأدمن هنا يحمل دور مالك المنصّة.
 */
abstract class AdminContentTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(AdminContentDemoSeeder::class);
    }

    protected function makeUser(array $attributes = []): User
    {
        return User::create($attributes + [
            'name' => 'مستخدم اختبار',
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => 'active',
        ]);
    }

    /** أدمن بصلاحيّات كاملة — مالك المنصّة يعلو الجميع (12.2.1). */
    protected function admin(): User
    {
        $user = $this->makeUser(['name' => 'أدمن المحتوى']);

        RoleUser::create([
            'role_id' => Role::query()->where('key', 'platform_owner')->value('id'),
            'user_id' => $user->id,
            'assigned_at' => now(),
        ]);

        return $user;
    }
}
