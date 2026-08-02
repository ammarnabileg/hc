<?php

namespace App\Http\Middleware;

use App\Support\Access\AccessEngine;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ⭐ باب لوحة الإدارة — `admin.panel` (12.2.1-أ).
 *
 * «**ممنوع صلاحيّة باسم شاشة** — الشاشة نتيجةٌ للصلاحيّات لا صلاحيّةً بذاتها،
 * ومنه: لوحة الإدارة تظهر لمن له **أيّ** صلاحيّة». فلا `permission:admin_panel.view`
 * بعد اليوم: الباب يُفتَح لمن يملك **أيّ صلاحيّة إداريّة**، ثمّ **كلّ صفحة تُحرَس
 * بصلاحيّتها هي**.
 *
 * وهذا الحارس يلزم **كلّ** مجموعات مسارات `admin/` لا مجموعةً واحدة: كانت خمس
 * مجموعات تُفتَح بـ`auth` وحدها، فتصير الصلاحيّة الشخصيّة بنطاق SELF مفتاحًا
 * لشاشةٍ إداريّة — ومنها تصدير أسماء وأكواد حاملي الشهادات.
 */
class EnsureAdminPanel
{
    public function __construct(private readonly AccessEngine $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! $this->access->opensAdminPanel($user)) {
            abort(403, 'لوحة الإدارة بتفتح لمن معاه صلاحيّة إداريّة — لو محتاج وصولًا كلّم مالك المنصّة.');
        }

        return $next($request);
    }
}
