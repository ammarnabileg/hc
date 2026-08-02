<?php

namespace App\Services\Ads;

use App\Models\Order;
use App\Models\TrackingEvent;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * ⭐ الأحداث الثمانية **عند لحظاتها بالضبط** (21.3-أ) — بلا أن يتلوّث كودُ أيّ مجالٍ آخر.
 *
 * وذلك بطريقتين:
 *  1) **خريطة المسار ⟵ الحدث**: أحداث «فتح الصفحة» تُلتقَط من اسم المسار الجاري،
 *     فلا نزرع نداءً في متحكّم التعلّم ولا المتجر ولا المصادقة.
 *  2) **مصالحة التحويلات**: الشراء والشحن **يقعان في الخادم أو في ويب-هوك بوّابة**،
 *     فلا يصحّ ربطهما بصفحة. نقرأ ما وقع فعلًا ولم يُرسَل بعد، ونرسله.
 *     وهذا هو المعيار الصحيح لأحداث الخادم: تُبنى على **الحقيقة في قاعدتنا** لا على زيارة.
 *
 * ⭐ ولا شيء من هذا يعمل بلا موافقة صريحة — الحارس في `AdEvents::record()` نفسه (21.3-د).
 */
class AdSignals
{
    public function __construct(
        private readonly AdEvents $events,
        private readonly Consent $consent,
    ) {}

    /**
     * خريطة المسار ⟵ الحدث (إعداد لا قائمة محروقة — 2.13).
     * `once` تعني: مرّة واحدة لكلّ مستخدم مهما تكرّرت الزيارة.
     *
     * @return array<string,array{event:string, once?:bool}>
     */
    public function routeMap(): array
    {
        $configured = setting('ads.events.route_map');

        if (is_array($configured) && $configured !== []) {
            return $configured;
        }

        return [
            'store.product' => ['event' => 'course_page_view'],
            'growth.preview.course' => ['event' => 'course_page_view'],
            'register' => ['event' => 'registration_started'],
            'learning.lesson' => ['event' => 'first_lesson_started', 'once' => true],
            'growth.preview.lesson' => ['event' => 'first_lesson_started', 'once' => true],
            'store.quote' => ['event' => 'checkout_opened'],
            'wallet.topup' => ['event' => 'checkout_opened'],
        ];
    }

    /**
     * تُنادى مرّةً واحدة لكلّ طلب من قالب التتبّع.
     * وتعود بالطابور الذي سيُطلقه البكسل في المتصفّح.
     */
    public function capture(?Request $request = null): array
    {
        $request ??= request();

        if (! $this->consent->allowsAds(null, $request)) {
            // ⛔ الرفض (أو الصمت) يوقف كلّ شيء فعليًّا — ولا حتّى نقرأ من القاعدة
            return [];
        }

        $this->captureRoute($request);
        $this->reconcile($request->user());

        return $this->events->pullQueue();
    }

    /** حدث «فتح الصفحة» من اسم المسار الجاري */
    private function captureRoute(Request $request): void
    {
        $name = $request->route()?->getName();

        if (! $name) {
            return;
        }

        $rule = $this->routeMap()[$name] ?? null;

        if (! is_array($rule) || ! isset($rule['event'])) {
            return;
        }

        $event = (string) $rule['event'];
        $user = $request->user();

        if (($rule['once'] ?? false) && $user && $this->alreadyRecorded($user, $event)) {
            return;
        }

        $this->events->record($event, $user, null, $this->payloadFor($request));
    }

    /**
     * ⭐ ما وقع فعلًا ولم يُرسَل: شراءٌ مدفوع أو شحنٌ ناجح.
     * فالبوّابة قد تعتمد الدفعة في ويب-هوك بلا متصفّح — والحدث لا يجوز أن يضيع لذلك.
     */
    public function reconcile(?User $user): void
    {
        if (! $user) {
            return;
        }

        $limit = max((int) setting('ads.events.reconcile_limit', 5), 1);

        $reportedOrders = TrackingEvent::query()
            ->where('user_id', $user->id)
            ->where('event', 'purchase_completed')
            ->pluck('reference_id')
            ->all();

        Order::query()
            ->where('user_id', $user->id)
            ->where('status', 'paid')
            ->whereNotIn('id', $reportedOrders ?: [0])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->each(fn (Order $order) => $this->events->record('purchase_completed', $user, $order, [
                'value' => (float) $order->total,
                'currency' => (string) setting('ads.events.currency', 'USD'),
            ]));

        $reportedTopups = TrackingEvent::query()
            ->where('user_id', $user->id)
            ->where('event', 'wallet_topup')
            ->pluck('reference_id')
            ->all();

        Transaction::query()
            ->where('user_id', $user->id)
            ->where('source', (string) setting('ads.events.topup_source', 'topup'))
            ->where('amount', '>', 0)
            ->whereNotIn('id', $reportedTopups ?: [0])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->each(fn (Transaction $tx) => $this->events->record('wallet_topup', $user, $tx, [
                'value' => (float) $tx->amount,
                'currency' => (string) setting('ads.events.currency', 'USD'),
            ]));
    }

    public function alreadyRecorded(User $user, string $event): bool
    {
        return TrackingEvent::query()
            ->where('user_id', $user->id)
            ->where('event', $event)
            ->exists();
    }

    /** ⭐ لا بيانات شخصيّة في الحمولة أبدًا — معرّف المحتوى ونوعه فقط (21.3-د) */
    private function payloadFor(Request $request): array
    {
        $parameters = $request->route()?->parameters() ?? [];
        $slug = $parameters['slug'] ?? null;
        $type = $parameters['type'] ?? null;

        return array_filter([
            'content_ids' => is_string($slug) ? [$slug] : null,
            'content_type' => is_string($type) ? $type : null,
        ]);
    }
}
