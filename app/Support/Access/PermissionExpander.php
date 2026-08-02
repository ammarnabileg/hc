<?php

namespace App\Support\Access;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * توسعة «manage» عند الحفظ (Expand-on-save — 12.2.1).
 * لا وراثة صامتة: إسناد manage يُخزَّن أسطرًا ظاهرةً للمراجعة والتدقيق.
 */
class PermissionExpander
{
    public function __construct(private readonly AccessEngine $access) {}

    /**
     * إسناد صلاحيّة لدور — مع فرد manage إن وُجدت.
     *
     * @return int عدد الأسطر المكتوبة
     */
    public function attachToRole(Role $role, string $permissionKey, string $scope = 'SELF', string $effect = 'allow', array $conditions = []): int
    {
        $keys = $this->expand($permissionKey);
        $written = 0;

        foreach ($keys as $key) {
            $permission = Permission::where('key', $key)->first();

            if (! $permission) {
                continue;
            }

            // النطاق المطلوب يجب أن يكون ضمن نطاقات الصلاحيّة المسموحة
            if (! $this->scopeAllowed($permission, $scope)) {
                continue;
            }

            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $role->id, 'permission_id' => $permission->id, 'scope' => $scope],
                [
                    'effect' => $effect,
                    'conditions' => $conditions ? json_encode($conditions, JSON_UNESCAPED_UNICODE) : null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            $written++;
        }

        $this->access->forget();

        return $written;
    }

    /** manage ⟵ الاثنا عشر فعلًا الظاهرة (والصلاحيّة نفسها تبقى مسجَّلة) */
    public function expand(string $permissionKey): array
    {
        [$resource, $action] = array_pad(explode('.', $permissionKey, 2), 2, null);

        if ($action !== 'manage') {
            return [$permissionKey];
        }

        $keys = [$permissionKey];

        foreach (config('access.manage_expands_to') as $sub) {
            $keys[] = "{$resource}.{$sub}";
        }

        return $keys;
    }

    private function scopeAllowed(Permission $permission, string $scope): bool
    {
        $allowed = $permission->allowed_scopes ?: [];

        return $allowed === [] || in_array($scope, $allowed, true);
    }
}
