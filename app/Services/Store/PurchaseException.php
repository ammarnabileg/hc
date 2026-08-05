<?php

namespace App\Services\Store;

use RuntimeException;

/**
 * خطأ الشراء برسالةٍ تشرح **ماذا حدث + ماذا تفعل** (2.17-ب) وبسببٍ مرمَّز
 * تستعمله الواجهة لتفتح الفعل المناسب (مثل [اشحن المحفظة] داخل البوب-أب).
 */
class PurchaseException extends RuntimeException
{
    /**
     * ⭐ مفاتيح الرسائل التي يقرؤها `of()` عبر `$settingKey` — يسجّلها
     * `SettingsCoverage::deadKeys()` فلا يُبلَّغ عنها «ميّتة»: هي مقروءةٌ فعلًا
     * لكن بمفتاحٍ يصل عبر معامل الدالّة لا نصًّا حرفيًّا داخل `setting()` نفسها،
     * والماسح النصّيّ لا يتتبّع هذا التمرير.
     *
     * @var array<int, string>
     */
    public const MESSAGE_KEYS = [
        'store.unavailable_text',
        'store.owned_text',
        'store.cart.empty_text',
        'store.disabled_text',
        'store.refund.ack_required_text',
        'store.insufficient_text',
    ];

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function of(string $reason, string $settingKey, string $default): self
    {
        return new self($reason, (string) setting($settingKey, $default));
    }
}
