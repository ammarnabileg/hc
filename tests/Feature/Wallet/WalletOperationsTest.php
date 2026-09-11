<?php

namespace Tests\Feature\Wallet;

use App\Models\Currency;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletExchange;
use App\Models\WalletTransfer;
use App\Models\WalletWithdrawal;
use App\Services\Wallet\ExchangeRates;
use App\Services\Wallet\ExchangeService;
use App\Services\Wallet\LedgerService;
use App\Services\Wallet\TransferService;
use App\Services\Wallet\WalletException;
use App\Services\Wallet\WithdrawService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;

/**
 * العمليّات المالِيّة الثلاث (19.3) + عزل أسعار الصرف (19.1).
 *
 * ما يثبته هذا الملفّ حرفيًّا:
 *  1) الرسوم تُحسَب في الخادم **ولا تُقبَل من الطلب** مهما زُوّرت.
 *  2) لا رصيد سالب في أيّ عمليّة.
 *  3) العمولة تُسجَّل **مرّة واحدة فقط** لكلّ حركة شحن.
 *  4) غير المالك يُمنَع من أسعار الصرف.
 */
class WalletOperationsTest extends WalletTestCase
{
    private function ledger(): LedgerService
    {
        return app(LedgerService::class);
    }

    /** المستخدم بصلاحيّات العمليّات — وصلاحيّات السحب والأرباح owner-only بحكم المصفوفة */
    private function operator(): User
    {
        $user = $this->makeUser();
        $this->grant($user, ['transfer.create', 'transfer.list']);

        return $user;
    }

    private function owner(): User
    {
        $this->seed(RoleSeeder::class);

        $user = $this->makeUser();
        $user->assignRole('platform_owner');

        return $user;
    }

    // ------------------------------------------------------------ 19.1 العملات

    /** ⭐ «الساعات» عملة منصوصة في 19.1 وتُعرَض في المحفظة */
    public function test_hours_currency_exists_and_shows_in_the_wallet(): void
    {
        $this->assertNotNull(Currency::query()->where('code', 'hours')->first());

        $this->actingAs($this->user)->get(route('wallet.index'))
            ->assertOk()
            ->assertSee('الساعات', false);
    }

    public function test_exchange_rates_follow_the_constitution_defaults(): void
    {
        $rates = app(ExchangeRates::class);

        $this->assertSame(50.0, $rates->usdToCoins());
        $this->assertSame(10.0, $rates->ticketToCoins());
        $this->assertSame(300.0, $rates->ticketToXp());
        // 1$ = 50 كوين = 5 تذاكر
        $this->assertSame(5.0, round($rates->rate('usd', 'tickets'), 4));
    }

    // ---------------------------------------------------------- 19.2 المسحوبات

