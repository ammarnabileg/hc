<?php

namespace Tests\Feature\Errors;

use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\CoreSeeder;
use Database\Seeders\HttpTextDemoSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * الفجوة المُصلَحة: كلّ `abort(403)` داخل اللوحة كان يردّ صفحة Laravel الخام
 * (بلا نظام تصميم ولا وجهة بديلة) لغياب `resources/views/errors/403.blade.php`.
 * هذا الاختبار يثبت أنّ الصفحة الآن من نظام التصميم برسالةٍ من `setting()`
 * وزرّ عودةٍ واحد — بانتحال `support_admin` (يملك بابَ اللوحة بلا مفتاح
 * الإعدادات) ثمّ فتح `/admin/settings`.
 */
class ForbiddenPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(HttpTextDemoSeeder::class);
    }

    private function supportAdmin(): User
    {
        $user = User::create([
            'name' => 'مسؤول الدعم',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'password' => 'secret-password',
            'code' => Str::upper(Str::random(8)),
            'status' => 'active',
        ]);

        $user->assignRole('support_admin');
        app(AccessEngine::class)->forget($user);

        return $user;
    }

    /** 403 اللوحة صار بتصميم النظام لا صفحة Laravel الخام */
    public function test_admin_forbidden_page_renders_the_design_system_card_with_a_single_action(): void
    {
        $support = $this->supportAdmin();

        $response = $this->actingAs($support)->get('/admin/settings');

        $response->assertForbidden()
            ->assertSee(setting('admin_roles.permission_guard.forbidden_page_message'), false)
            ->assertSee(setting('admin_roles.permission_guard.forbidden_page_action'), false)
            ->assertSee(route('admin.dashboard'), false);

        // البطاقة نفسها من نظام التصميم — لا صفحة Symfony/Laravel الافتراضيّة
        $response->assertSee('class="card p-8 text-center"', false);
        $response->assertDontSee('Symfony', false);
    }
}
