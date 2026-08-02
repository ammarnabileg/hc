<?php

namespace App\Http\Middleware;

use App\Services\Security\AccountContainmentGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * ⭐ **الحظر له أثر** (12.1-متقدّم-1).
 *
 * كان الاحتواء كلّه مكتوبًا في `UserModeration` — الحالة والرسالة وإنهاء الجلسات —
 * لكنّ `isContained()` **لم تُستدعَ في سطرٍ واحد**، فحسابٌ محظور كان يفتح
 * `/dashboard` بـ200 عاديًّا. هذا الحارس هو الوصلة الناقصة: **المحظور لا يفتح
 * أيّ صفحة**، والمعلَّق كذلك حتى تنقضي مدّته فيعود وحده.
 *
 * موضعه في `web` بعد سياق العضويّة وقبل شريط الانتحال — فلا يُحسَب للمحظور
 * سياقٌ ولا يُحقَن له شريط في صفحةٍ ما كان يجب أن يراها أصلًا.
 */
class EnsureAccountNotContained
{
    public function __construct(private readonly AccountContainmentGate $gate) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (! $this->gate->blocks($request)) {
                return $next($request);
            }
        } catch (Throwable) {
            // قبل الترحيل لا أعمدةَ احتواء — والجدار لا يجوز أن يقفل التنصيب نفسه
            return $next($request);
        }

        return $this->gate->wall($request);
    }
}
