<?php

namespace Tests\Feature\Admin\System;

use App\Models\Course;
use App\Models\Product;
use App\Models\Role;
use App\Models\RoleUser;
use App\Models\Task;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;

/**
 * سلّة المحذوفات الموحّدة (`soft_delete_recovery` — 12.2.2 سطر 2188-2191).
 *
 * الفجوة المؤكَّدة: الصلاحيّات الأربع كانت مزروعة في `permissions.json` ولا
 * دور واحد يحملها (`RolePermissionSeeder` خالٍ منها) ولا شاشة تقرؤها — مصفوفة
 * صلاحيّاتٍ ميّتة. هذا الاختبار يثبت الثلاثة معًا: **الربط بالدور** · **الشاشة
 * الحقيقيّة عبر سبعة موارد** · **الأفعال الأربعة** (عرض/استعراض/استرجاع داخل
 * النافذة الزمنيّة/حذف نهائيّ).
 */
class TrashRecoveryTest extends SystemTestCase
{
    /** ⭐ يثبت إصلاح الفجوة نفسها: `tech_admin` صار يحمل المفاتيح الأربعة فعليًّا بعد الزرع */
    public function test_tech_admin_role_is_wired_to_the_previously_dead_permission(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $techAdmin = $this->userWithRole('tech_admin');
        $supportAdmin = $this->userWithRole('support_admin');

        foreach (['view', 'list', 'restore', 'delete'] as $action) {
            $this->assertTrue($techAdmin->allows("soft_delete_recovery.{$action}"), "tech_admin يجب أن يملك soft_delete_recovery.{$action}");
            $this->assertFalse($supportAdmin->allows("soft_delete_recovery.{$action}"), "support_admin ما ينفعش يملك soft_delete_recovery.{$action}");
        }
    }

    public function test_trash_screen_is_hidden_behind_403_without_the_permission(): void
    {
        $user = $this->admin([]);

        $this->actingAs($user)->get(route('admin.ops.trash'))->assertForbidden();
    }

    public function test_index_lists_soft_deleted_rows_across_different_resources(): void
    {
        $admin = $this->admin(['soft_delete_recovery.list']);

        $course = Course::create(['slug' => 'course-'.str()->random(6), 'name_ar' => 'تدريب سلّة المحذوفات']);
        $task = Task::create(['title' => 'مهمّة سلّة المحذوفات']);
        $course->delete();
        $task->delete();

        $response = $this->actingAs($admin)->get(route('admin.ops.trash'));

        $response->assertOk()
            ->assertSee('تدريب سلّة المحذوفات', false)
            ->assertSee('مهمّة سلّة المحذوفات', false);
    }

    public function test_restore_within_the_retention_window_brings_the_row_back(): void
    {
        $admin = $this->admin(['soft_delete_recovery.restore']);

        $course = Course::create(['slug' => 'course-'.str()->random(6), 'name_ar' => 'تدريب قابل للاسترجاع']);
        $course->delete();

        $this->actingAs($admin)
            ->post(route('admin.ops.trash.restore', ['type' => 'courses', 'id' => $course->id]))
            ->assertRedirect();

        $this->assertNotNull(Course::query()->find($course->id));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'soft_delete_recovery.restore',
            'auditable_type' => Course::class,
            'auditable_id' => $course->id,
            'user_id' => $admin->id,
        ]);
    }

    /** ⭐ شرط `.restore` المنصوص بالحرف: «داخل النافذة الزمنيّة» — لا يُقرأ الافتراضيّ */
    public function test_restore_after_the_retention_window_is_refused(): void
    {
        $admin = $this->admin(['soft_delete_recovery.restore']);

        $course = Course::create(['slug' => 'course-'.str()->random(6), 'name_ar' => 'تدريب فات معاده']);
        $course->delete();
        // انتهت مهلة الاحتفاظ الافتراضيّة (30 يومًا) — سطر 5169
        $course->forceFill(['deleted_at' => now()->subDays(40)])->save();

        $this->actingAs($admin)
            ->post(route('admin.ops.trash.restore', ['type' => 'courses', 'id' => $course->id]))
            ->assertRedirect()
            ->assertSessionHasErrors('trash');

        $this->assertNull(Course::query()->find($course->id));
        $this->assertNotNull(Course::withTrashed()->find($course->id));
    }

    public function test_permanent_delete_removes_the_row_for_good_and_is_documented(): void
    {
        $admin = $this->admin(['soft_delete_recovery.delete']);

        $product = Product::create(['slug' => 'product-'.str()->random(6), 'name_ar' => 'منتج للحذف النهائيّ']);
        $product->delete();

        $this->actingAs($admin)
            ->delete(route('admin.ops.trash.destroy', ['type' => 'products', 'id' => $product->id]))
            ->assertRedirect();

        $this->assertNull(Product::withTrashed()->find($product->id));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'soft_delete_recovery.delete',
            'auditable_type' => Product::class,
            'auditable_id' => $product->id,
            'user_id' => $admin->id,
        ]);
    }

    private function userWithRole(string $roleKey): User
    {
        $user = $this->makeUser('صاحب دور '.$roleKey);

        RoleUser::create([
            'role_id' => Role::query()->where('key', $roleKey)->value('id'),
            'user_id' => $user->id,
            'assigned_at' => now(),
        ]);

        app(AccessEngine::class)->forget($user);

        return $user;
    }
}
