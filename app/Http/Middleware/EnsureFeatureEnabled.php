<?php

namespace App\Http\Middleware;

use App\Services\Features\FeatureCatalog;
use App\Services\Features\FeatureGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ⭐ حارس الميزة الموقوفة — **الحصر على الخادم لا بإخفاء زرّ** (24.3 · 2.15-أ-7).
 *
 * الشاشة وحدها لا تُطفئ ميزة: إخفاءُ الزرّ يترك المسار مفتوحًا لحمولةٍ مزوَّرة
 * أو نداءٍ مباشر — «إعدادٌ بلا أثر أسوأ من غيابه». فهذا الحارس يقرأ اسم المسار،
 * يسأل الكتالوج عن ميزته، ثمّ يسأل `FeatureGate` — القارئ نفسه الذي تسأله
 * الشاشة، فلا تفترق إجابةُ الخادم عن إجابة الواجهة.
 *
 * وسلوك المنع من الإعداد لا من الكود:
 *   • `hide`    ⟵ **إخفاء كامل**: 404 — الميزة كأنّها غير موجودة.
 *   • `message` ⟵ صفحةٌ بنصّ «ما يراه المستخدم بدلها» بحالة 403.
 *
 * ويقبل مفتاحًا صريحًا (`EnsureFeatureEnabled::class.':library.reader'`) لمن
 * أراد حراسة مسارٍ بعينه، وإلّا استنبطه من اسم المسار.
 */
class EnsureFeatureEnabled
{
    public function __construct(private readonly FeatureGate $gate) {}

    public function handle(Request $request, Closure $next, ?string $key = null): Response
    {
        $key ??= FeatureCatalog::featureForRoute($request->route()?->getName());

        if ($key === null) {
            return $next($request);
        }

        $state = $this->gate->state($key, $request->user());

        if ($state['allowed']) {
            return $next($request);
        }

        if ($state['behavior'] === 'message') {
            $message = $state['message'];

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'feature' => $key], 403);
            }

            return response()->view('admin.features.unavailable', [
                'message' => $message,
                'feature' => $key,
            ], 403);
        }

        // إخفاء كامل: لا 403 تكشف وجود الميزة، بل «غير موجودة» أصلًا
        abort(404);
    }
}
