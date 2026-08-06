<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Developers\WebhookSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * إرسال محاولة ويب-هوك واحدة (12.15-ب · 12.15-ج) — Queued Job على نفس
 * اتّصال الطابور الافتراضيّ للمشروع (`QUEUE_CONNECTION=database`؛ ولا
 * افتراضَ لـRedis، `phpunit.xml` يبدّله بـ`sync` في الاختبارات فتُنفَّذ
 * المحاولات وإعادات جدولتها **فورًا** داخل نفس الطلب).
 *
 * ⛔ **لا حمولة بلا توقيع تُرسَل أبدًا**: التوقيع يُشتقّ من نفس النصّ الخام
 * (JSON) المُرسَل حرفيًّا عبر `Http::withBody()` — لا مصفوفةٍ تُعاد تسلسلها
 * بعد التوقيع فيختلف النصّان (ترتيب مفاتيح مختلف مثلًا) فيسقط تحقّق المستقبِل.
 *
 * ⛔ **حدّ إعادة المحاولة حارسٌ حقيقيّ لا شكليّ** (12.15-ب): 3 محاولات
 * كحدٍّ أقصى افتراضيًّا (`developers.webhooks.max_retries`) بتأخيرٍ متصاعد
 * (`developers.webhooks.retry_delays_minutes`) ثمّ `exhausted` — **لا تكرار أبديّ**.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** لطول ردٍّ معقول لا يُخزَّن كاملًا — مصدر معاينة الردّ في الشاشة لا أرشيفًا كاملًا */
    private const RESPONSE_BODY_MAX = 4000;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);

        // الويب-هوك حُذِف (Cascade يحمل معه سجلّ محاولاته) — لا شيء يُرسَل
        if (! $delivery) {
            return;
        }

        $webhook = Webhook::find($delivery->webhook_id);

        if (! $webhook) {
            return;
        }

        $raw = json_encode([
            'event' => $delivery->event_key,
            'timestamp' => now()->toIso8601String(),
            'data' => $delivery->payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $secret = Crypt::decryptString($webhook->secret_encrypted);
        $signature = WebhookSigner::sign($secret, (string) $raw);

        [$responseCode, $responseBody, $succeeded] = $this->send($webhook, (string) $raw, $signature, $delivery->event_key);

        $delivery->attempt_count++;

        if ($succeeded) {
            $delivery->fill([
                'status' => 'success',
                'response_code' => $responseCode,
                'response_body' => $responseBody,
                'delivered_at' => now(),
                'next_retry_at' => null,
            ])->save();

            $webhook->fill([
                'last_triggered_at' => now(),
                'last_response_code' => $responseCode,
                'consecutive_failures' => 0,
            ])->save();

            return;
        }

        $maxRetries = max(1, (int) setting('developers.webhooks.max_retries', 3));

        if ($delivery->attempt_count < $maxRetries) {
            $delay = $this->retryDelayFor($delivery->attempt_count);

            $delivery->fill([
                'status' => 'failed',
                'response_code' => $responseCode,
                'response_body' => $responseBody,
                'next_retry_at' => now()->addMinutes($delay),
            ])->save();

            self::dispatch($delivery->id)->delay(now()->addMinutes($delay));
        } else {
            $delivery->fill([
                'status' => 'exhausted',
                'response_code' => $responseCode,
                'response_body' => $responseBody,
                'next_retry_at' => null,
            ])->save();
        }

        $webhook->fill([
            'last_triggered_at' => now(),
            'last_response_code' => $responseCode,
            'consecutive_failures' => $webhook->consecutive_failures + 1,
        ])->save();
    }

    /** تأخير الإعادة بحسب رقم المحاولة الفاشلة — `1,2,3 ⟵ الفهرس 0,1,2` مع ارتدادٍ لآخر قيمة إن قصرت القائمة */
    private function retryDelayFor(int $failedAttemptNumber): int
    {
        $raw = (string) setting('developers.webhooks.retry_delays_minutes', '1,5,30');

        $delays = array_values(array_filter(array_map(
            static fn (string $v) => (int) trim($v),
            explode(',', $raw),
        ), static fn (int $v) => $v > 0));

        if ($delays === []) {
            $delays = [1, 5, 30];
        }

        $index = min($failedAttemptNumber - 1, count($delays) - 1);

        return $delays[$index];
    }

    /** @return array{0:?int,1:?string,2:bool} */
    private function send(Webhook $webhook, string $raw, string $signature, string $eventKey): array
    {
        try {
            $response = Http::timeout((int) setting('developers.webhooks.timeout_seconds', 8))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => 'sha256='.$signature,
                    'X-Webhook-Event' => $eventKey,
                ])
                ->withBody($raw, 'application/json')
                ->post($webhook->url);

            $body = substr((string) $response->body(), 0, self::RESPONSE_BODY_MAX);

            return [$response->status(), $body, $response->successful()];
        } catch (Throwable $e) {
            return [null, substr($e->getMessage(), 0, self::RESPONSE_BODY_MAX), false];
        }
    }
}
