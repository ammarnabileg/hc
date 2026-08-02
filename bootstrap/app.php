<?php

use App\Http\Middleware\EnsureAccountNotContained;
use App\Http\Middleware\EnsureNotUnderMaintenance;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\SetMembershipContext;
use App\Services\Gamification\Wars\ReadinessBar;
use App\Services\Security\ImpersonationBanner;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => EnsurePermission::class,
        ]);

        // سياق العضويّة النشطة يُحدَّد قبل أيّ تقييم صلاحيّة
        $middleware->web(append: [
            SetMembershipContext::class,
            EnsureNotUnderMaintenance::class,   // وضع الصيانة يقفل المنصّة فعلًا (12.7-و-1)
            EnsureAccountNotContained::class,   // المحظور/المعلَّق لا يفتح أيّ صفحة (12.1-متقدّم-1)
            ImpersonationBanner::class,         // الشريط المعرِّف أثناء التصفّح كمستخدم (12.1)
            ReadinessBar::class,                // شريط الاستعداد العائم على كلّ الصفحات (15.0)
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            // الطلبات غير المتزامنة (الحفظ التلقائيّ · البوب-أبات) تحتاج 422 لا تحويلة 302
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
