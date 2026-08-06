<?php

namespace App\Services\Developers;

use App\Models\User;
use App\Models\Webhook;
use App\Services\Admin\Volunteer\AuditTrail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * تسجيل/تدوير/إيقاف الويب-هوكس (12.15-ب · 12.15-ج) — بنفس هيكل
 * `ApiKeyService` (المصدر الواحد لمنطق مشابه، راجعه قبل التعديل هنا).
 *
 * ⛔ **قيدان لا يُمَسّان:**
 * 1) السرّ الكامل **يُعرَض نصًّا صريحًا مرّة واحدة فقط** لحظة الإنشاء/التدوير
 *    — ثمّ `Crypt::encryptString()` وحده في القاعدة. ⚠️ خلافًا لمفتاح الـAPI
 *    (`Hash::make()`) — السرّ هنا **يُشتقّ منه توقيعٌ** وقت كلّ إرسال
 *    (`WebhookSigner::sign()`)، فلا بدّ أن يكون قابلًا للفكّ، لا مطابقةً فقط.
 * 2) `rotateSecret()` **يُبطل القديم فورًا**: أيّ توقيعٍ لاحقٍ يُشتقّ من السرّ
 *    الجديد وحده — وهو الحارس الذي يُثبِته اختبار الـMutation.
 */
class WebhookService
{
    /**
     * يسجّل ويب-هوكًا جديدًا، يخزّن السرّ مشفَّرًا، ويُرجع النصّ الصريح **مرّة واحدة**.
     *
     * @param  list<string>  $events
     * @return array{record: Webhook, plain_secret: string}
     */
    public function create(string $name, string $url, array $events, User $actor): array
    {
        $secret = Str::random(40);

        $webhook = Webhook::create([
            'name' => $name,
            'url' => $url,
            'events' => array_values(array_intersect($events, WebhookEventCatalog::EVENT_KEYS)),
            'secret_encrypted' => Crypt::encryptString($secret),
            'status' => 'active',
            'created_by' => $actor->id,
        ]);

        AuditTrail::log($actor, 'webhook.created', $webhook, [], ['name' => $name, 'url' => $url, 'events' => $webhook->events]);

        return ['record' => $webhook, 'plain_secret' => $secret];
    }

    /**
     * تدوير السرّ: يُبطل القديم فورًا ويُظهر الجديد مرّة واحدة (12.15-ب).
     * الاسم والرابط والأحداث كما هي — السرّ وحده يتغيّر.
     *
     * @return array{record: Webhook, plain_secret: string}
     */
    public function rotateSecret(Webhook $webhook, User $actor): array
    {
        $secret = Str::random(40);

        $webhook->fill(['secret_encrypted' => Crypt::encryptString($secret)])->save();

        AuditTrail::log($actor, 'webhook.secret_rotated', $webhook, [], ['name' => $webhook->name]);

        return ['record' => $webhook->fresh(), 'plain_secret' => $secret];
    }

    /** إيقاف مؤقّت — لا يحذف السجلّ ولا يمسّ تاريخ محاولاته (12.15-ب) */
    public function pause(Webhook $webhook, User $actor): void
    {
        $old = $webhook->only(['status']);

        $webhook->fill(['status' => 'paused'])->save();

        AuditTrail::log($actor, 'webhook.paused', $webhook, $old, ['status' => 'paused']);
    }

    /** استئناف بعد إيقاف */
    public function resume(Webhook $webhook, User $actor): void
    {
        $old = $webhook->only(['status']);

        $webhook->fill(['status' => 'active'])->save();

        AuditTrail::log($actor, 'webhook.resumed', $webhook, $old, ['status' => 'active']);
    }

    /** حذف نهائيّ — يحمل معه سجلّ محاولاته (cascadeOnDelete على `webhook_deliveries`) */
    public function delete(Webhook $webhook, User $actor): void
    {
        AuditTrail::log($actor, 'webhook.deleted', $webhook, ['name' => $webhook->name], []);

        $webhook->delete();
    }

    /** السرّ الخام وقت الإرسال وحده — لا يُستدعى إلّا داخل `DeliverWebhookJob` */
    public function plainSecret(Webhook $webhook): string
    {
        return Crypt::decryptString($webhook->secret_encrypted);
    }
}
