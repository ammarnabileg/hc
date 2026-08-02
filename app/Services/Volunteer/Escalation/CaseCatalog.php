<?php

namespace App\Services\Volunteer\Escalation;

/**
 * الحالات التسع على محرّك التصعيد وتسوياتها الآليّة (الدستور 23 — القسم 5).
 *
 * لماذا جدول واحد؟ لأنّ «التسوية عند فوات نافذة السقف» قاعدة دستوريّة نصًّا،
 * فلو تفرّقت في الكنترولرات اختلفت من شاشة لشاشة — وهنا مرجعها الوحيد.
 */
class CaseCatalog
{
    public const EXTENSION = 'extension';

    public const BLOCKED = 'blocked';

    public const APOLOGY = 'apology';

    public const NO_DELIVERY = 'no_delivery';

    public const ARBITRATION = 'arbitration';

    public const CONTRIBUTOR_WITHDRAW = 'contributor_withdraw';

    public const BROKEN_LINK = 'broken_link';

    public const REPEATED_RETURN = 'repeated_return';

    public const SUBTASK_BATCH = 'subtask_batch';

    /**
     * الحالات التسع بترتيب الدستور: النوع · الاسم · الأيقونة · القرارات المتاحة ·
     * التسوية الآليّة عند فوات نافذة السقف · وصفها المكتوب صراحةً في الشاشة.
     */
    public static function all(): array
    {
        return [
            self::EXTENSION => [
                'label' => 'طلب تمديد',
                'icon' => '⏳',
                'decisions' => ['approved' => 'موافقة', 'rejected' => 'رفض'],
                'settlement' => 'rejected',
                'settlement_label' => 'رفض',
            ],
            self::BLOCKED => [
                'label' => 'الموافقة على التعثّر',
                'icon' => '⛔',
                'decisions' => ['approved' => 'موافقة', 'rejected' => 'رفض'],
                'settlement' => 'rejected',
                'settlement_label' => 'رفض',
            ],
            self::APOLOGY => [
                'label' => 'الاعتذار عن عدم التسليم',
                'icon' => '🙏',
                'decisions' => ['accepted' => 'قبول الاعتذار', 'rejected' => 'رفض'],
                'settlement' => 'rejected',
                'settlement_label' => 'رفض',
            ],
            self::NO_DELIVERY => [
                'label' => 'مسار عدم التسليم',
                'icon' => '📤',
                'decisions' => [
                    'self_execute' => 'أنفّذها بنفسي',
                    'redistribute' => 'أفكّكها وأوزّعها',
                    'closed' => 'أغلقها',
                ],
                'settlement' => 'closed',
                'settlement_label' => 'تُغلَق',
                // ⭐ قاعدة نهائيّة: يصعد بلا خصم تباطؤ — مهمّة يتيمة تدور على مالك جديد
                'no_slowdown' => true,
                'notice' => 'بلا خصم تباطؤ — المهمّة يتيمة تدور على مالك جديد.',
            ],
            self::ARBITRATION => [
                'label' => 'التحكيم بين المالك والمساهم',
                'icon' => '⚖️',
                'decisions' => [
                    'award' => 'منح VXP',
                    'deduct' => 'خصم VXP',
                    'split' => 'قيمة وسط',
                    'shelved' => 'حفظ القضيّة',
                ],
                'settlement' => 'split',
                'settlement_label' => 'تسوية 50% للطرفين',
            ],
            self::CONTRIBUTOR_WITHDRAW => [
                'label' => 'طلب سحب مساهم',
                'icon' => '↩️',
                'decisions' => ['approved' => 'موافقة', 'rejected' => 'رفض'],
                'settlement' => 'approved',
                'settlement_label' => 'موافقة',
            ],
            self::BROKEN_LINK => [
                'label' => 'بلاغ رابط معطّل',
                'icon' => '🔗',
                'decisions' => ['link_works' => 'الرابط يعمل', 'link_broken' => 'الرابط معطّل فعلًا'],
                'settlement' => 'link_works',
                'settlement_label' => 'الرابط يعمل بلا خصم',
            ],
            self::REPEATED_RETURN => [
                'label' => 'إرجاع متكرّر',
                'icon' => '🔁',
                'decisions' => [
                    'returned' => 'إرجاع',
                    'approved' => 'اعتماد',
                    'finished' => 'إنهاء بقيمة Rep يدويّة',
                ],
                'settlement' => 'approved_no_rep',
                'settlement_label' => 'اعتماد بلا Rep',
            ],
            self::SUBTASK_BATCH => [
                'label' => 'مراجعة دفعة صب-تاسكات',
                'icon' => '🗂️',
                'decisions' => [
                    'approved' => 'موافقة جماعيّة',
                    'edited' => 'تعديل مباشر',
                    'item_deleted' => 'حذف بند',
                ],
                'settlement' => 'approved',
                'settlement_label' => 'اعتماد الدفعة كاملة',
            ],
        ];
    }

    public static function types(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $type): bool
    {
        return array_key_exists($type, self::all());
    }

    public static function get(string $type): array
    {
        return self::all()[$type] ?? [];
    }

    public static function label(string $type): string
    {
        return self::get($type)['label'] ?? $type;
    }

    public static function icon(string $type): string
    {
        return self::get($type)['icon'] ?? '•';
    }

    public static function decisions(string $type): array
    {
        return self::get($type)['decisions'] ?? [];
    }

    /** التسوية الآليّة عند فوات نافذة السقف — منصوصة بالحرف */
    public static function settlement(string $type): ?string
    {
        return self::get($type)['settlement'] ?? null;
    }

    public static function settlementLabel(string $type): string
    {
        return self::get($type)['settlement_label'] ?? '—';
    }

    /** هل يصعد هذا النوع بلا أثر تباطؤ؟ (مسار عدم التسليم وحده) */
    public static function skipsSlowdown(string $type): bool
    {
        return (bool) (self::get($type)['no_slowdown'] ?? false);
    }
}
