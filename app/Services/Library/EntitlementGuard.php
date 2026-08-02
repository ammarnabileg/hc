<?php

namespace App\Services\Library;

use App\Models\LibraryEntitlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * حارس الملكيّة والإتاحة (20.3 · 20.1).
 *
 * لماذا خدمة مستقلّة: لأنّ «الرابط لا يفتح لغير المالك» قاعدةٌ أمنيّة
 * تتكرّر في القارئ والبوب-أب والتحميل — فمصدرها واحد لا ثلاثة.
 */
class EntitlementGuard
{
    /** الإتاحة الحاليّة لعنصر يملكه المستخدم — أو null إن كان لا يملكه */
    public function entitlementFor(User $user, Model $item): ?LibraryEntitlement
    {
        return LibraryEntitlement::query()
            ->where('user_id', $user->id)
            ->where('itemable_type', $item->getMorphClass())
            ->where('itemable_id', $item->getKey())
            ->first();
    }

    public function owns(User $user, Model $item): bool
    {
        return $this->entitlementFor($user, $item) !== null;
    }

    /** هل نحن داخل فترة تشغيل العنصر؟ (عنصرٌ خارج فترته يظهر بحالته ولا يُخفى) */
    public function isAvailableNow(?LibraryEntitlement $entitlement): bool
    {
        if (! $entitlement) {
            return false;
        }

        $now = now();

        if ($entitlement->available_from && $entitlement->available_from->greaterThan($now)) {
            return false;
        }

        if ($entitlement->available_until && $entitlement->available_until->lessThan($now)) {
            return false;
        }

        return true;
    }

    /**
     * حالة الإتاحة بأسلوب أنيق: «متاح الآن / يبدأ يوم كذا» (20.1).
     *
     * @return array{state:string,label:string}
     */
    public function availability(?LibraryEntitlement $entitlement): array
    {
        if (! $entitlement) {
            return [
                'state' => 'idle',
                'label' => (string) setting('library.availability.not_owned_label', 'غير متاح'),
            ];
        }

        $now = now();

        if ($entitlement->available_from && $entitlement->available_from->greaterThan($now)) {
            return [
                'state' => 'warn',
                'label' => str_replace(
                    ':date',
                    $entitlement->available_from->translatedFormat((string) setting('library.availability.date_format', 'j F')),
                    (string) setting('library.availability.starts_label', 'يبدأ يوم :date'),
                ),
            ];
        }

        if ($entitlement->available_until && $entitlement->available_until->lessThan($now)) {
            return [
                'state' => 'idle',
                'label' => (string) setting('library.availability.ended_label', 'انتهت فترة الإتاحة'),
            ];
        }

        return [
            'state' => 'ok',
            'label' => (string) setting('library.availability.now_label', 'متاح الآن'),
        ];
    }
}
