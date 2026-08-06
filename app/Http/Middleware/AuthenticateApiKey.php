<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiRequestLog;
use App\Services\Developers\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * حارس واجهة الـAPI الخارجيّة (12.15-ج): مصادقة بالمفتاح + فحص الـScope +
 * حدّ المعدّل + تسجيل كلّ طلب — **لا استثناء صامت**.
 *
 * الاستخدام: `->middleware('api.key')` للمصادقة وحدها (نقطة `ping` مثلًا)،
 * أو `->middleware('api.key:read:courses')` لتطلّب Scope محدَّدًا أيضًا.
 *
 * ⛔ **قيدٌ لا يُمَسّ:** لا يُسجَّل أيّ طلبٍ بلا صفٍّ في `api_request_logs`،
 * ولا يُترَك رفضٌ بلا ردٍّ نصّه ثابتٌ يقرؤه المطوّر المستهلِك للـAPI.
 */
class AuthenticateApiKey
{
    public function __construct(private readonly ApiKeyService $keys) {}

    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $start = microtime(true);

        if (! (bool) setting('developers.api.enabled', true)) {
            return $this->reject($request, 503, 'api_disabled', $start, null);
        }

        $header = (string) $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            return $this->reject($request, 401, 'missing_or_invalid_key', $start, null);
        }

        $provided = trim(substr($header, strlen('Bearer ')));
        $apiKey = $provided === '' ? null : $this->keys->resolve($provided);

        if (! $apiKey) {
            return $this->reject($request, 401, 'missing_or_invalid_key', $start, null);
        }

        if ($scope !== null && ! in_array($scope, (array) $apiKey->scopes, true)) {
            return $this->reject($request, 403, 'insufficient_scope', $start, $apiKey);
        }

        $limiterKey = 'api:'.$apiKey->id;
        $maxAttempts = (int) ($apiKey->rate_limit_per_minute ?? setting('developers.api.default_rate_limit', 60));

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            return $this->reject($request, 429, 'too_many_requests', $start, $apiKey, [
                'Retry-After' => (string) RateLimiter::availableIn($limiterKey),
            ]);
        }

        RateLimiter::hit($limiterKey, 60);

        $request->attributes->set('apiKey', $apiKey);

        $response = $next($request);

        $this->finish($request, $response->getStatusCode(), $start, $apiKey);

        return $response;
    }

    private function reject(Request $request, int $status, string $error, float $start, ?ApiKey $apiKey, array $headers = []): Response
    {
        $this->finish($request, $status, $start, $apiKey);

        return response()->json(['error' => $error], $status, $headers);
    }

    /**
     * تسجيل الطلب (12.15-ج: **لا استثناء صامت**) + تحديث آخر استخدام على
     * المفتاح إن وُجد. طلبٌ بمفتاح مفقود أو خاطئ **يُسجَّل أيضًا**
     * (`api_key_id = null`) — فالرفض نفسه حدثٌ أمنيّ يستحقّ أثرًا في السجلّ،
     * لا مجرّد ردٍّ للمستدعي بلا أيّ بصمة على الخادم.
     */
    private function finish(Request $request, int $status, float $start, ?ApiKey $apiKey): void
    {
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        ApiRequestLog::create([
            'api_key_id' => $apiKey?->id,
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'status_code' => $status,
            'ip' => $request->ip(),
            'duration_ms' => $durationMs,
            'created_at' => now(),
        ]);

        $apiKey?->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $request->ip(),
        ])->saveQuietly();
    }
}
