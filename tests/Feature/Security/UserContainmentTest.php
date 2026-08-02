<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Security\Impersonator;
use Illuminate\Support\Facades\DB;

/**
 * أدوات احتواء الحساب المسيء (12.1).
 *
 * الفجوة المُصلَحة: `banned/suspended` كانتا **تسميتَي عرض** بلا أيّ إجراء.
 */
class UserContainmentTest extends SecurityTestCase
{
    private const SUPPORT = [
        'admin_panel.view', 'users.view', 'users.edit',
        'account_suspension.create', 'account_suspension.delete', 'user_sessions.delete',
    ];

    /** الأدوات ظاهرة في صفحة المستخدم — وما لا يملكه المشاهد يُخفى تمامًا (2.15-أ-7) */
    public function test_containment_tools_show_only_for_who_owns_the_permission(): void
    {
        $target = $this->makeUser('حساب مستهدَف');

        $this->actingAs($this->admin(self::SUPPORT))
            ->get(route('admin.users.show', ['user' => $target, 'tab' => 'advanced']))
            ->assertOk()
            ->assertSee(setting('admin.moderation.title'), false)
            ->assertSee('حظر الحساب', false);

        // أدمن بلا صلاحيّات احتواء: لا يرى القسم أصلًا
        $this->actingAs($this->admin(['admin_panel.view', 'users.view'], 'أدمن قراءة'))
            ->get(route('admin.users.show', ['user' => $target, 'tab' => 'advanced']))
            ->assertOk()
            ->assertDontSee(setting('admin.moderation.title'), false);
    }

    /** الحظر بحالة **ورسالة** — والحالة تنعكس فعلًا لا تسمية عرض */
    public function test_ban_sets_the_status_reason_and_kills_sessions(): void
    {
        $admin = $this->admin(self::SUPPORT);
        $target = $this->makeUser('حساب مسيء');
        UserDevice::create(['user_id' => $target->id, 'session_id' => 'abc', 'last_active_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.users.ban', $target), ['reason' => 'إساءة لمستخدم تاني'])
            ->assertRedirect();

        $target->refresh();

        $this->assertSame('banned', $target->status);
        $this->assertSame('إساءة لمستخدم تاني', $target->containment_reason);
        $this->assertDatabaseMissing('user_devices', ['user_id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.ban', 'auditable_id' => $target->id]);
    }

    /** التعليق المؤقّت بمدّة — بيرجع لوحده، والحدّ الأقصى إعداد لا رقم محروق */
    public function test_suspension_stores_an_end_date_and_respects_the_max(): void
    {
        $admin = $this->admin(self::SUPPORT);
        $target = $this->makeUser('حساب معلَّق');

        $this->actingAs($admin)
            ->post(route('admin.users.suspend', $target), ['reason' => 'محتوى مخالف', 'days' => 5])
            ->assertRedirect();

        $target->refresh();

        $this->assertSame('suspended', $target->status);
        $this->assertNotNull($target->suspended_until);
        $this->assertEqualsWithDelta(5, now()->diffInDays($target->suspended_until), 1);

        // فوق الحدّ يُرفَض من التحقّق لا يُقصّ بصمت
        $this->actingAs($admin)
            ->post(route('admin.users.suspend', $target), [
                'reason' => 'محتوى مخالف',
                'days' => (int) setting('admin.moderation.suspend_max_days', 90) + 1,
            ])
            ->assertSessionHasErrors('days');
    }

    /** رفع الاحتواء يرجّع الحساب شغّالًا ويمسح آثار المنع */
    public function test_release_restores_the_account(): void
    {
        $admin = $this->admin(self::SUPPORT);
        $target = $this->makeUser('حساب محظور', ['status' => 'banned', 'containment_reason' => 'قديم']);

        $this->actingAs($admin)->post(route('admin.users.release', $target))->assertRedirect();

        $target->refresh();

        $this->assertSame('active', $target->status);
        $this->assertNull($target->containment_reason);
    }

