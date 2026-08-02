<?php

namespace App\Services\Volunteer\Escalation;

use App\Models\Membership;
use App\Models\Position;
use App\Models\User;

/**
 * سلسلة أصحاب القرار (الدستور 23 — القسم 5).
 *
 * القاعدة الواحدة: صاحب القرار الأوّل = **الأبلاين المباشر لمالك المهمّة** —
 * ولا يتغيّر مهما كثر المساهمون ولا اختلفت أقسامهم. وفوقه سلسلة الأبلاينز
 * حتى السقف (مشرف عام التطوّع) صاحب نافذة الـ48.
 */
class HandlerChain
{
    /** بوزشن السقف — إعداد لا مفتاح محروق (2.13) */
    public function topPositionKey(): string
    {
        return (string) setting('workflow.escalation.top_position', 'volunteer_gm');
    }

    /** العضويّة النشطة التي تُقاس منها السلسلة (الأقرب للكيان المعنيّ) */
    public function membershipOf(?User $user, ?int $entityId = null): ?Membership
    {
        if (! $user) {
            return null;
        }

        return Membership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->when($entityId, fn ($q) => $q->orderByRaw('CASE WHEN entity_id = ? THEN 0 ELSE 1 END', [$entityId]))
            ->orderByDesc('is_primary')
            ->first();
    }

    /** أوّل صاحب قرار فوق هذا الشخص */
    public function firstHandlerFor(?User $user, ?int $entityId = null): ?User
    {
        $membership = $this->membershipOf($user, $entityId);

        return $this->userOfUpline($membership);
    }

    /** الأبلاين التالي بعد صاحب القرار الحاليّ */
    public function nextHandlerAfter(?User $handler, ?int $entityId = null): ?User
    {
        return $this->firstHandlerFor($handler, $entityId);
    }

    /**
     * هل بلغنا السقف؟ إمّا بوزشن مشرف عام التطوّع أو انقطاع السلسلة —
     * وعندها تصير النافذة 48 ساعة والتسوية الآليّة هي المآل.
     */
    public function isTop(?User $handler, ?int $entityId = null): bool
    {
        if (! $handler) {
            return true;
        }

        $membership = $this->membershipOf($handler, $entityId);

        if (! $membership) {
            return true;
        }

        $positionKey = Position::query()->whereKey($membership->position_id)->value('key');

        if ($positionKey === $this->topPositionKey()) {
            return true;
        }

        return $this->userOfUpline($membership) === null;
    }

    /** سلسلة القرار كاملة صعودًا — تُعرَض كسلّم تصعيد مرئيّ */
    public function chainFor(?User $user, ?int $entityId = null): array
    {
        $chain = [];
        $membership = $this->membershipOf($user, $entityId);
        $guard = 0;

        while ($membership && $membership->upline_id && $guard++ < 20) {
            $membership = Membership::query()->find($membership->upline_id);

            if (! $membership || $membership->status !== 'active') {
                break;
            }

            $chain[] = User::query()->find($membership->user_id);
        }

        return array_values(array_filter($chain));
    }

    /**
     * علاقة أبلاين/داونلاين في أيّ كيان — أساس كشف الرقم في التحكيم
     * (حقّ نظاميّ) وأساس تنازع المصالح الذي يتخطّى مستوى المحكّم.
     */
    public function relatedByLine(?User $a, ?User $b): bool
    {
        if (! $a || ! $b) {
            return false;
        }

        if ($a->id === $b->id) {
            return true;
        }

        return $this->isAncestor($a, $b) || $this->isAncestor($b, $a);
    }

    /** هل $ancestor يقع فوق $user في أيّ سلسلة عضويّة؟ */
    public function isAncestor(User $ancestor, User $user): bool
    {
        $memberships = Membership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        foreach ($memberships as $membership) {
            $cursor = $membership;
            $guard = 0;

            while ($cursor && $cursor->upline_id && $guard++ < 20) {
                $cursor = Membership::query()->find($cursor->upline_id);

                if ($cursor && (int) $cursor->user_id === (int) $ancestor->id) {
                    return true;
                }
            }
        }

        return false;
    }

    private function userOfUpline(?Membership $membership): ?User
    {
        if (! $membership || ! $membership->upline_id) {
            return null;
        }

        $upline = Membership::query()->find($membership->upline_id);

        if (! $upline || $upline->status !== 'active') {
            return null;
        }

        return User::query()->find($upline->user_id);
    }
}
