<?php

namespace App\Support\Scope;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\User;
use App\Support\Access\AccessEngine;
use App\Support\Access\MembershipContext;
use App\Support\Access\ScopeResolver;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * ⭐ المِعيار الواحد لتطبيق النطاق على **بيانات القوائم** (الدستور 12.2.1-ب).
 *
 * لماذا موجود؟ لأنّ الحارس على المسار يجيب عن سؤال «هل يستطيع مبدئيًّا؟» فقط —
 * `EnsurePermission` ينادي `allows()` بلا هدف، و`ScopeResolver::covers()` يعيد
 * `true` حين لا هدف. فمَن مُنِح `users.list@TEAM` كان يفتح قائمة المستخدمين
 * **كاملةً**، والدستور صريح: **«النطاق إلزاميّ مع كلّ صلاحيّة»**.
 *
 * فالنطاق هنا يُطبَّق على الاستعلام نفسه لا على الباب وحده:
 * SELF ⟵ نفسه · TEAM ⟵ داونلاينه المباشر · SUBTREE ⟵ كلّ مَن تحته
 * · ENTITY ⟵ كيانه وفروعه · TRACK ⟵ مساره · ALL ⟵ بلا قيد.
 *
 * ويقرأ من `ScopeResolver` القائم كما هو (`downlineUserIds` · `entityDescendantIds`)
 * فلا مصدرَ ثانيًا لتعريف الشجرة.
 */
class ScopeFilter
{
    public function __construct(
        private readonly ScopeResolver $scopes,
        private readonly MembershipContext $context,
        private readonly AccessEngine $access,
    ) {}

