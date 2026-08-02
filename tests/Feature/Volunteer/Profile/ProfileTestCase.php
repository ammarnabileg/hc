<?php

namespace Tests\Feature\Volunteer\Profile;

use App\Models\Membership;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\VolunteerOrgDemoSeeder;
use Database\Seeders\VolunteerProfileDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * قاعدة اختبارات «بروفايل المتطوّع» (13.4-م · 10.0) — تبني الهيكل التجريبيّ
 * ثمّ بيانات البروفايل فوقه (شكر · خصوصيّة · طلبات إظهار · ملاحظات).
 */
abstract class ProfileTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(VolunteerOrgDemoSeeder::class);
        $this->seed(VolunteerProfileDemoSeeder::class);
    }

    protected function actorWithRole(string $code, string $roleKey): User
    {
        $user = User::where('code', $code)->firstOrFail();
        $membership = $user->memberships()->where('status', 'active')->firstOrFail();

        $user->assignRole($roleKey, $membership);
        app(AccessEngine::class)->forget($user);

        return $user;
    }

    protected function userByCode(string $code): User
    {
        return User::where('code', $code)->firstOrFail();
    }

    protected function membershipOf(string $code): Membership
    {
        return $this->userByCode($code)->memberships()->where('status', 'active')->firstOrFail();
    }
}
