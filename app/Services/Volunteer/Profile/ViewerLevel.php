<?php

namespace App\Services\Volunteer\Profile;

use App\Models\User;
use App\Services\Account\ProfileVisibility;

/**
 * مستويات المشاهدة الأربعة على طبقة التطوّع (13.4-م · 10.0-ج):
 * صاحب البروفايل · زميل متطوّع · أبلاين مخوَّل · أدمن.
 *
 * القاعدة العليا: **الحسّاس مخفيّ افتراضيًّا**، ولا يظهر إلّا بمستوى مشاهدة
 * يسمح به **مع** إعداد خصوصيّة صاحبه.
 *
 * ولماذا `memberships.view` لا اسمًا آخر؟ لأنّ «الأبلاين **المخوَّل**» في الدستور
 * ليس كلّ مَن يعلوك تنظيميًّا، بل مَن يملك صلاحيّة الاطّلاع على عضويّتك داخل نطاقه —
 * فالعلاقة التنظيميّة وحدها لا تكفي (13.4-م).
 */
final class ViewerLevel
{
    public const OWNER = 'owner';

    public const PEER = 'peer';

    public const UPLINE = 'upline';

    public const ADMIN = 'admin';

    public function __construct(private readonly ProfileVisibility $base) {}

    public function for(?User $viewer, User $owner): string
    {
        // الزائر بلا حساب أدنى مستوى — والظاهر العامّ فقط
        if (! $viewer) {
            return self::PEER;
        }

        if ($viewer->id === $owner->id) {
            return self::OWNER;
        }

        if ($viewer->isPlatformOwner() || $viewer->allows('users.view', $owner)) {
            return self::ADMIN;
        }

        if ($this->base->isUplineOf($viewer, $owner) && $viewer->allows('memberships.view', $owner)) {
            return self::UPLINE;
        }

        return self::PEER;
    }

    /** الأبلاين المخوَّل والأدمن — ولهما وحدهما الأداء التفصيليّ وبيانات التواصل بلا موافقة */
    public function isPrivileged(string $level): bool
    {
        return in_array($level, [self::UPLINE, self::ADMIN], true);
    }

    /** صاحبه أو مخوَّل — لِما يراه صاحبه أيضًا (المهامّ · الشهادات · مساره للبوزشن الجاي) */
    public function isOwnerOrPrivileged(string $level): bool
    {
        return $level === self::OWNER || $this->isPrivileged($level);
    }

    public function label(string $level): string
    {
        return match ($level) {
            self::OWNER => (string) setting('volunteer.profile.level.owner', 'دي صفحتك — بتشوف كلّ حاجة عدا الملاحظات الإداريّة'),
            self::ADMIN => (string) setting('volunteer.profile.level.admin', 'مشاهدة إداريّة'),
            self::UPLINE => (string) setting('volunteer.profile.level.upline', 'مشاهدة مشرف'),
            default => (string) setting('volunteer.profile.level.peer', 'المشاهدة العامّة'),
        };
    }
}
