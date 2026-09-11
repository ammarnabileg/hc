<?php

namespace Tests\Feature\Wallet;

use App\Models\GatewayInvoice;
use App\Models\TopupOffer;
use App\Models\TopupRequest;
use App\Models\TransferMethod;
use App\Models\User;
use App\Services\Wallet\FawaterkClient;
use App\Services\Wallet\TopupLimits;
use App\Services\Wallet\TopupService;
use Database\Seeders\AdminSystemDemoSeeder;
use Database\Seeders\ServiceTextsDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * حدود الشحن — **مصدرٌ واحد** يُطبَّق فعلًا (24.3 · 19.5-و · 19.5-ج-5).
 *
 * قبل هذه الدفعة: `finance.topup.min_amount/max_amount/daily_limit` معروضةٌ في
 * شاشة 🔒 الماليّات **بلا قارئ واحد**، بينما `TopupController` يقرأ مفتاحًا
 * ثالثًا مخفيًّا (`topup.min_amount` = 10) — فما يكتبه المالك لا يسري، وما يسري
 * لا يراه. وحدّا الأقصى واليوميّ لم يكونا مطبَّقَين في أيّ مسار أصلًا.
 */
class TopupLimitsTest extends WalletTestCase
{
    private TransferMethod $method;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // تعريفات الإعدادات من **مسار الإنتاج** نفسه (2.13) — لا قيمًا مخترعة
        (new AdminSystemDemoSeeder)->settings();
        (new ServiceTextsDemoSeeder)->settings();

