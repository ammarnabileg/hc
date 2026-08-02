<?php

namespace Tests\Feature\Wallet;

use App\Models\GatewayInvoice;
use App\Models\GatewayWebhookLog;
use App\Models\Transaction;
use App\Services\Wallet\LedgerService;

/**
 * الويب هوك هو مصدر الحقيقة الوحيد لإضافة رصيد الشحن (19.5-ج-2).
 * وهذه الاختبارات تحرس القواعد التي لا يُتنازَل عنها.
 */
class GatewayWebhookTest extends WalletTestCase
{
    private const VENDOR_KEY = 'vendor-key-for-tests';

    private const INVOICE_ID = 'FW-90210';

    private const INVOICE_KEY = 'KEY-90210';

    private const METHOD = 'card';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setSetting('topup.gateway.vendor_key', self::VENDOR_KEY);
    }

    private function invoice(array $attributes = []): GatewayInvoice
    {
        return GatewayInvoice::create(array_merge([
            'user_id' => $this->user->id,
            'provider' => 'fawaterk',
            'invoice_id' => self::INVOICE_ID,
            'invoice_key' => self::INVOICE_KEY,
            'amount' => 500,
            'currency' => 'EGP',
            'status' => 'unpaid',
            'payload' => ['credit_amount' => 550],
        ], $attributes));
    }

    private function payload(string $status = 'paid', ?string $hash = null): array
    {
        return [
            'invoice_id' => self::INVOICE_ID,
            'invoice_key' => self::INVOICE_KEY,
            'payment_method' => self::METHOD,
            'invoice_status' => $status,
            'hashKey' => $hash ?? hash_hmac(
                'sha256',
                'InvoiceId='.self::INVOICE_ID.'&InvoiceKey='.self::INVOICE_KEY.'&PaymentMethod='.self::METHOD,
                self::VENDOR_KEY,
            ),
        ];
    }

    private function balance(): float
    {
        return app(LedgerService::class)->balance($this->user, 'coins');
    }

    /** ⭐ (1) الهاش الخاطئ يُرفَض ويُسجَّل */
    public function test_invalid_hash_is_rejected_and_logged(): void
    {
        $this->invoice();

        $this->postJson(route('webhooks.fawaterk'), $this->payload('paid', 'مزوّر'))
            ->assertStatus(403);

        $this->assertSame(0.0, $this->balance());

        $log = GatewayWebhookLog::query()->latest('id')->firstOrFail();
        $this->assertFalse($log->hash_valid);
        $this->assertSame('invalid_hash', $log->result);
    }

    public function test_webhook_route_needs_no_csrf_token_nor_auth(): void
    {
        $this->invoice();

        // نداء خادمٍ لا متصفّح: بلا جلسة وبلا توكن — ويعمل
        $this->post(route('webhooks.fawaterk'), $this->payload())->assertOk();

        $this->assertSame(550.0, $this->balance());
    }

    /** ⭐ (2) نفس invoice_id مرّتين لا يضاعف الرصيد */
    public function test_same_invoice_twice_does_not_double_the_balance(): void
    {
        $this->invoice();

        $this->postJson(route('webhooks.fawaterk'), $this->payload())->assertOk();
        $this->assertSame(550.0, $this->balance());

        // النداء المكرَّر يُقبَل بردٍّ ناجح بلا أيّ أثر ماليّ — والبوّابات تعيد الإرسال فعلًا
        $this->postJson(route('webhooks.fawaterk'), $this->payload())
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(550.0, $this->balance());
        $this->assertSame(1, Transaction::query()->where('source', 'topup')->count());

        $this->assertSame('duplicate', GatewayWebhookLog::query()->latest('id')->value('result'));
    }

    /** ⭐ (3) رابط الرجوع (successUrl) لا يزيد رصيدًا أبدًا */
    public function test_success_url_never_credits_the_wallet(): void
    {
        $this->invoice();

        $this->actingAs($this->user)
            ->get(route('wallet.topup.return', ['state' => 'success']))
            ->assertOk();

        $this->assertSame(0.0, $this->balance());
        $this->assertSame(0, Transaction::query()->count());
        $this->assertFalse((bool) GatewayInvoice::query()->value('credited'));
    }

    public function test_unknown_invoice_is_logged_and_refused(): void
    {
        $this->postJson(route('webhooks.fawaterk'), $this->payload())->assertStatus(404);

        $this->assertSame('unknown_invoice', GatewayWebhookLog::query()->latest('id')->value('result'));
    }

    /** حالة refunded ⟵ معاملة عكسيّة تلقائيّة موثّقة */
    public function test_refunded_status_creates_documented_reversal(): void
    {
        $this->invoice();

        $this->postJson(route('webhooks.fawaterk'), $this->payload())->assertOk();
        $this->assertSame(550.0, $this->balance());

        $this->postJson(route('webhooks.fawaterk'), $this->payload('refunded'))->assertOk();

        $this->assertSame(0.0, $this->balance());

        $correction = Transaction::query()->where('is_correction', true)->firstOrFail();
        $this->assertNotNull($correction->corrects_transaction_id);
        $this->assertSame('refunded', GatewayInvoice::query()->value('status'));
    }

    /** ⭐ (4) سجلّ خام لكلّ نداء بوقته وIP ونتيجة تحقّقه */
    public function test_every_call_is_logged_raw(): void
    {
        $this->invoice();

        $this->postJson(route('webhooks.fawaterk'), $this->payload('paid', 'مزوّر'));
        $this->postJson(route('webhooks.fawaterk'), $this->payload());

        $this->assertSame(2, GatewayWebhookLog::query()->count());

        $log = GatewayWebhookLog::query()->latest('id')->firstOrFail();
        $this->assertNotNull($log->ip);
        $this->assertNotEmpty($log->headers);
        $this->assertStringContainsString(self::INVOICE_ID, (string) $log->body);
        $this->assertSame('credited', $log->result);
    }
}
