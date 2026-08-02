<?php

namespace App\Services\Onboarding;

use App\Models\User;

/**
 * اسم صاحب الوثيقة كما يُطبَع عليها (2.5-ج + 12.5-ب + 9 + 9.1).
 *
 * القسم معنون **«بيانات الشهادات والإفادات»** — أيْ أنّ هذه الحقول ليست زينة
 * بروفايل: هي **ما يُطبَع على الشهادة والإفادة والسيرة**. ولذلك مصدرٌ واحد
 * يجيب على سؤالٍ واحد: «ما الاسم الذي يظهر في هذه اللغة، ومعه لقبه؟» — فلو
 * أجابت كلّ وثيقة بنفسها لخرجت شهادةٌ باسمٍ وإفادةٌ باسمٍ آخر لنفس الشخص.
 *
 * والارتداد مقصود: من لم يملأ الاسم بالإنجليزيّ بعد لا تخرج شهادته فارغةً —
 * تخرج باسمه المتاح، ثمّ يصحّحه من الإعدادات فتخرج التالية بالصحيح.
 */
class HolderIdentity
{
    /** الاسم في لغة الوثيقة، ومعه اللقب إن فعّله المالك */
    public static function displayName(User $user, string $language = 'ar'): string
    {
        $name = self::nameIn($user, $language);
        $title = trim((string) $user->title);

        if ($title === '' || ! setting('certificates.render.title_with_name', true)) {
            return $name;
        }

        return trim($title.' '.$name);
    }

    /** الاسم وحده بلا لقب — للسيرة الذاتيّة حيث اللقب حقلٌ مستقلّ */
    public static function nameIn(User $user, string $language = 'ar'): string
    {
        $ar = trim((string) $user->name_ar);
        $en = trim((string) $user->name_en);
        $fallback = trim((string) $user->name);

        $ordered = $language === 'en' ? [$en, $ar, $fallback] : [$ar, $en, $fallback];

        foreach ($ordered as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    /** العنوان الكامل: العنوان الفرعيّ ثمّ المحافظة ثمّ الدولة (2.5-ج) */
    public static function address(User $user): string
    {
        return collect([
            trim((string) $user->address_line),
            $user->governorate?->name_ar,
            $user->country?->name_ar,
        ])->filter(fn ($part) => filled($part))->implode('، ');
    }

    /**
     * الحقول التي تُطبَع على الوثائق — مصدر واحد للشهادة والسيرة والإفادة.
     *
     * @return array<string, string|null>
     */
    public static function documentFields(User $user, string $language = 'ar'): array
    {
        return [
            'holder_name' => self::displayName($user, $language),
            'holder_title' => trim((string) $user->title) ?: null,
            'holder_name_ar' => self::nameIn($user, 'ar'),
            'holder_name_en' => self::nameIn($user, 'en'),
            'holder_address' => self::address($user) ?: null,
        ];
    }
}
