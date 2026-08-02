<?php

namespace App\Support\Access;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\Session;

/**
 * سياق العضويّة النشطة (12.2.1).
 * كلّ تقييم صلاحيّة يقع «داخل عضويّة» — فلا سلطة عابرة للكيانات.
 */
class MembershipContext
{
    private ?Membership $override = null;

    /** تثبيت عضويّة بعينها لهذا الطلب (يستخدمه مبدّل السياق في الهيدر) */
    public function set(?Membership $membership): void
    {
        $this->override = $membership;

        if ($membership && Session::isStarted()) {
            Session::put(config('access.membership_session_key'), $membership->id);
        }
    }

    /** العضويّة النشطة للمستخدم: المثبَّتة ⟵ المحفوظة في الجلسة ⟵ الأساسيّة ⟵ أوّل نشطة */
    public function for(User $user): ?Membership
    {
        if ($this->override && $this->override->user_id === $user->id) {
            return $this->override;
        }

        $sessionId = Session::isStarted()
            ? Session::get(config('access.membership_session_key'))
            : null;

        $memberships = $user->relationLoaded('memberships')
            ? $user->memberships
            : $user->memberships()->with('entity', 'position')->get();

        $active = $memberships->where('status', 'active');

        if ($sessionId) {
            $found = $active->firstWhere('id', (int) $sessionId);
            if ($found) {
                return $found;
            }
        }

        return $active->firstWhere('is_primary', true) ?? $active->first();
    }

    /** كلّ العضويّات النشطة — يقرأها مبدّل السياق */
    public function allFor(User $user)
    {
        return $user->memberships()->where('status', 'active')->with('entity', 'position')->get();
    }
}
