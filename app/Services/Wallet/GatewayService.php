<?php

namespace App\Services\Wallet;

use App\Models\AppNotification;
use App\Models\GatewayInvoice;
use App\Models\TopupOffer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * بوّابة الدفع — إنشاء الفاتورة ثمّ إضافة الرصيد من الويب هوك حصرًا (19.5-ج-2).
 *
 * ⭐ لا تُستدعى `credit()` من أيّ مسار يفتحه المتصفّح، لأنّ رابط الرجوع
 *    يمكن استدعاؤه يدويًّا بلا دفع. مصدر الحقيقة هو نداء خادم فواتيرك الموقَّع.
 */
class GatewayService
{
    public function __construct(
        private readonly FawaterkClient $client,
        private readonly LedgerService $ledger,
    ) {}

    /**
     * ⭐ روابط الرجوع الثلاثة **من الإعدادات** (19.5-ج-5).
     *
     * كانت `TopupController::startGateway()` تحرقها بثلاث نداءات
     * `route('wallet.topup.return', …)`، فالمفاتيح الثلاثة معروضةٌ للتحرير في
     * شاشة البوّابة و**تعديلها بلا أثر** — وهو بالضبط ما تمنعه 2.13.
     *
     * @return array{successUrl:string, failUrl:string, pendingUrl:string}
     */
    public function redirectionUrls(): array
    {
        return [
            // النداء صريحٌ بالمفتاح لا بمتغيّر — فيراه `settings:coverage` قارئًا حقيقيًّا
            'successUrl' => $this->returnUrl((string) setting('topup.gateway.success_url', ''), 'success'),
            'failUrl' => $this->returnUrl((string) setting('topup.gateway.fail_url', ''), 'fail'),
            'pendingUrl' => $this->returnUrl((string) setting('topup.gateway.pending_url', ''), 'pending'),
        ];
    }

    /**
     * وسائل الدفع المفعَّلة (19.5-ج-5 — «بتفعيل إفراديّ»).
     *
     * ⛔ ولا تُمرَّر للبوّابة في `createInvoiceLink`: النصّ (19.5-ج-1 ⚠️) يشترط
     *    تأكيد **الأسماء الدقيقة للحقول** من داشبورد التاجر وقت البناء، وتخمين
     *    اسم حقلٍ هناك يكتب إعدادًا في الفراغ بدل أن يصله. فالمفتاح يُطبَّق
     *    **عندنا**: قائمةٌ فارغة = ما من وسيلةٍ مفعَّلة = لا فاتورة تُفتَح، ولا
     *    نُدخِل مستخدمًا صفحةَ دفعٍ بلا زرّ دفعٍ واحد فيها.
     *
     * @return list<string>
     */
    public function enabledMethods(): array
    {
        // الافتراضيّ هنا **نسخة المزروع** (2.13): غياب الصفّ ليس «ولا وسيلة»
        $raw = setting('topup.gateway.methods', ['card', 'wallet', 'fawry']);

        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return array_values(array_filter(
            array_map(fn ($method): string => trim((string) $method), (array) $raw),
            fn (string $method): bool => $method !== '',
        ));
    }

    /**
     * ما يُحصَّل فعلًا من المستخدم: قيمة العرض + رسوم البوّابة إن كانت
     * **محمَّلةً عليه** (19.5-ج-5 «تحميل الرسوم على المنصّة أو المستخدم»).
     *
     * ⭐ والكريدتس لا تتغيّر بحال: الرسوم تُغيّر مَن يدفعها لا ما يستلمه المستخدم.
     *
     * ⚠️ `topup.gateway.fee_percent` مزروعٌ **صفرًا عمدًا**: النصّ (19.5-ج-1)
     *    يؤجّل «رسوم كلّ وسيلة» لحين تأكيدها من داشبورد التاجر، فالصفر يعني
     *    «لم تُعرَف بعد» — واختراع نسبةٍ افتراضيّة هنا كان سيحمّل المستخدم رقمًا
     *    لم يقرّره أحد. وأوّل رقمٍ يكتبه المالك يُفعّل `fees_on` فورًا.
     *
     * @return array{charged:float, fee:float, bearer:string}
     */
    public function feeBreakdown(float $payAmount): array
    {
        $bearer = (string) setting('topup.gateway.fees_on', 'platform');
        $percent = max((float) setting('topup.gateway.fee_percent', 0), 0.0);
        $fee = round($payAmount * $percent / 100, 2);

        return $bearer === 'user'
            ? ['charged' => round($payAmount + $fee, 2), 'fee' => $fee, 'bearer' => $bearer]
            : ['charged' => $payAmount, 'fee' => $fee, 'bearer' => 'platform'];
    }

