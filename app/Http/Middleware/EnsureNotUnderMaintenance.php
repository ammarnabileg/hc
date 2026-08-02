<?php

namespace App\Http\Middleware;

use App\Services\Admin\System\MaintenanceService;
use App\Services\Security\MaintenanceGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * وضع الصيانة العامّ **يقفل المنصّة فعلًا** (12.7-و-1).
 *
 * كان المنطق كلّه موجودًا في MaintenanceService لكنّ `isActive()` لم يكن يُستدعى
 * إلّا من فيو الأدمن — فالموقع كان يفضل مفتوحًا. هذا الحارس هو الوصلة الناقصة.
 *
 * ⛔ **ولا صيانة جزئيّة لميزة بعينها — عامّة فقط** (12.7-و-1 · 2.13-و)،
 *    فالحارس بلا أيّ معامل ولا استثناء لمسار ميزة.
 */
class EnsureNotUnderMaintenance
{
    public function __construct(
        private readonly MaintenanceService $maintenance,
        private readonly MaintenanceGate $gate,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (! $this->maintenance->isActive()) {
                return $next($request);
            }

            if ($this->gate->allows($request)) {
                return $next($request);
            }
        } catch (Throwable) {
            // قبل الترحيل/التنصيب لا جدولَ إعدادات — والقفل لا يجوز أن يقفل التنصيب نفسه
            return $next($request);
        }

        return $this->gate->wall($request);
    }
}
