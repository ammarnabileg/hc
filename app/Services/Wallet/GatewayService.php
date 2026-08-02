<?php

namespace App\Services\Wallet;

use App\Models\AppNotification;
use App\Models\GatewayInvoice;
use App\Models\TopupOffer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    /** إنشاء الفاتورة محلّيًّا ثمّ لدى البوّابة، وإرجاعها بمسار الدفع */
    public function startInvoice(User $user, TopupOffer $offer, array $redirectionUrls): GatewayInvoice
    {
        $invoice = GatewayInvoice::create([
            'user_id' => $user->id,
            'topup_offer_id' => $offer->id,
            'provider' => (string) setting('topup.gateway.provider', 'fawaterk'),
            // رقمٌ محلّيّ مؤقّت حتى يعود رقم البوّابة — والعمود فريد فلا يتكرّر أبدًا
            'invoice_id' => 'local-'.Str::uuid()->toString(),
            'amount' => (float) $offer->pay_amount,
            'currency' => (string) setting('topup.gateway.currency', 'EGP'),
            'status' => 'unpaid',
            'payload' => [
                'credit_amount' => (float) $offer->credit_amount,
                'offer_label' => $offer->label_ar,
            ],
        ]);

        $result = $this->client->createInvoiceLink($user, $invoice, $offer->label_ar, $redirectionUrls);

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
                reason: 'شحن الحساب عبر بوّابة الدفع',
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
                'title' => 'رصيدك اتشحن ✓',
                'body' => 'اتضاف لمحفظتك '.rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.').' كوينز.',
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

            $correction = $this->ledger->reverse($original, 'عكس فاتورة مستردّة من البوّابة');

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
