<?php

namespace App\Http\Controllers;

use App\Models\GatewayInvoice;
use App\Models\GatewayWebhookLog;
use App\Services\Wallet\FawaterkClient;
use App\Services\Wallet\GatewayGuard;
use App\Services\Wallet\GatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ويب هوك بوّابة الدفع (19.5-ج-2) — مصدر الحقيقة الوحيد لإضافة رصيد الشحن.
 *
 * القواعد الأربع التي لا يُتنازَل عنها:
 *  1) التحقّق من الهاش إجباريّ بمقارنة آمنة زمنيًّا، والخاطئ يُرفَض ويُسجَّل.
 *  2) الرصيد يُضاف من هنا حصرًا — ورابط الرجوع للعرض فقط.
 *  3) النداء المكرّر يُقبَل بردٍّ ناجح بلا أثر ماليّ (البوّابات تعيد الإرسال، وهو سلوكٌ طبيعيّ).
 *  4) كلّ نداء يُسجَّل خامًا بوقته وIP وهيدرزه وجسمه ونتيجة تحقّقه.
 *  5) ⭐ **ولا نداء يُقبَل والبوّابة غير مضبوطة:** مفتاح تاجرٍ فارغ يعني توقيعًا
 *     يحسبه أيّ أحد، فالباب يُقفَل من أوّله ويُكتَب الرفض بسببه في السجلّ الخام —
 *     فالصمت هنا يخفي هجومًا.
 */
class GatewayWebhookController extends Controller
{
    /** الهيدرز التي لا تُخزَّن أبدًا — لا نحتفظ بأسرارٍ في سجلٍّ يقرأه الأدمن */
    private const REDACTED_HEADERS = ['authorization', 'cookie', 'x-api-key'];

    public function fawaterk(Request $request, GatewayService $gateway): JsonResponse
    {
        $body = $request->all();

        $invoiceId = (string) ($body['invoice_id'] ?? $body['invoiceId'] ?? '');
        $invoiceKey = (string) ($body['invoice_key'] ?? $body['invoiceKey'] ?? '');
        $paymentMethod = (string) ($body['payment_method'] ?? $body['paymentMethod'] ?? '');
        $provided = (string) ($body['hashKey'] ?? $body['hash_key'] ?? '');
        $status = strtolower((string) ($body['invoice_status'] ?? $body['status'] ?? ''));

        /*
        | 0) قبل الهاش نفسه: هل للبوّابة مفتاحٌ أصلًا؟
        | مفتاح تاجرٍ فارغ ⟵ HMAC يشتقّه أيّ أحد، فـ`hash_equals` يحرس بابًا
        | مفتاحُه معلَن. الرفض هنا **مع سببٍ مكتوب في السجلّ الخام** وتنبيهٍ
        | للمالك — لأنّ نداءً على بوّابةٍ بلا مفتاح إمّا عطبٌ في الضبط أو محاولة
        | شحنٍ مجّانيّ، وكلاهما لا يجوز أن يمرّ صامتًا (19.5-ج-2).
        | و503 لا 403: العطب عندنا لا في المنادي، والبوّابة تعيد الإرسال لاحقًا
        | فلا تضيع فاتورةٌ مدفوعة فعلًا بعد ضبط المفتاح.
        */
        if (! GatewayGuard::isConfigured()) {
            $this->log($request, $invoiceId, false, 'gateway_not_configured', GatewayGuard::notice());

            GatewayGuard::warnOwners();

            return response()->json([
                'ok' => false,
                'message' => (string) setting('topup.gateway.webhook.blocked_message', ''),
            ], 503);
        }

        // 1) الهاش أوّلًا وقبل أيّ قراءة للفاتورة — فالمجهول لا يفتح بابًا
        $hashValid = $provided !== ''
            && FawaterkClient::hashMatches($provided, $invoiceId, $invoiceKey, $paymentMethod);

        if (! $hashValid) {
            $this->log($request, $invoiceId, false, 'invalid_hash');

            return response()->json(['ok' => false, 'message' => 'invalid hash'], 403);
        }

        $invoice = GatewayInvoice::query()->where('invoice_id', $invoiceId)->first();

        if (! $invoice) {
            $this->log($request, $invoiceId, true, 'unknown_invoice');

            return response()->json(['ok' => false, 'message' => 'unknown invoice'], 404);
        }

        // 3) منع التكرار: نفس الفاتورة لا تزيد الرصيد مرّتين مهما تكرّر النداء
        if ($status === 'paid' && $invoice->credited) {
            $this->log($request, $invoiceId, true, 'duplicate');

            return response()->json(['ok' => true, 'message' => 'already processed']);
        }

        $result = match ($status) {
            'paid' => $gateway->creditOnce($invoice, $paymentMethod) ? 'credited' : 'duplicate',
            'refunded' => $this->markRefunded($gateway, $invoice),
            'expired', 'unpaid' => $this->markStatus($invoice, $status),
            default => 'ignored',
        };

        $this->log($request, $invoiceId, true, $result);

        return response()->json(['ok' => true, 'result' => $result]);
    }

    // ------------------------------------------------------------------ داخليّ

    private function markRefunded(GatewayService $gateway, GatewayInvoice $invoice): string
    {
        // 5) الاسترداد ⟵ معاملة عكسيّة موثّقة، لا تعديلٌ على الأصل (19.4)
        return $gateway->refund($invoice) ? 'refunded' : 'refund_noop';
    }

    private function markStatus(GatewayInvoice $invoice, string $status): string
    {
        $invoice->update(['status' => $status]);

        return $status;
    }

    /**
     * 4) سجلّ خام لكلّ نداء — الوقت وIP والهيدرز والجسم ونتيجة التحقّق.
     * و`$reason` سببٌ مقروء للرفض حين لا تكفي كلمة النتيجة وحدها.
     */
    private function log(Request $request, string $invoiceId, bool $hashValid, string $result, string $reason = ''): void
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = in_array(strtolower($name), self::REDACTED_HEADERS, true)
                ? '[محجوب]'
                : implode(', ', $values);
        }

        GatewayWebhookLog::create([
            'provider' => (string) setting('topup.gateway.provider', 'fawaterk'),
            'invoice_id' => $invoiceId !== '' ? $invoiceId : null,
            'ip' => $request->ip(),
            'headers' => $headers,
            'body' => $request->getContent() ?: json_encode($request->all(), JSON_UNESCAPED_UNICODE),
            'hash_valid' => $hashValid,
            'result' => $result,
            'reason' => $reason !== '' ? $reason : null,
        ]);
    }
}
