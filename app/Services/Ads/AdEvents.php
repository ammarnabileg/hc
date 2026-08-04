<?php

namespace App\Services\Ads;

use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * ⭐ الأحداث الثمانية المنصوصة (21.3-أ) **عند لحظاتها بالضبط**:
 * فتح صفحة تدريب · بدأ التسجيل · أتمّ التسجيل · فعّل الحساب · بدأ أوّل درس ·
 * فتح صفحة الشراء · أتمّ الشراء · شحن المحفظة.
 *
 * ⭐ ويُرسَل كلّ حدث **مرّتين**: من المتصفّح (بكسل) ومن الخادم (Conversions API)
 *    **بنفس Event ID** — لأنّ مانعات الإعلانات تُضيّع أحداث المتصفّح، ومفتاح
 *    إزالة التكرار يمنع احتسابه مرّتين.
 *
 * ⭐ **ولا شيء من هذا يحدث بلا موافقة صريحة** (21.3-د) — الحارس في `record()` نفسه
 *    لا في الواجهة، فلا يتسرّب حدثٌ من مسارٍ نسي الفحص.
 */
class AdEvents
{
    /** مفتاح الطابور في الجلسة: أحداث تنتظر أن يُطلقها البكسل في المتصفّح */
    public const QUEUE_KEY = 'ads.pixel.queue';

    public function __construct(
        private readonly Consent $consent,
        private readonly ConversionsApi $capi,
    ) {}

    /**
     * الخريطة المقفولة: مفتاحنا ⟵ [التسمية · اسم الحدث عند Meta · عند Google].
     *
     * @return array<string,array{label:string, meta:string, google:string}>
     */
    public static function catalog(): array
    {
        return [
            'course_page_view' => ['label' => setting('ads.ad_events.catalog_1', 'فتح صفحة تدريب'), 'meta' => 'ViewContent', 'google' => 'view_item'],
            'registration_started' => ['label' => setting('ads.ad_events.catalog_2', 'بدأ التسجيل'), 'meta' => 'InitiateCheckout', 'google' => 'begin_signup'],
            'registration_completed' => ['label' => setting('ads.ad_events.catalog_3', 'أتمّ التسجيل'), 'meta' => 'CompleteRegistration', 'google' => 'sign_up'],
            'account_activated' => ['label' => setting('ads.ad_events.catalog_4', 'فعّل الحساب'), 'meta' => 'Subscribe', 'google' => 'account_activated'],
            'first_lesson_started' => ['label' => setting('ads.ad_events.catalog_5', 'بدأ أوّل درس'), 'meta' => 'StartTrial', 'google' => 'tutorial_begin'],
            'checkout_opened' => ['label' => setting('ads.ad_events.catalog_6', 'فتح صفحة الشراء'), 'meta' => 'AddToCart', 'google' => 'begin_checkout'],
            'purchase_completed' => ['label' => setting('ads.ad_events.catalog_7', 'أتمّ الشراء'), 'meta' => 'Purchase', 'google' => 'purchase'],
            'wallet_topup' => ['label' => setting('ads.ad_events.catalog_8', 'شحن المحفظة'), 'meta' => 'AddPaymentInfo', 'google' => 'add_payment_info'],
        ];
    }

    /** تفعيل/إيقاف كلّ حدث على حدة (21.3-و) */
    public function eventEnabled(string $name): bool
    {
        return (bool) setting('ads.events.'.$name, true);
    }

    /**
     * تسجيل الحدث وإرساله من القناتين.
     *
     * @param  array<string,mixed>  $payload  قيمٌ غير حسّاسة فقط (سعر · عملة · معرّف محتوى)
     * @return string|null معرّف الحدث الموحَّد، أو null إن لم يُرسَل شيء
     */
    public function record(string $name, ?User $user = null, ?Model $reference = null, array $payload = []): ?string
    {
        if (! array_key_exists($name, self::catalog())) {
            return null;
        }

        $request = request();
        $user ??= $request?->user();

        // ⛔ الحارس الحاكم: بلا موافقة صريحة لا بكسل ولا حدث خادم — فعليًّا لا شكليًّا
        if (! $this->eventEnabled($name) || ! $this->consent->allowsAds($user, $request)) {
            return null;
        }

        $uid = (string) Str::uuid();

        TrackingEvent::create([
            'user_id' => $user?->id,
            'event' => $name,
            'event_uid' => $uid,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'utm_source' => $request?->query('utm_source'),
            'utm_medium' => $request?->query('utm_medium'),
            'utm_campaign' => $request?->query('utm_campaign'),
            'utm_content' => $request?->query('utm_content'),
            'sent_server_side' => true,
            'sent_browser_side' => true,
            'consent' => $this->consent->choice($user, $request),
            'dispatched_at' => now(),
        ]);

        // (1) المتصفّح: يُطلقه البكسل في الصفحة التالية بنفس المعرّف
        $this->queueForBrowser($name, $uid, $payload);

        // (2) الخادم: مباشرةً — وبالبصمة المشفَّرة لا بالبيانات الخام
        $this->capi->send($name, $uid, $user, $payload);

        return $uid;
    }

    /** أحداث تنتظر المتصفّح — تُقرأ مرّةً واحدة ثمّ تُفرَّغ */
    public function pullQueue(): array
    {
        $session = request()?->hasSession() ? request()->session() : null;

        if (! $session) {
            return [];
        }

        $queue = (array) $session->pull(self::QUEUE_KEY, []);

        return array_values($queue);
    }

    private function queueForBrowser(string $name, string $uid, array $payload): void
    {
        $session = request()?->hasSession() ? request()->session() : null;

        if (! $session) {
            return;
        }

        $queue = (array) $session->get(self::QUEUE_KEY, []);
        $queue[] = ['name' => $name, 'uid' => $uid, 'payload' => $this->safePayload($payload)];

        $session->put(self::QUEUE_KEY, array_slice($queue, -1 * (int) setting('ads.pixel.queue_max', 10)));
    }

    /**
     * ⭐ لا بيانات خام تخرج أبدًا (21.3-د): المسموح مفاتيح غير شخصيّة فقط،
     *    وأيّ مفتاح خارجها يُسقَط بلا نقاش.
     */
    private function safePayload(array $payload): array
    {
        $allowed = (array) setting('ads.pixel.payload_keys', ['value', 'currency', 'content_ids', 'content_name', 'content_type']);

        return array_intersect_key($payload, array_flip(array_map(fn ($k) => (string) $k, $allowed)));
    }
}
