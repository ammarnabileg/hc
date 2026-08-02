<?php

namespace Tests\Feature\Wallet;

use App\Models\Permission;
use App\Models\Setting;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** أساس مشترك لاختبارات المحفظة: العملات والإعدادات والصلاحيّات جاهزة قبل كلّ اختبار */
abstract class WalletTestCase extends TestCase
{
    use RefreshDatabase;

    /** صلاحيّات المتدرّب على محفظته */
    protected const WALLET_PERMISSIONS = ['wallet.view', 'wallet.list', 'wallet.export', 'topup.create', 'topup.list'];

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->user = $this->makeUser();
    }

    protected function makeUser(array $attributes = []): User
    {
        $user = User::create(array_merge([
            'name' => 'أحمد سمير',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'password' => 'secret-password',
            'code' => 'U'.Str::upper(Str::random(7)),
            'status' => 'active',
            'phone' => '0100'.random_int(1000000, 9999999),
        ], $attributes));

        $this->grant($user, self::WALLET_PERMISSIONS);

        return $user;
    }

    /** منح فرديّ بنطاق SELF — يكفي لشاشات صاحب الحساب */
    protected function grant(User $user, array $keys, string $scope = 'SELF'): void
    {
        foreach (Permission::query()->whereIn('key', $keys)->pluck('id') as $permissionId) {
            DB::table('permission_user')->insert([
                'permission_id' => $permissionId,
                'user_id' => $user->id,
                'membership_id' => null,
                'scope' => $scope,
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(AccessEngine::class)->forget($user);
    }

    protected function setSetting(string $key, string $value): void
    {
        Setting::query()->where('key', $key)->update(['value' => $value]);
        Cache::forget('settings');
    }
}
