<?php

namespace App\Services\Volunteer\Profile;

use App\Models\User;

/**
 * تابات طبقة التطوّع الخمس (13.4-م · 10.0-د) — تُحقَن في ستاك البروفايل الواحد
 * بعد تابات المتدرّب الأربعة، **ولا يراها ولا يعرف بوجودها غير المتطوّع**.
 *
 * الترتيب المنصوص عليه: التطوّع · التواصل · الهيكل التنظيميّ · الأداء · ملاحظات إداريّة.
 */
final class VolunteerProfileTabs
{
    public const OVERVIEW = 'volunteer';

    public const CONTACT = 'volunteer_contact';

    public const ORGANIZATION = 'volunteer_org';

    public const PERFORMANCE = 'volunteer_performance';

    public const NOTES = 'volunteer_notes';

    public const KEYS = [self::OVERVIEW, self::CONTACT, self::ORGANIZATION, self::PERFORMANCE, self::NOTES];

    public static function isVolunteerTab(?string $tab): bool
    {
        return $tab !== null && in_array($tab, self::KEYS, true);
    }

    /** التاب المطلوب إن كان تاب تطوّع، وإلّا `null` فتبقى تابات المتدرّب كما هي */
    public static function normalize(?string $tab): ?string
    {
        return self::isVolunteerTab($tab) ? $tab : null;
    }

    public static function label(string $key): string
    {
        return match ($key) {
            self::OVERVIEW => (string) setting('volunteer.profile.tab.overview', 'التطوّع'),
            self::CONTACT => (string) setting('volunteer.profile.tab.contact', 'التواصل'),
            self::ORGANIZATION => (string) setting('volunteer.profile.tab.organization', 'الهيكل التنظيميّ'),
            self::PERFORMANCE => (string) setting('volunteer.profile.tab.performance', 'الأداء'),
            self::NOTES => (string) setting('volunteer.profile.tab.notes', 'ملاحظات إداريّة'),
            default => $key,
        };
    }

    /**
     * التابات المسموحة لهذا الزائر — **وما لا يملكه يُخفى فعلًا** لا يُعطَّل (2.15-أ-7).
     *
     * @return array<int, array{key:string,label:string}>
     */
    public static function definitions(string $level, ?User $viewer): array
    {
        $keys = [self::OVERVIEW, self::CONTACT, self::ORGANIZATION];

        // الأداء التفصيليّ: لصاحبه وللمخوَّل — والزميل لا يراه أصلًا (13.4-م-2)
        if ($level !== ViewerLevel::PEER) {
            $keys[] = self::PERFORMANCE;
        }

        // الملاحظات الإداريّة: سرّيّة للمخوَّل بها وحده — ولا لصاحب البروفايل (13.4-م-5)
        if ($level !== ViewerLevel::OWNER && $viewer?->allows('admin_notes.view') === true) {
            $keys[] = self::NOTES;
        }

        return array_map(fn (string $key) => ['key' => $key, 'label' => self::label($key)], $keys);
    }

    public static function allowed(string $level, ?User $viewer): array
    {
        return array_column(self::definitions($level, $viewer), 'key');
    }
}