    /**
     * حصر استعلامٍ بأوسع نطاق يملكه المستخدم في هذه الصلاحيّة.
     *
     * @param  EloquentBuilder|QueryBuilder  $query  الاستعلام المراد حصره
     * @param  string  $permissionKey  مفتاح الصلاحيّة التي تُبنى عليها الشاشة
     * @param  string|null  $userColumn  عمود صاحب السجلّ (null = الجدول لا يحمل صاحبًا)
     * @param  string|null  $entityColumn  عمود الكيان إن وُجد — يُستعمل في ENTITY/TRACK
     */
    public function apply(
        EloquentBuilder|QueryBuilder $query,
        ?User $user,
        string $permissionKey,
        ?string $userColumn = 'user_id',
        ?string $entityColumn = null,
    ): EloquentBuilder|QueryBuilder {
        $scope = $this->scopeFor($user, $permissionKey);

        // بلا مستخدم أو بلا إسناد allow: لا صفوف — والحارس على المسار يبقى فوقه
        if ($user === null || $scope === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($scope === 'ALL') {
            return $query;
        }

        $membership = $this->context->for($user);

        // نطاقٌ ضيّق بلا عضويّة نشطة = لا سلطة يُقاس عليها ⟵ نفسه وحده
        if ($membership === null && in_array($scope, ['TEAM', 'SUBTREE', 'ENTITY', 'TRACK'], true)) {
            return $this->limitToUsers($query, $userColumn, [$user->id], $entityColumn, []);
        }

        return match ($scope) {
            'SELF' => $this->limitToUsers($query, $userColumn, [$user->id], $entityColumn, $this->entityIdsOf($membership, false)),
            'TEAM' => $this->limitToUsers($query, $userColumn, $this->teamUserIds($user, $membership), $entityColumn, $this->entityIdsOf($membership, false)),
            'SUBTREE' => $this->limitToUsers($query, $userColumn, $this->subtreeUserIds($user, $membership), $entityColumn, $this->entityIdsOf($membership, true)),
            'ENTITY' => $this->limitToEntities($query, $userColumn, $entityColumn, $this->entityIdsOf($membership, true), $user),
            'TRACK' => $this->limitToEntities($query, $userColumn, $entityColumn, $this->trackEntityIds($membership), $user),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * الوجه المختصر لقوائم **المستخدمين** — يحصر `users.id` مباشرةً.
     * (القائمة تعرض أشخاصًا، فالعمود هو المفتاح الأساسيّ لا `user_id`.)
     */
    public function applyToUsers(
        EloquentBuilder|QueryBuilder $query,
        ?User $user,
        string $permissionKey,
        string $idColumn = 'id',
    ): EloquentBuilder|QueryBuilder {
        return $this->apply($query, $user, $permissionKey, $idColumn, null);
    }

    /**
     * معرّفات المستخدمين المرئيّين، أو **null** حين لا قيد (ALL).
     * تفيد الشاشات التي تجمّع بلا استعلام Eloquent واحد.
     *
     * @return array<int, int>|null
     */
    public function visibleUserIds(?User $user, string $permissionKey): ?array
    {
        $scope = $this->scopeFor($user, $permissionKey);

        if ($user === null || $scope === null) {
            return [];
        }

        if ($scope === 'ALL') {
            return null;
        }

        $membership = $this->context->for($user);

        if ($membership === null) {
            return [$user->id];
        }

        return match ($scope) {
            'SELF' => [$user->id],
            'TEAM' => $this->teamUserIds($user, $membership),
            'SUBTREE' => $this->subtreeUserIds($user, $membership),
            'ENTITY' => $this->usersOfEntities($this->entityIdsOf($membership, true), $user),
            'TRACK' => $this->usersOfEntities($this->trackEntityIds($membership), $user),
            default => [],
        };
    }

    /**
     * معرّفات الكيانات المرئيّة، أو **null** حين لا قيد (ALL).
     *
     * @return array<int, int>|null
     */
    public function visibleEntityIds(?User $user, string $permissionKey): ?array
    {
        $scope = $this->scopeFor($user, $permissionKey);

        if ($user === null || $scope === null) {
            return [];
        }

        if ($scope === 'ALL') {
            return null;
        }

        $membership = $this->context->for($user);

        if ($membership === null) {
            return [];
        }

        return match ($scope) {
            'TRACK' => $this->trackEntityIds($membership),
            'ENTITY', 'SUBTREE' => $this->entityIdsOf($membership, true),
            default => $this->entityIdsOf($membership, false),
        };
    }

    /** أوسع نطاق يملكه في الصلاحيّة — ومالك المنصّة ALL دائمًا */
    public function scopeFor(?User $user, string $permissionKey): ?string
    {
        if ($user === null) {
            return null;
        }

        return $this->access->widestScope($user, $permissionKey);
    }

    // ------------------------------------------------------------------ داخليّ

    /** الداونلاين المباشر + نفسه (TEAM) */
    private function teamUserIds(User $user, Membership $membership): array
    {
        $direct = Membership::query()
            ->where('upline_id', $membership->id)
            ->where('status', 'active')
            ->pluck('user_id')
            ->all();

        return $this->ints(array_merge([$user->id], $direct));
    }

    /** كلّ مَن تحته لأيّ عمق + نفسه (SUBTREE) — من `ScopeResolver` بلا تكرار تعريف */
    private function subtreeUserIds(User $user, Membership $membership): array
    {
        return $this->ints(array_merge([$user->id], $this->scopes->downlineUserIds($membership)));
    }

    /** كيان العضويّة (ومعه فروعه عند الحاجة) */
    private function entityIdsOf(?Membership $membership, bool $withDescendants): array
    {
        if (! $membership || ! $membership->entity_id) {
            return [];
        }

        $ids = [(int) $membership->entity_id];

        if ($withDescendants) {
            $ids = array_merge($ids, $this->scopes->entityDescendantIds((int) $membership->entity_id));
        }

        return $this->ints($ids);
    }

    /** كلّ كيانات مسار العضويّة (TRACK) */
    private function trackEntityIds(?Membership $membership): array
    {
        $trackId = $membership?->entity?->track_id;

        if (! $trackId) {
            return $this->entityIdsOf($membership, true);
        }

        return $this->ints(Entity::query()->where('track_id', $trackId)->pluck('id')->all());
    }

    /** أصحاب العضويّات النشطة داخل كيانات بعينها + نفسه */
    private function usersOfEntities(array $entityIds, User $user): array
    {
        if ($entityIds === []) {
            return [$user->id];
        }

        $ids = Membership::query()
            ->whereIn('entity_id', $entityIds)
            ->where('status', 'active')
            ->pluck('user_id')
            ->all();

        return $this->ints(array_merge([$user->id], $ids));
    }

    /** حصر بقائمة أشخاص — ومع عمود كيان يُقبَل الصفّ بأيٍّ من الطرفين */
    private function limitToUsers(
        EloquentBuilder|QueryBuilder $query,
        ?string $userColumn,
        array $userIds,
        ?string $entityColumn,
        array $entityIds,
    ): EloquentBuilder|QueryBuilder {
        if ($userColumn === null && $entityColumn === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (BuilderContract $q) use ($userColumn, $userIds, $entityColumn, $entityIds) {
            if ($userColumn !== null) {
                $q->whereIn($userColumn, $userIds === [] ? [0] : $userIds);
            }

            // السجلّ بلا صاحب لكنّه داخل كياني (مثل الاجتماعات) يبقى مرئيًّا
            if ($entityColumn !== null && $entityIds !== []) {
                $userColumn === null
                    ? $q->whereIn($entityColumn, $entityIds)
                    : $q->orWhereIn($entityColumn, $entityIds);
            }
        });
    }

    /** حصر بكيانات — بعمود الكيان إن وُجد، وإلّا بأصحاب العضويّات داخلها */
    private function limitToEntities(
        EloquentBuilder|QueryBuilder $query,
        ?string $userColumn,
        ?string $entityColumn,
        array $entityIds,
        User $user,
    ): EloquentBuilder|QueryBuilder {
        if ($entityColumn !== null) {
            return $query->where(function (BuilderContract $q) use ($entityColumn, $entityIds, $userColumn, $user) {
                $q->whereIn($entityColumn, $entityIds === [] ? [0] : $entityIds);

                // صاحب السجلّ نفسه يرى سجلّه ولو كان خارج كيانه الحاليّ
                if ($userColumn !== null) {
                    $q->orWhere($userColumn, $user->id);
                }
            });
        }

        return $this->limitToUsers($query, $userColumn, $this->usersOfEntities($entityIds, $user), null, []);
    }

    /** @return array<int, int> */
    private function ints(array $values): array
    {
        return array_values(array_unique(array_map('intval', array_filter($values, fn ($v) => $v !== null))));
    }
}
