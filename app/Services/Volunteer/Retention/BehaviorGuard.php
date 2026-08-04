<?php

namespace App\Services\Volunteer\Retention;

use App\Models\BehaviorTransaction;
use App\Models\BehaviorViolation;
use App\Models\User;
use App\Support\Access\AccessEngine;
use App\Support\Access\MembershipContext;
use App\Support\Access\ScopeResolver;
use RuntimeException;

/**
 * قفصا معاملة السلوك (13.4-ن-هـ).
 *
 * الدستور يضع على المنفذ الوحيد لتقدير بشريّ على Rep قفصين لا واحدًا:
 *  1) **قفص الكيان:** «سوبرفايزر فأعلى **لداونلاينه داخل كيانه فقط**» — فلا
 *     يخصم أحدٌ من أحدٍ لا يقع تحته في عضويّته النشطة. و**الأدمن ومشرف عام
 *     التطوّع بلا سقف** كما هو منصوص.
 *  2) **قفص الواقعة:** «**معاملة واحدة لكلّ واقعة**» — فلا تُخصَم الواقعة
 *     الواحدة مرّتين بيدين مختلفتين ولا بيدٍ واحدة مرّتين.
 *
 * ولماذا `AccessEngine` لا فحصٌ يدويّ؟ لأنّ النطاقات الستّة معرَّفة مرّة واحدة
 * في المحرّك (SELF · TEAM · SUBTREE · ENTITY · TRACK · ALL) وتُقاس من العضويّة
 * النشطة — فأيّ فحصٍ موازٍ هنا سيختلف عنها يومًا ما.
 */
class BehaviorGuard
{
    /** صلاحيّة تسجيل المعاملة — ونطاقها المُسنَد هو قفص الكيان نفسه */
    public const RECORD_PERMISSION = 'rep_manual.create';

    /** صلاحيّة الاعتماد — حاملها (الأدمن ومشرف عام التطوّع) بلا سقف */
    public const APPROVE_PERMISSION = 'rep_manual.approve';

    public function __construct(
        private readonly AccessEngine $access,
        private readonly ScopeResolver $scopes,
        private readonly MembershipContext $context,
    ) {}

    /** بلا سقف: الأدمن ومشرف عام التطوّع (13.4-ن-هـ) */
    public function isUncapped(User $granter): bool
    {
        return $this->access->allows($granter, self::APPROVE_PERMISSION);
    }

    /** هل يقع الهدف داخل نطاق المانح؟ */
    public function canRecordOn(User $granter, User $target): bool
    {
        if ($this->isUncapped($granter)) {
            return true;
        }

        return $this->access->allows($granter, self::RECORD_PERMISSION, $target);
    }

    /**
     * @throws RuntimeException حين يخرج الهدف عن نطاق المانح
     */
    public function assertScope(User $granter, User $target): void
    {
        if ($this->canRecordOn($granter, $target)) {
            return;
        }

        throw new RuntimeException(
            setting('volunteer_offboarding.behavior_guard.assert_scope_1', 'العضو ده مش في داونلاينك داخل عضويّتك النشطة — معاملة السلوك لداونلاينك في كيانك وحدهم.')
        );
    }

    /** داونلاين المانح داخل عضويّته النشطة — مادّة قائمة الاختيار في الشاشة */
    public function downlineUserIds(User $granter): array
    {
        $membership = $this->context->for($granter);

        return $membership ? $this->scopes->downlineUserIds($membership) : [];
    }

    /**
     * مرجع الواقعة: نصّ صريح متى وُجد، وإلّا **يوم الواقعة** — فالمنفذ الوحيد
     * لا يبقى بلا مرجعٍ أصلًا، ويظلّ «معاملة واحدة لكلّ واقعة» ساريًا.
     */
    public function incidentRef(?string $reference): string
    {
        $reference = trim((string) $reference);

        return $reference !== '' ? mb_substr($reference, 0, 64) : 'day:'.now()->toDateString();
    }

    /**
     * @throws RuntimeException حين تكون الواقعة مسجَّلة على العضو بنفس المخالفة
     */
    public function assertNotDuplicated(User $target, BehaviorViolation $violation, string $incidentRef): void
    {
        $exists = BehaviorTransaction::query()
            ->where('user_id', $target->id)
            ->where('behavior_violation_id', $violation->id)
            ->where('incident_ref', $incidentRef)
            ->exists();

        if ($exists) {
            throw new RuntimeException(
                setting('volunteer_offboarding.behavior_guard.assert_not_duplicated_1', 'الواقعة دي متسجّلة على العضو بنفس المخالفة قبل كده — معاملة واحدة لكلّ واقعة.')
            );
        }
    }
}
