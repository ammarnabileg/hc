<?php

namespace Tests\Feature\Wallet;

use App\Models\Currency;
use App\Models\Transaction;
use App\Services\Wallet\LedgerService;
use App\Services\Wallet\WalletException;

/**
 * قاعُ العملة القابلة للصرف — **يُرَدّ ولا يُقَصّ** (15.2-4 · 19.3).
 *
 * النصّ الحاكم — **15.2-4:** «**بوابة ≥ 12 تذكرة** للطرفين، **والتذاكر لا تنزل
 * تحت الصفر**». وكان `debit()` ينزل بها تحت الصفر: رصيد 3 · خصم 12 ⟵ **−9**.
 */
class SpendableFloorTest extends WalletTestCase
{
    private function ledger(): LedgerService
    {
        return app(LedgerService::class);
    }

    /** القاع **بيانٌ** في العملة لا رقمٌ محروق في الخدمة (2.13) */
    public function test_spendable_currencies_carry_a_zero_floor_in_data(): void
    {
        foreach (['coins', 'tickets', 'usd'] as $code) {
            $currency = Currency::query()->where('code', $code)->firstOrFail();

            $this->assertTrue((bool) $currency->is_spendable, "{$code} يجب أن تكون قابلة للصرف");
            $this->assertNotNull($currency->min_value, "{$code} بلا قاع — والقاع بيانٌ لا كود");
            $this->assertSame(0.0, (float) $currency->min_value);
        }
    }

    /** ⭐ العطب الأصليّ: رصيد 3 · خصم 12 ⟵ كان **−9**، والآن **يُرَدّ** */
    public function test_a_debit_beyond_the_balance_is_refused_not_taken_below_zero(): void
    {
        $this->ledger()->credit($this->user, 'tickets', 3, 'grant');

        try {
            $this->ledger()->debit($this->user, 'tickets', 12, 'challenge', null, 'training', 'خسارة مواجهة');
            $this->fail('الخصم الذي يتجاوز الرصيد كان يجب أن يُرَدّ');
        } catch (WalletException $e) {
            $this->assertStringContainsString('مش مكفّي', $e->getMessage());
        }

        // الرصيد كما هو، **ولا سطر** في الدفتر — لا نصف خصمٍ ولا خصمٌ بلا رصيد
        $this->assertSame(3.0, $this->ledger()->balance($this->user, 'tickets'));
        $this->assertSame(1, Transaction::query()->where('user_id', $this->user->id)->count());
    }

    /** الكوينز كذلك: لا شراء بنصف ثمنه ولا رصيدٌ مدين (19.3) */
    public function test_coins_are_refused_below_zero_too(): void
    {
        $this->ledger()->credit($this->user, 'coins', 100, 'topup');

        $this->expectException(WalletException::class);
        $this->ledger()->debit($this->user, 'coins', 250, 'purchase', null, 'training', 'شراء');
    }

    /** ⭐ الدفتر والرصيد **لا يفترقان**: لا سطرٌ بقيمةٍ لم تنزل على الرصيد */
    public function test_the_ledger_never_records_more_than_the_balance_actually_moved(): void
    {
        $this->ledger()->credit($this->user, 'tickets', 10, 'grant');
        $this->ledger()->debit($this->user, 'tickets', 4, 'challenge');

        $rows = Transaction::query()
            ->where('user_id', $this->user->id)
            ->whereHas('currency', fn ($q) => $q->where('code', 'tickets'))
            ->orderBy('id')
            ->get();

        $moved = 0.0;

        foreach ($rows as $row) {
            $applied = (float) ($row->applied_amount ?? $row->amount);
            // ما سُجِّل هو ما طُبِّق بالضبط في العملة القابلة للصرف
            $this->assertSame((float) $row->amount, $applied);
            $moved += $applied;
        }

        $this->assertSame($moved, $this->ledger()->balance($this->user, 'tickets'));
        $this->assertSame(6.0, $moved);
    }

    /** والدرجات تبقى **مقصوصةً بسقفها** كما نصّ 13.4-ن — القاع للمال لا للدرجة */
    public function test_rep_still_clamps_at_its_bound_instead_of_being_refused(): void
    {
        $transaction = $this->ledger()->debit($this->user, 'rep', 3, 'behavior', null, 'volunteer', 'تجاوز');

        $this->assertNotNull($transaction);
        $this->assertLessThan(0, (float) $this->ledger()->balance($this->user, 'rep'));
    }

    /** ولا يُقفَل باب **التصحيح الموثّق** (19.4): المعاملة العكسيّة تمرّ دائمًا */
    public function test_a_documented_reversal_is_never_blocked_by_the_floor(): void
    {
        $topup = $this->ledger()->credit($this->user, 'coins', 100, 'topup', null, 'training', 'شحن');
        $this->ledger()->debit($this->user, 'coins', 90, 'purchase', null, 'training', 'شراء');

        // الاسترجاع من البوّابة يُقيَّد كاملًا ولو ترك الرصيد مدينًا — وإلّا بقي
        // في المحفظة رصيدٌ اعترف الدفتر بأنّه رُدّ
        $reversal = $this->ledger()->reverse($topup, 'استرجاع من البوّابة');

        $this->assertTrue($reversal->is_correction);
        $this->assertSame(-90.0, $this->ledger()->balance($this->user, 'coins'));
    }

    /**
     * ⭐ **ولا يُقفَل باب 12.9:** «**الخصم (ماينص) يقدر ينزل تحت الصفر** (مسموح
     * عادي)» — فخصمُ الأدمن **الموقَّع بإنسانٍ غير صاحب الرصيد** يمضي كاملًا،
     * والقاع يسري على **الخصم الآليّ** وحده (بلا توقيع).
     */
    public function test_a_human_signed_admin_debit_may_go_below_zero(): void
    {
        $admin = $this->makeUser(['name' => 'أدمن']);

        $transaction = $this->ledger()->debit(
            $this->user, 'coins', 250, 'admin', null, 'training', 'خصم يدويّ', $admin->id,
        );

        $this->assertSame(-250.0, $this->ledger()->balance($this->user, 'coins'));
        $this->assertSame('-250.00', (string) $transaction->amount);
        // ولا يفترق الدفتر عن الرصيد: المسجَّل هو المطبَّق بعينه
        $this->assertSame('-250.00', (string) $transaction->applied_amount);
    }

    /** أمّا الخصم **الآليّ** (بلا توقيع) فيقف عند القاع ولو حمل نفس المصدر */
    public function test_the_same_debit_without_a_human_signature_is_refused(): void
    {
        $this->expectException(WalletException::class);
        $this->ledger()->debit($this->user, 'coins', 250, 'admin', null, 'training', 'خصم آليّ');
    }

    /** و`debitOrFail` يرمي **قبل** أن يكتب شيئًا — لا سطرٌ يتيم */
    public function test_debit_or_fail_writes_nothing_when_it_refuses(): void
    {
        $this->ledger()->credit($this->user, 'coins', 20, 'topup');
        $before = Transaction::query()->count();

        $this->expectException(WalletException::class);

        try {
            $this->ledger()->debitOrFail($this->user, 'coins', 50, 'purchase');
        } finally {
            $this->assertSame($before, Transaction::query()->count());
            $this->assertSame(20.0, $this->ledger()->balance($this->user, 'coins'));
        }
    }
}
