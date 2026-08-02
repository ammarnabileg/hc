<?php

namespace App\Services\Ads;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ⭐ أحداث من الخادم (Conversions API) بالتوازي مع البكسل (21.3-أ).
 *
 * لماذا؟ «لأنّ مانعات الإعلانات وإعدادات الخصوصيّة تُضيّع جزءًا كبيرًا من أحداث
 * المتصفّح» — فالحدث نفسه يُرسَل من خادمنا، **وبنفس Event ID** فلا يُحتسَب مرّتين.
 *
 * ⭐ **ولا تغادر بياناتٌ خام خوادمنا أبدًا** (21.3-د): البريد والهاتف يُشفَّران
 *    SHA-256 قبل الإرسال — التشفير شرطُ صحّةٍ لا خيار.
 */
class ConversionsApi
{
    public function __construct(private readonly Consent $consent) {}

    public function configured(): bool
    {
        return trim((string) setting('ads.capi.token', '')) !== ''
            && trim((string) setting('ads.pixel.meta_id', '')) !== '';
    }

    /**
     * إرسال الحدث للمنصّة الإعلانيّة.
     *
     * @return bool هل خرج الطلب فعلًا؟ (false = مطفأ أو غير مضبوط أو بلا موافقة)
     */
    public function send(string $name, string $eventUid, ?User $user, array $payload = []): bool
    {
        // الحارس مكرَّر عمدًا: هذه الدالّة عامّة وقد تُنادى من مسارٍ آخر لاحقًا
        if (! $this->consent->trackingEnabled() || ! $this->consent->allowsAds($user)) {
            return false;
        }

        if (! (bool) setting('ads.capi.enabled', true) || ! $this->configured()) {
            return false;
        }

        $catalog = AdEvents::catalog();
        $metaName = $catalog[$name]['meta'] ?? null;

        if ($metaName === null) {
            return false;
        }

        $body = [
            'data' => [[
                'event_name' => $metaName,
                'event_time' => now()->getTimestamp(),
                // ⭐ مفتاح إزالة التكرار: نفس معرّف حدث المتصفّح
                'event_id' => $eventUid,
                'action_source' => 'website',
                'event_source_url' => request()?->fullUrl(),
                'user_data' => $this->hashedUserData($user),
                'custom_data' => $payload,
            ]],
        ];

        try {
            $response = Http::timeout((int) setting('ads.capi.timeout_seconds', 5))
                ->post($this->endpoint(), $body + ['access_token' => (string) setting('ads.capi.token', '')]);

            return $response->successful();
        } catch (Throwable $e) {
            // فشل منصّة إعلانيّة لا يوقف صفحةً على مستخدم — يُسجَّل ويُمضى
            Log::warning('CAPI event failed', ['event' => $name, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function endpoint(): string
    {
        $base = rtrim((string) setting('ads.capi.endpoint', 'https://graph.facebook.com'), '/');
        $version = (string) setting('ads.capi.api_version', 'v19.0');

        return $base.'/'.$version.'/'.(string) setting('ads.pixel.meta_id', '').'/events';
    }

    /**
     * ⭐ بصمة SHA-256 لا بيانات: هذا هو الشرط الذي لا يُكسَر.
     * والبريد يُطبَّع (صغيرة وبلا فراغ) والهاتف يُجرَّد من غير الأرقام قبل التشفير،
     * وإلّا فشلت المطابقة عند المنصّة.
     */
    private function hashedUserData(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $email = mb_strtolower(trim((string) $user->email));
        $phone = preg_replace('/\D+/', '', (string) $user->phone) ?: '';

        return array_filter([
            'em' => $email !== '' ? hash('sha256', $email) : null,
            'ph' => $phone !== '' ? hash('sha256', $phone) : null,
            'external_id' => $user->code ? hash('sha256', (string) $user->code) : null,
        ]);
    }
}
