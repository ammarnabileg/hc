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
 *
 * ⚠️ **وكان «إن وُجد» لا يتحقّق أبدًا:** التفويض كان يمرّر الوسائط **بالترتيب**
 * `[$user, $code, $amount, $source, $reason, $reference]` بينما توقيع الدفتر
 * `(…, $source, ?Model $reference, $layer, ?string $reason)` — فينزل نصّ السبب
 * في موضع `$reference` ويُرمى `TypeError`، ويبتلعه `catch (Throwable)` فيرجع
 * الجسر لكتابته الخاصّة **في كلّ نداء**. النتيجة: صفوفٌ بلا `applied_amount`
 * ولا مهلة اعتراض ولا سقف عملة — وكلّها ضماناتٌ في دفتر الأستاذ وحده.
 * فمن اليوم: التمرير **بوسائط مسمّاة**، والاسم لا ينزلق مع تغيّر التوقيع.
 */
class LedgerBridge
{
    private const LEDGER = 'App\Services\Wallet\LedgerService';

    /** «الرصيد لا يكفي» من الدفتر — نميّزها عن غياب الخدمة فلا نكتب مرّتين */
    private const WALLET_EXCEPTION = 'App\Services\Wallet\WalletException';

    /** رصيد المستخدم من عملة بعينها (coins · tickets · xp) */
    public function balance(User $user, string $currencyCode): float
    {
        if (class_exists(self::LEDGER)) {
            try {
                return (float) app(self::LEDGER)->balance($user, $currencyCode);
            } catch (Throwable) {
                // غياب الخدمة أو اختلاف توقيعها لا يمنع قراءة الرصيد من الجدول
            }
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

        $delegated = $this->delegate($direction, $user, $currencyCode, $amount, $source, $reason, $reference);

        if ($delegated !== null) {
            return $delegated;
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
     * تفويض الحركة لمجال المحفظة إن كان موجودًا — وإلّا نُكمل بأنفسنا.
     *
     * **الخصم يمرّ بـ`debitOrFail` لا بـ`debit`:** عقد هذا الجسر أنّ الخصم
     * يرجع `false` حين لا يكفي الرصيد (فيُلغى التسجيل في الفعاليّة)، بينما
     * `debit()` في الدفتر **يقصّ ما زاد بصمت** — فلو فوّضنا إليه لمرّ تسجيلٌ
     * بنصف ثمنه. و`debitOrFail` يرمي **قبل** أن يكتب شيئًا، فلا خصم مزدوج.
     *
     * @return bool|null نتيجة الدفتر، أو null حين لا دفتر أصلًا فنكتب بأنفسنا
     */
    private function delegate(
        string $direction,
        User $user,
        string $currencyCode,
        float $amount,
        string $source,
        string $reason,
        ?Model $reference,
    ): ?bool {
        if (! class_exists(self::LEDGER)) {
            return null;
        }

        $method = $direction === 'debit' ? 'debitOrFail' : 'credit';

        try {
            $service = app(self::LEDGER);
        } catch (Throwable) {
            return null;
        }

        if (! method_exists($service, $method)) {
            return null;
        }

        try {
            // ⭐ بوسائط مسمّاة: التمرير بالترتيب كان يُسقط `$reason` في موضع
            // `$reference` فيُرمى TypeError ويُبتلَع، فلا يُستدعى الدفتر أبدًا.
            $service->{$method}(
                user: $user,
                currencyCode: $currencyCode,
                amount: $amount,
                source: $source,
                reference: $reference,
                layer: 'training',
                reason: $reason,
            );

            return true;
        } catch (Throwable $e) {
            // رصيدٌ لا يكفي: قرارٌ صريح من الدفتر ولم يُكتَب شيء ⟵ «لا» لا رجوع
            if (is_a($e, self::WALLET_EXCEPTION)) {
                return false;
            }

            // غياب الخدمة أو اختلاف توقيعها لا يوقف التسجيل في فعاليّة
            return null;
        }
    }
}
