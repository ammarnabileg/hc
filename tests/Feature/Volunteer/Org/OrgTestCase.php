<?php

namespace Tests\Feature\Volunteer\Org;

use App\Models\Membership;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\VolunteerOrgDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * قاعدة اختبارات مجال «قسمي» — تبني الهيكل التجريبيّ الكامل
 * (قسم رئيسيّ + ثلاث فرعيّات + عنصر شرفيّ + مهامّ + بطاقات).
 */
abstract class OrgTestCase extends TestCase
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
    }

    /** مستخدم تجريبيّ بكوده، ومعه دوره داخل عضويّته (الدور «ماذا» والعضويّة «أين») */
    protected function actorWithRole(string $code, string $roleKey): User
    {
        $user = User::where('code', $code)->firstOrFail();
        $membership = $user->memberships()->where('status', 'active')->firstOrFail();

        $user->assignRole($roleKey, $membership);
        app(AccessEngine::class)->forget($user);

        return $user;
    }

    protected function membershipOf(string $code): Membership
    {
        return User::where('code', $code)->firstOrFail()
            ->memberships()->where('status', 'active')->firstOrFail();
    }
}
