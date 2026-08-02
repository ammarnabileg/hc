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
     * ⭐ **حدث الشراء عند لحظته**: عند اعتماد الطلب مدفوعًا لا عند أوّل زيارة بعده.
     *
     * لماذا لم نكتفِ بالمصالحة؟ لأنّها تنتظر طلبًا لاحقًا للمستخدم — وقد يغلق
     * المتصفّح بعد الشراء فلا يصل الحدث إلّا بعد أيّام أو لا يصل. والمنصّة
     * الإعلانيّة تنسب التحويل بوقته، فالتأخير يفسد النسبة لا يؤخّرها فقط.
     *
     * ولا يُلمَس هنا شيء من منطق الشراء نفسه (19.5): يُنادى من **مراقِبٍ** على
     * الطلب عند نقطة النجاح، كما فُعِل تمامًا بعمولة الريفيرال.
     */
    public function purchaseCompleted(Order $order): ?string
    {
        if ((string) $order->status !== 'paid') {
            return null;
        }

        $user = $order->relationLoaded('user') ? $order->user : $order->user()->first();

        if (! $user || $this->alreadyReported($user, 'purchase_completed', $order->getKey())) {
            return null;
        }

        // ⛔ الموافقة يفحصها `AdEvents::record()` نفسه — فلا حدث بلا موافقة صريحة (21.3-د)
        return $this->events->record('purchase_completed', $user, $order, [
            'value' => (float) $order->total,
            'currency' => (string) setting('ads.events.currency', 'USD'),
        ]);
    }

    /**
     * ⭐ **حدث الشحن عند لحظته**: عند اعتماد الشحن في دفتر الأستاذ.
     * وسطر الشحن هو اللحظة الموحّدة للمسارين (يدويّ بعد اعتماد الأدمن · وبوّابة
     * من الويب-هوك)، فالتقاطه منه يغطّيهما معًا بلا لمس أيٍّ منهما.
     */
    public function walletToppedUp(Transaction $transaction): ?string
    {
        $applied = (float) ($transaction->applied_amount ?? $transaction->amount);

        // الشحن المؤهَّل: موجب وغير تصحيحيّ — والتصحيح (عكس فاتورة مستردّة) ليس شحنًا
        if ((string) $transaction->source !== (string) setting('ads.events.topup_source', 'topup')
            || $applied <= 0
            || (bool) $transaction->is_correction) {
            return null;
        }

        $user = $transaction->relationLoaded('user') ? $transaction->user : $transaction->user()->first();

        if (! $user || $this->alreadyReported($user, 'wallet_topup', $transaction->getKey())) {
            return null;
        }

        return $this->events->record('wallet_topup', $user, $transaction, [
            'value' => (float) $transaction->amount,
            'currency' => (string) setting('ads.events.currency', 'USD'),
        ]);
    }

    /**
     * ⭐ شبكة الأمان: ما وقع فعلًا ولم يُرسَل — شراءٌ مدفوع أو شحنٌ ناجح.
     *
     * فالمراقِب يرسل الحدث بلحظته، لكنّه **لا يرسل شيئًا بلا موافقة** (وهذا هو
     * الصواب). فلو اشترى المستخدم قبل أن يوافق ثمّ وافق لاحقًا، تلتقط المصالحة
     * ما فات. وهي أيضًا الغطاء لأيّ مسار دفعٍ يُضاف مستقبلًا بلا مراقِب.
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

    /** هل أُرسِل هذا الحدث لهذا المرجع من قبل؟ — نفس مفتاح إزالة تكرار المصالحة */
    public function alreadyReported(User $user, string $event, int|string|null $referenceId): bool
    {
        if ($referenceId === null) {
            return false;
        }

        return TrackingEvent::query()
            ->where('user_id', $user->id)
            ->where('event', $event)
            ->where('reference_id', $referenceId)
            ->exists();
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
