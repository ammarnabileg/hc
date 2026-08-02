<?php

namespace App\Http\Middleware;

use App\Support\Access\AccessEngine;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * حارس المسارات: `permission:tasks.approve`.
 * الرفض 403 — والعناصر التي لا يملكها المستخدم تُخفى من الواجهة أصلًا (2.15-أ-7).
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

        foreach ($permissions as $permission) {
            if ($this->access->allows($user, $permission)) {
                return $next($request);
            }
        }

        abort(403, 'ليس لديك صلاحيّة الوصول لهذه الصفحة.');
    }
}
