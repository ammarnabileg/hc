<?php

namespace App\Services\Gamification;

use App\Models\Level;
use App\Models\User;

/**
 * ⭐ المستوى — **مصدر حقيقة واحد** (7 · 7.3).
 *
 * كان للمستوى مصدران: الداشبورد يحسبه طيرانًا من `levels` وXP الحاليّ، بينما
 * `users.level` عمودٌ لا يحدّثه أحد فتقرؤه الشارات قديمًا — فيرى المتدرّب
 * «المستوى 4» في شاشةٍ و«3» في منطق الشارات. والمستوى ليس رأيًا: هو دالّة في
 * XP وجدول المستويات، لا أكثر.
 *
 * فالقاعدة هنا: **الحقيقة = XP + جدول `levels`**، والعمود `users.level` صار
 * **نسخة مخبَّأة** تُزامَن من نقطة منح XP الموحّدة كي يبقى ما في قاعدة البيانات
 * مطابقًا لما يُعرَض، ولا يقرؤه أحدٌ كمصدرٍ مستقلّ.
 */
class LevelResolver
{
    /** رقم المستوى الموافق لهذا الـXP — من الجدول لا من عتبات محروقة (2.13) */
    public function levelFor(int $xp): int
    {
        return (int) (Level::query()
            ->where('min_xp', '<=', $xp)
            ->orderByDesc('min_xp')
            ->value('level') ?? 1);
    }

    /**
     * مزامنة العمود المخبَّأ مع الحقيقة.
     *
     * @return bool هل تغيّر المستوى فعلًا؟ (لحظة ترقٍّ تستحقّ احتفالًا — 2.14)
     */
    public function sync(User $user): bool
    {
        $level = $this->levelFor((int) $user->xp);

        if ((int) $user->level === $level) {
            return false;
        }

        $user->forceFill(['level' => $level])->saveQuietly();

        return true;
    }
}
