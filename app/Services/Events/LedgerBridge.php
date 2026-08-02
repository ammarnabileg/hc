<?php

namespace App\Services\Events;

use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * جسر المحفظة (القسم 19).
 *
 * السبب: مجال الفعاليّات والدعوات يقيّد ويصرف على نفس دفتر المحفظة،
 * لكنّه لا يملك مجال المحفظة — فإن وُجد `App\Services\Wallet\LedgerService`
 * فهو المرجع، وإلّا فهذا الجسر يكتب على نفس الجداول بأمان ولا ينكسر بغيابه.
 */
class LedgerBridge
{
    private const LEDGER = 'App\Services\Wallet\LedgerService';

    /** رصيد المستخدم من عملة بعينها (coins · tickets · xp) */
    public function balance(User $user, string $currencyCode): float
    {
        if ($result = $this->delegate('balance', [$user, $currencyCode])) {
            return (float) $result['value'];
        }

        return (float) $user->balances()
            ->whereHas('currency', fn ($q) => $q->where('code', $currencyCode))
            ->value('balance');
    }

    /** إضافة رصيد — تُرجع false إن تعذّر (عملة غير معرَّفة مثلًا) */
    public function credit(User $user, string $currencyCode, float $amount, string $source, string $reason, ?Model $reference = null): bool
    {
        return $this->move($user, $currencyCode, abs($amount), $source, $reason, $reference, 'credit');
    }

    /** خصم رصيد — تُرجع false إن كان الرصيد لا يكفي */
    public function debit(User $user, string $currencyCode, float $amount, string $source, string $reason, ?Model $reference = null): bool
    {
        return $this->move($user, $currencyCode, abs($amount), $source, $reason, $reference, 'debit');
    }

    private function move(User $user, string $currencyCode, float $amount, string $source, string $reason, ?Model $reference, string $direction): bool
    {
        if ($amount <= 0) {
            return true;
        }

        if ($result = $this->delegate($direction, [$user, $currencyCode, $amount, $source, $reason, $reference])) {
            return (bool) $result['value'];
        }

        $currency = Currency::query()->where('code', $currencyCode)->first();

        if (! $currency) {
            return false;
        }

        return DB::transaction(function () use ($user, $currency, $amount, $source, $reason, $reference, $direction) {
            $wallet = WalletBalance::query()
                ->where('user_id', $user->id)
                ->where('currency_id', $currency->id)
                ->lockForUpdate()
                ->first();

            $balance = (float) ($wallet->balance ?? 0);

            // لا يُخصَم ما لا يوجد — والرسالة للمستخدم تُصاغ في طبقة الخدمة
            if ($direction === 'debit' && $balance < $amount) {
                return false;
            }

            $signed = $direction === 'debit' ? -$amount : $amount;
            $after = $balance + $signed;

            $wallet = WalletBalance::query()->updateOrCreate(
                ['user_id' => $user->id, 'currency_id' => $currency->id],
                [
                    'balance' => $after,
                    'lifetime_earned' => (float) ($wallet->lifetime_earned ?? 0) + max($signed, 0),
                    'lifetime_spent' => (float) ($wallet->lifetime_spent ?? 0) + max(-$signed, 0),
                ],
            );

            Transaction::create([
                'user_id' => $user->id,
                'currency_id' => $currency->id,
                'amount' => $signed,
                'balance_after' => $after,
                'layer' => 'training',
                'source' => $source,
                'reason' => $reason,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->getKey(),
            ]);

            return true;
        });
    }

    /**
     * تفويض العمليّة لمجال المحفظة إن كان موجودًا — وإلّا نُكمل بأنفسنا.
     *
     * @return array{value: mixed}|null
     */
    private function delegate(string $method, array $arguments): ?array
    {
        if (! class_exists(self::LEDGER)) {
            return null;
        }

        try {
            $service = app(self::LEDGER);

            if (! method_exists($service, $method)) {
                return null;
            }

            return ['value' => $service->{$method}(...$arguments)];
        } catch (Throwable) {
            // غياب الخدمة أو اختلاف توقيعها لا يوقف التسجيل في فعاليّة
            return null;
        }
    }
}
