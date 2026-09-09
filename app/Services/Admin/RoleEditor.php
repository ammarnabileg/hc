<?php

namespace App\Services\Admin;

use App\Models\Membership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleUser;
use App\Models\User;
use App\Support\Access\AccessEngine;
use App\Support\Access\PermissionExpander;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * محرّر الأدوار والصلاحيّات (الدستور 12.2 · 12.2.1 · 12.2.3) — أهمّ شاشة في اللوحة.
 *
 * ثلاث قواعد حاكمة لا تُطفَأ:
 *  ⭐ **`manage` تُفرَد ظاهرةً عند الحفظ** — لا وراثة صامتة (12.2.1-د).
 *  ⭐ **منع تصعيد الامتياز** — لا يمنح أحدٌ ما لا يملك ولا نطاقًا أوسع (12.2.1-ز-2).
 *  ⭐ **عزل الحسّاس** — صلاحيّات `is_owner_only` لا تظهر أصلًا لغير مالك المنصّة (12.2.1-ز-3).
 *
 * وقابليّة الاستخدام شرط قبول (12.2.1-ط): بحث + مجموعات عرض + `manage` + قوالب جاهزة.
 */
class RoleEditor
{
    public function __construct(
        private readonly AccessEngine $access,
        private readonly PermissionExpander $expander,
        private readonly AuditTrail $audit,
    ) {}

    /** الأدوار مع عدّاداتها — القائمة اليمنى في لوح العمودين */
    public function roles(): Collection
    {
        $permissionCounts = DB::table('permission_role')
            ->selectRaw('role_id, count(*) as total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        $userCounts = DB::table('role_user')
            ->selectRaw('role_id, count(*) as total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        return Role::orderByRaw("case layer when 'platform' then 0 when 'volunteer' then 1 else 2 end")
            ->orderBy('id')
            ->get()
            ->map(function (Role $role) use ($permissionCounts, $userCounts) {
                $role->setAttribute('permissions_count', (int) ($permissionCounts[$role->id] ?? 0));
                $role->setAttribute('users_count', (int) ($userCounts[$role->id] ?? 0));

                return $role;
            });
    }

    /** المجموعات الثمانية من `permissions.group` — تُطوى وتُفتَح مجموعةً مجموعة */
    public function groups(User $actor): array
    {
        return Permission::query()
            ->when(! $this->access->isPlatformOwner($actor), fn ($q) => $q->where('is_owner_only', false))
            ->select('group')
            ->selectRaw('count(*) as total')
            ->groupBy('group')
            ->orderBy('group')
            ->pluck('total', 'group')
            ->all();
    }

    /**
     * مصفوفة مجموعة واحدة: لكلّ صلاحيّة نطاقُها المتاح وأثرُها الحاليّ.
     * ولا تُحمَّل المصفوفة كاملةً دفعةً واحدة — تُحمَّل بالمجموعات (24.1).
     */
    public function matrix(Role $role, User $actor, string $group, string $search = ''): Collection
    {
        $current = DB::table('permission_role')
            ->where('role_id', $role->id)
            ->get()
            ->keyBy('permission_id');

        return Permission::query()
            ->where('group', $group)
            // ⭐ عزل الحسّاس: ما هو owner-only لا يظهر أصلًا لغير مالك المنصّة
            ->when(! $this->access->isPlatformOwner($actor), fn ($q) => $q->where('is_owner_only', false))
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('key', 'like', '%'.$search.'%')
                ->orWhere('label_ar', 'like', '%'.$search.'%')
                ->orWhere('description', 'like', '%'.$search.'%')))
            ->orderBy('resource')
            ->orderBy('id')
            ->limit((int) setting('admin.roles.rows_per_group', 400))
            ->get()
            ->map(function (Permission $permission) use ($current) {
                $row = $current->get($permission->id);

                return [
                    'permission' => $permission,
                    'granted' => $row !== null,
                    'scope' => $row->scope ?? $this->defaultScope($permission),
                    'effect' => $row->effect ?? 'allow',
                    'scopes' => $permission->allowed_scopes ?: config('access.scopes'),
                ];
            });
    }

