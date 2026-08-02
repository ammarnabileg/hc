<?php

namespace Tests\Feature\Wallet;

use App\Models\GatewayInvoice;
use App\Models\Referral;
use App\Models\ReferralCommission;
use App\Models\Setting;
use App\Models\TopupOffer;
use App\Models\User;
use App\Services\Referral\ReferralService;
use App\Services\Wallet\GatewayService;
use App\Services\Wallet\LedgerService;
use App\Services\Wallet\ReferralCommissionService;
use Illuminate\Support\Facades\Cache;

/**
 * عمولة الريفيرال 7% عند نجاح الشحن (19.3).
 *
 * ما يثبته هذا الملفّ: العمولة **تُصرَف فعلًا رصيدًا قابلًا للسحب**،
 * و**مرّة واحدة فقط** مهما تكرّر النداء — على الشحن اليدويّ والبوّابة معًا.
 */
class ReferralCommissionTest extends WalletTestCase
{
    private User $referrer;

    private User $referred;

    protected function setUp(): void
    {
        parent::setUp();

        $this->referrer = $this->makeUser(['name' => 'داعية أوّل']);
        $this->referred = $this->makeUser(['name' => 'مدعوّ أوّل']);

        Referral::create([
            'referrer_id' => $this->referrer->id,
            'referred_id' => $this->referred->id,
            'code' => $this->referrer->code,
            'commission_percent' => 7,
        ]);
    }

    private function ledger(): LedgerService
    {
        return app(LedgerService::class);
    }

    /** ⭐ العمولة تنزل رصيدًا قابلًا للسحب لا رقمًا معروضًا */
    public function test_a_successful_topup_credits_the_referrer_withdrawable_balance(): void
    {
        // 500 كوين = $10 بسعر 1$ = 50 كوين ⟵ عمولة 7% = $0.70
        $this->ledger()->credit($this->referred, 'coins', 500, 'topup', null, 'training', 'شحن الحساب');

        $commission = ReferralCommission::query()->firstOrFail();

        $this->assertSame(0.7, (float) $commission->amount_usd);
        $this->assertSame(10.0, (float) $commission->base_usd);
        $this->assertSame(0.7, $this->ledger()->balance($this->referrer, 'usd'));

        // والعدّاد المعروض في صفحة الدعوات يبقى متّسقًا مع دفتر الأستاذ
        $this->assertSame(0.7, (float) Referral::query()->firstOrFail()->commission_earned);
    }

    /** ⭐ مرّة واحدة فقط: النداء المكرَّر على نفس الحركة بلا أثر ماليّ */
    public function test_the_commission_is_recorded_only_once_per_topup(): void
    {
        $transaction = $this->ledger()->credit($this->referred, 'coins', 500, 'topup', null, 'training', 'شحن الحساب');

        $service = app(ReferralCommissionService::class);

        // إعادة النداء يدويًّا مرّتين — كما لو أعادت البوّابة إرسال الويب هوك
        $this->assertNull($service->recordForTopup($transaction));
        $this->assertNull($service->recordForTopup($transaction));

        $this->assertSame(1, ReferralCommission::query()->count());
        $this->assertSame(0.7, $this->ledger()->balance($this->referrer, 'usd'));
        $this->assertSame(0.7, (float) Referral::query()->firstOrFail()->commission_earned);
    }

    /** شحنتان مختلفتان ⟵ عمولتان: القيد على الحركة لا على الدعوة */
    public function test_two_separate_topups_produce_two_commissions(): void
    {
        $this->ledger()->credit($this->referred, 'coins', 500, 'topup', null, 'training', 'شحنة أولى');
        $this->ledger()->credit($this->referred, 'coins', 500, 'topup', null, 'training', 'شحنة تانية');

        $this->assertSame(2, ReferralCommission::query()->count());
        $this->assertSame(1.4, $this->ledger()->balance($this->referrer, 'usd'));
    }

    /** الشحن عبر البوّابة يمرّ بنفس اللحظة — بلا لمس منطق 19.5 */
    public function test_a_gateway_topup_also_pays_the_commission(): void
    {
        $offer = TopupOffer::create([
            'method' => 'gateway', 'label_ar' => 'باقة المتعلّم',
            'pay_amount' => 500, 'credit_amount' => 500, 'bonus_percent' => 0, 'is_active' => true,
        ]);

        $invoice = GatewayInvoice::create([
            'user_id' => $this->referred->id,
            'topup_offer_id' => $offer->id,
            'provider' => 'fawaterk',
            'invoice_id' => 'FW-COMM-1',
            'amount' => 500,
            'currency' => 'EGP',
            'status' => 'unpaid',
            'payload' => ['credit_amount' => 500],
        ]);

        app(GatewayService::class)->creditOnce($invoice, 'card');

        $this->assertSame(1, ReferralCommission::query()->count());
        $this->assertSame(0.7, $this->ledger()->balance($this->referrer, 'usd'));

        // ⭐ إعادة إرسال الويب هوك: لا رصيد ثانٍ ولا عمولة ثانية
        app(GatewayService::class)->creditOnce($invoice->refresh(), 'card');

        $this->assertSame(1, ReferralCommission::query()->count());
        $this->assertSame(0.7, $this->ledger()->balance($this->referrer, 'usd'));
    }

    /** المعاملة العكسيّة لفاتورة مستردّة لا تولّد عمولة (19.4) */
    public function test_a_correction_transaction_never_pays_a_commission(): void
    {
        $original = $this->ledger()->credit($this->referred, 'coins', 500, 'topup', null, 'training', 'شحن الحساب');
        $before = ReferralCommission::query()->count();

        $this->ledger()->reverse($original, 'عكس فاتورة مستردّة');

        $this->assertSame($before, ReferralCommission::query()->count());
    }

    /** المستخدم بلا دعوة: لا عمولة ولا خطأ */
    public function test_a_topup_without_a_referral_records_nothing(): void
    {
        $lonely = $this->makeUser();
        $this->ledger()->credit($lonely, 'coins', 500, 'topup', null, 'training', 'شحن الحساب');

        $this->assertSame(0, ReferralCommission::query()->count());
    }

    /** المدخل القديم `recordCommission()` صار موصولًا بالطبقة الماليّة فعلًا */
    public function test_the_referral_service_entrypoint_is_wired_to_the_ledger(): void
    {
        $transaction = $this->ledger()->credit($this->referred, 'coins', 500, 'topup', null, 'training', 'شحن الحساب');

        // العمولة اتسجّلت لحظة الشحن، فالنداء المتأخّر يرجع صفرًا بلا أثر
        $this->assertSame(0.0, app(ReferralService::class)->recordCommission($this->referred, $transaction));
        $this->assertSame(1, ReferralCommission::query()->count());
    }

    /** النسبة إعداد لا رقم محروق (2.13) */
    public function test_the_commission_percent_comes_from_settings(): void
    {
        // نسبة الدعوة صفر ⟵ يرجع الحساب للإعداد العامّ لا لرقمٍ محروق
        Referral::query()->update(['commission_percent' => 0]);

        Setting::updateOrCreate(['key' => 'finance.referral.commission_percent'], [
            'group' => 'finance', 'label_ar' => 'عمولة الريفيرال (%)', 'type' => 'number',
            'value' => '10', 'default_value' => '7', 'is_owner_only' => true,
        ]);
        Cache::forget('settings');

        $this->ledger()->credit($this->referred, 'coins', 500, 'topup', null, 'training', 'شحن الحساب');

        $this->assertSame(1.0, $this->ledger()->balance($this->referrer, 'usd'));
    }
}