        $this->method = TransferMethod::create([
            'type' => 'instapay',
            'name_ar' => 'إنستا باي',
            'account_number' => 'platform@instapay',
            'beneficiary_name' => 'مؤسّسة المنصّة',
            'is_active' => true,
        ]);
    }

    private function submit(float $amount, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->user)->post(route('wallet.topup.manual'), [
            'transferred_amount' => $amount,
            'paid_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'transfer_method_id' => $this->method->id,
            'contact_phone' => ($user ?? $this->user)->phone,
            'receipt' => UploadedFile::fake()->image('receipt-'.uniqid().'.png', random_int(10, 400), 10),
        ]);
    }

    // ================================================================ الحدّ الأدنى

    /** ⭐ الحدّ الأدنى المقروء هو **مفتاح شاشة الماليّات** لا مفتاحًا مخفيًّا */
    public function test_a_manual_topup_below_the_finance_minimum_is_rejected(): void
    {
        $this->setSetting('finance.topup.min_amount', '50');

        $this->submit(20)->assertSessionHasErrors('transferred_amount');

        $this->assertSame(0, TopupRequest::query()->count());

        $this->assertStringContainsString(
            'أقلّ مبلغ للشحن 50',
            (string) session('errors')->first('transferred_amount'),
        );
    }

    /**
     * 🔴 **الفجوة نفسها**: بالقيمة المزروعة القديمة (`topup.min_amount` = 10)
     * كان تحويلٌ بـ20 يمرّ رغم أنّ المالك ضابطٌ 50 في شاشته. وهنا **يُرفَض** —
     * ويمرّ بـ50 فقط، فالرفض حدٌّ لا قفلٌ عامّ.
     */
    public function test_the_same_amount_passes_once_the_owner_lowers_the_finance_minimum(): void
    {
        $this->setSetting('finance.topup.min_amount', '10');

        $this->submit(20)->assertRedirect(route('wallet.topup.requests'));

        $this->assertSame(1, TopupRequest::query()->count());
        $this->assertSame(20.0, (float) TopupRequest::query()->value('transferred_amount'));
    }

    // ================================================================ الحدّ الأقصى

    public function test_a_manual_topup_above_the_finance_maximum_is_rejected(): void
    {
        $this->setSetting('finance.topup.max_amount', '20000');

        $this->submit(20001)->assertSessionHasErrors('transferred_amount');

        $this->assertSame(0, TopupRequest::query()->count());
        $this->assertStringContainsString(
            'أقصى مبلغ للعمليّة الواحدة 20000',
            (string) session('errors')->first('transferred_amount'),
        );
    }

    /** وصفرُ السقف يعني **بلا سقف** لا سقفًا صفريًّا يقفل الشحن كلّه */
    public function test_a_zero_maximum_means_no_ceiling_at_all(): void
    {
        $this->setSetting('finance.topup.max_amount', '0');
        $this->setSetting('finance.topup.daily_limit', '0');

        $this->submit(999999)->assertRedirect(route('wallet.topup.requests'));

        $this->assertSame(1, TopupRequest::query()->count());
    }

    // ================================================================ الحدّ اليوميّ

    /** ⭐ الحدّ اليوميّ يجمع ما شُحِن اليوم فعلًا — والطلب الذي يتخطّاه يُرفَض */
    public function test_the_daily_limit_counts_todays_topups_and_blocks_the_one_that_crosses_it(): void
    {
        $this->setSetting('finance.topup.daily_limit', '1000');
        $this->setSetting('finance.topup.min_amount', '10');

        $this->submit(600)->assertRedirect(route('wallet.topup.requests'));

        // الطلب المعلَّق الأوّل يُغلق بالاعتماد حتى لا يصطدم الثاني بقفل «طلب معلَّق واحد»
        TopupRequest::query()->firstOrFail()->update(['status' => TopupService::COMPLETED]);

        $this->assertSame(600.0, app(TopupLimits::class)->usedToday($this->user));

        $this->submit(500)->assertSessionHasErrors('transferred_amount');

        $this->assertSame(1, TopupRequest::query()->count());
        $this->assertStringContainsString(
            'الحدّ اليوميّ للشحن 1000',
            (string) session('errors')->first('transferred_amount'),
        );

        // …وما يسع الباقي يمرّ: الحدّ سقفٌ لا إيقاف
        $this->submit(400)->assertRedirect(route('wallet.topup.requests'));
        $this->assertSame(2, TopupRequest::query()->count());
    }

    /** والحدّ **لكلّ مستخدم على حدة** — لا حدٌّ جماعيّ يقفل الناس ببعضهم */
    public function test_the_daily_limit_is_per_user(): void
    {
        $this->setSetting('finance.topup.daily_limit', '1000');

        $this->submit(900);

        $other = $this->makeUser();
        $this->submit(900, $other)->assertRedirect(route('wallet.topup.requests'));

        $this->assertSame(2, TopupRequest::query()->count());
    }

    /** الطلب الملغى لا يُحسَب في اليوميّ — المال لم يخرج أصلًا */
    public function test_a_cancelled_request_does_not_consume_the_daily_limit(): void
    {
        $this->submit(900);

        TopupRequest::query()->firstOrFail()->update(['status' => TopupService::CANCELLED]);

        $this->assertSame(0.0, app(TopupLimits::class)->usedToday($this->user));
    }

    /** وفاتورة البوّابة تُحسَب **حين يُضاف رصيدها** لا حين تُفتَح */
    public function test_only_credited_gateway_invoices_count_towards_the_daily_limit(): void
    {
        $invoice = GatewayInvoice::create([
            'user_id' => $this->user->id,
            'provider' => 'fawaterk',
            'invoice_id' => 'FW-DAILY-1',
            'amount' => 700,
            'currency' => 'EGP',
            'status' => 'unpaid',
        ]);

        $this->assertSame(0.0, app(TopupLimits::class)->usedToday($this->user));

        $invoice->update(['status' => 'paid', 'credited' => true]);

        $this->assertSame(700.0, app(TopupLimits::class)->usedToday($this->user));
    }

    // ================================================================ قناة البوّابة

    /** ⭐ نافذة المزوّد **تضيّق** سياسة المنصّة ولا توسّعها (19.5-ج-5) */
    public function test_the_gateway_window_narrows_the_platform_policy_never_widens_it(): void
    {
        $limits = app(TopupLimits::class);

        $this->setSetting('finance.topup.min_amount', '50');
        $this->setSetting('finance.topup.max_amount', '20000');
        $this->setSetting('topup.gateway.min_amount', '100');
        $this->setSetting('topup.gateway.max_amount', '5000');

        $this->assertSame(50.0, $limits->min(TopupLimits::CHANNEL_MANUAL));
        $this->assertSame(100.0, $limits->min(TopupLimits::CHANNEL_GATEWAY));
        $this->assertSame(20000.0, $limits->max(TopupLimits::CHANNEL_MANUAL));
        $this->assertSame(5000.0, $limits->max(TopupLimits::CHANNEL_GATEWAY));

        // ولو حاولت نافذة المزوّد توسيع السياسة، بقيت السياسة هي الحاكمة
        $this->setSetting('topup.gateway.min_amount', '10');
        $this->setSetting('topup.gateway.max_amount', '99999');

        $this->assertSame(50.0, $limits->min(TopupLimits::CHANNEL_GATEWAY));
        $this->assertSame(20000.0, $limits->max(TopupLimits::CHANNEL_GATEWAY));
    }

    /** وعرضُ بوّابةٍ خارج النافذة لا يفتح فاتورةً أصلًا — والرسالة من `setting()` */
    public function test_a_gateway_offer_above_the_gateway_maximum_opens_no_invoice(): void
    {
        $this->setSetting('topup.gateway.api_key', 'api-key-for-tests');
        $this->setSetting('topup.gateway.vendor_key', 'vendor-key-for-tests');
        $this->setSetting('topup.gateway.max_amount', '400');

        $this->app->instance(FawaterkClient::class, new class extends FawaterkClient
        {
            public function createInvoiceLink(User $user, GatewayInvoice $invoice, string $itemName, array $redirectionUrls): array
            {
                throw new \RuntimeException('ما كانش المفروض يوصل هنا');
            }
        });

        $offer = TopupOffer::create([
            'method' => 'gateway', 'label_ar' => 'باقة كبيرة',
            'pay_amount' => 500, 'credit_amount' => 550, 'bonus_percent' => 10, 'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->post(route('wallet.topup.gateway'), ['topup_offer_id' => $offer->id])
            ->assertRedirect();

        $this->assertSame(0, GatewayInvoice::query()->count());
        $this->assertStringContainsString('أقصى مبلغ للعمليّة الواحدة 400', (string) session('status'));
    }
}
