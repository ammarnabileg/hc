<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\WalletWithdrawal;
use App\Services\Wallet\LedgerService;
use App\Services\Wallet\WithdrawService;
use Database\Seeders\RoleSeeder;
use Tests\Feature\Wallet\WalletTestCase;

/**
 * لوحة إدارة طلبات السحب (19.2 · 19.3) — الشاشة كانت **غائبة كلّيًّا**: المبلغ
 * يُخصَم لحظة الطلب ولا يوجد أيّ مسارٍ يعتمده أو يرفضه، فيبقى معلَّقًا للأبد.
 *
 * ما يثبته هذا الملفّ حرفيًّا:
 *  1) الاعتماد يقفل الطلب «مستلمة» بلا تحريك رصيد (كان مخصومًا بالفعل).
 *  2) الرفض يردّ المبلغ فورًا بعكس صفّ الخصم الأصليّ بعينه.
 *  3) الاعتماد والرفض لمالك المنصّة وحده — ولو مُنِحت الصلاحيّة بالخطأ.
 *  4) لا معالجة مزدوجة لنفس الطلب.
 */
class WithdrawalAdminTest extends WalletTestCase
{
    private function owner(): User
    {
        $this->seed(RoleSeeder::class);

        $user = $this->makeUser();
        $user->assignRole('platform_owner');

        return $user;
    }

    /** أدمن عاديّ يرى الطلبات (نطاق ALL) لكن بلا صلاحيّة الاعتماد أو الرفض */
    private function viewerAdmin(): User
    {
        $user = $this->makeUser();
        $this->grant($user, ['withdraw.list'], 'ALL');

        return $user;
    }

    private function pendingWithdrawal(?User $trainee = null): WalletWithdrawal
    {
        $trainee ??= $this->makeUser();

        app(LedgerService::class)->credit($trainee, WithdrawService::CURRENCY, 100, 'test-seed');

        return app(WithdrawService::class)->request($trainee, 20, 'bank', '1234567890', 'صاحب الحساب');
    }

    // ------------------------------------------------------------ الاعتماد

    public function test_owner_approving_marks_the_request_paid_and_notifies_the_user_without_moving_the_balance(): void
    {
        $owner = $this->owner();
        $withdrawal = $this->pendingWithdrawal();
        $balanceBefore = app(LedgerService::class)->balance($withdrawal->user, WithdrawService::CURRENCY);

        $this->actingAs($owner)
            ->post(route('admin.withdrawals.approve', $withdrawal), ['note' => 'تحويل بنكيّ — رقم العمليّة 999'])
            ->assertRedirect(route('admin.withdrawals.index'));

        $withdrawal->refresh();
        $this->assertSame(WalletWithdrawal::PAID, $withdrawal->status);
        $this->assertSame('تحويل بنكيّ — رقم العمليّة 999', $withdrawal->admin_note);
        $this->assertSame($owner->id, $withdrawal->processed_by);
        $this->assertNotNull($withdrawal->processed_at);

        // الاعتماد لا يحرّك الرصيد — كان مخصومًا لحظة الطلب
        $this->assertSame($balanceBefore, app(LedgerService::class)->balance($withdrawal->user, WithdrawService::CURRENCY));

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $withdrawal->user_id,
            'reference_type' => $withdrawal->getMorphClass(),
            'reference_id' => $withdrawal->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'withdraw.approve',
            'auditable_id' => $withdrawal->id,
            'user_id' => $owner->id,
        ]);
    }

    /** بلا ملاحظة صرف مكتوبة: يُرفَض على الخادم لا يمرّ صامتًا */
    public function test_approve_requires_a_written_note(): void
    {
        $owner = $this->owner();
        $withdrawal = $this->pendingWithdrawal();

        $this->actingAs($owner)
            ->post(route('admin.withdrawals.approve', $withdrawal), ['note' => ''])
            ->assertSessionHasErrors('note');

        $this->assertSame(WalletWithdrawal::PENDING, $withdrawal->fresh()->status);
    }

    // ------------------------------------------------------------ الرفض

    public function test_owner_rejecting_refunds_the_exact_debited_amount(): void
    {
        $owner = $this->owner();
        $trainee = $this->makeUser();
        app(LedgerService::class)->credit($trainee, WithdrawService::CURRENCY, 100, 'test-seed');
        $balanceBeforeRequest = app(LedgerService::class)->balance($trainee, WithdrawService::CURRENCY);

        $withdrawal = app(WithdrawService::class)->request($trainee, 20, 'bank', '1234567890');
        $this->assertLessThan($balanceBeforeRequest, app(LedgerService::class)->balance($trainee, WithdrawService::CURRENCY));

        $this->actingAs($owner)
            ->post(route('admin.withdrawals.reject', $withdrawal), ['reason' => 'رقم الحساب غير صحّ'])
            ->assertRedirect(route('admin.withdrawals.index'));

        $withdrawal->refresh();
        $this->assertSame(WalletWithdrawal::REJECTED, $withdrawal->status);
        $this->assertSame('رقم الحساب غير صحّ', $withdrawal->reject_reason);
        $this->assertNotNull($withdrawal->refund_transaction_id);

        // المبلغ رجع بالضبط — لا أكثر ولا أقلّ
        $this->assertSame($balanceBeforeRequest, app(LedgerService::class)->balance($trainee, WithdrawService::CURRENCY));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'withdraw.reject',
            'auditable_id' => $withdrawal->id,
        ]);
    }

    public function test_reject_requires_a_written_reason(): void
    {
        $owner = $this->owner();
        $withdrawal = $this->pendingWithdrawal();

        $this->actingAs($owner)
            ->post(route('admin.withdrawals.reject', $withdrawal), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(WalletWithdrawal::PENDING, $withdrawal->fresh()->status);
    }

    // ------------------------------------------------------------ 🔒 مالك المنصّة وحده

    /** ⭐ يرى الطلبات (نطاق ALL) لكن لا يقدر يعتمد ولا يرفض — الحسّاس مقفول مرّتين */
    public function test_a_viewer_admin_can_see_but_not_approve_or_reject(): void
    {
        $admin = $this->viewerAdmin();
        $withdrawal = $this->pendingWithdrawal();

        $this->actingAs($admin)->get(route('admin.withdrawals.index'))->assertOk()->assertSee($withdrawal->number);
        $this->actingAs($admin)->get(route('admin.withdrawals.show', $withdrawal))->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.withdrawals.approve', $withdrawal), ['note' => 'محاولة'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.withdrawals.reject', $withdrawal), ['reason' => 'محاولة'])
            ->assertForbidden();

        $this->assertSame(WalletWithdrawal::PENDING, $withdrawal->fresh()->status);
    }

    // ------------------------------------------------------------ لا معالجة مزدوجة

    public function test_an_already_processed_request_cannot_be_approved_again(): void
    {
        $owner = $this->owner();
        $withdrawal = $this->pendingWithdrawal();

        $this->actingAs($owner)->post(route('admin.withdrawals.approve', $withdrawal), ['note' => 'أوّل مرّة']);
        $this->assertSame(WalletWithdrawal::PAID, $withdrawal->fresh()->status);

        $this->actingAs($owner)
            ->post(route('admin.withdrawals.approve', $withdrawal), ['note' => 'محاولة تانية'])
            ->assertSessionHasErrors('note');

        // ملاحظة الصرف لم تتغيّر — المعالجة الثانية لم تنفّذ شيئًا
        $this->assertSame('أوّل مرّة', $withdrawal->fresh()->admin_note);
    }
}