    /**
     * حفظ مجموعة واحدة من المصفوفة.
     *
     * @param  array<int, array{on?: string, scope?: string, effect?: string}>  $rows  المفتاح = permission_id
     * @return array{written: int, rejected: array<int, string>, dropped: array<int, string>}
     */
    public function save(Role $role, User $actor, string $group, array $rows): array
    {
        $isOwner = $this->access->isPlatformOwner($actor);

        // النطاق الذي نكتب فيه: صلاحيّات هذه المجموعة **المرئيّة لهذا المحرّر** فقط،
        // فلا يمحو أدمنٌ عاديّ صفوفًا حسّاسةً لا يراها أصلًا.
        $visible = Permission::query()
            ->where('group', $group)
            ->when(! $isOwner, fn ($q) => $q->where('is_owner_only', false))
            ->get()
            ->keyBy('id');

        $rejected = [];
        $accepted = [];

        foreach ($rows as $permissionId => $row) {
            $permission = $visible->get((int) $permissionId);

            // الصفّ الذي رُفِع عنه الاختيار لا يُكتَب — وسيُمحى مع بقيّة صفوف المجموعة
            if (! $permission || empty($row['on'])) {
                continue;
            }

            $scope = (string) ($row['scope'] ?? $this->defaultScope($permission));
            $effect = ($row['effect'] ?? 'allow') === 'deny' ? 'deny' : 'allow';

            /*
             | ⭐ سقف نطاق المصفوفة (12.2.2): نطاقٌ خارج `allowed_scopes` **يُرفَض
             | صراحةً**، لا يُستبدَل بالافتراضيّ في السرّ — فالتضييق الصامت نقيض
             | 12.2.1-د «يرى بعينه ما مُنِح». هذا الفحص يحمي `save()` بذاتها حتى
             | لو نودِيَت مباشرةً بلا مرور على `RoleController::scopesBeyondCeiling()`.
             */
            if ($effect === 'allow' && ! in_array($scope, $permission->allowed_scopes ?: config('access.scopes'), true)) {
                $rejected[] = strtr(
                    (string) setting(
                        'admin.roles.scope_ceiling_message',
                        'مقدرناش نحفظ «:permission» بنطاق :scope — المصفوفة (12.2.2) بتحدّد لها :scopes وبس.',
                    ),
                    [
                        ':permission' => $permission->label_ar.' ('.$permission->key.')',
                        ':scope' => $scope,
                        ':scopes' => implode(' · ', $permission->allowed_scopes ?: config('access.scopes')),
                    ],
                );

                continue;
            }

            // ⭐ منع تصعيد الامتياز — يُفحَص **قبل** كتابة أيّ سطر، والرفض برسالة تشرح
            if (! $this->access->canGrant($actor, $permission->key, $scope)) {
                $rejected[] = strtr(
                    (string) setting('admin.roles.escalation_message', 'مقدرناش نحفظ «:permission» بنطاق :scope.'),
                    [':permission' => $permission->label_ar.' ('.$permission->key.')', ':scope' => $scope],
                );

                continue;
            }

            $accepted[$permission->id] = ['key' => $permission->key, 'scope' => $scope, 'effect' => $effect];
        }

        $before = DB::table('permission_role')
            ->where('role_id', $role->id)
            ->whereIn('permission_id', $visible->keys())
            ->count();

        $written = 0;
        $dropped = [];

        DB::transaction(function () use ($role, $visible, $accepted, &$written, &$dropped) {
            // المسح ثمّ الكتابة: فالسطر الذي رُفِع عنه الاختيار يختفي فعلًا
            DB::table('permission_role')
                ->where('role_id', $role->id)
                ->whereIn('permission_id', $visible->keys())
                ->delete();

            foreach ($accepted as $row) {
                // ⭐ manage تُفرَد ظاهرةً عند الحفظ — يفعلها المحرّك الجاهز لا نحن
                $result = $this->expander->report($role, $row['key'], $row['scope'], $row['effect']);
                $written += $result['written'];

                /*
                 | ⭐ لا إسقاط صامت (12.2.1-د): `complaints.manage@ALL` كان يكتب
                 | ستّة أسطر ويبتلع `view` و`create` لأنّ نطاقهما SELF — فينتهي
                 | الدور يعدّل الشكاوى ولا يفتحها والمسؤول لا يدري. الآن يُقال.
                 */
                if ($message = $this->expander->explainSkipped($result['skipped'], $row['scope'])) {
                    $dropped[] = '«'.$row['key'].'»: '.$message;
                }
            }
        });

        $this->access->forget();

        $after = DB::table('permission_role')->where('role_id', $role->id)->whereIn('permission_id', $visible->keys())->count();

        // Audit على كلّ تغيير صلاحيّة (12.2.1-ز-4)
        $this->audit->record($actor, 'role.permissions.updated', $role,
            ['group' => $group, 'rows' => $before],
            ['group' => $group, 'rows' => $after, 'rejected' => count($rejected), 'dropped' => count($dropped)],
        );

        return ['written' => $written, 'rejected' => $rejected, 'dropped' => $dropped];
    }

