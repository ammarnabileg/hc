<?php

namespace App\Services\Volunteer\Goals;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * نطاق الرؤية داخل العضويّة (الدستور 12.2.1 · 23 — 0.2).
 *
 * لماذا هنا؟ لأنّ كلّ شاشات هذا المجال تُبنى على السؤال نفسه: «أيّ كيانات أراها؟»
 * فنجيب عنه مرّة واحدة من **أوسع نطاق يملكه المستخدم** ثمّ نفلتر الاستعلامات به —
 * بدل تكرار الشرط في كلّ كنترولر فيختلف من شاشة لشاشة.
 */
class EntityScope
{
    /** كيانات العضويّات النشطة — تُغذّي مبدّل الكيان في الهيدر */
    public function memberships(User $user): Collection
    {
        return Membership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->with('entity', 'position')
            ->get();
    }

    /**
     * معرّفات الكيانات المرئيّة، أو **null** حين يكون النطاق واسعًا (ALL/TRACK)
     * فلا فلترة أصلًا — وnull تعني «بلا قيد» لا «بلا شيء».
     */
    public function visibleEntityIds(User $user, string $permissionKey = 'goals.view'): ?array
    {
        $widest = $user->widestScope($permissionKey);

        if (in_array($widest, ['ALL', 'TRACK'], true)) {
            return null;
        }

        $roots = $this->memberships($user)->pluck('entity_id')->filter()->unique()->values()->all();

        if ($roots === []) {
            return [];
        }

        return $this->withDescendants($roots);
    }

    /**
     * ⭐ مسارات المستخدم (الدستور 23 — 1.2): «مشرف المسار لا يرى ولا يربط إلّا
     * كيانات **مساره هو**». وnull هنا تعني «كلّ المسارات» (نطاق ALL — القمّة).
     *
     * ولماذا لا يكفي `visibleEntityIds`؟ لأنّها تُرجِع null لنطاق TRACK كذلك —
     * فتصير «بلا قيد» في شاشات البناء، فيربط مشرف الأقسام حزمةً بمحافظة. القيد
     * هنا **مسارٌ لا كيان**: مسارات العضويّات النشطة وحدها.
     *
     * @return list<int>|null
     */
    public function trackIds(User $user, string $permissionKey = 'work_packages.create'): ?array
    {
        if ($user->widestScope($permissionKey) === 'ALL') {
            return null;
        }

        $entityIds = $this->memberships($user)->pluck('entity_id')->filter()->unique()->all();

        if ($entityIds === []) {
            return [];
        }

        return Entity::query()
            ->whereIn('id', $entityIds)
            ->pluck('track_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * الكيانات التي **تُربَط بها حزم العمل** داخل مسارات المستخدم.
     *
     * وقاعدتان من القاموس تُفرَضان هنا لا في الواجهة:
     *  1) «القسم الفرعي … **ولا تُربَط به حزم عمل**» ⟵ الجذور وحدها (`parent_id = null`).
     *  2) الكيان المؤرشف (الملفّ المنتهي) خارج الاختيار ⟵ `status = active`.
     *
     * @return Collection<int,Entity>
     */
    public function linkableEntities(User $user, string $permissionKey = 'work_packages.create'): Collection
    {
        $trackIds = $this->trackIds($user, $permissionKey);

        return Entity::query()
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->when($trackIds !== null, fn ($q) => $q->whereIn('track_id', $trackIds ?: [0]))
            ->with('track')
            ->orderBy('track_id')
            ->orderBy('name_ar')
            ->get();
    }

    /** هل يجوز لهذا المستخدم أن يربط حزمةً بهذا الكيان؟ — يُفحَص على الخادم */
    public function canLinkEntity(User $user, Entity $entity, string $permissionKey = 'work_packages.create'): bool
    {
        if ($entity->parent_id !== null || $entity->status !== 'active') {
            return false;
        }

        $trackIds = $this->trackIds($user, $permissionKey);

        return $trackIds === null || in_array((int) $entity->track_id, $trackIds, true);
    }

    /** الكيان المختار حاليًّا: من الرابط إن كان مسموحًا، وإلّا العضويّة النشطة */
    public function currentEntityId(User $user, ?int $requested = null): ?int
    {
        $allowed = $this->memberships($user)->pluck('entity_id')->map(fn ($v) => (int) $v)->all();

        if ($requested && in_array($requested, $allowed, true)) {
            return $requested;
        }

        return $user->activeMembership()?->entity_id;
    }

    /** الكيان وأبناؤه — لأنّ نطاق ENTITY يشمل الأقسام الفرعيّة تحته */
    public function withDescendants(array $rootIds): array
    {
        $all = $rootIds;
        $frontier = $rootIds;
        $guard = 0;

        while ($frontier !== [] && $guard++ < 10) {
            $frontier = Entity::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->reject(fn ($id) => in_array($id, $all, true))
                ->values()
                ->all();

            $all = array_merge($all, $frontier);
        }

        return array_values(array_unique(array_map('intval', $all)));
    }
}
