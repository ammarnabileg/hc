<?php

namespace App\Http\Middleware;

use App\Support\Access\AccessEngine;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * حارس المسارات: `permission:tasks.approve`.
 * الرفض 403 — والعناصر التي لا يملكها المستخدم تُخفى من الواجهة أصلًا (2.15-أ-7).
 *
 * **دلالة الفاصلة = «أيٌّ من»** (`permission:complaints.list,complaints.view`) —
 * وهي مقصودة للمسار الذي يقبل أكثر من طريقٍ للوصول.
 *
 * ⭐ لكنّها كانت **بابًا خلفيًّا داخل اللوحة**: مسار `admin/guidance` محروسٌ بـ
 * `announcements.list,announcements.view`، و`announcements.view@SELF` صلاحيّةُ
 * **قراءةٍ شخصيّة** يحملها كلّ متدرّب («استقبال منشورات التعليمات كفيد» — 12.2.2)،
 * فيكفي أضعفُ المفتاحين لفتح شاشةِ إدارة. القاعدة الآن:
 *
 *   داخل `admin.*` — إن كان في المجموعة **مفتاحٌ إداريّ** فهو وحده الذي يفتح،
 *   وتسقط منها مفاتيحُ الصفحات العامّة. فالمفتاح الإداريّ (`announcements.list`)
 *   هو الشرط، لا أضعف ما في السطر.
 *
 * وإن لم يكن في المجموعة أيّ مفتاح إداريّ (مسار إدارةٍ محروسٌ بمفتاحٍ عامّ وحده)
 * تبقى الدلالة كما هي، ويظلّ **باب اللوحة** (`admin.panel`) هو الحارس الأوّل.
 */
class EnsurePermission
{
    public function __construct(private readonly AccessEngine $access) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        foreach ($this->effectiveKeys($request, $permissions) as $permission) {
            if ($this->access->allows($user, $permission)) {
                return $next($request);
            }
        }

        abort(403, 'ليس لديك صلاحيّة الوصول لهذه الصفحة.');
    }

    /**
     * @param  array<int, string>  $permissions
     * @return array<int, string>
     */
    private function effectiveKeys(Request $request, array $permissions): array
    {
        if (count($permissions) < 2 || ! $this->insidePanel($request)) {
            return $permissions;
        }

        $administrative = array_values(array_intersect($permissions, $this->access->adminPermissionKeys()));

        return $administrative === [] ? $permissions : $administrative;
    }

    private function insidePanel(Request $request): bool
    {
        $name = (string) $request->route()?->getName();
        $prefix = (string) config('access.panel.route_prefix', 'admin.');

        return str_starts_with($name, $prefix) || $request->is('admin', 'admin/*');
    }
}
