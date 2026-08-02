<?php

namespace Tests\Feature\Admin\Ops;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الصلاحيّة على **كلّ** مسار (12.2.1)، والعنصر الذي لا يملكه المستخدم
 * **يُخفى ولا يُعطَّل** (2.15-أ-7) — والتحقّق في الخادم لا في الواجهة.
 */
class AdminOpsPermissionsTest extends OpsTestCase
{
    public function test_every_screen_is_refused_without_permission(): void
    {
        $stranger = $this->admin([], 'مستخدم بلا صلاحيّات');

        $this->actingAs($stranger)->get(route('admin.ops.onboarding'))->assertForbidden();
        $this->actingAs($stranger)->get(route('admin.ops.onboarding.preview'))->assertForbidden();
        $this->actingAs($stranger)->get(route('admin.ops.updates'))->assertForbidden();
        $this->actingAs($stranger)->get(route('admin.ops.updates.history'))->assertForbidden();
        $this->actingAs($stranger)->get(route('admin.ops.system'))->assertForbidden();
    }

    public function test_guests_are_sent_to_login_not_to_the_screens(): void
    {
        $this->get(route('admin.ops.updates'))->assertRedirect(route('login'));
        $this->get(route('admin.ops.system'))->assertRedirect(route('login'));
        $this->get(route('admin.ops.onboarding'))->assertRedirect(route('login'));
    }

    /** قراءة الشاشة لا تعني تنفيذ أخطر ما فيها — كلّ فعل بصلاحيّته */
    public function test_read_permission_does_not_allow_dangerous_actions(): void
    {
        $viewer = $this->admin(['updates.view', 'system_health.view', 'backups.view', 'onboarding.view'], 'مطّلع');
        $this->set('updates.migrations_path', 'tests/Feature/Admin/Ops/fixtures/migrations');

        $this->actingAs($viewer)->post(route('admin.ops.updates.dry-run'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.ops.updates.migrate'), [
            'confirm' => 'تنفيذ', 'understood' => '1',
        ])->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.ops.updates.rollback'), [
            'confirm' => 'استرجاع', 'understood' => '1',
        ])->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.ops.system.backups.store'), ['kind' => 'database'])->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.ops.system.schedule'), [
            'frequency' => 'daily', 'time' => '03:00', 'kind' => 'full', 'keep' => 3,
        ])->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.ops.onboarding.slides.store'), [
            'screen' => 'welcome', 'title_ar' => 'مرحلة مهرّبة',
        ])->assertForbidden();

        $this->assertFalse(Schema::hasTable('ops_probe'));
        $this->assertSame(0, DB::table('backup_files')->count());
        $this->assertDatabaseMissing('onboarding_slides', ['title_ar' => 'مرحلة مهرّبة']);
    }

    /** الصلاحيّة المفقودة تُخفي الزرّ من الصفحة أصلًا — لا تعرضه رماديًّا */
    public function test_actions_without_permission_are_hidden_from_the_page(): void
    {
        $viewer = $this->admin(['system_health.view', 'backups.view'], 'مطّلع على النظام');

        $this->actingAs($viewer)->get(route('admin.ops.system'))
            ->assertOk()
            ->assertDontSee('نسخة احتياطيّة الآن')
            ->assertDontSee('حفظ الجدولة');
    }

    /** أيّ صلاحيّة من الاثنتين تكفي لفتح شاشة النظام — والباقي محجوب داخلها */
    public function test_either_health_or_backups_permission_opens_the_system_screen(): void
    {
        $this->actingAs($this->admin(['system_health.view'], 'صحّة فقط'))
            ->get(route('admin.ops.system'))->assertOk();

        $this->actingAs($this->admin(['backups.view'], 'نسخ فقط'))
            ->get(route('admin.ops.system'))->assertOk();
    }
}
