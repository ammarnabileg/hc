<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\SetMembershipContext;
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            // الطلبات غير المتزامنة (الحفظ التلقائيّ · البوب-أبات) تحتاج 422 لا تحويلة 302
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
