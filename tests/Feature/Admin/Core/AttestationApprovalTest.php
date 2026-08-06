<?php

namespace Tests\Feature\Admin\Core;

use App\Models\Attestation;
use App\Models\Permission;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * اعتماد/رفض طلبات الإفادة (9.1 · 24.5): «الفورم/البوب-أب … + حالة الطلب» —
 * بلا هذه الشاشة تبقى الحالة «قيد الانتظار» للأبد، ولا وفاء لوعد «هنبلّغك».
 */
class AttestationApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function makeUser(string $name = 'مستخدم'): User
    {
        return User::create([
            'name' => $name,
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => 'active',
        ]);
    }

    private function withManage(): User
    {
        $user = $this->makeUser('مسؤول');

        foreach (['users.view', 'user_attestation.manage'] as $key) {
            $permission = Permission::query()->where('key', $key)->firstOrFail();

            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => $permission->id,
                'user_id' => $user->id,
                'membership_id' => null,
                'scope' => 'ALL',
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(AccessEngine::class)->forget();

        return $user->fresh();
    }

    /** يمنح صاحب الطلب صلاحيّة رؤية إفاداته — بمعزل عن مصفوفة الأدوار الكاملة */
    private function grantsOwnAttestations(User $trainee): void
    {
        $permission = Permission::query()->where('key', 'user_attestation.view')->firstOrFail();

        DB::table('permission_user')->insertOrIgnore([
            'permission_id' => $permission->id,
            'user_id' => $trainee->id,
            'membership_id' => null,
            'scope' => 'SELF',
            'effect' => 'allow',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(AccessEngine::class)->forget();
    }

    private function requestFor(User $trainee): Attestation
    {
        return Attestation::create([
            'user_id' => $trainee->id,
            'from_name' => 'شركة الاختبار',
            'body' => 'سبب الطلب',
            'status' => 'requested',
            'is_public' => false,
        ]);
    }

    public function test_the_admin_tab_lists_the_pending_request_and_approving_flips_its_status(): void
    {
        $admin = $this->withManage();
        $trainee = $this->makeUser('متدرّب');
        $attestation = $this->requestFor($trainee);

        $this->actingAs($admin)
            ->get(route('admin.users.show', ['user' => $trainee, 'tab' => 'admin']))
            ->assertOk()
            ->assertSee('شركة الاختبار');

        $this->actingAs($admin)
            ->post(route('admin.users.attestations.approve', [$trainee, $attestation]))
            ->assertRedirect();

        $this->assertSame('approved', $attestation->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attestation.approved', 'auditable_id' => $attestation->id]);
    }

    /** ⭐ الرفض بسبب واضح يصل للمستخدم — لا حالة صامتة (9.1 · 24.5) */
    public function test_rejecting_requires_a_reason_and_it_is_stored_for_the_user_to_see(): void
    {
        $admin = $this->withManage();
        $trainee = $this->makeUser('متدرّب');
        $this->grantsOwnAttestations($trainee);
        $attestation = $this->requestFor($trainee);

        $this->actingAs($admin)
            ->post(route('admin.users.attestations.reject', [$trainee, $attestation]), [])
            ->assertSessionHasErrors('reason');

        $this->assertSame('requested', $attestation->refresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.users.attestations.reject', [$trainee, $attestation]), ['reason' => 'الجهة مش موثَّقة'])
            ->assertRedirect();

        $attestation->refresh();
        $this->assertSame('rejected', $attestation->status);
        $this->assertSame('الجهة مش موثَّقة', $attestation->rejection_reason);

        $this->actingAs($trainee)
            ->get(route('attestations.index'))
            ->assertOk()
            ->assertSee('الجهة مش موثَّقة');
    }

    public function test_the_action_is_blocked_without_the_permission(): void
    {
        $stranger = $this->makeUser('غريب');
        $trainee = $this->makeUser('متدرّب');
        $attestation = $this->requestFor($trainee);

        $this->actingAs($stranger)
            ->post(route('admin.users.attestations.approve', [$trainee, $attestation]))
            ->assertForbidden();

        $this->assertSame('requested', $attestation->refresh()->status);
    }

    /** ⭐ مسار مُركَّب لمستخدمين مختلفين لا يصحّ — العزل صريح لا افتراض (12.2.1) */
    public function test_the_request_must_actually_belong_to_the_url_user(): void
    {
        $admin = $this->withManage();
        $trainee = $this->makeUser('متدرّب');
        $someoneElse = $this->makeUser('شخص آخر');
        $attestation = $this->requestFor($someoneElse);

        $this->actingAs($admin)
            ->post(route('admin.users.attestations.approve', [$trainee, $attestation]))
            ->assertNotFound();

        $this->assertSame('requested', $attestation->refresh()->status);
    }
}
