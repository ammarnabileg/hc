<?php

namespace Tests\Feature\Volunteer\Goals;

use App\Services\Volunteer\Goals\Integrations;
use App\Services\Volunteer\Goals\RepService;

/**
 * حدّ الخسارة اليوميّ (الدستور 13.4-ن-و).
 * ⭐ ما زاد عن الحدّ **لا يضيع**: يُسجَّل كاملًا في السجلّ ويُوسَم صراحةً.
 */
class GoalsDailyLossCapTest extends GoalsTestCase
{
    /** القيمة نفسها من `rep_rule()` لا من رقم في الكود */
    public function test_cap_value_comes_from_rep_rule(): void
    {
        $this->assertSame(rep_rule('limit.daily_loss'), app(RepService::class)->dailyLossCap());
        $this->assertLessThan(0, app(RepService::class)->dailyLossCap());
    }

    /** ⭐ الخصم فوق الحدّ يُطبَّق مقصوصًا على الرقم الظاهر ويُسجَّل كاملًا بوسمه */
    public function test_loss_above_daily_cap_is_recorded_in_full_and_flagged(): void
    {
        $user = $this->makeUser();
        $rep = app(RepService::class);
        $cap = $rep->dailyLossCap(); // −2

        // خصمان مجموعهما −2.5 بينما الحدّ −2
        Integrations::debit($user, RepService::CURRENCY, rep_rule('task.no_delivery'), 'task', null, 'عدم تسليم'); // −0.75
        $second = Integrations::debit($user, RepService::CURRENCY, 2.0, 'behavior', null, 'مخالفة موثّقة');

        $this->assertNotNull($second);

        // الرقم الظاهر لا ينزل أكثر من الحدّ في اليوم الواحد
        $this->assertSame($cap, $rep->score($user->fresh()));

        // والمعاملة مسجَّلة بقيمتها الكاملة مع وسم التخطّي
        $this->assertTrue((bool) $second->exceeded_daily_cap);
        $this->assertSame(-2.0, (float) $second->amount);
        $this->assertGreaterThan(abs((float) $second->applied_amount), abs((float) $second->amount));

        $exceeded = $rep->exceededToday($user);
        $this->assertCount(1, $exceeded);
        $this->assertGreaterThan(0, $rep->unappliedAmount($exceeded->first()));
    }

    /** الخصم داخل الحدّ يُطبَّق كاملًا بلا وسم */
    public function test_loss_within_cap_is_applied_fully(): void
    {
        $user = $this->makeUser();

        $transaction = Integrations::debit($user, RepService::CURRENCY, rep_rule('task.apology_accepted'), 'task', null, 'اعتذار مقبول');

        $this->assertFalse((bool) $transaction->exceeded_daily_cap);
        $this->assertSame(rep_rule('task.apology_accepted'), (float) $transaction->applied_amount);
    }

    /** المؤشّر الأحمر عتبةٌ من جدول Rep — والبانر يعتمد عليها لا على رقم مكتوب */
    public function test_red_indicator_threshold_comes_from_rep_rule(): void
    {
        $rep = app(RepService::class);

        $this->assertSame(rep_rule('limit.red_indicator'), $rep->redThreshold());
        $this->assertSame(rep_rule('limit.warning_threshold'), $rep->warningThreshold());
        $this->assertSame('danger', $rep->state($rep->redThreshold()));
        $this->assertSame('warn', $rep->state(-1));
        $this->assertSame('ok', $rep->state(3));
    }
}