    public function test_withdrawals_tab_opens_for_the_platform_owner(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('wallet.withdrawals'))
            ->assertOk()
            ->assertSee('متاح للسحب', false)
            ->assertSee('جدول المسحوبات', false);
    }

    /**
     * ⭐ 2.13: وسم حالة طلب السحب على شاشة صاحب المحفظة يُقرَأ من الإعداد لا من
     * نصٍّ محروق في `WalletWithdrawal::statusLabel()`. نغيّر المفاتيح الأربعة
     * إلى قيمٍ شاهدة، فلو عاد أيّ نصٍّ للكود سقط الاختبار في الحال. والقالبان
     * (سطح المكتب والموبايل) يُصدَران معًا في الصفحة نفسها، فالمرّتان لكلٍّ منهما.
     */
    public function test_withdrawal_status_label_comes_from_settings_on_both_row_partials(): void
    {
        $owner = $this->owner();

        $labels = [
            WalletWithdrawal::PENDING => ['finance.withdraw.status_pending', 'تحت الفحص (شاهد)'],
            WalletWithdrawal::PROCESSING => ['finance.withdraw.status_processing', 'في الطريق (شاهد)'],
            WalletWithdrawal::PAID => ['finance.withdraw.status_paid', 'وصلت (شاهد)'],
            WalletWithdrawal::REJECTED => ['finance.withdraw.status_rejected', 'مردودة (شاهد)'],
        ];

        foreach ($labels as $status => [$key, $value]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => 'wallet',
                'label_ar' => 'وسم حالة سحب',
                'type' => 'string',
                'default_value' => $value,
                'value' => $value,
            ]);

            WalletWithdrawal::create([
                'number' => 'WD-'.$status,
                'user_id' => $owner->id,
                'amount' => 100,
                'fee_percent' => 1,
                'fee_amount' => 1,
                'net_amount' => 99,
                'method' => 'wallet',
                'account_number' => '01000000000',
                'status' => $status,
            ]);
        }

        Cache::forget('settings');

        $response = $this->actingAs($owner)->get(route('wallet.withdrawals'))->assertOk();

        foreach ($labels as [, $value]) {
            // مرّة في صفّ سطح المكتب ومرّة في كارت الموبايل — القالبان كلاهما مشمول
            $response->assertSeeText($value, false);
            $this->assertSame(2, substr_count($response->getContent(), $value),
                "وسم «{$value}» لازم يظهر في القالبين معًا (سطح المكتب والموبايل).");
        }

        // ولا يبقى النصّ القديم المحروق في الصفحة بعد تغيير الإعداد
        $this->assertStringNotContainsString('مرفوضة', $response->getContent());
    }

    /** المحظور يُخفى ولا يُعطَّل — والتاب أصلًا لا يظهر لغير المالك (2.15-أ-7) */
    public function test_withdrawals_tab_is_hidden_from_a_regular_user(): void
    {
        $this->actingAs($this->user)->get(route('wallet.withdrawals'))->assertForbidden();

        $this->actingAs($this->user)->get(route('wallet.index'))
            ->assertOk()
            ->assertDontSee('جدول المسحوبات', false);
    }

    /** كروت الأرباح الأربعة وأزرار العمليّات تظهر لمن يملكها */
    public function test_earnings_cards_and_actions_show_for_the_owner(): void
    {
        $owner = $this->owner();
        $this->ledger()->credit($owner, 'usd', 20, 'referral', null, 'training', 'عمولة دعوة');

        $this->actingAs($owner)->get(route('wallet.index'))
            ->assertOk()
            ->assertSee('جاهزة للسحب', false)
            ->assertSee('قيد التحويل', false)
            ->assertSee('مستلمة', false)
            ->assertSee('إجماليّة', false)
            ->assertSee('إرسال حوالة', false)
            ->assertSee('تحويل العملة', false)
            ->assertSee('سحب الأرباح', false);
    }

    // ------------------------------------------------------- 19.3 إرسال حوالة

    /** ⭐ الرسوم من الخادم: 15% كوينز · 85% XP · 0% تذاكر */
    public function test_transfer_fees_match_the_constitution_per_currency(): void
    {
        $transfers = app(TransferService::class);

        $this->assertSame(85.0, $transfers->quote('coins', 100)['net']);
        $this->assertSame(15.0, $transfers->quote('xp', 100)['net']);
        $this->assertSame(100.0, $transfers->quote('tickets', 100)['net']);
    }

    public function test_transfer_moves_the_net_amount_and_keeps_the_fee(): void
    {
        $sender = $this->operator();
        $recipient = $this->makeUser();

        $this->ledger()->credit($sender, 'coins', 1000, 'topup', null, 'training', 'شحن');

        $this->actingAs($sender)->post(route('wallet.transfer'), [
            'code' => $recipient->code,
            'currency' => 'coins',
            'amount' => 100,
        ])->assertRedirect();

        $transfer = WalletTransfer::query()->firstOrFail();

        $this->assertSame(15.0, (float) $transfer->fee_amount);
        $this->assertSame(85.0, (float) $transfer->net_amount);
        $this->assertSame(900.0, $this->ledger()->balance($sender, 'coins'));
        $this->assertSame(85.0, $this->ledger()->balance($recipient, 'coins'));
    }

    /** ⭐ الرسوم لا تُقبَل من الطلب: الحقول المزوَّرة تُهمَل تمامًا */
    public function test_forged_fee_fields_in_the_request_are_ignored(): void
    {
        $sender = $this->operator();
        $recipient = $this->makeUser();

        $this->ledger()->credit($sender, 'coins', 1000, 'topup', null, 'training', 'شحن');

        $this->actingAs($sender)->post(route('wallet.transfer'), [
            'code' => $recipient->code,
            'currency' => 'coins',
            'amount' => 100,
            // محاولة تزوير: رسوم صفر وصافي كامل
            'fee_percent' => 0,
            'fee_amount' => 0,
            'net_amount' => 100,
        ])->assertRedirect();

        $transfer = WalletTransfer::query()->firstOrFail();

        $this->assertSame(15.0, (float) $transfer->fee_percent);
        $this->assertSame(85.0, (float) $transfer->net_amount);
        $this->assertSame(85.0, $this->ledger()->balance($recipient, 'coins'));
    }

    /** ⭐ لا رصيد سالب: الحوالة فوق الرصيد تُرَدّ ولا تُنفَّذ نصفها */
    public function test_transfer_above_balance_is_refused_and_leaves_no_negative(): void
    {
        $sender = $this->operator();
        $recipient = $this->makeUser();

        $this->ledger()->credit($sender, 'coins', 50, 'topup', null, 'training', 'شحن');

        $this->actingAs($sender)->post(route('wallet.transfer'), [
            'code' => $recipient->code,
            'currency' => 'coins',
            'amount' => 500,
        ])->assertSessionHasErrors('wallet');

        $this->assertSame(50.0, $this->ledger()->balance($sender, 'coins'));
        $this->assertSame(0.0, $this->ledger()->balance($recipient, 'coins'));
        $this->assertSame(0, WalletTransfer::query()->count());
    }

    public function test_transfer_to_self_is_refused(): void
    {
        $sender = $this->operator();
        $this->ledger()->credit($sender, 'coins', 500, 'topup', null, 'training', 'شحن');

        $this->actingAs($sender)->post(route('wallet.transfer'), [
            'code' => $sender->code,
            'currency' => 'coins',
            'amount' => 100,
        ])->assertSessionHasErrors('wallet');

        $this->assertSame(0, WalletTransfer::query()->count());
    }

    /** الملخّص اللحظيّ يُحسَب في الخادم ويُعاد كما هو */
    public function test_transfer_quote_is_computed_on_the_server(): void
    {
        $sender = $this->operator();
        $recipient = $this->makeUser();

        $this->actingAs($sender)->postJson(route('wallet.transfer.quote'), [
            'currency' => 'coins',
            'amount' => 200,
            'code' => $recipient->code,
        ])->assertOk()->assertJson([
            'amount' => 200.0,
            'fee_percent' => 15.0,
            'fee' => 30.0,
            'net' => 170.0,
        ]);
    }

    // ------------------------------------------------------ 19.3 تحويل العملة

    /** ⭐ رسوم 5% ثابتة لكلّ المسارات */
    public function test_exchange_fee_is_five_percent_on_every_path(): void
    {
        $exchanges = app(ExchangeService::class);

        foreach ($exchanges->paths() as $path) {
            $this->assertSame(5.0, $exchanges->quote($path['from'], $path['to'], 100)['fee_percent']);
        }
    }

    public function test_exchange_converts_coins_to_tickets_at_the_platform_rate(): void
    {
        $user = $this->operator();
        $this->ledger()->credit($user, 'coins', 1000, 'topup', null, 'training', 'شحن');

        $this->actingAs($user)->post(route('wallet.exchange'), [
            'from' => 'coins',
            'to' => 'tickets',
            'amount' => 100,
        ])->assertRedirect();

        $exchange = WalletExchange::query()->firstOrFail();

        // 100 كوين − 5% = 95 كوين ⟵ 9.5 تذكرة ⟵ 9 بعد التقريب لأسفل
        $this->assertSame(5.0, (float) $exchange->fee_amount);
        $this->assertSame(9.0, (float) $exchange->credited_amount);
        $this->assertSame(900.0, $this->ledger()->balance($user, 'coins'));
        $this->assertSame(9.0, $this->ledger()->balance($user, 'tickets'));
    }

    public function test_exchange_above_balance_is_refused(): void
    {
        $user = $this->operator();
        $this->ledger()->credit($user, 'coins', 40, 'topup', null, 'training', 'شحن');

        $this->actingAs($user)->post(route('wallet.exchange'), [
            'from' => 'coins',
            'to' => 'tickets',
            'amount' => 400,
        ])->assertSessionHasErrors('wallet');

        $this->assertSame(40.0, $this->ledger()->balance($user, 'coins'));
        $this->assertSame(0.0, $this->ledger()->balance($user, 'tickets'));
    }

    /** المسار غير المنصوص (تذاكر ← كوينز) مرفوض من الخادم */
    public function test_unlisted_exchange_path_is_refused(): void
    {
        $user = $this->operator();
        $this->ledger()->credit($user, 'tickets', 50, 'academy', null, 'training', 'تذاكر');

        $this->actingAs($user)->post(route('wallet.exchange'), [
            'from' => 'tickets',
            'to' => 'coins',
            'amount' => 10,
        ])->assertSessionHasErrors('to');
    }

    // ------------------------------------------------------- 19.3 سحب الأرباح

    /** ⭐ رسوم 1% بحدّ أدنى $0.50 */
    public function test_withdraw_fee_is_one_percent_with_a_half_dollar_floor(): void
    {
        $withdrawals = app(WithdrawService::class);

        // 1% من 20 = 0.20 وهي أقلّ من الحدّ الأدنى ⟵ الرسوم 0.50
        $this->assertSame(0.5, $withdrawals->quote(20)['fee']);
        // 1% من 200 = 2.00 وهي أكبر من الحدّ ⟵ الرسوم 2.00
        $this->assertSame(2.0, $withdrawals->quote(200)['fee']);
        $this->assertSame(198.0, $withdrawals->quote(200)['net']);
    }

    public function test_withdraw_request_debits_the_earnings_immediately(): void
    {
        $owner = $this->owner();
        $this->ledger()->credit($owner, 'usd', 100, 'referral', null, 'training', 'عمولة دعوة');

        $this->actingAs($owner)->post(route('wallet.withdraw'), [
            'amount' => 100,
            'method' => 'instapay',
            'account_number' => 'user@instapay',
        ])->assertRedirect(route('wallet.withdrawals'));

        $withdrawal = WalletWithdrawal::query()->firstOrFail();

        $this->assertSame(1.0, (float) $withdrawal->fee_amount);
        $this->assertSame(99.0, (float) $withdrawal->net_amount);
        // «جاهزة للسحب» تنقص فورًا فلا يُسحَب نفس الدولار مرّتين
        $this->assertSame(0.0, $this->ledger()->balance($owner, 'usd'));

        $earnings = app(WithdrawService::class)->earnings($owner);
        $this->assertSame(0.0, $earnings['ready']);
        $this->assertSame(100.0, $earnings['in_transit']);
    }

    /** ⭐ لا رصيد سالب في السحب كذلك */
    public function test_withdraw_above_earnings_is_refused(): void
    {
        $owner = $this->owner();
        $this->ledger()->credit($owner, 'usd', 10, 'referral', null, 'training', 'عمولة دعوة');

        $this->actingAs($owner)->post(route('wallet.withdraw'), [
            'amount' => 90,
            'method' => 'bank',
            'account_number' => '123456789',
        ])->assertSessionHasErrors('wallet');

        $this->assertSame(10.0, $this->ledger()->balance($owner, 'usd'));
        $this->assertSame(0, WalletWithdrawal::query()->count());
    }

    public function test_withdraw_is_forbidden_for_a_regular_user(): void
    {
        $this->actingAs($this->operator())->post(route('wallet.withdraw'), [
            'amount' => 10,
            'method' => 'bank',
            'account_number' => '123456789',
        ])->assertForbidden();
    }

    /** ⭐ لا رصيد سالب حتى عند نداء الخدمة مباشرةً بلا واجهة */
    public function test_the_ledger_never_produces_a_negative_balance(): void
    {
        $user = $this->makeUser();
        $this->ledger()->credit($user, 'coins', 10, 'topup', null, 'training', 'شحن');

        $this->expectException(WalletException::class);
        $this->ledger()->debitOrFail($user, 'coins', 25, 'purchase', null, 'training', 'شراء');
    }

    public function test_a_refused_debit_leaves_the_balance_untouched(): void
    {
        $user = $this->makeUser();
        $this->ledger()->credit($user, 'coins', 10, 'topup', null, 'training', 'شحن');

        try {
            $this->ledger()->debitOrFail($user, 'coins', 25, 'purchase', null, 'training', 'شراء');
        } catch (WalletException) {
            // متوقَّع
        }

        $this->assertSame(10.0, $this->ledger()->balance($user, 'coins'));
        $this->assertSame(1, Transaction::query()->where('user_id', $user->id)->count());
    }

    // ------------------------------------------------- 19.1 عزل أسعار الصرف 🔒

    public function test_platform_owner_reaches_the_exchange_rates_screen(): void
    {
        $this->actingAs($this->owner())->get(route('admin.wallet.rates'))
            ->assertOk()
            ->assertSee('أسعار الصرف', false)
            ->assertSee('1$ = 50 كوين', false);
    }

    /** ⭐ غير المالك يُمنَع من أسعار الصرف — ولو أُسنِدت له الصلاحيّة بالخطأ */
    public function test_a_non_owner_is_blocked_from_exchange_rates(): void
    {
        $admin = $this->makeUser();
        $this->grant($admin, ['exchange_rates.view', 'exchange_rates.edit'], 'ALL');

        $this->actingAs($admin)->get(route('admin.wallet.rates'))->assertForbidden();

        $this->actingAs($admin)->post(route('admin.wallet.rates.save'), [
            'key' => 'finance.rates.usd_to_coins',
            'value' => '1',
            'reason' => 'محاولة تعديل غير مصرَّح بها',
        ])->assertForbidden();
    }

    public function test_owner_can_edit_a_rate_with_a_mandatory_reason(): void
    {
        $owner = $this->owner();

        // الشاشة تُنشئ تعريف الإعداد عند أوّل فتح، فلا تظهر فارغة في أيّ بيئة
        $this->actingAs($owner)->get(route('admin.wallet.rates'))->assertOk();

        $this->actingAs($owner)->post(route('admin.wallet.rates.save'), [
            'key' => 'finance.rates.usd_to_coins',
            'value' => '40',
        ])->assertSessionHasErrors('reason');

        $this->actingAs($owner)->post(route('admin.wallet.rates.save'), [
            'key' => 'finance.rates.usd_to_coins',
            'value' => '40',
            'reason' => 'تعديل سعر الدولار بعد مراجعة السوق',
        ])->assertSessionHas('status');

        $this->assertSame('40', Setting::query()->where('key', 'finance.rates.usd_to_coins')->value('value'));
    }

    public function test_a_zero_exchange_rate_is_refused(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->get(route('admin.wallet.rates'))->assertOk();

        $this->actingAs($owner)->post(route('admin.wallet.rates.save'), [
            'key' => 'finance.rates.usd_to_coins',
            'value' => '0',
            'reason' => 'محاولة وضع صفر',
        ])->assertSessionHasErrors('rates');
    }
}
