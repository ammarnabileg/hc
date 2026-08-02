<?php

namespace App\Services\Gamification\Exceptions;

use RuntimeException;

/**
 * الرصيد لا يكفي لدخول التحدّي.
 * لماذا رسالة تحمل الأرقام: رسالة الخطأ = ماذا حدث + ماذا تفعل (2.17-ب).
 */
class InsufficientBalanceException extends RuntimeException
{
    public function __construct(
        public readonly float $required,
        public readonly float $available,
        public readonly string $currencyLabel,
    ) {
        parent::__construct("رصيدك {$available} {$currencyLabel} والدخول محتاج {$required} — اشحن وارجع كمّل.");
    }

    public function shortfall(): float
    {
        return max(0, $this->required - $this->available);
    }
}
