<?php

namespace Tests\Feature\Wallet;

use App\Services\Wallet\LedgerService;

class LedgerServiceTest extends WalletTestCase
{
    private function ledger(): LedgerService
    {
        return app(LedgerService::class);
    }

    public function test_credit_writes_balance_and_transaction_with_balance_after(): void
    {
        $transaction = $this->ledger()->credit($this->user, 'coins', 550, 'topup', null, 'training', 'شحن الحساب');

        $this->assertSame(550.0, $this->ledger()->balance($this->user, 'coins'));
        $this->assertSame('550.00', (string) $transaction->balance_after);
        $this->assertSame('topup', $transaction->source);
        // مهلة الاعتراض تُضبَط من الإعداد لا من رقمٍ محروق
        $this->assertNotNull($transaction->objection_deadline_at);
        $this->assertSame(
            (int) setting('rep.objection.window_days'),
            (int) now()->startOfDay()->diffInDays($transaction->objection_deadline_at->startOfDay()),
        );
    }

    public function test_debit_reduces_balance(): void
    {
        $this->ledger()->credit($this->user, 'coins', 500, 'topup');
        $this->ledger()->debit($this->user, 'coins', 300, 'purchase', null, 'training', 'شراء تدريب');

        $this->assertSame(200.0, $this->ledger()->balance($this->user, 'coins'));
    }

    /** ⭐ (6) حدّ الخسارة اليوميّ لـRep: الزائد يُسجَّل كاملًا بوسمه */
    public function test_rep_daily_loss_cap_records_full_amount_with_flag(): void
    {
        $cap = rep_rule('limit.daily_loss'); // −2.00
        $this->assertLessThan(0, $cap);

        $transaction = $this->ledger()->debit($this->user, 'rep', 3, 'behavior', null, 'volunteer', 'تجاوز');

        // القيمة المطلوبة تُسجَّل كاملةً…
        $this->assertSame('-3.00', (string) $transaction->amount);
        // …والمطبَّق فعلًا هو حدّ اليوم لا أكثر
        $this->assertSame(number_format($cap, 2, '.', ''), (string) $transaction->applied_amount);
        $this->assertTrue($transaction->exceeded_daily_cap);
        $this->assertSame($cap, $this->ledger()->balance($this->user, 'rep'));
    }

    public function test_rep_daily_loss_cap_counts_earlier_losses_of_the_same_day(): void
    {
        $this->ledger()->debit($this->user, 'rep', 1.5, 'tasks', null, 'volunteer', 'تأخير');
        $second = $this->ledger()->debit($this->user, 'rep', 1.5, 'tasks', null, 'volunteer', 'تأخير آخر');

        $this->assertSame('-1.50', (string) $second->amount);
        $this->assertSame('-0.50', (string) $second->applied_amount);
        $this->assertTrue($second->exceeded_daily_cap);
        $this->assertSame(rep_rule('limit.daily_loss'), $this->ledger()->balance($this->user, 'rep'));
    }

    /** Rep مسقوف −10…+10 */
    public function test_rep_is_capped_at_currency_bounds(): void
    {
        $this->ledger()->credit($this->user, 'rep', 25, 'leadership', null, 'volunteer', 'تقدير');

        $this->assertSame(10.0, $this->ledger()->balance($this->user, 'rep'));
    }

    /** VXP تراكميّ لا يُخصَم آليًّا */
    public function test_cumulative_currency_is_not_debited_automatically(): void
    {
        $this->ledger()->credit($this->user, 'vxp', 100, 'task', null, 'volunteer', 'إنتاج');
        $transaction = $this->ledger()->debit($this->user, 'vxp', 40, 'task', null, 'volunteer', 'خصم آليّ');

        $this->assertSame('-40.00', (string) $transaction->amount);
        $this->assertSame('0.00', (string) $transaction->applied_amount);
        $this->assertSame(100.0, $this->ledger()->balance($this->user, 'vxp'));
    }

    public function test_reverse_creates_documented_correction(): void
    {
        $original = $this->ledger()->credit($this->user, 'coins', 200, 'topup');
        $correction = $this->ledger()->reverse($original, 'عكس فاتورة مستردّة');

        $this->assertTrue($correction->is_correction);
        $this->assertSame($original->id, $correction->corrects_transaction_id);
        $this->assertSame(0.0, $this->ledger()->balance($this->user, 'coins'));
    }
}
