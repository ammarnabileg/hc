<?php

namespace App\Services\Store;

use RuntimeException;

/**
 * خطأ الشراء برسالةٍ تشرح **ماذا حدث + ماذا تفعل** (2.17-ب) وبسببٍ مرمَّز
 * تستعمله الواجهة لتفتح الفعل المناسب (مثل [اشحن المحفظة] داخل البوب-أب).
 */
class PurchaseException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function of(string $reason, string $settingKey, string $default): self
    {
        return new self($reason, (string) setting($settingKey, $default));
    }
}