    /** إنشاء دور جديد = **نسخ قالب** وتعديله (12.2.3-4) */
    public function duplicate(Role $template, string $nameAr, User $actor): Role
    {
        $copy = Role::create([
            'key' => $this->uniqueKey($template->key),
            'name_ar' => $nameAr,
            'name_en' => $template->name_en,
            'description' => strtr(setting('admin_dashboard.role_editor.duplicate_1', 'منسوخ من قالب: :p1'), [':p1' => (string) ($template->name_ar)]),
            'layer' => $template->layer,
            'is_system' => false,
            'is_deletable' => true,
            'requires_membership' => $template->requires_membership,
        ]);

        $isOwner = $this->access->isPlatformOwner($actor);

        $source = DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('permission_role.role_id', $template->id)
            ->when(! $isOwner, fn ($q) => $q->where('permissions.is_owner_only', false))
            ->get(['permissions.key as key', 'permission_role.scope', 'permission_role.effect']);

        foreach ($source as $row) {
            // حتى في النسخ: لا يُنقَل ما لا يملكه الناسخ (12.2.1-ز-2)
            if ($this->access->canGrant($actor, $row->key, $row->scope)) {
                $this->expander->attachToRole($copy, $row->key, $row->scope, $row->effect);
            }
        }

        $this->access->forget();
        $this->audit->record($actor, 'role.created', $copy, [], ['template' => $template->key]);

        return $copy;
    }

    /** دور مالك المنصّة ثابت نظاميّ — غير قابل للحذف (12.2.3) */
    public function canDelete(Role $role): bool
    {
        return $role->is_deletable && $role->key !== config('access.owner_role');
    }

    public function delete(Role $role, User $actor): void
    {
        $this->audit->record($actor, 'role.deleted', $role, ['key' => $role->key], []);
        $role->delete();
        $this->access->forget();
    }

    /**
     * إسناد دور لمستخدم **داخل عضويّة** — «الدور ماذا والعضويّة أين» (12.2.1-و).
     *
     * @return array{ok: bool, message: string}
     */
    public function assign(User $actor, User $target, Role $role, ?Membership $membership): array
    {
        if ($role->requires_membership && ! $membership) {
            return ['ok' => false, 'message' => setting('admin_dashboard.role_editor.assign_1', 'الدور ده دور تطوّع، فلازم تختار العضويّة اللي هيشتغل جوّاها — الدور «ماذا» والعضويّة «أين».')];
        }

        if ($membership && $membership->user_id !== $target->id) {
            return ['ok' => false, 'message' => setting('admin_dashboard.role_editor.assign_2', 'العضويّة دي مش بتاعة المستخدم ده — اختر عضويّة من عضويّاته.')];
        }

        // ⭐ منع تصعيد الامتياز على الإسناد: لا يُسنِد أحدٌ دورًا يحوي ما لا يملكه
        if ($blocked = $this->firstUngrantable($actor, $role)) {
            return [
                'ok' => false,
                'message' => strtr(
                    (string) setting('admin.roles.escalation_message', 'مقدرناش نحفظ «:permission» بنطاق :scope.'),
                    [':permission' => $blocked['key'], ':scope' => $blocked['scope']],
                ),
            ];
        }

        $target->assignRole($role, $membership, $actor->id);

        $this->audit->record($actor, 'role.assigned', $target, [], [
            'role' => $role->key,
            'membership_id' => $membership?->id,
        ]);

        return ['ok' => true, 'message' => setting('admin_dashboard.role_editor.assign_3', 'اتسند الدور ✓')];
    }

    public function unassign(User $actor, RoleUser $assignment): void
    {
        $target = User::find($assignment->user_id);

        $this->audit->record($actor, 'role.unassigned', $target ?? $assignment, [
            'role_id' => $assignment->role_id,
            'membership_id' => $assignment->membership_id,
        ], []);

        $assignment->delete();
        $this->access->forget();
    }

    /** إسنادات قائمة للعرض في شاشة الإسناد */
    public function assignments(int $limit = 25): Collection
    {
        return RoleUser::query()
            ->with(['user', 'role', 'membership.entity', 'membership.position'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return array{key: string, scope: string}|null */
    private function firstUngrantable(User $actor, Role $role): ?array
    {
        if ($this->access->isPlatformOwner($actor)) {
            return null;
        }

        $rows = DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('permission_role.role_id', $role->id)
            ->get(['permissions.key as key', 'permission_role.scope']);

        foreach ($rows as $row) {
            if (! $this->access->canGrant($actor, $row->key, $row->scope)) {
                return ['key' => $row->key, 'scope' => $row->scope];
            }
        }

        return null;
    }

    private function defaultScope(Permission $permission): string
    {
        $default = (string) setting('admin.roles.default_scope', 'SELF');
        $allowed = $permission->allowed_scopes ?: config('access.scopes');

        return in_array($default, $allowed, true) ? $default : (string) ($allowed[0] ?? 'SELF');
    }

    private function uniqueKey(string $base): string
    {
        $key = $base.'_copy';
        $index = 1;

        while (Role::where('key', $key)->exists()) {
            $index++;
            $key = $base.'_copy'.$index;
        }

        return $key;
    }
}