    /**
     * رابطٌ واحد: الفارغ يرتدّ لصفحة الحالة الداخليّة.
     *
     * ⛔ ولا يخرج رابط الرجوع عن نطاق المنصّة أبدًا: البوّابة تعيد المستخدم إليه
     *    بعد الدفع، فرابطٌ لنطاقٍ غريب يحوّل إعدادَ أدمن إلى **إعادة توجيهٍ
     *    مفتوحة** موقَّعةٍ باسمنا. المسار النسبيّ يُحَلّ على نطاقنا، والمطلق
     *    يُقبَل إن كان مضيفُه مضيفَنا، وما عداه يرتدّ للصفحة الداخليّة.
     */
    private function returnUrl(string $configured, string $state): string
    {
        $fallback = route('wallet.topup.return', ['state' => $state]);
        $configured = trim($configured);

        if ($configured === '') {
            return $fallback;
        }

        if (str_starts_with($configured, '/')) {
            return url($configured);
        }

        $host = parse_url($configured, PHP_URL_HOST);

        return $host !== null && $host === parse_url(url('/'), PHP_URL_HOST) ? $configured : $fallback;
    }

    /**
     * إنشاء الفاتورة محلّيًّا ثمّ لدى البوّابة، وإرجاعها بمسار الدفع.
     *
     * `$redirectionUrls` اختياريّة: الافتراضيّ هو المقروء من الإعدادات، ولا
     * يُمرَّر غيره إلّا في اختبارٍ يحقن روابط بعينها.
     *
     * @param  array{successUrl?:string, failUrl?:string, pendingUrl?:string}  $redirectionUrls
     */
    public function startInvoice(User $user, TopupOffer $offer, array $redirectionUrls = []): GatewayInvoice
    {
        /*
        | ⭐ `topup.gateway.enabled` لا يُعتَدّ به وحده (19.5-ج-5): بلا `api_key`
        | و`vendor_key` معًا لا تُفتَح فاتورةٌ أصلًا — فلا نُدخِل مستخدمًا في
        | مسار دفعٍ حارسُ ويب هوكه غير موجود. والرسالة تقول للأدمن ماذا نقص
        | وماذا يفعل (2.17-ب)، وتصله إشعارًا في لوحته.
        */
        if (! GatewayGuard::isEnabled()) {
            GatewayGuard::warnOwners();

            // استثناء تشغيليّ لا `WalletException`: المستخدم يرى رسالة الكنترولر
            // العامّة ولا يقرأ أبدًا **أيّ مفتاحٍ ينقصنا** — والتفصيل للمالك وحده.
            throw new RuntimeException(GatewayGuard::notice() ?: 'gateway disabled');
        }

        // ⛔ حارسٌ ثانٍ خلف الكنترولر: ولا وسيلة دفعٍ مفعَّلة = لا فاتورة تُفتَح
        if ($this->enabledMethods() === []) {
            throw new RuntimeException(
                (string) setting('wallet.gateway_service.start_invoice_1', 'مافيش وسيلة دفع مفعَّلة على البوّابة.'),
            );
        }

        $fees = $this->feeBreakdown((float) $offer->pay_amount);

        $invoice = GatewayInvoice::create([
            'user_id' => $user->id,
            'topup_offer_id' => $offer->id,
            'provider' => (string) setting('topup.gateway.provider', 'fawaterk'),
            // رقمٌ محلّيّ مؤقّت حتى يعود رقم البوّابة — والعمود فريد فلا يتكرّر أبدًا
            'invoice_id' => 'local-'.Str::uuid()->toString(),
            // ما يُحصَّل فعلًا: قيمة العرض، ومعها الرسوم إن كانت محمَّلةً على المستخدم
            'amount' => $fees['charged'],
            'currency' => (string) setting('topup.gateway.currency', 'EGP'),
            'status' => 'unpaid',
            'payload' => [
                // ⭐ الكريدتس من العرض لا من المحصَّل — فالرسوم لا تنقص رصيد أحد
                'credit_amount' => (float) $offer->credit_amount,
                'offer_label' => $offer->label_ar,
                'pay_amount' => (float) $offer->pay_amount,
                'gateway_fee' => $fees['fee'],
                'fees_on' => $fees['bearer'],
                // وسائل الدفع المعروضة لحظة فتح الفاتورة — أثرٌ يُقرأ في السجلّ الخام
                'enabled_methods' => $this->enabledMethods(),
            ],
        ]);

        $result = $this->client->createInvoiceLink(
            $user,
            $invoice,
            $offer->label_ar,
            $redirectionUrls ?: $this->redirectionUrls(),
        );

        $invoice->update([
            'invoice_id' => $result['invoice_id'],
            'invoice_key' => $result['invoice_key'],
            'payment_url' => $result['payment_url'],
            'payload' => array_merge($invoice->payload ?? [], ['gateway_response' => $result['raw']]),
        ]);

        return $invoice->refresh();
    }

