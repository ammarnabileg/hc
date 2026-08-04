<?php

namespace App\Services\Account;

/**
 * قائمة حقول الخصوصيّة المقفولة (الدستور 10 · 13.4-م · 12.14-د).
 *
 * لماذا قائمة مقفولة؟ لأنّ كشف البيانات لا يُترَك للاجتهاد: ما ليس في القائمة
 * لا يُتحكَّم فيه أصلًا — لا معطَّلًا ولا مخفيًّا (2.15-أ-7).
 */
class PrivacyFields
{
    /**
     * ⭐ المحافظة حقل عامّ دائمًا ولا يجوز إخفاؤها (12.14-د) —
     * استثناءٌ صريح من خصوصيّة الحقول، ولذلك هي **غير موجودة في القائمة أصلًا**.
     */
    public const ALWAYS_PUBLIC = ['governorate'];

    /** مستويات الإظهار الثلاثة (13.4-م) */
    public const VISIBILITIES = ['all_users', 'all_volunteers', 'supervisors'];

    /** الحسّاس مخفيّ افتراضيًّا — قاعدة عليا في 13.4-م */
    public const SENSITIVE = ['phone', 'email', 'birthdate', 'emergency_contact'];

    /** @return array<string, string> مفتاح الحقل ⟵ اسمه العربيّ */
    public static function all(): array
    {
        return [
            'phone' => setting('account.privacy_fields.all_1', 'رقم الموبايل'),
            'email' => setting('account.privacy_fields.all_2', 'البريد الإلكترونيّ'),
            'birthdate' => setting('account.privacy_fields.all_3', 'تاريخ الميلاد'),
            'gender' => setting('account.privacy_fields.all_4', 'النوع'),
            'country' => setting('account.privacy_fields.all_5', 'الدولة'),
            'emergency_contact' => setting('account.privacy_fields.all_6', 'جهة الطوارئ'),
            'certificates' => setting('account.privacy_fields.all_7', 'الشهادات'),
            'achievements' => setting('account.privacy_fields.all_8', 'الإنجازات'),
            'experience' => setting('account.privacy_fields.all_9', 'خبراتي (السيرة الذاتيّة)'),
        ];
    }

    /** @return array<string, string> مفتاح مستوى الإظهار ⟵ اسمه العربيّ */
    public static function visibilityLabels(): array
    {
        return [
            'all_users' => setting('account.privacy_fields.visibility_labels_1', 'كلّ المستخدمين'),
            'all_volunteers' => setting('account.privacy_fields.visibility_labels_2', 'كلّ المتطوّعين'),
            'supervisors' => setting('account.privacy_fields.visibility_labels_3', 'مشرفيني فقط'),
        ];
    }

    /** هل يُسمح للمستخدم بالتحكّم في هذا الحقل؟ */
    public static function isControllable(string $field): bool
    {
        return ! in_array($field, self::ALWAYS_PUBLIC, true)
            && array_key_exists($field, self::all());
    }

    public static function isSensitive(string $field): bool
    {
        return in_array($field, self::SENSITIVE, true);
    }

    /**
     * الافتراضيّ: الحسّاس مقفول على المشرفين، وغيره مفتوح —
     * والأدمن يقدر يشدّ الحدّ الأدنى لكلّ المنصّة (24.5).
     */
    public static function defaultVisibility(string $field): string
    {
        $default = self::isSensitive($field)
            ? (string) setting('account.privacy.default_sensitive', 'supervisors')
            : (string) setting('account.privacy.default_public', 'all_users');

        return in_array($default, self::VISIBILITIES, true) ? $default : 'supervisors';
    }

    /** الحدّ الأدنى الذي يفرضه الأدمن — لا يُسمح بأوسع منه (24.5) */
    public static function adminFloor(): string
    {
        $floor = (string) setting('account.privacy.min_visibility', 'all_users');

        return in_array($floor, self::VISIBILITIES, true) ? $floor : 'all_users';
    }

    /**
     * ترتيب الاتّساع: كلّ المستخدمين ← كلّ المتطوّعين ← مشرفيني فقط.
     * ويُستخدَم لمنع اختيار أوسع ممّا سمح به الأدمن.
     */
    public static function rank(string $visibility): int
    {
        return (int) array_search($visibility, self::VISIBILITIES, true);
    }

    /** الخيارات المسموحة فعليًّا لهذا الحقل بعد تطبيق حدّ الأدمن */
    public static function allowedVisibilities(): array
    {
        $floor = self::rank(self::adminFloor());

        return array_values(array_filter(
            self::VISIBILITIES,
            fn (string $v) => self::rank($v) >= $floor,
        ));
    }
}
