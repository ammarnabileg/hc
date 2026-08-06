<?php

namespace App\Services\Developers;

/**
 * توقيع الحمولة الصادرة (12.15-ج): HMAC-SHA256 — **نفس أسلوب
 * `FawaterkClient::expectedHash`** (اشتقاقٌ خارج نداء الشبكة، ومقارنةٌ آمنة
 * زمنيًّا عند التحقّق، لا `==` مباشرة).
 *
 * ⛔ **لا حمولة بلا توقيع تُرسَل أبدًا** (12.15-ب) — `DeliverWebhookJob` يشتقّ
 * التوقيع من هذا الصنف وحده، فمصدر التوقيع واحدٌ لكلّ المنصّة.
 */
class WebhookSigner
{
    /** توقيع الحمولة الخام (JSON) — يُشتقّ من نفس النصّ المُرسَل حرفيًّا لا من مصفوفةٍ تُعاد تسلسلها لاحقًا */
    public static function sign(string $secret, string $rawPayload): string
    {
        return hash_hmac('sha256', $rawPayload, $secret);
    }

    /** مقارنة آمنة زمنيًّا — يستخدمها من يتحقّق من توقيع واردٍ (مثلًا في اختبارات الطرف المستقبِل) */
    public static function matches(string $provided, string $secret, string $rawPayload): bool
    {
        return hash_equals(self::sign($secret, $rawPayload), $provided);
    }
}
