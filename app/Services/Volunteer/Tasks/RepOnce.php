<?php

namespace App\Services\Volunteer\Tasks;

use App\Models\Transaction;

/**
 * حارس «واقعة واحدة ⟵ حركة واحدة» (الدستور 23 — القسم 6).
 *
 * لماذا حارس أصلًا؟ لأنّ نفس الواقعة يلمسها أكثر من مسار (التسليم · الاعتماد ·
 * المسحة الدوريّة · التوليد المتكرّر)، فبلا مفتاحٍ يوسم الواقعة نفسها تتكرّر
 * الحركة فيتضاعف الجدول كلّه ويبلغ المتطوّع عتبات −8 و−9.5 و−10 في نصف الوقت
 * المنصوص. والمبدأ الحاكم صريح: «أيّ حدث بيلمس الاتنين مع بعض = خصم مزدوج على
 * غلطة واحدة — **ممنوع**».
 *
 * والمفتاح يُكتَب في `meta` بالمعاملة نفسها — فالسجلّ يشرح لماذا لم تتكرّر.
 */
class RepOnce
{
    /** هل سُجِّلت حركةٌ لهذه الواقعة بالضبط من قبل؟ */
    public static function done(string $eventKey): bool
    {
        return Transaction::query()->where('meta->rep_event', $eventKey)->exists();
    }

    /** وسم الحركة بمفتاح واقعتها — فلا يكتبها مسارٌ ثانٍ */
    public static function stamp(?Transaction $transaction, string $eventKey): ?Transaction
    {
        if (! $transaction) {
            return null;
        }

        $meta = (array) ($transaction->meta ?? []);
        $meta['rep_event'] = $eventKey;

        $transaction->forceFill(['meta' => $meta])->save();

        return $transaction;
    }

    /**
     * كتابة محروسة: لو الواقعة مسجَّلة لا يُكتَب شيء، وإلّا كُتبت ووُسِمت.
     *
     * @param  callable():?Transaction  $write
     */
    public static function record(string $eventKey, callable $write): ?Transaction
    {
        if (self::done($eventKey)) {
            return null;
        }

        return self::stamp($write(), $eventKey);
    }

    /** مفتاح واقعة تسليم بنسخته — فالإصلاح بعد الإرجاع واقعة أخرى بسلّمها المستقلّ */
    public static function deliveryKey(int $taskId, int $version): string
    {
        return 'task.delivery:'.$taskId.':'.$version;
    }

    /** مفتاح واقعة عدم التسليم — مرّة واحدة لكلّ مهمّة */
    public static function noDeliveryKey(int $taskId): string
    {
        return 'task.no_delivery:'.$taskId;
    }
}
