<?php

namespace App\Services\Wallet;

use App\Models\GatewayInvoice;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * عميل بوّابة فواتيرك (19.5-ج).
 *
 * كلّ مفتاح وكلّ رابط يأتي من `setting()` — لا مفتاح محروق في الكود ولا في المستودع.
 * والصنف قابل للحقن كاملًا حتى تعمل الاختبارات بلا شبكة (نبديله بمزدوج في الحاويّة).
 */
class FawaterkClient
{
    private const BASE_SANDBOX = 'https://staging.fawaterk.com/api/v2/';

    private const BASE_PRODUCTION = 'https://app.fawaterk.com/api/v2/';

    /** البيئة تُبدَّل من لوحة الأدمن بمفتاح واحد (19.5-ج-5) */
    public function baseUrl(): string
    {
        return setting('topup.gateway.sandbox') ? self::BASE_SANDBOX : self::BASE_PRODUCTION;
    }

    /**
     * إنشاء فاتورة ورابط دفع.
     *
     * @param  array{successUrl:string,failUrl:string,pendingUrl:string}  $redirectionUrls
     * @return array{invoice_id:string,invoice_key:?string,payment_url:?string,raw:array}
     */
    public function createInvoiceLink(
        User $user,
        GatewayInvoice $invoice,
        string $itemName,
        array $redirectionUrls,
    ): array {
        // ⭐ المفتاحان معًا شرطُ أيّ عمل: فاتورةٌ تُنشَأ بلا `vendor_key` تولد
        //    بلا حارسٍ لويب هوكها — أوّل خطوةٍ في الطريق إلى شحنٍ مجّانيّ.
        if (! GatewayGuard::isConfigured()) {
            throw new RuntimeException(GatewayGuard::notice());
        }

        $apiKey = (string) setting('topup.gateway.api_key', '');

        [$firstName, $lastName] = $this->splitName($user->name);

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl().'createInvoiceLink', [
                'cartTotal' => (float) $invoice->amount,
                'currency' => $invoice->currency,
                'customer' => [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => (string) $user->email,
                    'phone' => (string) ($user->phone ?? ''),
                    'address' => (string) setting('topup.gateway.customer_address', '-'),
                ],
                'cartItems' => [[
                    'name' => $itemName,
                    'price' => (float) $invoice->amount,
                    'quantity' => 1,
                ]],
                'redirectionUrls' => $redirectionUrls,
                // ⭐ يعود إلينا مع الويب هوك فنعرف صاحب الفاتورة بلا تخمين
                'payLoad' => [
                    'gateway_invoice_id' => $invoice->id,
                    'user_id' => $user->id,
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('تعذّر إنشاء الفاتورة لدى البوّابة.');
        }

        $body = (array) $response->json();
        $data = (array) ($body['data'] ?? $body);

        $invoiceId = $data['invoiceId'] ?? $data['invoice_id'] ?? $data['invoiceKey'] ?? null;

        if ($invoiceId === null) {
            throw new RuntimeException('ردّ البوّابة بلا رقم فاتورة.');
        }

        return [
            'invoice_id' => (string) $invoiceId,
            'invoice_key' => isset($data['invoiceKey']) ? (string) $data['invoiceKey'] : null,
            'payment_url' => $data['url'] ?? $data['payment_data']['redirectTo'] ?? null,
            'raw' => $body,
        ];
    }

    /**
     * ⭐ التحقّق من هاش الويب هوك (19.5-ج-2) — إجباريّ وبمقارنة آمنة زمنيًّا.
     * ويبقى خارج نداء الشبكة عمدًا حتى لا يُعطَّل التحقّق حين نبدّل العميل في الاختبار.
     *
     * ⛔ ولا يُشتقّ توقيعٌ بمفتاحٍ فارغ: HMAC بمفتاح `''` رقمٌ يحسبه أيّ أحد،
     *    فالاشتقاق نفسه — لا المقارنة وحدها — يجب أن يتوقّف.
     *
     * @throws RuntimeException إن كان `vendor_key` غير مضبوط
     */
    public static function expectedHash(string $invoiceId, string $invoiceKey, string $paymentMethod): string
    {
        $vendorKey = trim((string) setting('topup.gateway.vendor_key', ''));

        if ($vendorKey === '') {
            throw new RuntimeException(GatewayGuard::notice());
        }

        return hash_hmac(
            'sha256',
            "InvoiceId={$invoiceId}&InvoiceKey={$invoiceKey}&PaymentMethod={$paymentMethod}",
            $vendorKey,
        );
    }

    /**
     * لا تُطابِق أبدًا حين تكون البوّابة غير مضبوطة — والمقارنة الآمنة زمنيًّا
     * (`hash_equals`) تبقى كما هي لكلّ نداءٍ عن مفتاحٍ حقيقيّ.
     */
    public static function hashMatches(string $provided, string $invoiceId, string $invoiceKey, string $paymentMethod): bool
    {
        if (! GatewayGuard::isConfigured()) {
            return false;
        }

        return hash_equals(self::expectedHash($invoiceId, $invoiceKey, $paymentMethod), $provided);
    }

    /** @return array{0:string,1:string} */
    private function splitName(?string $name): array
    {
        $parts = preg_split('/\s+/u', trim((string) $name), 2, PREG_SPLIT_NO_EMPTY) ?: [];

        return [$parts[0] ?? '-', $parts[1] ?? '-'];
    }
}
