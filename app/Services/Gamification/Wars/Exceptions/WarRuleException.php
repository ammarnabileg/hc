<?php

namespace App\Services\Gamification\Wars\Exceptions;

use RuntimeException;

/**
 * خرق قاعدة حرب — ورسالتها موجَّهة للمستخدم مباشرةً:
 * **ماذا حدث + ماذا تفعل** (2.17-ب).
 */
class WarRuleException extends RuntimeException
{
    public function __construct(string $message, private readonly ?float $shortfall = null)
    {
        parent::__construct($message);
    }

    /** كم ينقصه من التذاكر — لعرض زرّ الشحن في الواجهة */
    public function shortfall(): ?float
    {
        return $this->shortfall;
    }
}
