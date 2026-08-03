<?php

namespace App\Services\Volunteer\Escalation;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Volunteer\Retention\RepLadder;
use App\Services\Wallet\LedgerService;
use Illuminate\Database\Eloquent\Model;

/**
 * غلاف آمن حول دفتر الأستاذ (نقطة تكامل — قد لا يكون مبنيًّا بعد).
 *
 * لماذا غلاف؟ لأنّ مجالنا لا يملك خدمة المحفظة، فلو غابت وجب أن تستمرّ دورة
 * العمل بلا انهيار — والقيم تُسجَّل حين تتوفّر الخدمة لا قبلها.
 *
 * ومبدأ الفصل حاكم هنا (23 — القسم 6): التأخير يمسّ Rep ولا يمسّ VXP،
 * والجودة تحجّم VXP ولا تمسّ Rep — فلا خصم مزدوج على غلطة واحدة.
 */
class FlowLedger
{
    public const VXP = 'vxp';

    public const REP = 'rep';

    /** هل خدمة الدفتر متاحة أصلًا؟ */
    public static function available(): bool
    {
        return class_exists(LedgerService::class);
    }

    /** إضافة VXP — تُصرَف للمساهم لحظة اعتماد بنده (23 — القسم 4) */
    public static function creditVxp(User $user, float $amount, string $source, ?Model $reference = null, ?string $reason = null, ?int $createdBy = null): ?Transaction
    {
        if (! self::available() || $amount <= 0) {
            return null;
        }

        return app(LedgerService::class)->credit($user, self::VXP, $amount, $source, $reference, 'volunteer', $reason, $createdBy);
    }

    /**
     * خصم VXP — لا يقع آليًّا أبدًا (23 — القسم 5): إمّا قرار محكّم أو حجز
     * صريح من رصيد المالك بموافقته، ولذلك نمرّر `createdBy` دائمًا.
     */
    public static function debitVxp(User $user, float $amount, string $source, ?Model $reference = null, ?string $reason = null, ?int $createdBy = null): ?Transaction
    {
        if (! self::available() || $amount <= 0) {
            return null;
        }

        return app(LedgerService::class)->debit($user, self::VXP, $amount, $source, $reference, 'volunteer', $reason, $createdBy ?? $user->id);
    }

    /** حركة درجة الالتزام بقيمتها من جدول Rep الموحَّد (13.4-ن) — بإشارتها */
    public static function rep(User $user, float $value, string $source, ?Model $reference = null, ?string $reason = null, ?int $createdBy = null): ?Transaction
    {
        if (! self::available() || $value == 0.0) {
            return null;
        }

        $ledger = app(LedgerService::class);

        $transaction = $value > 0
            ? $ledger->credit($user, self::REP, $value, $source, $reference, 'volunteer', $reason, $createdBy)
            : $ledger->debit($user, self::REP, abs($value), $source, $reference, 'volunteer', $reason, $createdBy ?? $user->id);

        // ⭐ سلّم عتبات الهبوط الثلاث يقع **فورًا** لا في مسحة الغد (23-0.2)
        RepLadder::afterRepMovement($user, self::REP, $value, $transaction);

        return $transaction;
    }

    public static function balance(User $user, string $currency = self::VXP): float
    {
        if (! self::available()) {
            return 0.0;
        }

        return app(LedgerService::class)->balance($user, $currency);
    }
}
