<?php

namespace Database\Seeders\Concerns;

use App\Models\Permission;
use Illuminate\Support\Facades\DB;

/**
 * ⭐⭐ **نقطة القصّ الوحيدة: لا صفَّ يُكتَب في `permission_role` فوق سقف المصفوفة** (12.2.2).
 *
 * ================== النصّ الحاكم ==================
 * • **12.2.2** تحدّد لكلّ مفتاح **النطاقات المسموحة** (SELF · TEAM · SUBTREE ·
 *   ENTITY · TRACK · ALL) — فإعطاؤه نطاقًا خارجها مخالفةٌ صريحة.
 * • **12.2.3** تحدّد أيّ الموارد **يغطّيها الدور** — فسحبُ المنح مخالفةٌ لها.
 * فالحسم الذي استقرّ عليه المستودع: **يُقصّ النطاق إلى السقف ولا يُسحَب المنح** —
 * ويُصان النصّان معًا.
 *
 * ================== لماذا تُرِك المكان الأصليّ؟ ==================
 * كانت المصالحة داخل `RolePermissionSeeder::insertRows()` وحده، وهو **مسار
 * الإنتاج** — فصار فيه صفر مخالفة. لكنّ **سيدرات العرض** تكتب في نفس الجدول
 * بـ`DB::table('permission_role')->upsert/insert` مباشرةً، فبقيت عشرات الصفوف
 * تدخل من **بابٍ خلفيّ** لا يمرّ بالقصّ. وحارسٌ يحرس بابًا من بابين لا يحرس شيئًا:
 * `migrate:fresh --seed` ثمّ `DemoSeeder` يعيد إنتاج المخالفة كاملةً.
 *
 * فالقاعدة الآن **صفةٌ مشتركة** تستعملها كلّ السيدرات: نسخةٌ واحدة من المنطق،
 * ويوم يتغيّر الحكم يتغيّر في موضعٍ واحد.
 */
trait GrantsWithinMatrixCeiling
{
    /**
     * يكتب صفوف الإسناد بعد قصّ نطاقها إلى ما تسمح به المصفوفة.
     *
     * @param  array<int, int>  $permissionIds
     * @param  bool  $matrixFloor  حين يكون **كلّ** ما تسمح به المصفوفة أوسعَ من
     *                             النطاق المطلوب، يُكتَب المفتاح بـ**أضيق** نطاقٍ
     *                             تسمح به بدل أن يسقط. ولا يتناقض هذا مع «لا
     *                             أوسع»: المصفوفة تحدّد ما **يمكن التعبير عنه**
     *                             أصلًا لهذا المفتاح، فمفتاحٌ لا SELF في نطاقاته
     *                             لا يُكتَب SELF بحال — و`public_board.view`
     *                             نطاقها **ALL وحده** لأنّها بنصّ 12.2.2 «لوحة
     *                             **مفتوحة لكلّ المتطوّعين**»، فإسقاطها يقفل
     *                             اللوحة في وجه أصحابها ويخالف 12.2.3.
     */
    protected function insertRows(?int $roleId, array $permissionIds, string $scope, bool $matrixFloor = false): void
    {
        // دورٌ غائب (سيدر لم يُشغَّل بعد) لا يُكتَب له صفٌّ بـ`role_id = 0`
        if (! $roleId || $permissionIds === []) {
            return;
        }

        $allowedById = Permission::query()
            ->whereIn('id', $permissionIds)
            ->pluck('allowed_scopes', 'id');

        $byScope = [];

        foreach ($permissionIds as $id) {
            $allowed = $allowedById[$id] ?? [];
            $allowed = is_array($allowed) ? $allowed : (json_decode((string) $allowed, true) ?: []);

            // صلاحيّة بلا سقفٍ منصوص تقبل الستّة — فيُكتَب المطلوب كما هو
            $effective = $allowed === []
                ? $scope
                : ($this->resolveScope($scope, $allowed) ?? ($matrixFloor ? $this->narrowest($allowed) : null));

            if ($effective === null) {
                continue;
            }

            $byScope[$effective][] = $id;
        }

        foreach ($byScope as $effective => $ids) {
            $this->writeRows($roleId, $ids, (string) $effective);
        }
    }

    /** @param  array<int, int>  $permissionIds */
    protected function writeRows(int $roleId, array $permissionIds, string $scope): void
    {
        foreach (array_chunk($permissionIds, 400) as $chunk) {
            $payload = array_map(fn ($id) => [
                'role_id' => $roleId,
                'permission_id' => $id,
                'scope' => $scope,
                'effect' => 'allow',
                'conditions' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ], $chunk);

            DB::table('permission_role')->upsert($payload, ['role_id', 'permission_id', 'scope'], ['effect', 'updated_at']);
        }
    }

    /** أضيق نطاقٍ تسمح به المصفوفة لهذا المفتاح */
    protected function narrowest(array $allowed): ?string
    {
        $order = config('access.scopes');

        $candidates = array_values(array_filter($allowed, fn ($s) => in_array($s, $order, true)));

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn ($a, $b) => array_search($a, $order, true) <=> array_search($b, $order, true));

        return $candidates[0];
    }

    /** أوسع نطاق مسموح لا يتجاوز المطلوب */
    protected function resolveScope(string $requested, array $allowed): ?string
    {
        $order = config('access.scopes');

        if ($allowed === []) {
            return $requested;
        }

        $max = array_search($requested, $order, true);
        $candidates = array_filter($allowed, fn ($s) => array_search($s, $order, true) !== false
            && array_search($s, $order, true) <= $max);

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn ($a, $b) => array_search($b, $order, true) <=> array_search($a, $order, true));

        return $candidates[0];
    }

    /**
     * منح **مفاتيح بعينها** لدورٍ بمفتاحه — للسيدرات التي تعرف المفاتيح لا الأرقام.
     *
     * @param  array<int, string>|array<string, string>  $keys  قائمة مفاتيح، أو مفتاح ⟵ نطاقه
     */
    protected function grantKeysWithinCeiling(?int $roleId, array $keys, ?string $scope = null, bool $matrixFloor = false): void
    {
        if ($roleId === null || $keys === []) {
            return;
        }

        $byScope = [];

        foreach ($keys as $key => $value) {
            [$permissionKey, $wanted] = is_int($key) ? [$value, (string) $scope] : [(string) $key, (string) $value];
            $byScope[$wanted][] = $permissionKey;
        }

        foreach ($byScope as $wanted => $permissionKeys) {
            $ids = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();

            $this->insertRows($roleId, $ids, (string) $wanted, $matrixFloor);
        }
    }
}
