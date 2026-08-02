<?php

namespace App\Services\Setup\Middleware;

use App\Services\Setup\SetupPaths;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * الأمان الحاسم (2.2): بعد اكتمال التنصيب تُقفَل كلّ مسارات ‎/setup‎ نهائيًّا.
 * نردّ **404** لا 403 — فلا نعترف أصلًا بوجود معالج تنصيب على الخادم.
 */
class EnsureSetupIsOpen
{
    public function __construct(private readonly SetupPaths $paths) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->paths->isInstalled(), 404);

        return $next($request);
    }
}