    /** ⭐ إنهاء كلّ الجلسات يعمل: الأجهزة والسيشن معًا و«فكّرني» يتبطّل */
    public function test_ending_all_sessions_clears_devices_sessions_and_remember_token(): void
    {
        $admin = $this->admin(self::SUPPORT);
        $target = $this->makeUser('حساب بجلسات');
        $target->forceFill(['remember_token' => 'old-token'])->saveQuietly();

        UserDevice::create(['user_id' => $target->id, 'session_id' => 's1', 'last_active_at' => now()]);
        UserDevice::create(['user_id' => $target->id, 'session_id' => 's2', 'last_active_at' => now()]);

        DB::table('sessions')->insert([
            'id' => 's1', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($admin)->post(route('admin.users.sessions.end', $target))->assertRedirect();

        $this->assertDatabaseMissing('user_devices', ['user_id' => $target->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        $this->assertNotSame('old-token', $target->refresh()->remember_token);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.sessions.end_all', 'auditable_id' => $target->id]);
    }

    /** تأكيد البريد يدويًّا — لمن اتعطّل عنده الرمز */
    public function test_admin_can_verify_the_email_manually(): void
    {
        $admin = $this->admin(self::SUPPORT);
        $target = $this->makeUser('بريد غير مؤكَّد');

        $this->actingAs($admin)->post(route('admin.users.verify-email', $target))->assertRedirect();

        $this->assertNotNull($target->refresh()->email_verified_at);
    }

    /** رابط تغيير كلمة السرّ **قابل للنسخ** يظهر مرّة واحدة في الصفحة */
    public function test_password_link_is_generated_and_shown_once_for_copying(): void
    {
        $admin = $this->admin(self::SUPPORT);
        $target = $this->makeUser('محتاج رابط');

        $this->actingAs($admin)
            ->post(route('admin.users.password-link', $target))
            ->assertSessionHas('moderation_password_link');

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $target->email]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password.link', 'auditable_id' => $target->id]);
    }

    /** ⛔ الحسابات المحميّة: مالك المنصّة والمرء نفسه لا يُحتوَيان */
    public function test_protected_accounts_cannot_be_contained(): void
    {
        $admin = $this->admin(self::SUPPORT);
        $owner = $this->owner();

        $this->actingAs($admin)->post(route('admin.users.ban', $owner), ['reason' => 'محاولة'])->assertRedirect();
        $this->assertSame('active', $owner->refresh()->status);

        $this->actingAs($admin)->post(route('admin.users.ban', $admin), ['reason' => 'محاولة'])->assertRedirect();
        $this->assertSame('active', $admin->refresh()->status);
    }

    // ---------------------------------------------------------------- الانتحال

    /** ⭐ Impersonate يُسجَّل في `audit_logs` ويحوّل الهويّة فعلًا */
    public function test_impersonation_is_audited_and_switches_the_identity(): void
    {
        $owner = $this->owner();
        $target = $this->makeUser('مستخدم للتصفّح');

        $this->actingAs($owner)
            ->post(route('admin.users.impersonate', $target))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($target);
        $this->assertSame($owner->id, session(Impersonator::SESSION_KEY));

        $log = AuditLog::query()->where('action', 'impersonation.start')->latest('id')->firstOrFail();

        $this->assertSame($owner->id, $log->user_id);
        $this->assertSame($target->id, $log->auditable_id);
        $this->assertSame($target->code, $log->new_values['target_code']);
    }

    /** الشريط العلويّ المعرِّف + زرّ العودة ظاهران في كلّ صفحة أثناء الانتحال */
    public function test_banner_and_return_button_appear_on_every_page(): void
    {
        $owner = $this->owner();
        $target = $this->makeUser('مستخدم للتصفّح');

        $this->actingAs($owner)->post(route('admin.users.impersonate', $target));

        $this->get('/help')
            ->assertOk()
            ->assertSee(setting('impersonation.stop_label'), false)
            ->assertSee(route('admin.impersonate.stop'), false);
    }

    /** زرّ العودة يرجّع الأدمن لحسابه — ويكتب سطر عودة في السجلّ */
    public function test_stopping_returns_the_admin_and_is_audited(): void
    {
        $owner = $this->owner();
        $target = $this->makeUser('مستخدم للتصفّح');

        $this->actingAs($owner)->post(route('admin.users.impersonate', $target));

        $this->post(route('admin.impersonate.stop'))->assertRedirect(route('admin.users.index'));

        $this->assertAuthenticatedAs($owner);
        $this->assertNull(session(Impersonator::SESSION_KEY));
        $this->assertDatabaseHas('audit_logs', ['action' => 'impersonation.stop', 'user_id' => $owner->id]);
    }

    /** ⛔ الانتحال مجموعة محميّة: أدمن الدعم بلا الصلاحيّة يُرفَض على المسار نفسه */
    public function test_impersonation_is_guarded_by_an_owner_only_permission(): void
    {
        $admin = $this->admin(self::SUPPORT, 'أدمن دعم');
        $target = $this->makeUser('مستخدم');

        $this->actingAs($admin)->post(route('admin.users.impersonate', $target))->assertForbidden();

        // والمصفوفة المعتمَدة تُعرّفها مجموعةً محميّة لمالك المنصّة وحده (12.2.1-5)
        $matrix = collect(json_decode(file_get_contents(database_path('data/permissions.json')), true))
            ->where('resource', 'impersonation');

        $this->assertTrue($matrix->isNotEmpty());
        $this->assertTrue($matrix->every(fn ($row) => (bool) $row['is_owner_only']));
    }

    /** ⛔ ولا يُنتحَل مالك المنصّة */
    public function test_platform_owner_cannot_be_impersonated(): void
    {
        $owner = $this->owner();
        $other = $this->owner();

        $this->actingAs($owner)->post(route('admin.users.impersonate', $other))->assertRedirect();
        $this->assertAuthenticatedAs($owner);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'impersonation.start', 'auditable_id' => $other->id]);
    }

    /** المستخدم المحظور لا يقدر يدخل تاني — الحظر له أثر عند الباب */
    public function test_banned_user_sessions_are_gone_so_he_must_log_in_again(): void
    {
        $admin = $this->admin(self::SUPPORT);
        $target = $this->makeUser('حساب مسيء');

        $this->actingAs($admin)->post(route('admin.users.ban', $target), ['reason' => 'حساب مكرّر']);

        $this->assertFalse(User::find($target->id)->isActive());
    }
}
