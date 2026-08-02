<?php

namespace App\Support\Access;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * تقييم النطاقات الستّة: SELF · TEAM · SUBTREE · ENTITY · TRACK · ALL.
 * كلّها — عدا ALL و SELF — تُقاس من العضويّة النشطة، فلا سلطة عابرة للكيانات.
 */
class ScopeResolver
{
    /** هل يغطّي هذا النطاق الهدفَ المطلوب؟ */
    public function covers(string $scope, User $user, mixed $target, ?Membership $context): bool
    {
        /*
         | نطاقٌ خارج القائمة الستّة = صفٌّ تالف، ويُرفَض **قبل** اختصار «بلا هدف».
         | فبدون هذا السطر كان الفحص المبدئيّ (بلا هدف) يمرّ لأيّ نصٍّ في العمود.
         */
        if (! in_array($scope, config('access.scopes'), true)) {
            return false;
        }

        // بلا هدف: النطاق يكفي بذاته (فحص «هل يستطيع مبدئيًّا؟»)
        if ($target === null) {
            return true;
        }

        return match ($scope) {
            'ALL' => true,
            'SELF' => $this->isSelf($user, $target),
            'TEAM' => $this->inTeam($user, $target, $context),
            'SUBTREE' => $this->inSubtree($user, $target, $context),
            'ENTITY' => $this->inEntity($target, $context),
            'TRACK' => $this->inTrack($target, $context),
            default => false,
        };
    }

    private function isSelf(User $user, mixed $target): bool
    {
        if ($target instanceof User) {
            return $target->id === $user->id;
        }

        if ($target instanceof Model) {
            foreach (['user_id', 'owner_id', 'contributor_id', 'author_id'] as $key) {
                if (array_key_exists($key, $target->getAttributes()) && $target->{$key} === $user->id) {
                    return true;
                }
            }
        }

        return false;
    }

    private function inTeam(User $user, mixed $target, ?Membership $context): bool
    {
        if (! $context) {
            return false;
        }

        if ($this->isSelf($user, $target)) {
            return true;
        }

        $targetUserId = $this->targetUserId($target);

        if ($targetUserId === null) {
            return false;
        }

        // الداونلاين المباشر داخل العضويّة النشطة فقط
        return Membership::query()
            ->where('upline_id', $context->id)
            ->where('status', 'active')
            ->where('user_id', $targetUserId)
            ->exists();
    }

    private function inSubtree(User $user, mixed $target, ?Membership $context): bool
    {
        if (! $context) {
            return false;
        }

        if ($this->isSelf($user, $target)) {
            return true;
        }

        $targetUserId = $this->targetUserId($target);

        if ($targetUserId === null) {
            return false;
        }

        return in_array($targetUserId, $this->downlineUserIds($context), true);
    }

    private function inEntity(mixed $target, ?Membership $context): bool
    {
        if (! $context) {
            return false;
        }

        $entityId = $this->targetEntityId($target);

        if ($entityId === null) {
            return false;
        }

        // الكيان نفسه أو أيّ كيان تحته في الشجرة
        return $entityId === $context->entity_id
            || in_array($entityId, $this->entityDescendantIds($context->entity_id), true);
    }

    private function inTrack(mixed $target, ?Membership $context): bool
    {
        if (! $context || ! $context->entity) {
            return false;
        }

        $entityId = $this->targetEntityId($target);

        if ($entityId === null) {
            return false;
        }

        $entity = Entity::find($entityId);

        return $entity && $entity->track_id === $context->entity->track_id;
    }

    /** معرّف صاحب الهدف */
    private function targetUserId(mixed $target): ?int
    {
        if ($target instanceof User) {
            return $target->id;
        }

        if ($target instanceof Membership) {
            return $target->user_id;
        }

        if ($target instanceof Model) {
            foreach (['user_id', 'owner_id', 'contributor_id', 'author_id'] as $key) {
                if (array_key_exists($key, $target->getAttributes())) {
                    return $target->{$key};
                }
            }
        }

        return null;
    }

    /** معرّف كيان الهدف */
    private function targetEntityId(mixed $target): ?int
    {
        if ($target instanceof Entity) {
            return $target->id;
        }

        if ($target instanceof Membership) {
            return $target->entity_id;
        }

        if ($target instanceof Model && array_key_exists('entity_id', $target->getAttributes())) {
            return $target->entity_id;
        }

        // الهدف مستخدم: نأخذ كياناته النشطة
        if ($target instanceof User) {
            return $target->memberships()->where('status', 'active')->value('entity_id');
        }

        return null;
    }

    /** كلّ مَن تحت هذه العضويّة في الشجرة (بعمق غير محدود) */
    public function downlineUserIds(Membership $context): array
    {
        $ids = [];
        $frontier = [$context->id];

        while ($frontier !== []) {
            $rows = Membership::query()
                ->whereIn('upline_id', $frontier)
                ->where('status', 'active')
                ->get(['id', 'user_id']);

            if ($rows->isEmpty()) {
                break;
            }

            $frontier = $rows->pluck('id')->all();
            $ids = array_merge($ids, $rows->pluck('user_id')->all());
        }

        return array_values(array_unique($ids));
    }

    /** كلّ الكيانات تحت كيانٍ في الشجرة */
    public function entityDescendantIds(?int $entityId): array
    {
        if (! $entityId) {
            return [];
        }

        $ids = [];
        $frontier = [$entityId];

        while ($frontier !== []) {
            $rows = Entity::query()->whereIn('parent_id', $frontier)->pluck('id')->all();

            if ($rows === []) {
                break;
            }

            $frontier = $rows;
            $ids = array_merge($ids, $rows);
        }

        return array_values(array_unique($ids));
    }
}
