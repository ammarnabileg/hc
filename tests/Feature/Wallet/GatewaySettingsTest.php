<?php

namespace Tests\Feature\Wallet;

use App\Models\GatewayInvoice;
use App\Models\Setting;
use App\Models\TopupOffer;
use App\Models\User;
use App\Services\Admin\System\GatewayAdminService;
use App\Services\Wallet\FawaterkClient;
use App\Services\Wallet\GatewayService;
use Database\Seeders\AdminSystemDemoSeeder;
use Database\Seeders\HttpTextDemoSeeder;
use Database\Seeders\ServiceTextsDemoSeeder;
use Illuminate\Support\Facades\File;

/**
 * إعدادات شاشة «بوّابة الدفع» (19.5-ج-5) — **كلّ مفتاحٍ معروضٍ للتحرير يسري**.
 *
 * قبل هذه الدفعة كانت سبعةٌ من عشرة مفاتيح في `GatewayAdminService::PUBLIC_KEYS`
 * بلا قارئٍ واحد في المنصّة: روابط الرجوع الثلاثة كانت **محروقةً**
 * `route('wallet.topup.return', …)` في `TopupController::startGateway()`،
 * و`methods`/`min_amount`/`max_amount`/`fees_on` لا يقرؤها أحد أصلًا.
 */
class GatewaySettingsTest extends WalletTestCase
{
    /** الروابط التي أُرسِلت فعلًا للبوّابة في آخر نداء */
    public static array $sentUrls = [];

    public static float $sentCartTotal = 0.0;

    protected function setUp(): void
    {
        parent::setUp();

        (new AdminSystemDemoSeeder)->settings();
        (new HttpTextDemoSeeder)->settings();
        (new ServiceTextsDemoSeeder)->settings();

        $this->setSetting('topup.gateway.api_key', 'api-key-for-tests');
        $this->setSetting('topup.gateway.vendor_key', 'vendor-key-for-tests');

        self::$sentUrls = [];
        self::$sentCartTotal = 0.0;

        // الشبكة محجوبة: مزدوجٌ في الحاويّة يلتقط ما أُرسِل فعلًا
        $this->app->instance(FawaterkClient::class, new class extends FawaterkClient
        {
            public function createInvoiceLink(User $user, GatewayInvoice $invoice, string $itemName, array $redirectionUrls): array
            {
                GatewaySettingsTest::$sentUrls = $redirectionUrls;
                GatewaySettingsTest::$sentCartTotal = (float) $invoice->amount;

                return [
                    'invoice_id' => 'FW-TEST-1',
                    'invoice_key' => 'KEY-TEST-1',
                    'payment_url' => 'https://staging.fawaterk.com/pay/FW-TEST-1',
                    'raw' => ['redirectionUrls' => $redirectionUrls],
                ];
            }
        });
    }

    private function offer(float $pay = 500, float $credit = 550): TopupOffer
    {
        return TopupOffer::create([
            'method' => 'gateway', 'label_ar' => 'باقة المتعلّم',
            'pay_amount' => $pay, 'credit_amount' => $credit, 'bonus_percent' => 10, 'is_active' => true,
        ]);
    }

    private function start(TopupOffer $offer)
    {
        return $this->actingAs($this->user)
            ->post(route('wallet.topup.gateway'), ['topup_offer_id' => $offer->id]);
    }

    // ================================================================ روابط الرجوع

    /** ⭐ تعديل المالك لروابط الرجوع **يغيّر ما يُرسَل للبوّابة فعلًا** */
    public function test_editing_the_return_urls_changes_what_the_gateway_receives(): void
    {
        $this->setSetting('topup.gateway.success_url', '/wallet?topup=success');
        $this->setSetting('topup.gateway.fail_url', '/wallet?topup=fail');
        $this->setSetting('topup.gateway.pending_url', '/wallet?topup=pending');

        $this->start($this->offer())->assertRedirect('https://staging.fawaterk.com/pay/FW-TEST-1');

        $this->assertSame(url('/wallet?topup=success'), self::$sentUrls['successUrl']);
        $this->assertSame(url('/wallet?topup=fail'), self::$sentUrls['failUrl']);
        $this->assertSame(url('/wallet?topup=pending'), self::$sentUrls['pendingUrl']);

        // ولم تعد صفحةَ الحالة الداخليّة المحروقة
        $this->assertNotSame(route('wallet.topup.return', ['state' => 'success']), self::$sentUrls['successUrl']);
    }

    /** والفارغ يرتدّ لصفحة الحالة الداخليّة — لا رابطَ فارغ يصل البوّابة */
    public function test_an_empty_return_url_falls_back_to_the_internal_state_page(): void
    {
        $this->setSetting('topup.gateway.success_url', '');

        $this->start($this->offer());

        $this->assertSame(route('wallet.topup.return', ['state' => 'success']), self::$sentUrls['successUrl']);
    }

    /**
     * ⛔ ولا يخرج رابط الرجوع عن نطاق المنصّة: إعدادُ أدمنٍ بنطاقٍ غريب كان
     * سيصير **إعادة توجيهٍ مفتوحة** تعيد المستخدم من صفحة دفعٍ إلى موقع غيرنا.
     */
    public function test_an_off_host_return_url_is_refused_and_falls_back(): void
    {
        $this->setSetting('topup.gateway.success_url', 'https://evil.example/steal');

        $this->start($this->offer());

        $this->assertSame(route('wallet.topup.return', ['state' => 'success']), self::$sentUrls['successUrl']);
        $this->assertStringNotContainsString('evil.example', implode(' ', self::$sentUrls));
    }

