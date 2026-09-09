<?php

namespace App\Services\Volunteer\Org;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * ⭐ حدّ العضويّة الواحدة لكلّ مسار (23-0.2 — العضويّات المتعدّدة): «عضويّة
 * واحدة كحدّ أقصى في المسار الواحد (قسم/محافظة/ملف) — الحدّ إعداد» (القاعدة
 * الذهبيّة 2.13). كان المفتاح `volunteer.org.memberships_per_track` مزروعًا
 * بلا قارئٍ له في `app/` — فأيّ مسار تسكينٍ (تعيينٌ مباشر أو دعوة ملفّ) يفتح
 * للمستخدم عضويّة ثانية في نفس المسار (قسمين معًا، أو محافظتين، أو ملفّين)
 * بلا حارسٍ إطلاقًا.
 *
 * ⛔ والحارس **لا يمنع مسارًا آخر**: عضويّة في قسم + عضويّة في محافظة معًا
 * قانونيّتان تمامًا («النقل داخل المسار الواحد فقط» — الانضمام لمسارٍ آخر
 * تزويدٌ لا نقل) — الحدّ داخل نفس المسار وحده.
 */
class TrackCapacityGuard
{
    /**
     * @throws ValidationException
     */
    public function assertWithinCap(User $user, ?Entity $entity): void
    {
        if (! $entity || ! $entity->track_id) {
            return;
        }

        $cap = max(1, (int) setting('volunteer.org.memberships_per_track', 1));

        $current = Membership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereHas('entity', fn ($q) => $q->where('track_id', $entity->track_id))
            ->count();

        if ($current < $cap) {
            return;
        }

        throw ValidationException::withMessages([
            'entity_id' => strtr(
                setting('volunteer_org.track_capacity_guard.assert_within_cap_1', 'عنده بالفعل :p1 عضويّة فعّالة في هذا المسار — الحدّ :p2.'),
                [':p1' => (string) $current, ':p2' => (string) $cap],
            ),
        ]);
    }
}
