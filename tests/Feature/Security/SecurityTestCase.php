<?php

namespace Tests\Feature\Security;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\System\MaintenanceService;
use App\Support\Access\AccessEngine;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SecurityDemoSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * أساس اختبارات مجال «الأمان»: الصيانة · الاسترجاع والتحقّق ·
 * منطقة الخطر · احتواء الحسابات والانتحال · التحسين التدريجيّ.
 *
 * كلّ اختبار هنا يقابل **قاعدةً منصوصةً** في الدستور لا مجرّد شاشة تفتح.
 */
abstract class SecurityTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(SecurityDemoSeeder::class);

        Cache::forget('settings');
    }

    /** مالك المنصّة — يعلو الجميع ويملك المجموعة المحميّة (12.2.1) */
    protected function owner(): User
    {
        $user = $this->makeUser('مالك المنصّة');
        $user->assignRole(Role::query()->where('key', config('access.owner_role'))->firstOrFail());

        app(AccessEngine::class)->forget($user);

        return $user;
    }

    protected function admin(array $permissionKeys, string $name = 'أدمن'): User
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

    protected function makeUser(string $name, array $attributes = []): User
    {
        return User::create([
            'name' => $name,
            'email' => Str::lower(Str::random(10)).'@test.local',
            'password' => 'secret-password',
            'code' => Str::upper(Str::random(8)),
            'status' => 'active',
            ...$attributes,
        ]);
    }

    /** تشغيل وضع الصيانة كما يشغّله الأدمن تمامًا: فترة مفتوحة + علم مرفوع */
    protected function startMaintenance(int $hours = 2, string $message = 'بنطوّر حاجة حلوة — هنرجع قريب.'): void
    {
        // بيئة الاختبار تطلب من 127.0.0.1 وهو مستثنًى افتراضيًّا — نفرغ القائمة
        // حتى نختبر الجدار نفسه لا الاستثناء (والاستثناء له اختباره الخاصّ)
        DB::table('settings')->where('key', 'system.maintenance.exempt_ips')->update(['value' => '']);

        app(MaintenanceService::class)
            ->start($this->owner(), $message, $hours);

        Cache::forget('settings');
    }
}
