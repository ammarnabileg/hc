<?php

namespace App\Services\Volunteer\Org;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Position;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use App\Services\Admin\Volunteer\Integrations;
use App\Services\Volunteer\People\PositionRoleAssigner;
use App\Services\Volunteer\Tasks\TaskOwnershipTransfer;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ النقل بين الأقسام الرئيسيّة (23-0.2 — قاعدة النقل بين الأقسام، من الهيكل
 * المعتمد): «أيّ نقل من قسم رئيسي إلى قسم رئيسي آخر ⟵ المتطوّع يشغل بوزشن
 * «منسّق» في القسم الجديد أيًّا كانت درجته السابقة».
 *
 * وتنفيذًا حرفيًّا: «البوزشن القديم يُقفَل بتاريخه، ويُنشأ بوزشن Coordinator
 * بالقسم الجديد، **ويُعاد ربط الأبلاين** — وكلّه موثَّق في تايم-لاين البوزشنات
 * بالبروفايل». وموثَّق تلقائيًّا: `OrganizationPanel::positionTimeline()`
 * يقرأ صفوف `Membership` الخام مباشرةً، فلا عمل شاشةٍ إضافيّ هنا.
 *
 * ومعه ثلاث قواعد فرعيّة:
 *  - **«النقل بين الأقسام الفرعيّة داخل نفس القسم الرئيسي لا يُعَدّ نقلًا بين
 *    أقسام — يحتفظ بدرجته»**: `isSameMainDepartment()` تحسم الفرق.
 *  - **«تصفية قبل النقل»**: مهامّه المفتوحة داخل كيانه القديم تنتقل لأبلاينه
 *    المباشر بنفس ديدلاييناتها وبلا خصم — قبل إغلاق العضويّة القديمة، بنفس
 *    الأداة التي تخدم الأوفبوردنج (`TaskOwnershipTransfer`).
 *  - **«VXP تراكميّ يمشي معه»**: رصيدٌ شخصيّ بـ`user_id` لا بالعضويّة — فلا
 *    شيء يلمسه هذا الملفّ إطلاقًا، وهو ما يجعل القاعدة صحيحة **بالبناء**.
 *
 * ⛔ وما لا يفعله هذا الملفّ: **النقل بطلب الشخص بموافقة الدايركتورَين**، ولا
 * **التكليف (استثناء الكيان)** — كلاهما تفويضٌ يقرّر *مَن* يبدأ `transfer()`
 * ومَن يوافق عليه، لا آليّة التنفيذ نفسها. هذا الملفّ ينفّذ فقط.
 */
class TransferService
{
    public function __construct(
        private readonly PositionRoleAssigner $assigner,
        private readonly TaskOwnershipTransfer $tasks,
        private readonly PromotionLadder $ladder,
    ) {}

    /**
     * هل الكيانان تحت نفس القسم الرئيسي (الجذر)؟ — يحسم بين «تنقّل داخليّ لا
     * يمسّ الدرجة» و«نقلٌ حقيقيّ بين أقسام رئيسيّة».
     */
    public function isSameMainDepartment(Entity $from, Entity $to): bool
    {
        return $this->mainRootId($from) === $this->mainRootId($to);
    }

    private function mainRootId(Entity $entity): int
    {
        $current = $entity;
        $guard = 0;

        while ($current->parent_id && $guard++ < 32) {
            $next = $current->relationLoaded('parent') ? $current->parent : Entity::query()->find($current->parent_id);

            if (! $next) {
                break;
            }

            $current = $next;
        }

        return (int) $current->id;
    }

    /**
     * تنفيذ النقل: يرمي 422 لو النقل يعبر مسارًا آخر — «النقل داخل المسار
     * الواحد فقط» (الانضمام لمسارٍ آخر تزويدٌ لا نقل — 23-0.2).
     */
    public function transfer(Membership $from, Entity $to, User $actor, ?string $reason = null): Membership
    {
        $from->loadMissing('entity');
        $fromEntity = $from->entity;

        abort_if($fromEntity === null, 422, (string) setting(
            'volunteer_org.transfer_service.transfer_1',
            'العضويّة دي بلا كيان — مفيش نقل بلا كيان أصل.',
        ));

        abort_unless((int) $fromEntity->track_id === (int) $to->track_id, 422, (string) setting(
            'volunteer_org.transfer_service.transfer_2',
            'النقل داخل المسار الواحد فقط — الانضمام لمسارٍ آخر تزويدٌ لا نقل.',
        ));

        $sameMain = $this->isSameMainDepartment($fromEntity, $to);

        return DB::transaction(function () use ($from, $to, $actor, $reason, $sameMain, $fromEntity) {
            $positionId = $sameMain
                ? (int) $from->position_id
                : (int) Position::query()->where('key', 'coordinator')->value('id');

            $newMembership = Membership::create([
                'user_id' => $from->user_id,
                'entity_id' => $to->id,
                'position_id' => $positionId,
                'upline_id' => $this->resolveUpline($to)?->id,
                'is_primary' => $from->is_primary,
                'started_at' => now(),
                'status' => 'active',
            ]);

            $this->assigner->grant($newMembership, $actor->id);

            // ⭐ تصفية المهامّ المفتوحة قبل إغلاق العضويّة القديمة (23-0.2-H3)
            $this->tasks->toUpline($from);

            $from->forceFill(['status' => 'ended', 'ended_at' => now(), 'end_reason' => 'transfer'])->save();
            $this->assigner->revoke($from);

            AuditTrail::log($actor, 'membership.transfer', $newMembership, [
                'entity_id' => $fromEntity->id,
                'position_id' => $from->position_id,
            ], [
                'entity_id' => $to->id,
                'position_id' => $positionId,
                'same_main_department' => $sameMain,
                'reason' => $reason,
            ]);

            // ⭐ لا فترة شغور أصلًا على المقعد القديم — يُملأ فورًا من داونلاينه (القسم 0)
            $this->ladder->fillVacancy($from, $actor);

            Integrations::notify(
                $from->user,
                'volunteer',
                (string) setting('volunteer_org.transfer_service.notify_title', 'اتنقلت لقسم جديد'),
                $sameMain
                    ? (string) setting('volunteer_org.transfer_service.notify_body_internal', 'اتنقلت لقسمٍ فرعيّ جديد — بنفس درجتك.')
                    : (string) setting('volunteer_org.transfer_service.notify_body_coordinator', 'بدأت بوزشن كوردنيتور في قسمك الجديد.'),
                null,
                'volunteer',
            );

            return $newMembership;
        });
    }

    /**
     * أقرب صاحب قرارٍ فوق كوردنيتور داخل الكيان المستقبِل مباشرةً — أو فارغ
     * إن لم يوجد بعد (كيانٌ حديث بلا هيكل مكتمل)، فلا يُخترَع أبلاينٌ زائف.
     */
    private function resolveUpline(Entity $entity): ?Membership
    {
        return Membership::query()
            ->where('entity_id', $entity->id)
            ->where('status', 'active')
            ->join('positions', 'positions.id', '=', 'memberships.position_id')
            ->where('positions.rank', '>', 1)
            ->orderBy('positions.rank')
            ->orderBy('memberships.id')
            ->select('memberships.*')
            ->first();
    }
}
