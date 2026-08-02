<?php

namespace Tests\Feature\Security;

use App\Models\User;

/**
 * ⭐ **الحظر له أثر** (12.1-متقدّم-1·3).
 *
 * الفجوة المُصلَحة: `UserModeration::isContained()` كانت دالّةً صحيحة **لا
 * يستدعيها أحد** — فحسابٌ `status='banned'` كان يفتح `/dashboard` بـ200 عاديًّا.
 *
 * كلّ اختبار هنا يقابل جملةً منصوصة في 12.1 لا مجرّد شاشة تفتح.
 */
class AccountContainmentWallTest extends SecurityTestCase
{
    /** ⛔ المحظور **لا يفتح أيّ صفحة** — ويرى «تم حظر الحساب» ورسالة الأدمن */
    public function test_banned_user_cannot_open_any_page_and_sees_the_admin_message(): void
    {
        $banned = $this->makeUser('حساب محظور', [
            'status' => 'banned',
            'containment_reason' => 'إساءة لمستخدم تاني',
        ]);

        foreach (['/dashboard', '/help', '/wallet', '/profile'] as $path) {
            $this->actingAs($banned)
                ->get($path)
                ->assertForbidden()
                ->assertSee(setting('account.containment.banned_title'), false)
                ->assertSee(setting('account.containment.support_line'), false)
                // كارت «رسالة إداريّة» بالرسالة التي كتبها الأدمن
                ->assertSee(setting('account.containment.notice_title'), false)
                ->assertSee('إساءة لمستخدم تاني', false);
        }
    }

    /** الحظر بلا رسالة: الصفحة تظهر **بلا كارت رسالة فارغ** */
    public function test_the_admin_notice_card_is_hidden_when_there_is_no_message(): void
    {
        $banned = $this->makeUser('محظور بلا رسالة', ['status' => 'banned']);

        $this->actingAs($banned)
            ->get('/dashboard')
            ->assertForbidden()
            ->assertSee(setting('account.containment.banned_title'), false)
            ->assertDontSee(setting('account.containment.notice_title'), false);
    }

    /** المعلَّق ممنوع كذلك طول مدّته، ويرى موعد رجوعه */
    public function test_suspended_user_is_blocked_until_the_period_ends(): void
    {
        $suspended = $this->makeUser('حساب معلَّق', [
            'status' => 'suspended',
            'containment_reason' => 'محتوى مخالف',
            'suspended_until' => now()->addDays(3),
        ]);

        $this->actingAs($suspended)
            ->get('/dashboard')
            ->assertForbidden()
            ->assertSee(setting('account.containment.suspended_title'), false)
            ->assertSee('محتوى مخالف', false);
    }

    /** ⭐ التعليق **ينتهي وحده** بانقضاء مدّته فيعود الحساب بلا تدخّل من أحد */
    public function test_suspension_expires_by_itself_and_the_account_returns(): void
    {
        $user = $this->makeUser('انتهت مدّته', [
            'status' => 'suspended',
            'containment_reason' => 'محتوى مخالف',
            'suspended_until' => now()->subMinute(),
        ]);

        $this->grant($user, ['enrollments.view']);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->refresh();

        $this->assertSame('active', $user->status);
        $this->assertNull($user->suspended_until);
        $this->assertNull($user->containment_reason);

        // والرفع يُسجَّل بفاعلٍ نظاميّ — ما رفعه أحدٌ بل انقضاء المدّة
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.suspension.expired',
            'auditable_id' => $user->id,
            'user_id' => null,
        ]);
    }

    /** الدخول من الباب: المحظور يسجّل دخوله فيقع على صفحة الحظر لا على اللوحة */
    public function test_logging_in_lands_the_banned_user_on_the_wall_not_the_dashboard(): void
    {
        $banned = $this->makeUser('محظور بيحاول يدخل', [
            'status' => 'banned',
            'containment_reason' => 'حساب مكرّر',
        ]);

        $this->post(route('login'), [
            'identifier' => $banned->email,
            'password' => 'secret-password',
        ])->assertRedirect();

        $this->get('/dashboard')
            ->assertForbidden()
            ->assertSee(setting('account.containment.banned_title'), false);
    }

    /** ⭐ الخروج يفضل مفتوحًا — وإلّا حبسنا المحظور في جلسةٍ لا يقدر يقفلها */
    public function test_logout_stays_open_for_a_contained_account(): void
    {
        $banned = $this->makeUser('محظور عايز يخرج', ['status' => 'banned']);

        $this->actingAs($banned)->post(route('logout'))->assertRedirect();

        $this->assertGuest();
    }

    /** الحساب السليم لا يمسّه الجدار أصلًا */
    public function test_an_active_account_passes_through_untouched(): void
    {
        $user = $this->makeUser('حساب سليم');
        $this->grant($user, ['enrollments.view']);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    /** ردّ JSON للطلبات غير المتزامنة: 403 برسالة لا صفحة HTML */
    public function test_json_requests_get_a_403_payload_instead_of_a_page(): void
    {
        $banned = $this->makeUser('محظور بنداء JSON', [
            'status' => 'banned',
            'containment_reason' => 'محاولة اختراق أو تلاعب',
        ]);

        $this->actingAs($banned)
            ->getJson('/dashboard')
            ->assertForbidden()
            ->assertJson(['contained' => true, 'message' => 'محاولة اختراق أو تلاعب']);
    }

    /** حظرٌ من الأدمن ⟵ أثرٌ فوريّ: نفس الحساب يتقفل عليه الباب حالًا */
    public function test_banning_from_the_admin_page_closes_the_platform_immediately(): void
    {
        $admin = $this->admin([
            'admin_panel.view', 'users.view', 'users.edit', 'account_suspension.create',
        ]);
        $target = $this->makeUser('هيتحظر دلوقتي');

        $this->actingAs($admin)
            ->post(route('admin.users.ban', $target), ['reason' => 'محتوى مخالف'])
            ->assertRedirect();

        $this->actingAs(User::find($target->id))
            ->get('/dashboard')
            ->assertForbidden()
            ->assertSee('محتوى مخالف', false);
    }
}
