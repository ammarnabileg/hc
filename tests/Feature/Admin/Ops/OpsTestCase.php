<?php

namespace Tests\Feature\Admin\Ops;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\AdminOpsDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * أساس اختبارات صفحات النظام (12.7-أ · هـ · و).
 * كلّ اختبار هنا يقابل **قاعدةً منصوصةً** في الدستور لا مجرّد شاشة تفتح.
 */
abstract class OpsTestCase extends TestCase
{
    use RefreshDatabase;

    /** مجلّد نسخ معزول للاختبار — حتى لا نلوّث نسخ التنصيب الحقيقيّة */
    protected string $backupFolder = 'framework/testing/ops-backups';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(AdminOpsDemoSeeder::class);

        $this->set('backups.path', $this->backupFolder);
        $this->cleanBackups();
    }

    protected function tearDown(): void
    {
        $this->cleanBackups();

        parent::tearDown();
    }

    /** ضبط إعداد أثناء الاختبار — لأنّ كلّ رقم ونصّ في هذا المجال إعداد (2.13) */
    protected function set(string $key, mixed $value): void
    {
        $value = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);

        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['group' => 'backups', 'label_ar' => $key, 'type' => 'string', 'value' => $value, 'default_value' => $value],
        );

        Cache::forget('settings');
    }

    protected function owner(): User
    {
        $user = $this->makeUser('مالك المنصّة');
        $user->assignRole(Role::query()->where('key', config('access.owner_role'))->firstOrFail());

        app(AccessEngine::class)->forget($user);

        return $user;
    }

    /** أدمن بصلاحيّات محدَّدة بالاسم — والباقي ممنوع عنه فعلًا لا شكلًا */
    protected function admin(array $permissionKeys, string $name = 'أدمن نظام'): User
    {
        $user = $this->makeUser($name);

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

        return $user;
    }

    protected function permission(string $key): Permission
    {
        [$resource, $action] = array_pad(explode('.', $key, 2), 2, 'view');

        return Permission::firstOrCreate(
            ['key' => $key],
            [
                'resource' => $resource,
                'action' => $action,
                'group' => 'النظام',
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

    protected function backupPath(): string
    {
        return storage_path($this->backupFolder);
    }

    private function cleanBackups(): void
    {
        foreach (glob($this->backupPath().DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            @unlink($file);
        }
    }
}