    /**
     * ⭐ إضافة الرصيد مرّةً واحدة مهما تكرّر النداء (Idempotency — 19.5-ج-2).
     * ترجع `null` إن كانت الفاتورة مضافةً من قبل: نداءٌ مقبول بلا أيّ أثر ماليّ.
     */
    public function creditOnce(GatewayInvoice $invoice, ?string $paymentMethod = null): ?Transaction
    {
        return DB::transaction(function () use ($invoice, $paymentMethod) {
            // قفل الصفّ: نداءان متزامنان لا يريان «غير مضافة» معًا
            $locked = GatewayInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->credited) {
                return null;
            }

            $user = $locked->user()->firstOrFail();
            $amount = $this->creditAmount($locked);

            $transaction = $this->ledger->credit(
                user: $user,
                currencyCode: (string) setting('topup.credit_currency', 'coins'),
                amount: $amount,
                source: 'topup',
                reference: $locked,
                layer: 'training',
                reason: setting('wallet.gateway_service.credit_once_1', 'شحن الحساب عبر بوّابة الدفع'),
            );

            $locked->update([
                'status' => 'paid',
                'payment_method' => $paymentMethod ?: $locked->payment_method,
                'paid_at' => now(),
                'credited' => true,
                'transaction_id' => $transaction->id,
            ]);

            AppNotification::create([
                'user_id' => $user->id,
                'layer' => 'platform',
                'category' => 'topup',
                'title' => setting('wallet.gateway_service.credit_once_2', 'رصيدك اتشحن ✓'),
                'body' => strtr(setting('wallet.gateway_service.credit_once_3', 'اتضاف لمحفظتك :p1 كوينز.'), [':p1' => (string) (rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.'))]),
                'url' => route('wallet.index'),
                'reference_type' => $locked->getMorphClass(),
                'reference_id' => $locked->getKey(),
            ]);

            return $transaction;
        });
    }

    /**
     * حالة `refunded` ⟵ معاملة عكسيّة تلقائيّة موثّقة (19.4 · 19.5-ج-2).
     * ولا استرجاع نقديّ — السطر العكسيّ يصحّح الرصيد ويبقى الأثر ظاهرًا.
     */
    public function refund(GatewayInvoice $invoice): ?Transaction
    {
        return DB::transaction(function () use ($invoice) {
            $locked = GatewayInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! $locked->credited || ! $locked->transaction_id) {
                $locked->update(['status' => 'refunded']);

                return null;
            }

            $alreadyReversed = Transaction::query()
                ->where('corrects_transaction_id', $locked->transaction_id)
                ->exists();

            if ($alreadyReversed) {
                return null;
            }

            $original = Transaction::query()->findOrFail($locked->transaction_id);

            $correction = $this->ledger->reverse($original, setting('wallet.gateway_service.refund_1', 'عكس فاتورة مستردّة من البوّابة'));

            $locked->update(['status' => 'refunded']);

            return $correction;
        });
    }

    /** قيمة الكريدتس: من العرض إن وُجد، وإلّا من قيمة الفاتورة — وتُحسَب في الخادم دائمًا */
    private function creditAmount(GatewayInvoice $invoice): float
    {
        $fromPayload = (float) data_get($invoice->payload, 'credit_amount', 0);

        if ($fromPayload > 0) {
            return $fromPayload;
        }

        $offer = $invoice->topup_offer()->first();

        return (float) ($offer?->credit_amount ?? $invoice->amount);
    }
}
