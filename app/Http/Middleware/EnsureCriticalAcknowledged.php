<?php

namespace App\Http\Middleware;

use App\Services\Notifications\AcknowledgementGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * ⭐ **«قبل المتابعة» صار شرطَ مرور** (13.2).
 *
 * كان الإقرار الإلزاميّ مبنيًّا بالكامل — بوب-أب ومكافأة ومنعُ تكرار — ولا
 * **يُفرَض** في سطرٍ واحد: صاحب توجيهٍ حرجٍ غير مُقَرّ يتصفّح المنصّة كلّها بـ200،
 * والبوب-أب في صفحة `/announcements` وحدها وقابلٌ للإغلاق بـ✕. فهذا الحارس هو
 * الوصلة الناقصة، ويسأل `AcknowledgementGate` سؤالًا واحدًا على الخادم.
 *
 * موضعه في `web` **بعد** جدار الاحتواء وقفل الصيانة: فالمحظور لا يُساق إلى
 * صفحة تعليمات، والمنصّة المقفولة للصيانة مقفولةٌ للجميع أصلًا. والترتيب يجعل
 * الأشدّ يسبق الأخفّ فلا تتنازع الجدران على الردّ.
 */
class EnsureCriticalAcknowledged
{
    public function __construct(private readonly AcknowledgementGate $gate) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (! $this->gate->blocks($request)) {
                return $next($request);
            }
        } catch (Throwable) {
            // قبل الترحيل لا جدولَ منشورات — والجدار لا يجوز أن يقفل التنصيب نفسه
            return $next($request);
        }

        return $this->gate->wall($request);
    }
}
