<?php

namespace App\Services\Wallet;

use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * دفتر الأستاذ — المصدر الوحيد لتغيير أيّ رصيد في المنصّة (19 · 13.4-ن).
 *
 * لماذا كلّ شيء يمرّ من هنا؟
 *  - لأنّ الرصيد لا يُلمَس إلّا داخل معاملة قاعدة بيانات بقفل صفّ المحفظة،
 *    فلا يتسابق نداءان على نفس المحفظة ولا تتغيّر خانةٌ بلا سطرٍ يشرحها.
 *  - ولأنّ حدود العملات (سقف Rep · تراكميّة VXP · حدّ الخسارة اليوميّ)
 *    قاعدة واحدة لا تتكرّر في كلّ مجال فتختلف من مكان لمكان.
 */
class LedgerService
{
    /** درجة الالتزام — وحدها التي يسري عليها حدّ الخسارة اليوميّ (13.4-ن-و) */
    public const REP = 'rep';

    /** إضافة رصيد */
    public function credit(
        User $user,
        string $currencyCode,
        float $amount,
        string $source,
        ?Model $reference = null,
        string $layer = 'training',
        ?string $reason = null,
        ?int $createdBy = null,
    ): Transaction {
        return $this->record($user, $currencyCode, abs($amount), $source, $reference, $layer, $reason, $createdBy);
    }

    /** خصم رصيد */
    public function debit(
        User $user,
        string $currencyCode,
        float $amount,
        string $source,
        ?Model $reference = null,
        string $layer = 'training',
        ?string $reason = null,
        ?int $createdBy = null,
    ): Transaction {
        return $this->record($user, $currencyCode, -abs($amount), $source, $reference, $layer, $reason, $createdBy);
    }

    /**
     * معاملة عكسيّة موثّقة — لا تعديل للأصل ولا حذف (19.4).
     * تُستعمَل في حالة `refunded` من البوّابة وفي تصحيح الخطأ التقنيّ.
     */
    public function reverse(Transaction $original, ?string $reason = null, ?int $createdBy = null): Transaction
    {
        $currency = Currency::query()->findOrFail($original->currency_id);
        $effective = (float) ($original->applied_amount ?? $original->amount);

        return $this->record(
            user: $original->user()->firstOrFail(),
            currencyCode: $currency->code,
            requested: -$effective,
            source: $original->source,
            reference: $original->reference,
            layer: $original->layer,
            reason: $reason,
            createdBy: $createdBy,
            isCorrection: true,
            correctsTransactionId: $original->id,
        );
    }

    /** الرصيد الحاليّ لعملة بعينها */
    public function balance(User $user, string $currencyCode): float
    {
        return (float) WalletBalance::query()
            ->where('user_id', $user->id)
            ->whereHas('currency', fn ($q) => $q->where('code', $currencyCode))
            ->value('balance');
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * كتابة الحركة: تحديث الرصيد + سطر في الجدول الموحّد بـ`balance_after`.
     *
     * @param  float  $requested  القيمة المطلوبة بإشارتها (+ إضافة · − خصم)
     */
    private function record(
        User $user,
        string $currencyCode,
        float $requested,
        string $source,
        ?Model $reference,
        string $layer,
        ?string $reason,
        ?int $createdBy,
        bool $isCorrection = false,
        ?int $correctsTransactionId = null,
    ): Transaction {
        return DB::transaction(function () use (
            $user, $currencyCode, $requested, $source, $reference,
            $layer, $reason, $createdBy, $isCorrection, $correctsTransactionId
        ) {
            $currency = Currency::query()->where('code', $currencyCode)->firstOrFail();

            $wallet = $this->lockedWallet($user, (int) $currency->id);

            $before = (float) $wallet->balance;
            $applied = round($requested, 2);
            $exceededDailyCap = false;

            /*
             | العملة التراكميّة غير القابلة للصرف (VXP · XP) لا تُخصَم آليًّا (13.4-ن):
             | الخصم منها لا يكون إلّا بقرار إنسانٍ موثَّق (تصحيح أو إجراء أدمن).
             */
            if ($applied < 0 && $currency->is_cumulative && ! $currency->is_spendable
                && ! $isCorrection && $createdBy === null) {
                $applied = 0.0;
            }

            // حدّ الخسارة اليوميّ لدرجة الالتزام: ما زاد يُسجَّل كاملًا بوسمه (13.4-ن-و)
            if ($applied < 0 && $currencyCode === self::REP) {
                $cap = rep_rule('limit.daily_loss');

                if ($cap < 0) {
                    $remaining = min(0.0, $cap - $this->lostToday($user, (int) $currency->id));

                    if ($applied < $remaining) {
                        $exceededDailyCap = true;
                        $applied = $remaining;
                    }
                }
            }

            // سقف العملة وحدّها الأدنى (Rep مسقوف −10…+10)
            $after = $before + $applied;

            if ($currency->min_value !== null) {
                $after = max($after, (float) $currency->min_value);
            }

            if ($currency->max_value !== null) {
                $after = min($after, (float) $currency->max_value);
            }

            $after = round($after, 2);
            $applied = round($after - $before, 2);

            $wallet->balance = $after;
            $wallet->lifetime_earned = round((float) $wallet->lifetime_earned + max($applied, 0), 2);
            $wallet->lifetime_spent = round((float) $wallet->lifetime_spent + abs(min($applied, 0)), 2);
            $wallet->save();

            return Transaction::create([
                'user_id' => $user->id,
                'currency_id' => $currency->id,
                // القيمة المطلوبة تُسجَّل كاملةً حتى لو لم تُطبَّق كلّها
                'amount' => round($requested, 2),
                'applied_amount' => $applied,
                'balance_after' => $after,
                'layer' => $layer,
                'source' => $source,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'created_by' => $createdBy,
                'exceeded_daily_cap' => $exceededDailyCap,
                'is_correction' => $isCorrection,
                'corrects_transaction_id' => $correctsTransactionId,
                // مهلة الاعتراض تبدأ من لحظة الحركة (13.4-ل)
                'objection_deadline_at' => now()->addDays((int) setting('rep.objection.window_days', 5)),
            ]);
        });
    }

    /** صفّ المحفظة مقفولًا حتى نهاية المعاملة — فلا يقرأ نداءان رصيدًا واحدًا معًا */
    private function lockedWallet(User $user, int $currencyId): WalletBalance
    {
        WalletBalance::query()->firstOrCreate(
            ['user_id' => $user->id, 'currency_id' => $currencyId],
            ['balance' => 0, 'lifetime_earned' => 0, 'lifetime_spent' => 0],
        );

        return WalletBalance::query()
            ->where('user_id', $user->id)
            ->where('currency_id', $currencyId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** مجموع ما خُصِم فعلًا اليوم (بالسالب) — الأساس الذي يُقاس عليه الحدّ اليوميّ */
    private function lostToday(User $user, int $currencyId): float
    {
        return (float) Transaction::query()
            ->where('user_id', $user->id)
            ->where('currency_id', $currencyId)
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(applied_amount, amount) < 0 THEN COALESCE(applied_amount, amount) ELSE 0 END), 0) AS total')
            ->value('total');
    }
}
