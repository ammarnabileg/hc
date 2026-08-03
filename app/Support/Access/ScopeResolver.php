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
    /**
     * هل يغطّي هذا النطاق الهدفَ المطلوب؟
     *
     * ⭐ **الهدف يُقاس على البُعد الذي يحمله** (12.2.1-ب): النطاقات الستّة معرَّفة
     * على **الأشخاص والكيانات** — «نفسه · داونلاينه · مَن تحته · الكيان · المسار».
     * فالسجلّ الذي يحمل **صاحبًا** يُقاس على سلسلة الأشخاص، والذي يحمل **كيانًا**
     * يُقاس على شجرة الكيانات، والذي يحمل أحدهما ولا يحمل الآخر يُقاس على ما يحمل.
     *
     * أمّا السجلّ الذي **لا يحمل صاحبًا ولا كيانًا** (تدريب · درس · فعاليّة · امتحان)
     * فلا تملك طبقةُ الوصول ما تقيس النطاق عليه، ويحرسه **المجال صاحب الشاشة** —
     * وهي نفس قاعدة `guard => 'domain'` المعتمَدة في `config/access.php` للشروط،
     * لا فتحة استثناء جديدة. والحصر على بيانات القوائم يبقى بـ`ScopeFilter`.
     */
    public function covers(string $scope, User $user, mixed $target, ?Membership $context): bool
    {
        /*
         | نطاقٌ خارج القائمة الستّة = صفٌّ تالف، ويُرفَض **قبل** اختصار «بلا هدف».
         | فبدون هذا السطر كان الفحص المبدئيّ (بلا هدف) يمرّ لأيّ نصٍّ في العمود.
         */
        if (! in_array($scope, config('access.scopes'), true)) {
            return false;
        }

        if ($scope === 'ALL') {
            return true;
        }

        // بلا هدف: النطاق يكفي بذاته (فحص «هل يستطيع مبدئيًّا؟» — للقوائم والإخفاء)
        if ($target === null) {
            return true;
        }

        $ownerId = $this->targetUserId($target);
        $entityId = $this->targetEntityId($target);

        // هدفٌ بلا صاحبٍ ولا كيان: لا مقياس للنطاق عليه ⟵ يحرسه المجال (انظر الوصف)
        if ($ownerId === null && $entityId === null) {
            return true;
        }

        return match ($scope) {
            'SELF' => $ownerId !== null
                ? $this->isSelf($user, $target)
                : $this->inOwnEntity($entityId, $context),
            'TEAM' => $ownerId !== null
                ? $this->inTeam($user, $target, $context)
                : $this->inEntity($target, $context),
            'SUBTREE' => $ownerId !== null
                ? $this->inSubtree($user, $target, $context)
                : $this->inEntity($target, $context),
            'ENTITY' => $this->inEntity($target, $context),
            'TRACK' => $this->inTrack($target, $context),
            default => false,
        };
    }

    /** كيان الهدف هو كيان العضويّة النشطة نفسه — أضيق ما يُقاس به كيانٌ بلا صاحب */
    private function inOwnEntity(?int $entityId, ?Membership $context): bool
    {
        return $context !== null && $entityId !== null && $entityId === $context->entity_id;
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
            return $this->entityIdOfUser($target->id);
        }

        /*
         | سجلٌّ يحمل **صاحبًا** ولا يحمل عمود كيان: كيانه هو كيان صاحبه.
         | وبدون هذا كان النطاق ENTITY/TRACK يسقط fail-closed على كلّ سجلٍّ شخصيّ
         | (شهادة · تسجيل · شكوى) فيُردّ الدايركتور عن سجلّات كيانه هو.
         */
        $ownerId = $this->targetUserId($target);

        return $ownerId === null ? null : $this->entityIdOfUser($ownerId);
    }

    private function entityIdOfUser(int $userId): ?int
    {
        return Membership::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->value('entity_id');
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
