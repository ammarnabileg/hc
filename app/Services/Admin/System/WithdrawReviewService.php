<?php

namespace App\Services\Admin\System;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletWithdrawal;
use App\Services\Wallet\LedgerService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * مراجعة طلبات سحب الأرباح في لوحة الإدارة (19.2 · 19.3).
 *
 * ⭐ المبلغ **مخصومٌ بالفعل** لحظة الطلب (`WithdrawService::request`) — فالاعتماد
 * هنا لا يحرّك رصيدًا، إنّما يقفل الطلب «مستلمة» بعد صرفه فعليًّا خارج المنصّة.
 * ⛔ والرفض وحده يردّ المبلغ: عكس صفّ الخصم الأصليّ بعينه (`debit_transaction_id`)
 * لا خصمًا جديدًا مقلوب الإشارة — فالتصحيح مربوطٌ بأصله في دفتر الأستاذ (13.4).
 */
class WithdrawReviewService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /** عدّاد التاب: أيّ طلبٍ لم يُبتّ فيه بعد */
    public function pendingCount(): int
    {
        return WalletWithdrawal::query()->where('status', WalletWithdrawal::PENDING)->count();
    }

    /**
     * ⭐ الاعتماد = تأكيد أنّ التحويل خرج فعلًا خارج المنصّة — لا تُحرَّك الأرقام هنا.
     *
     * @throws RuntimeException عند طلبٍ ليس «قيد المراجعة» أو بلا سبب مكتوب
     */
    public function approve(WalletWithdrawal $withdrawal, User $actor, string $note): WalletWithdrawal
    {
        if ($withdrawal->status !== WalletWithdrawal::PENDING) {
            throw new RuntimeException(setting('finance.withdraw_review.approve_1', 'الطلب ده مش في حالة «قيد المراجعة» — راجع حالته الأوّل.'));
        }

        $note = trim($note);

        if ($note === '') {
            throw new RuntimeException(setting('finance.withdraw_review.approve_2', 'اكتب ملاحظة الصرف — بيتسجّل في سجلّ التدقيق ويوصل صاحب الطلب.'));
        }

        return DB::transaction(function () use ($withdrawal, $actor, $note) {
            $locked = WalletWithdrawal::query()->whereKey($withdrawal->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== WalletWithdrawal::PENDING) {
                throw new RuntimeException(setting('finance.withdraw_review.approve_3', 'الطلب اتعالج بالفعل.'));
            }

            $locked->update([
                'status' => WalletWithdrawal::PAID,
                'admin_note' => $note,
                'processed_by' => $actor->id,
                'processed_at' => now(),
            ]);

            $this->notify(
                $locked,
                setting('finance.withdraw_review.approve_4', 'طلب السحب اتصرف ✓'),
                strtr(setting('finance.withdraw_review.approve_5', 'طلبك رقم :p1 اتحوّل — :p2.'), [':p1' => (string) $locked->number, ':p2' => $note]),
            );

            $this->audit($locked, 'withdraw.approve', ['status' => WalletWithdrawal::PENDING], ['status' => WalletWithdrawal::PAID, 'note' => $note], $actor);

            return $locked->refresh();
        });
    }

    /**
     * ⭐ الرفض يردّ المبلغ المحجوز فورًا — عكس صفّ الخصم الأصليّ بعينه (13.4).
     *
     * @throws RuntimeException عند طلبٍ ليس «قيد المراجعة» أو بلا سبب مكتوب
     */
    public function reject(WalletWithdrawal $withdrawal, User $actor, string $reason): WalletWithdrawal
    {
        if ($withdrawal->status !== WalletWithdrawal::PENDING) {
            throw new RuntimeException(setting('finance.withdraw_review.reject_1', 'الطلب ده مش في حالة «قيد المراجعة» — راجع حالته الأوّل.'));
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new RuntimeException(setting('finance.withdraw_review.reject_2', 'سبب الرفض إلزاميّ — المستخدم لازم يفهم يعدّل إيه.'));
        }

        return DB::transaction(function () use ($withdrawal, $actor, $reason) {
            $locked = WalletWithdrawal::query()->whereKey($withdrawal->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== WalletWithdrawal::PENDING) {
                throw new RuntimeException(setting('finance.withdraw_review.reject_3', 'الطلب اتعالج بالفعل.'));
            }

            $debit = Transaction::query()->findOrFail($locked->debit_transaction_id);
            $refund = $this->ledger->reverse($debit, $reason, $actor->id);

            $locked->update([
                'status' => WalletWithdrawal::REJECTED,
                'reject_reason' => $reason,
                'refund_transaction_id' => $refund->id,
                'processed_by' => $actor->id,
                'processed_at' => now(),
            ]);

            $this->notify(
                $locked,
                setting('finance.withdraw_review.reject_4', 'طلب السحب اترفض'),
                strtr(setting('finance.withdraw_review.reject_5', ':p1 — والمبلغ رجع لمحفظتك، تقدر تبعت طلبًا جديدًا.'), [':p1' => $reason]),
            );

            $this->audit($locked, 'withdraw.reject', ['status' => WalletWithdrawal::PENDING], ['status' => WalletWithdrawal::REJECTED, 'reason' => $reason], $actor);

            return $locked->refresh();
        });
    }

    private function notify(WalletWithdrawal $withdrawal, string $title, string $body): void
    {
        AppNotification::create([
            'user_id' => $withdrawal->user_id,
            'layer' => 'platform',
            'category' => 'wallet',
            'title' => $title,
            'body' => $body,
            'url' => route('wallet.withdrawals'),
            'reference_type' => $withdrawal->getMorphClass(),
            'reference_id' => $withdrawal->getKey(),
        ]);
    }

    private function audit(WalletWithdrawal $withdrawal, string $action, array $old, array $new, User $actor): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => $withdrawal->getMorphClass(),
            'auditable_id' => $withdrawal->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
        ]);
    }
}
