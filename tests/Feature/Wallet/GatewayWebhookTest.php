<?php

namespace Tests\Feature\Wallet;

use App\Models\AppNotification;
use App\Models\GatewayInvoice;
use App\Models\GatewayWebhookLog;
use App\Models\Role;
use App\Models\TopupOffer;
use App\Models\Transaction;
use App\Services\Wallet\FawaterkClient;
use App\Services\Wallet\GatewayGuard;
use App\Services\Wallet\GatewayService;
use App\Services\Wallet\LedgerService;
use Database\Seeders\AdminSystemDemoSeeder;
use RuntimeException;

/**
 * الويب هوك هو مصدر الحقيقة الوحيد لإضافة رصيد الشحن (19.5-ج-2).
 * وهذه الاختبارات تحرس القواعد التي لا يُتنازَل عنها.
 */
class GatewayWebhookTest extends WalletTestCase
{
    private const VENDOR_KEY = 'vendor-key-for-tests';

    private const API_KEY = 'api-key-for-tests';

    private const INVOICE_ID = 'FW-90210';

    private const INVOICE_KEY = 'KEY-90210';

    private const METHOD = 'card';

    protected function setUp(): void
    {
        parent::setUp();

        // تعريفات الإعدادات من **مسار الإنتاج** نفسه (2.13) — فما يقرؤه الكود هنا
        // هو ما سيجده المالك في لوحته، لا قيمًا مخترعةً للاختبار.
        (new AdminSystemDemoSeeder)->settings();

        // البوّابة مضبوطة: المفتاحان معًا (19.5-ج-5)
        $this->setSetting('topup.gateway.api_key', self::API_KEY);
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

    // ================================================================ أ-1 — المفتاح الفارغ

    /**
     * 🔴 **الهجوم نفسه** (أ-1): مفتاح التاجر فارغ — وهي القيمة المزروعة —
     * فيشتقّ صاحبُ الفاتورة التوقيعَ بنفسه ويبعث `paid` بلا أن يدفع.
     *
     * قبل الإصلاح: `200 {"ok":true,"result":"credited"}` و**+550 كوين**.
     * بعده: **مرفوض · صفر مليم · وسببٌ مكتوب في السجلّ الخام**.
     */
    public function test_webhook_signed_with_an_empty_vendor_key_credits_nothing(): void
    {
        $this->setSetting('topup.gateway.vendor_key', '');
        $this->invoice();

        // المهاجم يحسب الهاش بمفتاحٍ فارغ — وهو رقمٌ يقدر عليه أيّ أحد
        $forged = hash_hmac(
            'sha256',
            'InvoiceId='.self::INVOICE_ID.'&InvoiceKey='.self::INVOICE_KEY.'&PaymentMethod='.self::METHOD,
            '',
        );

        $this->postJson(route('webhooks.fawaterk'), $this->payload('paid', $forged))
            ->assertStatus(503)
            ->assertJson(['ok' => false]);

        $this->assertSame(0.0, $this->balance());
        $this->assertSame(0, Transaction::query()->count());
        $this->assertFalse((bool) GatewayInvoice::query()->value('credited'));
    }

    /** والرفض **يُكتَب** في السجلّ الخام بسببٍ مقروء — فالصمت يخفي هجومًا */
    public function test_the_empty_key_refusal_is_written_to_the_raw_log_with_a_reason(): void
    {
        $this->setSetting('topup.gateway.vendor_key', '');
        $this->invoice();

        $this->postJson(route('webhooks.fawaterk'), $this->payload());

        $log = GatewayWebhookLog::query()->latest('id')->firstOrFail();

        $this->assertSame('gateway_not_configured', $log->result);
        $this->assertFalse((bool) $log->hash_valid);
        $this->assertNotNull($log->ip);
        $this->assertStringContainsString(self::INVOICE_ID, (string) $log->body);

        // السبب يقول ماذا نقص وماذا يفعل (2.17-ب) — ونصّه من `setting()`
        $this->assertNotEmpty($log->reason);
        $this->assertStringContainsString('مفتاح التاجر', (string) $log->reason);
        $this->assertStringContainsString('اختبار الاتّصال', (string) $log->reason);
    }

    /** ونقصُ `api_key` وحده يقفل الباب كذلك — المفتاحان معًا أو لا بوّابة */
    public function test_an_empty_api_key_alone_also_blocks_the_webhook(): void
    {
        $this->setSetting('topup.gateway.api_key', '');
        $this->invoice();

        $this->postJson(route('webhooks.fawaterk'), $this->payload())->assertStatus(503);

        $this->assertSame(0.0, $this->balance());
        $this->assertSame('gateway_not_configured', GatewayWebhookLog::query()->latest('id')->value('result'));
        $this->assertStringContainsString('مفتاح API', (string) GatewayWebhookLog::query()->latest('id')->value('reason'));
    }

    /** ⛔ ولا يُشتقّ توقيعٌ بمفتاحٍ فارغ أصلًا — الاشتقاق نفسه يتوقّف */
    public function test_no_signature_is_derived_from_an_empty_vendor_key(): void
    {
        $this->setSetting('topup.gateway.vendor_key', '');

        $this->expectException(RuntimeException::class);

        FawaterkClient::expectedHash(self::INVOICE_ID, self::INVOICE_KEY, self::METHOD);
    }

    /** والمقارنة نفسها لا تُطابِق أبدًا والبوّابة غير مضبوطة — حارسٌ ثانٍ خلف الأوّل */
    public function test_hash_never_matches_while_the_gateway_is_unconfigured(): void
    {
        $this->setSetting('topup.gateway.vendor_key', '');

        $forged = hash_hmac(
            'sha256',
            'InvoiceId='.self::INVOICE_ID.'&InvoiceKey='.self::INVOICE_KEY.'&PaymentMethod='.self::METHOD,
            '',
        );

        $this->assertFalse(FawaterkClient::hashMatches($forged, self::INVOICE_ID, self::INVOICE_KEY, self::METHOD));
    }

    /**
     * ⭐ `topup.gateway.enabled` لا يُعتَدّ به بلا المفتاحين (19.5-ج-5):
     * التوجّل مرفوع والبوّابة **غير** مفعَّلة، ولا فاتورة تُفتَح.
     */
    public function test_the_enabled_toggle_is_not_honoured_without_both_keys(): void
    {
        $this->setSetting('topup.gateway.enabled', '1');
        $this->setSetting('topup.gateway.vendor_key', '');

        $this->assertTrue((bool) setting('topup.gateway.enabled'));
        $this->assertFalse(GatewayGuard::isEnabled());
        $this->assertSame(['topup.gateway.vendor_key'], GatewayGuard::missingKeys());
    }

    /** ولا فاتورة تُفتَح أصلًا: لا نُدخِل مستخدمًا مسارَ دفعٍ حارسُ ويب هوكه غائب */
    public function test_no_invoice_is_opened_while_the_gateway_is_unconfigured(): void
    {
        $this->setSetting('topup.gateway.vendor_key', '');

        $offer = TopupOffer::create([
            'method' => 'gateway',
            'label_ar' => 'باقة المتعلّم',
            'pay_amount' => 500,
            'credit_amount' => 550,
            'bonus_percent' => 10,
            'is_active' => true,
        ]);

        try {
            app(GatewayService::class)->startInvoice($this->user, $offer, [
                'successUrl' => 'https://example.test/ok',
                'failUrl' => 'https://example.test/fail',
                'pendingUrl' => 'https://example.test/pending',
            ]);

            $this->fail('فُتِحت فاتورةٌ والبوّابة بلا مفتاح.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('مفتاح التاجر', $e->getMessage());
        }

        $this->assertSame(0, GatewayInvoice::query()->count());
    }

    /** ورسالة الأدمن تقول **ماذا نقص وماذا يفعل** وتصل إليه إشعارًا في لوحته */
    public function test_the_owner_is_told_what_is_missing_and_what_to_do(): void
    {
        $owner = $this->makeUser();
        $owner->assignRole(Role::query()->firstOrCreate(
            ['key' => (string) config('access.owner_role')],
            ['name_ar' => 'مالك المنصّة', 'name_en' => 'Platform Owner'],
        ));

        $this->setSetting('topup.gateway.vendor_key', '');
        $this->invoice();

        $this->postJson(route('webhooks.fawaterk'), $this->payload())->assertStatus(503);

        $notification = AppNotification::query()
            ->where('user_id', $owner->id)
            ->where('category', 'topup')
            ->latest('id')
            ->first();

        $this->assertNotNull($notification, 'المالك لم يُبلَّغ بعطبِ ضبط البوّابة.');
        $this->assertStringContainsString('مفتاح التاجر', (string) $notification->body);   // ماذا نقص
        $this->assertStringContainsString('بوّابة الدفع', (string) $notification->body);   // ماذا يفعل
        $this->assertTrue((bool) $notification->requires_action);

        // ونداءٌ ثانٍ لا يولّد إشعارًا ثانيًا — التبريد إعدادٌ لا رقم محروق
        $this->postJson(route('webhooks.fawaterk'), $this->payload())->assertStatus(503);

        $this->assertSame(1, AppNotification::query()->where('user_id', $owner->id)->count());
    }

    // ================================================================ أ-1 — حدّ النداءات

    /**
     * ⭐ حدّ النداءات قيمته **إعداد** لا رقم محروق (2.13): نضبطه على 3 فيمرّ
     * الثلاثة ويُحجَب الرابع — والافتراضيّ المزروع 60/دقيقة، وهو أضعافُ أيّ
     * ذروة دفعٍ واقعيّة فلا يُسقِط نداءات البوّابة المشروعة المتتابعة.
     */
    public function test_the_webhook_route_is_throttled_by_a_setting(): void
    {
        $this->setSetting('topup.gateway.webhook.rate_limit', '3');
        $this->invoice();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('webhooks.fawaterk'), $this->payload())->assertOk();
        }

        $this->postJson(route('webhooks.fawaterk'), $this->payload())->assertStatus(429);

        // والرصيد لم يتحرّك بعد أوّل إضافة مهما تكرّر النداء (Idempotency)
        $this->assertSame(550.0, $this->balance());
    }

    /** والحدّ المزروع واسعٌ عمدًا: عشرة نداءات متتابعة مشروعة تمرّ كلّها */
    public function test_the_seeded_limit_does_not_drop_legitimate_bursts(): void
    {
        $this->invoice();

        $this->assertGreaterThanOrEqual(60, (int) setting('topup.gateway.webhook.rate_limit'));

        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('webhooks.fawaterk'), $this->payload())->assertOk();
        }
    }
}
