<?php

namespace Tests\Feature\Wallet;

use App\Models\GatewayInvoice;
use App\Models\Role;
use App\Models\TopupOffer;
use App\Models\Transaction;
use App\Models\TransferMethod;
use App\Models\User;
use App\Services\Wallet\FawaterkClient;
use App\Services\Wallet\LedgerService;
use App\Support\Access\AccessEngine;

class WalletScreensTest extends WalletTestCase
{
    public function test_wallet_index_shows_balance_and_last_five_moves(): void
    {
        $ledger = app(LedgerService::class);
        $ledger->credit($this->user, 'coins', 550, 'topup', null, 'training', 'شحن الحساب');
        $ledger->debit($this->user, 'coins', 50, 'purchase', null, 'training', 'شراء منتج');

        $this->actingAs($this->user)->get(route('wallet.index'))
            ->assertOk()
            ->assertSee('رصيدي وشحن', false)
            ->assertSee('500', false)
            ->assertSee('آخر الحركات', false);
    }

    public function test_tickets_screen_shows_balance_earn_and_spend(): void
    {
        app(LedgerService::class)->credit($this->user, 'tickets', 4, 'academy', null, 'training', 'ستريك 7 أيّام');

        $this->actingAs($this->user)->get(route('wallet.tickets'))
            ->assertOk()
            ->assertSee('إزّاي تكسب تذاكر', false)
            ->assertSee('تصرفها فين', false);
    }

    /** ⭐ جدول المعاملات مفلتر على جانب التدريب فقط */
    public function test_transactions_screen_shows_training_layer_only(): void
    {
        $ledger = app(LedgerService::class);
        $ledger->credit($this->user, 'coins', 100, 'topup', null, 'training', 'حركة تدريب');
        $ledger->credit($this->user, 'vxp', 30, 'task', null, 'volunteer', 'حركة تطوّع');

        $this->actingAs($this->user)->get(route('wallet.transactions', ['all_time' => 1]))
            ->assertOk()
            ->assertSee('حركة تدريب', false)
            ->assertDontSee('حركة تطوّع', false);
    }

