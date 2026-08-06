<?php

namespace Tests\Feature\Wallet;

use App\Models\User;
use App\Models\WalletWithdrawal;
use App\Services\Wallet\LedgerService;
use Illuminate\Support\Str;

/**
 * ⭐ تمرير تدريجيّ بدل ترقيم الصفحات (13.1 · قرار §25 دستوريّ صريح — «مرفوض ⛔:
 * ترقيم الصفحات بدل التمرير اللانهائيّ»). لا `?page=` هنا إطلاقًا — الجلبة
 * التالية بـ`offset` وردّها Fragment وحده (صفوف/كروت لا صفحة كاملة).
 */
class WalletLoadMoreTest extends WalletTestCase
{
    public function test_transactions_screen_shows_first_batch_then_loads_the_rest_by_offset(): void
    {
        $this->setSetting('wallet.transactions.per_page', '2');

        $ledger = app(LedgerService::class);
        $ledger->credit($this->user, 'coins', 10, 'topup', null, 'training', 'أوّل حركة');
        $ledger->credit($this->user, 'coins', 20, 'topup', null, 'training', 'ثاني حركة');
        $ledger->credit($this->user, 'coins', 30, 'topup', null, 'training', 'ثالث حركة');

        // أوّل تحميل: أحدث حركتين فقط (الأحدث بمعرّفٍ أعلى) — وبلا ?page=
        $first = $this->actingAs($this->user)->get(route('wallet.transactions', ['all_time' => 1]))
            ->assertOk()
            ->assertSee('ثالث حركة')
            ->assertSee('ثاني حركة')
            ->assertDontSee('أوّل حركة');

        // ⭐ HTML يهرِّب الـ`&` إلى `&amp;` — فاحتراس التطابق يبقى Escaped (لا Raw)
        $first->assertSee(route('wallet.transactions.more', ['all_time' => 1, 'offset' => 2]));
        $first->assertDontSee('?page=', false);

        // الجلبة التالية Fragment: الحركة المتبقّية فقط
        $this->actingAs($this->user)
            ->get(route('wallet.transactions.more', ['all_time' => 1, 'offset' => 2]))
            ->assertOk()
            ->assertSee('أوّل حركة')
            ->assertDontSee('ثاني حركة');

        // بعد النهاية: Fragment فارغ
        $this->actingAs($this->user)
            ->get(route('wallet.transactions.more', ['all_time' => 1, 'offset' => 4]))
            ->assertOk()
            ->assertDontSee('أوّل حركة')
            ->assertDontSee('ثاني حركة')
            ->assertDontSee('ثالث حركة');
    }

    public function test_transactions_load_more_carries_active_filters_forward(): void
    {
        $this->setSetting('wallet.transactions.per_page', '20');

        $ledger = app(LedgerService::class);
        $ledger->credit($this->user, 'coins', 10, 'topup', null, 'training', 'شحن يظهر');
        $ledger->credit($this->user, 'coins', 5, 'purchase', null, 'training', 'شراء ما يظهرش');

        // فلتر «النوع» لازم يفضل شغّال على أوّل صفحة **وعلى الجلبة التالية** كذلك
        $this->actingAs($this->user)
            ->get(route('wallet.transactions', ['source' => 'topup', 'all_time' => 1]))
            ->assertOk()
            ->assertSee('شحن يظهر')
            ->assertDontSee('شراء ما يظهرش');

        $this->actingAs($this->user)
            ->get(route('wallet.transactions.more', ['source' => 'topup', 'all_time' => 1, 'offset' => 0]))
            ->assertOk()
            ->assertSee('شحن يظهر')
            ->assertDontSee('شراء ما يظهرش');
    }

    public function test_withdrawals_screen_shows_first_batch_then_loads_the_rest_by_offset(): void
    {
        $this->setSetting('wallet.transactions.per_page', '2');
        $this->grant($this->user, ['withdraw.list']);

        $this->withdrawal($this->user, 'أوّل', now()->subMinutes(3));
        $this->withdrawal($this->user, 'ثاني', now()->subMinutes(2));
        $this->withdrawal($this->user, 'ثالث', now()->subMinutes(1));

        $first = $this->actingAs($this->user)->get(route('wallet.withdrawals'))
            ->assertOk()
            ->assertSee('WD-ثالث', false)
            ->assertSee('WD-ثاني', false)
            ->assertDontSee('WD-أوّل', false);

        $first->assertSee(route('wallet.withdrawals.more', ['offset' => 2]), false);
        $first->assertDontSee('?page=', false);

        $this->actingAs($this->user)
            ->get(route('wallet.withdrawals.more', ['offset' => 2]))
            ->assertOk()
            ->assertSee('WD-أوّل', false)
            ->assertDontSee('WD-ثاني', false);

        $this->actingAs($this->user)
            ->get(route('wallet.withdrawals.more', ['offset' => 4]))
            ->assertOk()
            ->assertDontSee('WD-أوّل', false)
            ->assertDontSee('WD-ثاني', false)
            ->assertDontSee('WD-ثالث', false);
    }

    private function withdrawal(User $user, string $label, $at): WalletWithdrawal
    {
        $withdrawal = WalletWithdrawal::create([
            'number' => 'WD-'.$label.'-'.Str::random(4),
            'user_id' => $user->id,
            'amount' => 100,
            'fee_percent' => 5,
            'fee_amount' => 5,
            'net_amount' => 95,
            'method' => 'wallet',
            'account_number' => '01000000000',
            'status' => WalletWithdrawal::PENDING,
        ]);

        $withdrawal->forceFill(['created_at' => $at])->saveQuietly();

        return $withdrawal->refresh();
    }
}
