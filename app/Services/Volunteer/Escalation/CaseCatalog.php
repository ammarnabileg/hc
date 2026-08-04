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

    /** المخالفة الجسيمة: موافقة مستوى أعلى بنافذة 24 ساعة (13.4-ن-هـ) */
    public const BEHAVIOR_SEVERE = 'behavior_severe';

    /**
     * الحالات التسع بترتيب الدستور: النوع · الاسم · الأيقونة · القرارات المتاحة ·
     * التسوية الآليّة عند فوات نافذة السقف · وصفها المكتوب صراحةً في الشاشة.
     */
    public static function all(): array
    {
        return [
            self::EXTENSION => [
                'label' => setting('volunteer.case_catalog.all_1', 'طلب تمديد'),
                'icon' => '⏳',
                'decisions' => ['approved' => setting('volunteer.case_catalog.all_2', 'موافقة'), 'rejected' => setting('volunteer.case_catalog.all_3', 'رفض')],
                'settlement' => 'rejected',
                'settlement_label' => setting('volunteer.case_catalog.all_4', 'رفض'),
            ],
            self::BLOCKED => [
                'label' => setting('volunteer.case_catalog.all_5', 'الموافقة على التعثّر'),
                'icon' => '⛔',
                'decisions' => ['approved' => setting('volunteer.case_catalog.all_6', 'موافقة'), 'rejected' => setting('volunteer.case_catalog.all_7', 'رفض')],
                'settlement' => 'rejected',
                'settlement_label' => setting('volunteer.case_catalog.all_8', 'رفض'),
            ],
            self::APOLOGY => [
                'label' => setting('volunteer.case_catalog.all_9', 'الاعتذار عن عدم التسليم'),
                'icon' => '🙏',
                'decisions' => ['accepted' => setting('volunteer.case_catalog.all_10', 'قبول الاعتذار'), 'rejected' => setting('volunteer.case_catalog.all_11', 'رفض')],
                'settlement' => 'rejected',
                'settlement_label' => setting('volunteer.case_catalog.all_12', 'رفض'),
            ],
            self::NO_DELIVERY => [
                'label' => setting('volunteer.case_catalog.all_13', 'مسار عدم التسليم'),
                'icon' => '📤',
                'decisions' => [
                    'self_execute' => setting('volunteer.case_catalog.all_14', 'أنفّذها بنفسي'),
                    'redistribute' => setting('volunteer.case_catalog.all_15', 'أفكّكها وأوزّعها'),
                    'closed' => setting('volunteer.case_catalog.all_16', 'أغلقها'),
                ],
                'settlement' => 'closed',
                'settlement_label' => setting('volunteer.case_catalog.all_17', 'تُغلَق'),
                // ⭐ قاعدة نهائيّة: يصعد بلا خصم تباطؤ — مهمّة يتيمة تدور على مالك جديد
                'no_slowdown' => true,
                'notice' => setting('volunteer.case_catalog.all_18', 'بلا خصم تباطؤ — المهمّة يتيمة تدور على مالك جديد.'),
            ],
            self::ARBITRATION => [
                'label' => setting('volunteer.case_catalog.all_19', 'التحكيم بين المالك والمساهم'),
                'icon' => '⚖️',
                'decisions' => [
                    'award' => setting('volunteer.case_catalog.all_20', 'منح VXP'),
                    'deduct' => setting('volunteer.case_catalog.all_21', 'خصم VXP'),
                    'split' => setting('volunteer.case_catalog.all_22', 'قيمة وسط'),
                    'shelved' => setting('volunteer.case_catalog.all_23', 'حفظ القضيّة'),
                ],
                'settlement' => 'split',
                'settlement_label' => setting('volunteer.case_catalog.all_24', 'تسوية 50% للطرفين'),
            ],
            self::CONTRIBUTOR_WITHDRAW => [
                'label' => setting('volunteer.case_catalog.all_25', 'طلب سحب مساهم'),
                'icon' => '↩️',
                'decisions' => ['approved' => setting('volunteer.case_catalog.all_26', 'موافقة'), 'rejected' => setting('volunteer.case_catalog.all_27', 'رفض')],
                'settlement' => 'approved',
                'settlement_label' => setting('volunteer.case_catalog.all_28', 'موافقة'),
            ],
            self::BROKEN_LINK => [
                'label' => setting('volunteer.case_catalog.all_29', 'بلاغ رابط معطّل'),
                'icon' => '🔗',
                'decisions' => ['link_works' => setting('volunteer.case_catalog.all_30', 'الرابط يعمل'), 'link_broken' => setting('volunteer.case_catalog.all_31', 'الرابط معطّل فعلًا')],
                'settlement' => 'link_works',
                'settlement_label' => setting('volunteer.case_catalog.all_32', 'الرابط يعمل بلا خصم'),
                // ⭐ وحدها: خصم التباطؤ فيها **قيمة معلَّقة قابلة للاسترجاع** (23-5)
                'suspended_slowdown' => true,
                'notice' => setting('volunteer.case_catalog.all_33', 'خصم التباطؤ هنا معلَّق: يُشال عن الكلّ لو الرابط شغّال، ويُعتمَد لو معطّل فعلًا.'),
            ],
            self::REPEATED_RETURN => [
                'label' => setting('volunteer.case_catalog.all_34', 'إرجاع متكرّر'),
                'icon' => '🔁',
                'decisions' => [
                    'returned' => setting('volunteer.case_catalog.all_35', 'إرجاع'),
                    'approved' => setting('volunteer.case_catalog.all_36', 'اعتماد'),
                    'finished' => setting('volunteer.case_catalog.all_37', 'إنهاء بقيمة Rep يدويّة'),
                ],
                'settlement' => 'approved_no_rep',
                'settlement_label' => setting('volunteer.case_catalog.all_38', 'اعتماد بلا Rep'),
            ],
            self::SUBTASK_BATCH => [
                'label' => setting('volunteer.case_catalog.all_39', 'مراجعة دفعة صب-تاسكات'),
                'icon' => '🗂️',
                'decisions' => [
                    'approved' => setting('volunteer.case_catalog.all_40', 'موافقة جماعيّة'),
                    'edited' => setting('volunteer.case_catalog.all_41', 'تعديل مباشر'),
                    'item_deleted' => setting('volunteer.case_catalog.all_42', 'حذف بند'),
                ],
                'settlement' => 'approved',
                'settlement_label' => setting('volunteer.case_catalog.all_43', 'اعتماد الدفعة كاملة'),
            ],
            // ⭐ الجسيمة (−1) لا تُطبَّق إلّا بموافقة مستوى أعلى، والصمت رفض (13.4-ن-هـ)
            self::BEHAVIOR_SEVERE => [
                'label' => setting('volunteer.case_catalog.all_44', 'اعتماد مخالفة سلوك جسيمة'),
                'icon' => '⚖️',
                'decisions' => ['approved' => setting('volunteer.case_catalog.all_45', 'اعتماد المخالفة'), 'rejected' => setting('volunteer.case_catalog.all_46', 'رفض المخالفة')],
                'settlement' => 'rejected',
                'settlement_label' => setting('volunteer.case_catalog.all_47', 'رفض — لا تُطبَّق على درجة الالتزام'),
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

    /**
     * هل أثر التباطؤ في هذا النوع **معلَّق قابل للاسترجاع** بدل أن يقع نهائيًّا؟
     * (بلاغ الرابط المعطّل وحده — 23-5: «شغّال ⟵ المعلَّق يتشال عن الكل».)
     */
    public static function suspendsSlowdown(string $type): bool
    {
        return (bool) (self::get($type)['suspended_slowdown'] ?? false);
    }
}
