<?php

namespace App\Support\Access;

use App\Models\Membership;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * محرّك الصلاحيّات (الدستور 12.2.1).
 *
 * قواعد حاكمة:
 *  1) الصلاحيّة تُسمّى «المورد.الفعل».
 *  2) النطاق إلزاميّ ويُقيَّم داخل سياق العضويّة النشطة.
 *  3) Deny > Allow دائمًا.
 *  4) منع تصعيد الامتياز: لا يمنح أحدٌ ما لا يملك ولا نطاقًا أوسع.
 *  5) عزل الحسّاس: الماليّ ونظائره لمالك المنصّة وحده.
 *  6) أسبقيّة طبقة المنصّة: الأدمنز أعلى من طبقة التطوّع.
 */
class AccessEngine
{
    /** كاش لكلّ طلب: user_id => Collection<Grant> */
    private array $cache = [];

    public function __construct(
        private readonly ScopeResolver $scopes,
        private readonly ConditionEvaluator $conditions,
        private readonly MembershipContext $context,
    ) {}

    /**
     * هل يملك المستخدم هذه الصلاحيّة على هذا الهدف؟
     *
     * @param  string  $permissionKey  مثل: volunteers.approve
     * @param  mixed  $target  السجلّ المستهدَف (اختياريّ — بدونه يُفحَص المبدأ فقط)
     */
    public function allows(User $user, string $permissionKey, mixed $target = null, ?Membership $context = null): bool
    {
        $membership = $context ?? $this->context->for($user);
        $grants = $this->grantsFor($user)->where('permissionKey', $permissionKey);

        // 3) Deny > Allow — يُفحَص المنع أوّلًا وقبل أيّ شيء
        foreach ($grants->where('effect', 'deny') as $deny) {
            if ($this->matches($deny, $user, $target, $membership)) {
                return false;
            }
        }

        // مالك المنصّة يعلو الجميع — بعد المنع الصريح
        if ($this->isPlatformOwner($user)) {
            return true;
        }

        // 5) عزل الحسّاس: ما هو owner-only لا يُمنَح لغير مالك المنصّة مهما كان الدور
        if ($this->isOwnerOnly($permissionKey)) {
            return false;
        }

        foreach ($grants->where('effect', 'allow') as $allow) {
            if ($this->matches($allow, $user, $target, $membership)) {
                return true;
            }
        }

        return false;
    }

    public function denies(User $user, string $permissionKey, mixed $target = null, ?Membership $context = null): bool
    {
        return ! $this->allows($user, $permissionKey, $target, $context);
    }

    /**
     * 4) منع تصعيد الامتياز:
     * لا يُسنِد أحدٌ صلاحيّةً لا يملكها، ولا بنطاقٍ أوسع من نطاقه فيها.
     */
    public function canGrant(User $granter, string $permissionKey, string $scope, ?Membership $context = null): bool
    {
        if ($this->isPlatformOwner($granter)) {
            return true;
        }

        if ($this->isOwnerOnly($permissionKey)) {
            return false;
        }

        $membership = $context ?? $this->context->for($granter);
        $target = array_search($scope, config('access.scopes'), true);

        if ($target === false) {
            return false;
        }

        $grants = $this->grantsFor($granter)->where('permissionKey', $permissionKey);

        // منعٌ صريح على الصلاحيّة يبطل المنح كلّه
        foreach ($grants->where('effect', 'deny') as $deny) {
            if ($deny->scopeRank() >= $target) {
                return false;
            }
        }

        foreach ($grants->where('effect', 'allow') as $allow) {
            if ($allow->scopeRank() >= $target && $this->membershipApplies($allow, $membership)) {
                return true;
            }
        }

        return false;
    }

    /** أوسع نطاق يملكه المستخدم في صلاحيّة — يفيد في بناء الاستعلامات */
    public function widestScope(User $user, string $permissionKey): ?string
    {
        if ($this->isPlatformOwner($user)) {
            return 'ALL';
        }

        $allows = $this->grantsFor($user)
            ->where('permissionKey', $permissionKey)
            ->where('effect', 'allow');

        if ($allows->isEmpty()) {
            return null;
        }

        return $allows->sortByDesc(fn (Grant $g) => $g->scopeRank())->first()->scope;
    }

    public function isPlatformOwner(User $user): bool
    {
        return $this->roleKeys($user)->contains(config('access.owner_role'));
    }

    /** مسح الكاش — يُستدعى بعد أيّ تغيير في الأدوار أو الإسنادات */
    public function forget(?User $user = null): void
    {
        if ($user) {
            unset($this->cache[$user->id]);

            return;
        }

        $this->cache = [];
    }

    // ------------------------------------------------------------------ داخليّ

    private function matches(Grant $grant, User $user, mixed $target, ?Membership $membership): bool
    {
        if (! $this->membershipApplies($grant, $membership)) {
            return false;
        }

        if (! $this->scopes->covers($grant->scope, $user, $target, $membership)) {
            return false;
        }

        return $this->conditions->passes($grant->conditions, $user, $target, $membership);
    }

    /**
     * الإسناد المرتبط بعضويّة لا يسري إلّا داخلها (قفص العضويّة).
     * والإسناد بلا عضويّة (أدوار المنصّة) يسري في كلّ سياق.
     */
    private function membershipApplies(Grant $grant, ?Membership $membership): bool
    {
        if ($grant->membershipId === null) {
            return true;
        }

        return $membership !== null && $grant->membershipId === $membership->id;
    }

    /** @return Collection<int, Grant> */
    private function grantsFor(User $user): Collection
    {
        if (isset($this->cache[$user->id])) {
            return $this->cache[$user->id];
        }

        $grants = collect();

        // (أ) من الأدوار
        $roleRows = \DB::table('role_user')
            ->join('permission_role', 'permission_role.role_id', '=', 'role_user.role_id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->get([
                'permissions.key as permission_key',
                'permission_role.scope',
                'permission_role.effect',
                'permission_role.conditions',
                'role_user.membership_id',
                'roles.key as role_key',
            ]);

        foreach ($roleRows as $row) {
            $grants->push(new Grant(
                permissionKey: $row->permission_key,
                scope: $row->scope,
                effect: $row->effect,
                conditions: $this->decode($row->conditions),
                membershipId: $row->membership_id,
                origin: 'role:'.$row->role_key,
            ));
        }

        // (ب) استثناءات فرديّة فوق الأدوار — بنفس قاعدة Deny > Allow
        $userRows = \DB::table('permission_user')
            ->join('permissions', 'permissions.id', '=', 'permission_user.permission_id')
            ->where('permission_user.user_id', $user->id)
            ->get([
                'permissions.key as permission_key',
                'permission_user.scope',
                'permission_user.effect',
                'permission_user.conditions',
                'permission_user.membership_id',
            ]);

        foreach ($userRows as $row) {
            $grants->push(new Grant(
                permissionKey: $row->permission_key,
                scope: $row->scope,
                effect: $row->effect,
                conditions: $this->decode($row->conditions),
                membershipId: $row->membership_id,
                origin: 'user',
            ));
        }

        return $this->cache[$user->id] = $grants;
    }

    private function roleKeys(User $user): Collection
    {
        return collect(\DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->pluck('roles.key'));
    }

    private function isOwnerOnly(string $permissionKey): bool
    {
        static $ownerOnly = null;

        if ($ownerOnly === null) {
            $ownerOnly = Permission::query()->where('is_owner_only', true)->pluck('key')->all();
        }

        return in_array($permissionKey, $ownerOnly, true);
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return json_decode($value, true) ?: [];
        }

        return [];
    }
}
