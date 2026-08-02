<?php

namespace App\Support\Access;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * ⭐ سطح لوحة الإدارة — بديل مفتاح `admin_panel.view` المحذوف.
 *
 * الدستور 12.2.1-أ ينصّ: «**ممنوع صلاحيّة باسم شاشة** — الشاشة نتيجةٌ للصلاحيّات
 * لا صلاحيّةً بذاتها، ومنه: **لوحة الإدارة تظهر لمن له أيّ صلاحيّة**». وكان الواقع
 * عكسَه تمامًا: مفتاحٌ باسم شاشة (`admin_panel.view`) يعمل **قائمة سماح** فوق
 * الصلاحيّات، فيُردّ الدايركتور والكوردنيتور بـ403 ومعهما عشرات الصلاحيّات.
 *
 * فالباب الآن **يُحسَب** من جدول المسارات نفسه لا من قائمة محروقة:
 *   صلاحيّة إداريّة = تحرس مسارًا داخل `admin.*`
 *                  − ولا تحرس أيّ مسارٍ خارجها (وإلّا فهي مفتاح صفحةٍ عامّة)
 *                  − ولا تدخل في قالب المستخدم النهائيّ (طبقة `user` — 12.2.3).
 *
 * والاستثناءان ليسا تخفيفًا بل تشديد: بدونهما يفتح **المتدرّب** اللوحة لأنّه يحمل
 * `store_products.list` و`badges.view` (وهي مفاتيح صفحاتٍ عامّة يتصادف أن تحرس
 * شاشة إدارة كذلك). وبهما يبقى الباب حكرًا على من يملك سلطةً إداريّةً فعلًا.
 */
final class AdminPanelSurface
{
    /**
     * مفاتيح الصلاحيّات التي تفتح باب اللوحة.
     *
     * لا كاش ساكن هنا عمدًا: القائمة تعتمد على **حالة قاعدة البيانات** (أدوار طبقة
     * المستخدم)، وكاشٌ بعمر العمليّة كان سيبقى قديمًا في عامل طوابير طويل العمر.
     * والحفظ يقع في `AccessEngine` لكلّ طلب، ويُمسَح مع `forget()`.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        $prefix = (string) config('access.panel.route_prefix', 'admin.');
        $inside = [];
        $outside = [];

        foreach (Route::getRoutes() as $route) {
            $keys = self::guardKeys($route->gatherMiddleware());

            if ($keys === []) {
                continue;
            }

            $bucket = str_starts_with((string) $route->getName(), $prefix) ? 'inside' : 'outside';

            foreach ($keys as $key) {
                ${$bucket}[$key] = true;
            }
        }

        $keys = array_diff_key($inside, $outside);

        foreach (self::endUserKeys() as $key) {
            unset($keys[$key]);
        }

        $keys = array_keys($keys);
        sort($keys);

        return $keys;
    }

    /**
     * مفاتيح `permission:a,b` من ميدلوير مسار.
     *
     * @param  array<int, mixed>  $middleware
     * @return array<int, string>
     */
    private static function guardKeys(array $middleware): array
    {
        $keys = [];

        foreach ($middleware as $entry) {
            if (! is_string($entry) || ! str_starts_with($entry, 'permission:')) {
                continue;
            }

            foreach (explode(',', substr($entry, strlen('permission:'))) as $key) {
                $key = trim($key);

                if ($key !== '') {
                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }

    /**
     * ما يحمله قالب المستخدم النهائيّ (متدرّب · تحت المراجعة — 12.2.3):
     * حملُه ليس دليلَ سلطةٍ إداريّة، فلا يفتح بابًا.
     *
     * @return array<int, string>
     */
    private static function endUserKeys(): array
    {
        $layer = (string) config('access.panel.end_user_layer', 'user');

        if (! self::tablesReady()) {
            return [];
        }

        return DB::table('permission_role')
            ->join('roles', 'roles.id', '=', 'permission_role.role_id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('roles.layer', $layer)
            ->where('permission_role.effect', 'allow')
            ->pluck('permissions.key')
            ->all();
    }

    private static function tablesReady(): bool
    {
        try {
            return Role::query()->getConnection()->getSchemaBuilder()->hasTable('permission_role')
                && Permission::query()->getConnection()->getSchemaBuilder()->hasTable('permissions');
        } catch (\Throwable) {
            return false;
        }
    }
}
