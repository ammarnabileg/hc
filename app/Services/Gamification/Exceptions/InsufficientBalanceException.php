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
        parent::__construct(strtr(setting('gamification_xp.insufficient_balance_exception.construct_1', 'رصيدك :p1 :p2 والدخول محتاج :p3 — اشحن وارجع كمّل.'), [':p1' => (string) ($available), ':p2' => (string) ($currencyLabel), ':p3' => (string) ($required)]));
    }

    public function shortfall(): float
    {
        return max(0, $this->required - $this->available);
    }
}