    /**
     * تصدير سجلّ المعاملات لمالك المنصّة (`wallet.export` — 12.2.2 «مالك المنصّة
     * فقط»). أمّا تصدير المستخدم لبياناته هو فله مورده الخاصّ `data_export`،
     * فالتمييز مقصود: قراءة محفظتك حقُّك، وتصديرُ دفتر الأستاذ سلطةٌ.
     */
    public function test_transactions_can_be_exported_as_csv(): void
    {
        $owner = User::create([
            'name' => 'مالك المنصّة',
            'email' => 'owner-export@test.local',
            'password' => 'secret-password',
            'code' => 'OWNEXP1',
            'status' => 'active',
        ]);
        $owner->assignRole(Role::query()->firstOrCreate(
            ['key' => config('access.owner_role')],
            ['name_ar' => 'مالك المنصّة', 'layer' => 'platform'],
        ));
        app(AccessEngine::class)->forget();

        app(LedgerService::class)->credit($owner, 'coins', 100, 'topup', null, 'training', 'شحن الحساب');

        $response = $this->actingAs($owner)->get(route('wallet.transactions.export', ['all_time' => 1]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('شحن الحساب', $response->streamedContent());
    }

    public function test_wallet_screens_are_hidden_without_permission(): void
    {
        $stranger = User::create([
            'name' => 'زائر', 'email' => 'guest@test.local', 'password' => 'secret-password',
            'code' => 'UGUEST01', 'status' => 'active',
        ]);

        $this->actingAs($stranger)->get(route('wallet.index'))->assertForbidden();
    }

    public function test_a_user_never_sees_another_users_transactions(): void
    {
        $other = $this->makeUser();
        app(LedgerService::class)->credit($other, 'coins', 700, 'topup', null, 'training', 'سرّ الجيران');

        $this->actingAs($this->user)->get(route('wallet.transactions', ['all_time' => 1]))
            ->assertOk()
            ->assertDontSee('سرّ الجيران', false);
    }

    public function test_topup_page_opens_on_the_tab_from_settings(): void
    {
        TransferMethod::create(['type' => 'bank', 'name_ar' => 'البنك الأهليّ', 'account_number' => '123456', 'is_active' => true]);

        $this->setSetting('topup.default_tab', 'manual');

        $this->actingAs($this->user)->get(route('wallet.topup'))
            ->assertOk()
            ->assertSee('طرق التحويل', false)
            ->assertSee('البنك الأهليّ', false);

        $this->setSetting('topup.default_tab', 'gateway');

        $this->actingAs($this->user)->get(route('wallet.topup'))
            ->assertOk()
            ->assertDontSee('طرق التحويل', false);
    }

    /** ⭐ العرض بقيمته الحقيقيّة صراحةً — بلا Dark Patterns */
    public function test_offer_states_its_real_value_explicitly(): void
    {
        TopupOffer::create([
            'method' => 'manual', 'label_ar' => 'باقة المتعلّم',
            'pay_amount' => 500, 'credit_amount' => 550, 'bonus_percent' => 10, 'is_active' => true,
        ]);

        $this->actingAs($this->user)->get(route('wallet.topup', ['tab' => 'manual']))
            ->assertOk()
            ->assertSee('ادفع 500', false)
            ->assertSee('550 كوينز', false)
            ->assertSee('+10%', false);
    }

    /** ⛔ لا تُعلَن أيّ مهلة مراجعة للمُرسِل إطلاقًا (19.5-أ) */
    public function test_no_review_deadline_is_promised_to_the_sender(): void
    {
        $response = $this->actingAs($this->user)->get(route('wallet.topup', ['tab' => 'manual']));

        foreach (['خلال 24', 'خلال 48', 'مهلة المراجعة', 'مدّة المراجعة', 'خلال ساعة'] as $forbidden) {
            $response->assertDontSee($forbidden, false);
        }
    }

    /** بدء الدفع لا يزيد رصيدًا — الرصيد من الويب هوك حصرًا */
    public function test_starting_a_gateway_payment_creates_an_invoice_without_credit(): void
    {
        $offer = TopupOffer::create([
            'method' => 'gateway', 'label_ar' => 'باقة المتعلّم',
            'pay_amount' => 500, 'credit_amount' => 540, 'bonus_percent' => 8, 'is_active' => true,
        ]);

        // الشبكة محجوبة: نبدّل العميل بمزدوجٍ في الحاويّة فلا نداء حقيقيّ
        $this->app->instance(FawaterkClient::class, new class extends FawaterkClient
        {
            public function createInvoiceLink(User $user, GatewayInvoice $invoice, string $itemName, array $redirectionUrls): array
            {
                return [
                    'invoice_id' => 'FW-TEST-1',
                    'invoice_key' => 'KEY-TEST-1',
                    'payment_url' => 'https://staging.fawaterk.com/pay/FW-TEST-1',
                    'raw' => ['redirectionUrls' => $redirectionUrls, 'payLoad' => ['gateway_invoice_id' => $invoice->id, 'user_id' => $user->id]],
                ];
            }
        });

        $this->actingAs($this->user)
            ->post(route('wallet.topup.gateway'), ['topup_offer_id' => $offer->id])
            ->assertRedirect('https://staging.fawaterk.com/pay/FW-TEST-1');

        $invoice = GatewayInvoice::query()->firstOrFail();

        $this->assertSame('FW-TEST-1', $invoice->invoice_id);
        $this->assertFalse($invoice->credited);
        $this->assertSame(540.0, (float) data_get($invoice->payload, 'credit_amount'));
        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(0.0, app(LedgerService::class)->balance($this->user, 'coins'));
    }

    public function test_gateway_base_url_follows_the_sandbox_setting(): void
    {
        $client = app(FawaterkClient::class);

        $this->setSetting('topup.gateway.sandbox', '1');
        $this->assertSame('https://staging.fawaterk.com/api/v2/', $client->baseUrl());

        $this->setSetting('topup.gateway.sandbox', '0');
        $this->assertSame('https://app.fawaterk.com/api/v2/', $client->baseUrl());
    }
}
