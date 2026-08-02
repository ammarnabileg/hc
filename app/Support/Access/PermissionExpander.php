<?php

namespace App\Support\Access;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * توسعة «manage» عند الحفظ (Expand-on-save — 12.2.1-د).
 *
 * قاعدتان لا تُطفآن:
 *  ⭐ **`manage` تشمل `create/edit/delete/archive/assign`** — خمسةً بنصّ الدستور
 *     لا اثني عشر. فلا تمنح ضغطةٌ واحدة سلطةَ الاعتماد والرفض والتصدير والاستيراد.
 *  ⭐ **لا وراثة صامتة:** «يرى بعينه ما مُنِح». فما يُكتَب يُكتَب أسطرًا ظاهرة،
 *     و**ما يسقط يُقال ولماذا** — كان `complaints.manage@ALL` يكتب ستّة أسطر
 *     ويُسقِط `view` و`create` بلا أيّ إخبار لأنّ نطاقهما SELF، فينتهي الدور
 *     يعدّل الشكاوى ولا يفتحها.
 */
class PermissionExpander
{
    public function __construct(private readonly AccessEngine $access) {}

    /**
     * إسناد صلاحيّة لدور — مع فرد manage إن وُجدت.
     *
     * @return int عدد الأسطر المكتوبة (والتفصيل الكامل في `report()`)
     */
    public function attachToRole(Role $role, string $permissionKey, string $scope = 'SELF', string $effect = 'allow', array $conditions = []): int
    {
        return $this->report($role, $permissionKey, $scope, $effect, $conditions)['written'];
    }

    /**
     * نفس الإسناد لكن بتقرير كامل: ما كُتِب وما سقط ولماذا.
     *
     * @return array{written: int, keys: array<int, string>, skipped: array<int, array{key: string, reason: string, scopes: array<int, string>}>}
     */
    public function report(Role $role, string $permissionKey, string $scope = 'SELF', string $effect = 'allow', array $conditions = []): array
    {
        $keys = $this->expand($permissionKey);
        $written = 0;
        $writtenKeys = [];
        $skipped = [];

        foreach ($keys as $key) {
            $permission = Permission::where('key', $key)->first();

            if (! $permission) {
                // ليس لكلّ موردٍ كلُّ الأفعال في المصفوفة — نُبلّغ عن الأصل وحده
                if ($key === $permissionKey) {
                    $skipped[] = ['key' => $key, 'reason' => 'missing', 'scopes' => []];
                }

                continue;
            }

            $allowed = $permission->allowed_scopes ?: [];

            // النطاق المطلوب يجب أن يكون ضمن نطاقات الصلاحيّة المسموحة
            if ($allowed !== [] && ! in_array($scope, $allowed, true)) {
                $skipped[] = ['key' => $key, 'reason' => 'scope', 'scopes' => $allowed];

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
            $writtenKeys[] = $key;
        }

        $this->access->forget();

        return ['written' => $written, 'keys' => $writtenKeys, 'skipped' => $skipped];
    }

    /** manage ⟵ الأفعال الخمسة المنصوصة (والصلاحيّة نفسها تبقى مسجَّلة) */
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

    /**
     * رسالة عربيّة تقول **ما سقط ولماذا** — لا إسقاط صامت (12.2.1-د).
     *
     * @param  array<int, array{key: string, reason: string, scopes: array<int, string>}>  $skipped
     */
    public function explainSkipped(array $skipped, string $scope): ?string
    {
        if ($skipped === []) {
            return null;
        }

        $parts = [];

        foreach ($skipped as $row) {
            $parts[] = $row['reason'] === 'scope'
                ? "«{$row['key']}» مااتحفظتش بنطاق {$scope} — نطاقاتها المسموحة: ".implode(' · ', $row['scopes'])
                : "«{$row['key']}» مش موجودة في المصفوفة";
        }

        return 'سقط '.count($skipped).' سطر: '.implode(' · ', $parts);
    }
}
