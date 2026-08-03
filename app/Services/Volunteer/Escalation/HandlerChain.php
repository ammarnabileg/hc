<?php

namespace App\Services\Volunteer\Escalation;

use App\Models\Membership;
use App\Models\Position;
use App\Models\User;
use App\Services\Volunteer\Org\AbsenceService;
use App\Services\Volunteer\Retention\SuspensionService;

/**
 * سلسلة أصحاب القرار (الدستور 23 — القسم 5).
 *
 * القاعدة الواحدة: صاحب القرار الأوّل = **الأبلاين المباشر لمالك المهمّة** —
 * ولا يتغيّر مهما كثر المساهمون ولا اختلفت أقسامهم. وفوقه سلسلة الأبلاينز
 * حتى السقف (مشرف عام التطوّع) صاحب نافذة الـ48.
 *
 * ⭐ **وهي المصدر الواحد لسؤال «مَن صاحب هذه النافذة؟»** — لأنّ الجواب ليس
 * «الأبلاين» مجرّدًا: فوقه استثناءان منصوصان يجب أن يُقرآ معه دائمًا، وأيّ
 * مسارٍ يحسب الأبلاين بنفسه يسقط منهما:
 *  · **الغائب المفوَّض** (23-6): «كلّ نوافذ القرار الواردة إليه **تُوجَّه للبديل
 *    مباشرةً**».
 *  · **المعلَّق عند −10** (23-0.2-4): «تنتقل **مسؤوليّاته الإشرافيّة تلقائيًّا
 *    لأبلاينه المباشر** … فلا يبقى فريق بلا مراجِع طوال مدّة التحقيق».
 */
class HandlerChain
{
    /**
     * ⭐ حالات العضويّة التي **تظلّ في السلسلة**: النشِطة و**المعلَّقة**.
     *
     * ولماذا تبقى المعلَّقة؟ لأنّ إسقاطها **يقطع السلسلة على مَن تحتها**:
     * داونلاينه يشير إلى عضويّته بـ`upline_id`، فلو قرأناها «غير موجودة» لَعاد
     * صاحبُ نافذتهم `null` — أي **«بلغنا السقف»** — فتتسوّى قراراتهم آليًّا بلا
     * مراجِع. وهو عين ما ينفيه النصّ: «فلا يبقى فريق بلا مراجِع». فالعضويّة
     * المعلَّقة تبقى **حلقةً في السلسلة** ويقوم عنها **بديلُ التغطية**.
     */
    public const CHAIN_STATUSES = ['active', SuspensionService::MEMBERSHIP_STATUS];

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

    /**
     * أوّل صاحب قرار فوق هذا الشخص — **وإن كان غائبًا فالقرار للبديل المفوَّض**
     * (23-6): «كلّ نوافذ القرار الواردة إليه تُوجَّه للبديل مباشرةً وتُحتسَب عليه».
     */
    public function firstHandlerFor(?User $user, ?int $entityId = null): ?User
    {
        $membership = $this->membershipOf($user, $entityId);

        return $this->substituteIfAbsent($this->userOfUpline($membership), $entityId);
    }

    /**
     * البديل عن الغائب أو المعلَّق — بسلسلة محروسة: لو البديل نفسه غائب انتقلنا
     * لبديله، ولو انقطع البدلاء رجعنا لأبلاين الغائب فلا تبقى نافذة بلا صاحب.
     */
    public function substituteIfAbsent(?User $handler, ?int $entityId = null, int $guard = 0): ?User
    {
        if (! $handler || $guard >= 5) {
            return $handler;
        }

        /*
         | ⭐ **المعلَّق قبل الغائب** — والترتيب مقصود: الغياب عذرٌ مؤقّت يُفوَّض
         | فيه بمن يختاره صاحبُ الصلاحيّة، أمّا التعليق فرفعُ يدٍ كاملٌ عن
         | البوزشن لا يملك معه المعلَّق أن يفوّض ولا أن يُفوَّض إليه. فتغطية
         | البوزشن (23-0.2-4) تسبق قراءة أيّ تفويض غيابٍ كان قد فتحه لنفسه.
         */
        $cover = app(SuspensionService::class)->coverFor($handler, $entityId);

        if ($cover && (int) $cover->id !== (int) $handler->id) {
            return $this->substituteIfAbsent($cover, $entityId, $guard + 1);
        }

        $absences = app(AbsenceService::class);

        if (! $absences->isAbsent($handler, $entityId)) {
            return $handler;
        }

        $delegate = $absences->delegateFor($handler, $entityId)
            ?? $this->userOfUpline($this->membershipOf($handler, $entityId));

        if (! $delegate || (int) $delegate->id === (int) $handler->id) {
            return $handler;
        }

        return $this->substituteIfAbsent($delegate, $entityId, $guard + 1);
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

            if (! $membership || ! in_array($membership->status, self::CHAIN_STATUSES, true)) {
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

        // العضويّة المعلَّقة تبقى حلقةً في السلسلة — ويقوم عنها بديلُ التغطية
        if (! $upline || ! in_array($upline->status, self::CHAIN_STATUSES, true)) {
            return null;
        }

        return User::query()->find($upline->user_id);
    }
}