    /** والمطلق على نطاقنا يُقبَل كما هو */
    public function test_an_absolute_url_on_our_own_host_is_kept(): void
    {
        $own = url('/wallet/topup/done');
        $this->setSetting('topup.gateway.success_url', $own);

        $this->start($this->offer());

        $this->assertSame($own, self::$sentUrls['successUrl']);
    }

    // ================================================================ وسائل الدفع

    /** ⭐ إفراغ «وسائل الدفع المفعَّلة» يمنع فتح الفاتورة — بلا صفحة دفعٍ بلا زرّ دفع */
    public function test_emptying_the_enabled_methods_blocks_the_gateway_flow(): void
    {
        $this->setSetting('topup.gateway.methods', '[]');

        $this->start($this->offer())->assertRedirect();

        $this->assertSame(0, GatewayInvoice::query()->count());
        $this->assertSame([], self::$sentUrls);
        $this->assertStringContainsString('مافيش وسيلة دفع مفعَّلة', (string) session('status'));
    }

    /** وحارسٌ ثانٍ في الخدمة نفسها خلف حارس الكنترولر */
    public function test_the_service_itself_refuses_to_open_an_invoice_without_a_method(): void
    {
        $this->setSetting('topup.gateway.methods', '[]');

        $this->expectException(\RuntimeException::class);

        app(GatewayService::class)->startInvoice($this->user, $this->offer());
    }

    /** والمفعَّلة تُسجَّل على الفاتورة — أثرٌ يُقرأ وقت مراجعة السجلّ الخام */
    public function test_the_enabled_methods_are_recorded_on_the_invoice(): void
    {
        $this->setSetting('topup.gateway.methods', '["card","fawry"]');

        $this->start($this->offer());

        $this->assertSame(['card', 'fawry'], data_get(GatewayInvoice::query()->value('payload'), 'enabled_methods'));
    }

    // ================================================================ تحميل الرسوم

    /** ⭐ `fees_on = user` ⟵ الرسوم تُضاف فوق قيمة العرض في `cartTotal` */
    public function test_fees_on_user_adds_the_fee_to_what_the_gateway_charges(): void
    {
        $this->setSetting('topup.gateway.fees_on', 'user');
        $this->setSetting('topup.gateway.fee_percent', '2.5');

        $this->start($this->offer(500, 550));

        $this->assertSame(512.5, self::$sentCartTotal);

        $payload = GatewayInvoice::query()->value('payload');
        $this->assertSame(12.5, (float) data_get($payload, 'gateway_fee'));
        $this->assertSame('user', data_get($payload, 'fees_on'));

        // ⭐ والكريدتس لا تتغيّر: الرسوم تغيّر مَن يدفع لا ما يستلمه المستخدم
        $this->assertSame(550.0, (float) data_get($payload, 'credit_amount'));
    }

    /** و`platform` تبتلعها فلا يدفع المستخدم غير قيمة العرض */
    public function test_fees_on_platform_leaves_the_charged_amount_at_the_offer_price(): void
    {
        $this->setSetting('topup.gateway.fees_on', 'platform');
        $this->setSetting('topup.gateway.fee_percent', '2.5');

        $this->start($this->offer(500, 550));

        $this->assertSame(500.0, self::$sentCartTotal);
        $this->assertSame('platform', data_get(GatewayInvoice::query()->value('payload'), 'fees_on'));
    }

    /** والنسبة المزروعة **صفر** (19.5-ج-1 ⚠️) فلا أثرَ ماليًّا حتى يكتبها المالك */
    public function test_the_seeded_fee_percent_is_zero_so_nothing_changes_until_the_owner_sets_it(): void
    {
        $this->setSetting('topup.gateway.fees_on', 'user');

        $this->assertSame(0.0, (float) setting('topup.gateway.fee_percent'));

        $this->start($this->offer(500, 550));

        $this->assertSame(500.0, self::$sentCartTotal);
    }

    // ================================================================ الحارس الدائم

    /**
     * 🏆 **الحارس**: كلّ مفتاحٍ في `PUBLIC_KEYS` له صفٌّ مزروع **وقارئٌ في الكود**.
     * أيّ مفتاحٍ يُضاف للشاشة غدًا بلا قارئ يُسقِط هذا الاختبار فورًا (2.13).
     */
    public function test_every_public_gateway_key_is_both_seeded_and_actually_read(): void
    {
        $sources = collect(['app', 'routes'])
            ->flatMap(fn (string $dir) => File::allFiles(base_path($dir)))
            ->filter(fn ($file) => $file->getExtension() === 'php')
            ->reject(fn ($file) => str_contains($file->getPathname(), 'GatewayAdminService.php'))
            ->map(fn ($file) => (string) file_get_contents($file->getPathname()))
            ->implode("\n");

        foreach (GatewayAdminService::PUBLIC_KEYS as $key) {
            $this->assertNotNull(
                Setting::query()->where('key', $key)->value('key'),
                "المفتاح {$key} معروضٌ في الشاشة بلا صفٍّ مزروع.",
            );

            $this->assertStringContainsString(
                "'{$key}'",
                $sources,
                "المفتاح {$key} معروضٌ للتحرير ولا يقرؤه أيّ كودٍ خارج قائمة الشاشة — وعدٌ كاذب (2.13).",
            );
        }
    }
}
