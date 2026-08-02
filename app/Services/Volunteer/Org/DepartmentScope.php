<?php

namespace App\Services\Volunteer\Org;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * نطاق «قسمي»: الكيان الجذر وكلّ ما تحته.
 *
 * لماذا الجذر لا العضويّة؟ لأنّ الدستور يوجب رؤية **القسم كاملًا حتى لو كنتُ في فرعيّ**
 * (24.4 · 13.4-م) — فالعضو يعرف زملاءه في القسم كلّه لا في فرعيّه وحده.
 * والعنصر الشرفيّ «أخوكم» يُستبعَد من كلّ صفوف هذا النطاق (13.4-ص-ج).
 */
final class DepartmentScope
{
    /** حالات العضويّة التي يظلّ صاحبها ضمن القسم (المنتهية تخرج) */
    public const LIVE_STATUSES = ['active', 'absent', 'suspended'];

    /**
     * جذور الأقسام التي ينتمي إليها المستخدم — مادّة «مبدّل الكيان».
     *
     * @return Collection<int, Entity>
     */
    public function rootsFor(User $user): Collection
    {
        return $user->memberships()
            ->whereIn('status', self::LIVE_STATUSES)
            ->with('entity')
            ->get()
            ->map(fn (Membership $m) => $m->entity ? $this->rootOf($m->entity) : null)
            ->filter()
            ->unique('id')
            ->values()
            ->keyBy('id');
    }

    /** جذر القسم المطلوب — ولا يُقبَل إلّا من جذور المستخدم نفسه (لا سلطة عابرة للكيانات) */
    public function rootFor(User $user, ?int $requestedEntityId = null): ?Entity
    {
        $roots = $this->rootsFor($user);

        if ($requestedEntityId && $roots->has($requestedEntityId)) {
            return $roots->get($requestedEntityId);
        }

        $primary = $user->memberships()
            ->whereIn('status', self::LIVE_STATUSES)
            ->orderByDesc('is_primary')
            ->with('entity')
            ->first();

        if ($primary?->entity) {
            return $this->rootOf($primary->entity);
        }

        return $roots->first();
    }

    /** أعلى أبٍ في شجرة الكيانات */
    public function rootOf(Entity $entity): Entity
    {
        $guard = 0;

        while ($entity->parent_id && $guard++ < 32) {
            $parent = Entity::find($entity->parent_id);

            if (! $parent) {
                break;
            }

            $entity = $parent;
        }

        return $entity;
    }

    /**
     * الجذر وكلّ الكيانات تحته.
     *
     * @return list<int>
     */
    public function entityIds(Entity $root): array
    {
        $ids = [$root->id];
        $frontier = [$root->id];
        $guard = 0;

        while ($frontier !== [] && $guard++ < 32) {
            $frontier = Entity::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return array_values(array_unique($ids));
    }

    /**
     * الكيانات الفرعيّة مباشرةً تحت الجذر — مادّة فلتر «الفرعيّ» وبارات الإشغال.
     *
     * @return Collection<int, Entity>
     */
    public function subEntities(Entity $root): Collection
    {
        return Entity::query()
            ->where('parent_id', $root->id)
            ->orderBy('name_ar')
            ->get()
            ->keyBy('id');
    }

    /**
     * كلّ عضويّات القسم — **بلا العنصر الشرفيّ** فلا يدخل صفًّا ولا عدّادًا (13.4-ص-ج).
     *
     * @param  list<int>  $entityIds
     * @return Collection<int, Membership>
     */
    public function memberships(array $entityIds): Collection
    {
        return Membership::query()
            ->whereIn('entity_id', $entityIds)
            ->whereIn('status', self::LIVE_STATUSES)
            ->whereHas('position', fn ($q) => $q->where('is_honorary', false))
            ->with(['user', 'position', 'entity', 'upline.user'])
            ->get()
            ->keyBy('id');
    }

    /**
     * العنصر الشرفيّ «أخوكم» (13.4-ص): يظهر بوصفه من الإعدادات، وبلا أيّ مؤشّر تشغيليّ،
     * ولا يُحتسَب في عدّاد ولا نطاق إشراف ولا صحّة قسم.
     *
     * ⭐ **مصدرٌ واحد** لكلّ أماكن ظهوره (`HonoraryElement`) — وإلّا اختلف
     * الكانفاس عن صفحة الأعضاء عن الصفحة التعريفيّة وصار الإعداد بلا أثر (2.13).
     * وهنا يُقيَّد بمكانه «الكانفاس» من «أماكن الظهور» (13.4-ص-ب).
     *
     * @return array{user: User, label: string, frame: string}|null
     */
    public function honorary(string $place = 'canvas'): ?array
    {
        $element = app(HonoraryElement::class);

        if (! $element->showsOn($place)) {
            return null;
        }

        $resolved = $element->resolve();

        return $resolved ? [
            'user' => $resolved['user'],
            'label' => $resolved['label'],
            'frame' => $resolved['frame'],
        ] : null;
    }

    /**
     * سلسلة الأبلاين لأعلى — يراها المخوَّل، وعليها يتوقّف ظهور بيانات التواصل بلا موافقة (13.4-م).
     *
     * @return list<int> معرّفات مستخدمي الأبلاين
     */
    public function uplineUserIds(Membership $membership, Collection $pool): array
    {
        $ids = [];
        $current = $membership;
        $guard = 0;

        while ($current?->upline_id && $guard++ < 32) {
            $current = $pool->get($current->upline_id) ?? Membership::with('user')->find($current->upline_id);

            if (! $current) {
                break;
            }

            $ids[] = (int) $current->user_id;
        }

        return array_values(array_unique($ids));
    }
}
