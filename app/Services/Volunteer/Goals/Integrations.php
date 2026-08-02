<?php

namespace App\Services\Volunteer\Goals;

use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * جسر آمن إلى الخدمات المشتركة (دفتر الأستاذ وبوّابة الإشعارات).
 *
 * لماذا جسر؟ لأنّ المجالات تُبنى متوازيةً، فقد لا تكون الخدمة المشتركة موجودة بعد.
 * فنستعملها إن وُجدت (`class_exists`)، وإلّا نكتب بأبسط صورة صحيحة بدلًا من الانهيار —
 * ولا يكرّر أيّ مجالٍ منطقَ الحدود، لأنّه يبقى في مكانه الواحد متى وُجد.
 */
final class Integrations
{
    public const LEDGER = \App\Services\Wallet\LedgerService::class;

    public const NOTIFIER = \App\Services\Notifications\Notifier::class;

    /** طبقة التطوّع — كي لا تختلط معاملات التطوّع بمعاملات التدريب في العرض */
    public const LAYER = 'volunteer';

    public static function hasLedger(): bool
    {
        return class_exists(self::LEDGER);
    }

    public static function hasNotifier(): bool
    {
        return class_exists(self::NOTIFIER);
    }

    /** إضافة رصيد (VXP أو Rep) — بحدود العملة وحدّ الخسارة اليوميّ حيث ينطبق */
    public static function credit(
        User $user,
        string $currencyCode,
        float $amount,
        string $source,
        ?Model $reference = null,
        ?string $reason = null,
        ?int $createdBy = null,
    ): ?Transaction {
        if (self::hasLedger()) {
            return app(self::LEDGER)->credit(
                $user, $currencyCode, $amount, $source, $reference, self::LAYER, $reason, $createdBy,
            );
        }

        return self::fallbackRecord($user, $currencyCode, abs($amount), $source, $reference, $reason, $createdBy);
    }

    /** خصم رصيد — و«VXP لا يُخصَم آليًّا» محكومٌ في دفتر الأستاذ نفسه (13.4-ن) */
    public static function debit(
        User $user,
        string $currencyCode,
        float $amount,
        string $source,
        ?Model $reference = null,
        ?string $reason = null,
        ?int $createdBy = null,
    ): ?Transaction {
        if (self::hasLedger()) {
            return app(self::LEDGER)->debit(
                $user, $currencyCode, $amount, $source, $reference, self::LAYER, $reason, $createdBy,
            );
        }

        return self::fallbackRecord($user, $currencyCode, -abs($amount), $source, $reference, $reason, $createdBy);
    }

    public static function balance(User $user, string $currencyCode): float
    {
        return (float) WalletBalance::query()
            ->where('user_id', $user->id)
            ->whereHas('currency', fn ($q) => $q->where('code', $currencyCode))
            ->value('balance');
    }

    /** إشعار في طبقة التطوّع — ويُهمَل بصمت إن لم تكن البوّابة موجودة */
    public static function notify(
        User $user,
        string $category,
        string $title,
        ?string $body = null,
        ?string $url = null,
        ?Carbon $deadlineAt = null,
        bool $requiresAction = false,
    ): void {
        if (! self::hasNotifier()) {
            return;
        }

        (self::NOTIFIER)::send($user, $category, $title, $body, $url, self::LAYER, $deadlineAt, $requiresAction);
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * كتابة مبسّطة عند غياب دفتر الأستاذ: رصيد + سطر معاملة داخل معاملة واحدة،
     * وبحدّي العملة الأدنى والأعلى فقط (Rep مسقوف −10…+10).
     */
    private static function fallbackRecord(
        User $user,
        string $currencyCode,
        float $requested,
        string $source,
        ?Model $reference,
        ?string $reason,
        ?int $createdBy,
    ): ?Transaction {
        $currency = Currency::query()->where('code', $currencyCode)->first();

        if (! $currency) {
            return null;
        }

        return DB::transaction(function () use ($user, $currency, $requested, $source, $reference, $reason, $createdBy) {
            $wallet = WalletBalance::query()->firstOrCreate(
                ['user_id' => $user->id, 'currency_id' => $currency->id],
                ['balance' => 0, 'lifetime_earned' => 0, 'lifetime_spent' => 0],
            );

            $before = (float) $wallet->balance;
            $after = $before + $requested;

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
                'amount' => round($requested, 2),
                'applied_amount' => $applied,
                'balance_after' => $after,
                'layer' => self::LAYER,
                'source' => $source,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'created_by' => $createdBy,
                'objection_deadline_at' => now()->addDays((int) setting('rep.objection.window_days', 5)),
            ]);
        });
    }
}
