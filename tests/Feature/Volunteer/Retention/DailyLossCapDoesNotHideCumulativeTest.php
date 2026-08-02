<?php

namespace Tests\Feature\Volunteer\Retention;

use App\Models\Currency;
use App\Models\Transaction;
use App\Models\WalletBalance;
use App\Services\Volunteer\Goals\RepService;
use App\Services\Volunteer\Retention\CommitteePath;
use App\Services\Wallet\LedgerService;

/**
 * ⭐ سقف الخسارة اليوميّ **لا يخفي** المكتسَب التراكميّ (13.4-ن-و · 13.4-س-ج).
 *
 * عتبة «−15 خلال 90 يومًا» كُتبت خصّيصًا لسدّ ثغرة التصفير الشهريّ؛ فلو قِيست على
 * `applied_amount` (المسقوف بحدّ اليوم) عادت الثغرة نفسها من بابٍ آخر: من يهبط
 * −18 في يومٍ واحد يُقاس عليه −2 فلا يبلغ العتبة أبدًا.
 */
class DailyLossCapDoesNotHideCumulativeTest extends RetentionTestCase
{
    /** ⭐ مخالفتان تتجاوزان السقف اليوميّ ⟵ التراكميّ يقرأ الكامل وتُفتَح اللجنة */
    public function test_violations_above_the_daily_cap_still_reach_the_cumulative_threshold(): void
    {
        $ledger = app(LedgerService::class);
        $committee = app(CommitteePath::class);

        $user = $this->makeUser('هالة');
        $this->makeMembership($user, $this->makeEntity());

        // مخالفتان مجموعهما −18 في يومٍ واحد، والحدّ اليوميّ −2
        $first = $ledger->debit($user, LedgerService::REP, 9, 'behavior', null, 'volunteer', 'مخالفة موثّقة');
        $second = $ledger->debit($user, LedgerService::REP, 9, 'behavior', null, 'volunteer', 'مخالفة موثّقة ثانية');

        // السقف اليوميّ سليم ولم يُكسَر: الرقم الظاهر لا ينزل أكثر من −2
        $this->assertSame(rep_rule('limit.daily_loss'), round($ledger->balance($user, LedgerService::REP), 2));
        $this->assertTrue((bool) $second->exceeded_daily_cap);

        // والقيمة الكاملة محفوظة في السجلّ
        $this->assertSame(-9.0, (float) $first->amount);
        $this->assertSame(-9.0, (float) $second->amount);

        // ⭐ والتراكميّ يقرأ الكامل — لا المسقوف
        $this->assertSame(-18.0, $committee->cumulativeEarned([$user->id])[$user->id]);
        $this->assertLessThanOrEqual($committee->cumulativeThreshold(), $committee->cumulativeEarned([$user->id])[$user->id]);

        // فتُفتَح اللجنة بالباب التراكميّ
        $result = $committee->sweep();

        $this->assertSame(1, $result['referred']);
        $this->assertDatabaseHas(CommitteePath::TABLE, [
            'user_id' => $user->id,
            'trigger' => CommitteePath::TRIGGER_CUMULATIVE,
            'status' => 'open',
        ]);
    }

    /** والمكتسَب التراكميّ في المحفظة يسجّل الكامل كذلك — لا المسقوف */
    public function test_wallet_lifetime_records_the_full_amount_not_the_capped_one(): void
    {
        $ledger = app(LedgerService::class);
        $user = $this->makeUser('ياسر');

        $ledger->debit($user, LedgerService::REP, 4.5, 'behavior', null, 'volunteer', 'مخالفة');

        $currencyId = (int) Currency::query()->where('code', LedgerService::REP)->value('id');
        $wallet = WalletBalance::query()
            ->where('user_id', $user->id)->where('currency_id', $currencyId)->firstOrFail();

        $this->assertSame(4.5, (float) $wallet->lifetime_spent);
        $this->assertSame(rep_rule('limit.daily_loss'), (float) $wallet->balance);
    }

    /** والتصفير الشهريّ لا يمسّ التراكميّ — فالعتبة تبقى قادرةً على العمل بعده */
    public function test_monthly_reset_keeps_the_cumulative_reading_intact(): void
    {
        $ledger = app(LedgerService::class);
        $committee = app(CommitteePath::class);

        $user = $this->makeUser('نهى');
        $this->makeMembership($user, $this->makeEntity());

        $ledger->debit($user, LedgerService::REP, 16, 'behavior', null, 'volunteer', 'مخالفة جسيمة');

        app(RepService::class)->resetMonthly();

        $this->assertSame(0.0, round($ledger->balance($user, LedgerService::REP), 2));
        $this->assertSame(-16.0, $committee->cumulativeEarned([$user->id])[$user->id]);
        $this->assertSame(1, $committee->sweep()['referred']);
    }

    /** والاعتراض المقبول يمحو الأثر كاملًا فلا يبقى فائضٌ يفتح لجنةً على مخالفةٍ أُلغِيت */
    public function test_an_accepted_objection_clears_the_full_recorded_amount(): void
    {
        $ledger = app(LedgerService::class);
        $committee = app(CommitteePath::class);

        $user = $this->makeUser('سامي');
        $this->makeMembership($user, $this->makeEntity());

        $transaction = $ledger->debit($user, LedgerService::REP, 16, 'behavior', null, 'volunteer', 'اتّهام لاحقًا سقط');
        $this->assertSame(-16.0, $committee->cumulativeEarned([$user->id])[$user->id]);

        $ledger->reverse($transaction, 'قبول اعتراض', $user->id);

        $this->assertSame(0.0, $committee->cumulativeEarned([$user->id])[$user->id]);
        $this->assertSame(0, $committee->sweep()['referred']);

        // ولا تُحذَف المعاملة الأصليّة: التصحيح سطرٌ جديد لا تعديلٌ على القديم (19.4)
        $this->assertSame(2, Transaction::query()->where('user_id', $user->id)->count());
    }
}
