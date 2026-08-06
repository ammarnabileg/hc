<?php

namespace App\Services\Developers;

use App\Jobs\DeliverWebhookJob;
use App\Models\Webhook;
use App\Models\WebhookDelivery;

/**
 * نقطة الدخول الوحيدة لإطلاق حدثٍ نحو الويب-هوكس المشترِكة فيه (12.15-ب).
 *
 * ⛔ **كتالوجٌ مقفول** (12.15-ب): حدثٌ خارج `WebhookEventCatalog::EVENT_KEYS`
 * يُتجاهَل صامتًا — لا استثناء يُلقى، فمواضع الاستدعاء (إصدار شهادة · تسجيل
 * مستخدم …) لا تُفشِل مسارها الأصليّ لخطإ مطبعيّ في اسم حدث.
 *
 * ⚠️ **لا يُنتظَر الإرسال هنا**: يُنشِئ سجلّ `webhook_deliveries` ويُرسِل
 * Job فقط — فطلب HTTP الأصليّ (تسجيل/إصدار شهادة) لا يتأخّر ولا يفشل لو
 * تعطّلت وجهة ويب-هوكٍ خارجيّة.
 */
class WebhookDispatcher
{
    public static function dispatch(string $eventKey, array $payload): void
    {
        if (! in_array($eventKey, WebhookEventCatalog::EVENT_KEYS, true)) {
            return;
        }

        if (! (bool) setting('developers.webhooks.enabled', true)) {
            return;
        }

        Webhook::query()
            ->where('status', 'active')
            ->get()
            ->filter(fn (Webhook $webhook) => in_array($eventKey, (array) $webhook->events, true))
            ->each(function (Webhook $webhook) use ($eventKey, $payload): void {
                $delivery = WebhookDelivery::create([
                    'webhook_id' => $webhook->id,
                    'event_key' => $eventKey,
                    'payload' => $payload,
                    'status' => 'pending',
                ]);

                DeliverWebhookJob::dispatch($delivery->id);
            });
    }
}
