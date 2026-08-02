<?php

namespace Tests\Feature\Volunteer\Retention;

use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Volunteer\Retention\CommitteePath;
use App\Services\Wallet\LedgerService;
use Illuminate\Support\Facades\DB;

/**
 * عتبة المكتسَب التراكميّ (13.4-س-ج): «مجموع Rep المكتسَب خلال آخر **90 يومًا**
 * ≤ **−15** ⟵ **نفس مسار اللجنة**» — وهي التي تسدّ ثغرة التصفير الشهريّ.
 */
class CumulativeThresholdTest extends RetentionTestCase
{
    /** ⭐ من بلغ العتبة يُحال، ومن هو أعلى منها لا يُحال. */
    public function test_only_the_member_at_or_below_the_cumulative_threshold_is_referred(): void
    {
        $threshold = rep_rule('limit.cumulative_90d');
        $this->assertSame(-15.0, $threshold, 'العتبة المنصوصة في الدستور');

        $entity = $this->makeEntity();

        $reached = $this->makeUser('سلمى');
        $this->makeMembership($reached, $entity);

        $above = $this->makeUser('عمرو');
        $this->makeMembership($above, $entity);

        // كلاهما رقمه الظاهر صفر بعد التصفير الشهريّ — والفرق في السجلّ وحده
        $this->writeHistory($reached, [-4, -4, -4, -3]);   // −15 بالضبط ⟵ العتبة
        $this->writeHistory($above, [-4, -4, -4, -1.5]);   // −13.5 ⟵ أعلى منها

        $this->assertSame(0.0, $this->displayedRep($reached));
        $this->assertSame(0.0, $this->displayedRep($above));

        $this->artisan('volunteers:inactivity')->assertSuccessful();

        $this->assertDatabaseHas(CommitteePath::TABLE, [
            'user_id' => $reached->id,
            'trigger' => CommitteePath::TRIGGER_CUMULATIVE,
            'status' => 'open',
        ]);

        $this->assertDatabaseMissing(CommitteePath::TABLE, ['user_id' => $above->id]);

        // الإحالة لا تتكرّر مع المسحة اليوميّة
        $this->artisan('volunteers:inactivity')->assertSuccessful();
        $this->assertSame(1, DB::table(CommitteePath::TABLE)->where('user_id', $reached->id)->count());

        // وقد سُجّلت في الأودِت وأُشعِر صاحبها
        $this->assertDatabaseHas('audit_logs', ['action' => 'volunteer_committee.refer']);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $reached->id,
            'title' => 'اتفتح ملفّ لجنة تحقيق على درجة الالتزام',
        ]);
    }

    /** والباب الأوّل باقٍ: الرقم الظاهر ≤ −10 يفتح **نفس** المسار. */
    public function test_the_displayed_suspension_threshold_opens_the_same_path(): void
    {
        $user = $this->makeUser('طارق');
        $this->makeMembership($user, $this->makeEntity());

        // نزول الرقم الظاهر إلى عتبة التعليق مباشرةً
        DB::table('wallet_balances')->updateOrInsert(
            ['user_id' => $user->id, 'currency_id' => Currency::query()->where('code', LedgerService::REP)->value('id')],
            ['balance' => rep_rule('limit.suspension'), 'lifetime_earned' => 0, 'lifetime_spent' => 10,
                'created_at' => now(), 'updated_at' => now()],
        );

        $this->artisan('volunteers:inactivity')->assertSuccessful();

        $this->assertDatabaseHas(CommitteePath::TABLE, [
            'user_id' => $user->id,
            'trigger' => CommitteePath::TRIGGER_DISPLAYED,
            'status' => 'open',
        ]);
    }

    /** النافذة والعتبة إعدادان يُقرآن، لا رقمان محروقان (2.13). */
    public function test_window_and_threshold_are_read_from_settings(): void
    {
        $committee = app(CommitteePath::class);

        $this->assertSame((int) setting('volunteer.offboarding.cumulative_window_days'), $committee->windowDays());
        $this->assertSame(rep_rule('limit.cumulative_90d'), $committee->cumulativeThreshold());

        // وما خرج عن النافذة لا يُحسَب
        $user = $this->makeUser('قديم');
        $this->makeMembership($user, $this->makeEntity());
        $this->writeHistory($user, [-20], $committee->windowDays() + 5);

        $this->assertSame(0.0, $committee->cumulativeEarned([$user->id])[$user->id]);
    }

    // ------------------------------------------------------------------ أدوات

    /** كتابة سجلّ حركات Rep داخل النافذة — السجلّ هو ما لا يمحوه التصفير */
    private function writeHistory(User $user, array $amounts, int $daysAgo = 10): void
    {
        $currencyId = (int) Currency::query()->where('code', LedgerService::REP)->value('id');

        foreach ($amounts as $index => $amount) {
            Transaction::create([
                'user_id' => $user->id,
                'currency_id' => $currencyId,
                'amount' => $amount,
                'applied_amount' => $amount,
                'balance_after' => 0,
                'layer' => 'volunteer',
                'source' => 'behavior',
                'reason' => 'حركة اختبار',
                'created_at' => now()->subDays($daysAgo + $index),
                'updated_at' => now()->subDays($daysAgo + $index),
            ]);
        }
    }

    private function displayedRep(User $user): float
    {
        return round(app(LedgerService::class)->balance($user, LedgerService::REP), 2);
    }
}
