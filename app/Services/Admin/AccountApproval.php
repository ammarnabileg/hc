<?php

namespace App\Services\Admin;

use App\Models\Referral;
use App\Models\Role;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Services\Wallet\LedgerService;
use App\Support\Access\AccessEngine;
use Illuminate\Support\Facades\DB;

/**
 * اعتماد الحسابات (الدستور 2.5-د).
 *
 * ⭐ القاعدة النهائيّة: **التفعيل مجّانيّ باعتماد إداريّ** — لا رسوم تفعيل ولا اشتراك
 * ولا أيّ حاجز ماليّ عند الباب. فالخدمة دي بتغيّر الحالة وتمنح الدور وتصرف الهديّة،
 * وما بتلمسش أيّ رصيد كشرطٍ للدخول.
 *
 * وهديّة الريفيرال (تذكرة ترحيب المدعوّ) تُصرَف **بعد** قبول الحساب لا قبله.
 */
class AccountApproval
{
    public function __construct(
        private readonly AuditTrail $audit,
        private readonly LedgerService $ledger,
        private readonly AccessEngine $access,
    ) {}

    /** أسباب الرفض من الإعدادات (CRUD من لوحة الإدارة) */
    public function rejectReasons(): array
    {
        $reasons = setting('admin.approvals.reject_reasons', []);

        return is_array($reasons) ? array_values($reasons) : [];
    }

    public function bulkLimit(): int
    {
        return max(1, (int) setting('admin.approvals.bulk_max', 50));
    }

    /**
     * اعتماد حساب واحد.
     *
     * @return bool هل تغيّرت حالة الحساب فعلًا؟
     */
    public function approve(User $actor, User $account): bool
    {
        if ($account->status !== 'pending') {
            return false;
        }

        DB::transaction(function () use ($actor, $account) {
            $account->forceFill([
                'status' => 'active',
                'activated_at' => now(),
                'activated_by' => $actor->id,
                'rejection_reason' => null,
            ])->save();

            $this->grantTraineeRole($account, $actor);
            $this->grantWelcomeTicket($account);

            $this->audit->record($actor, 'user.approved', $account,
                ['status' => 'pending'],
                ['status' => 'active'],
            );
        });

        $this->access->forget($account);

        if (setting('admin.approvals.notify_user', true)) {
            Notifier::send(
                $account,
                'account',
                (string) setting('admin.approvals.accept_message', 'تمّ قبول حسابك — أهلًا بيك معانا 🎉'),
                null,
                url('/dashboard'),
            );
        }

        return true;
    }

    /** رفض حساب بسبب واضح — والرسالة تشرح ولا تعاتب (2.17-ج) */
    public function reject(User $actor, User $account, string $reason): bool
    {
        if ($account->status !== 'pending') {
            return false;
        }

        $account->forceFill([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ])->save();

        $this->audit->record($actor, 'user.rejected', $account,
            ['status' => 'pending'],
            ['status' => 'rejected', 'reason' => $reason],
        );

        if (setting('admin.approvals.notify_user', true)) {
            Notifier::send(
                $account,
                'account',
                strtr((string) setting('admin.approvals.reject_message', 'حسابك محتاج مراجعة: :reason'), [':reason' => $reason]),
            );
        }

        return true;
    }

    /** الدور الافتراضيّ لكلّ حساب مفعَّل (12.2.3-ج) */
    private function grantTraineeRole(User $account, User $actor): void
    {
        $key = (string) setting('admin.approvals.default_role', 'trainee');

        if ($role = Role::where('key', $key)->first()) {
            $account->assignRole($role, null, $actor->id);
        }

        // «تحت المراجعة» انتهى دوره بمجرّد الاعتماد
        if ($pending = Role::where('key', 'pending_review')->first()) {
            $account->roles()->detach($pending->id);
        }
    }

    /**
     * تذكرة الترحيب للمدعوّ — تُصرَف مرّةً واحدةً بعد القبول،
     * والعلامة `referrals.welcome_ticket_granted` هي التي تمنع التكرار.
     */
    private function grantWelcomeTicket(User $account): void
    {
        if (! setting('admin.approvals.grant_referral_gift', true)) {
            return;
        }

        $referral = Referral::where('referred_id', $account->id)
            ->where('welcome_ticket_granted', false)
            ->first();

        if (! $referral) {
            return;
        }

        $tickets = (float) setting('admin.approvals.welcome_tickets', 1);

        if ($tickets > 0) {
            $this->ledger->credit(
                user: $account,
                currencyCode: 'tickets',
                amount: $tickets,
                source: 'referral',
                reference: $referral,
                layer: 'training',
                reason: 'تذكرة ترحيب بعد قبول الحساب',
            );
        }

        $referral->forceFill(['welcome_ticket_granted' => true])->save();
    }
}
