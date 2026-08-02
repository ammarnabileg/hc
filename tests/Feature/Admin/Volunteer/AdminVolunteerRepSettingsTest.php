<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\BehaviorViolation;
use App\Models\RepRule;
use App\Models\User;
use App\Services\Admin\Volunteer\BehaviorLedger;
use App\Services\Admin\Volunteer\RepRuleWriter;
use RuntimeException;

/**
 * ضبط Rep (13.4-ن): كلّ قيمة إعداد، والتعديل ينعكس فورًا على `rep_rule()`.
 */
class AdminVolunteerRepSettingsTest extends AdminVolunteerTestCase
{
    /** ⭐ تعديل قيمة Rep من الشاشة ينعكس فورًا على `rep_rule()` بلا انتظار كاش. */
    public function test_editing_a_rep_value_reflects_immediately_on_rep_rule_helper(): void
    {
        $admin = $this->grant($this->makeUser(), 'rep_transactions.view', 'volunteer_central_settings.edit');

        $this->assertSame(1.0, rep_rule('meeting.within_3h'));

        $this->actingAs($admin)
            ->post(route('admin.volunteer.rep.rules.save'), [
                'rules' => ['meeting.within_3h' => 1.5, 'task.early' => 0.4],
            ])
            ->assertRedirect();

        // القراءة من الهيلبر مباشرةً — وهي ما تستعمله المنصّة كلّها
        $this->assertSame(1.5, rep_rule('meeting.within_3h'));
        $this->assertSame(0.4, rep_rule('task.early'));
        $this->assertSame(1.5, (float) RepRule::where('key', 'meeting.within_3h')->value('value'));
    }

    /** ↺ Reset يرجّع القيمة لافتراضيّها المنصوص في الدستور. */
    public function test_reset_returns_a_rep_value_to_its_constitutional_default(): void
    {
        $admin = $this->grant($this->makeUser(), 'volunteer_central_settings.edit', 'volunteer_central_settings.manage');

        RepRuleWriter::put('task.no_delivery', -0.1, $admin);
        $this->assertSame(-0.1, rep_rule('task.no_delivery'));

        $this->actingAs($admin)
            ->post(route('admin.volunteer.rep.rules.reset'), ['key' => 'task.no_delivery'])
            ->assertRedirect();

        $this->assertSame(-0.75, rep_rule('task.no_delivery'));
    }

    /** معاملة السلوك تتطلّب مبرّرًا مكتوبًا — لا خصم بلا سبب موثّق. */
    public function test_behavior_transaction_requires_written_justification(): void
    {
        $granter = $this->grant($this->makeUser('مانح'), 'rep_manual.create');
        $target = $this->makeUser('متطوّع');
        $violation = BehaviorViolation::where('requires_higher_approval', false)->firstOrFail();

        $this->expectException(RuntimeException::class);

        BehaviorLedger::record($granter, $target, $violation, 'قصير');
    }

    /** ⭐ السقف الشهريّ للمانح مُحترَم — والمانح بلا صلاحيّة الاعتماد مقيَّد به. */
    public function test_behavior_transaction_respects_the_monthly_cap_per_granter(): void
    {
        $granter = $this->grant($this->makeUser('مانح'), 'rep_manual.create');
        $violation = BehaviorViolation::where('requires_higher_approval', false)->firstOrFail();

        $cap = BehaviorLedger::monthlyCap();
        $this->assertGreaterThan(0, $cap);

        for ($i = 0; $i < $cap; $i++) {
            BehaviorLedger::record($granter, $this->makeUser('عضو '.$i), $violation, 'تكرار عدم الالتزام بالمواعيد رغم التنبيه.');
        }

        $this->assertSame(0, BehaviorLedger::remainingQuota($granter));

        $this->expectException(RuntimeException::class);

        BehaviorLedger::record($granter, $this->makeUser('عضو زائد'), $violation, 'تكرار عدم الالتزام بالمواعيد رغم التنبيه.');
    }

    /** المخالفة الجسيمة لا تُطبَّق إلّا بموافقة مستوى أعلى (نافذة 24 ساعة). */
    public function test_severe_violation_waits_for_higher_approval_before_touching_rep(): void
    {
        $granter = $this->grant($this->makeUser('سوبرفايزر'), 'rep_manual.create');
        $target = $this->makeUser('متطوّع');
        $violation = BehaviorViolation::where('requires_higher_approval', true)->firstOrFail();

        $record = BehaviorLedger::record($granter, $target, $violation, 'تجاوز موثّق في التعامل مع زميل داخل اجتماع الفريق.');

        $this->assertSame('pending_approval', $record->status);
        $this->assertSame(0.0, $this->repOf($target));

        $approver = $this->grant($this->makeUser('مشرف عام'), 'rep_manual.approve');
        BehaviorLedger::approve($approver, $record->fresh());

        $this->assertSame('applied', $record->fresh()->status);
        $this->assertLessThan(0.0, $this->repOf($target));
    }

    /** قائمة المخالفات المكوَّدة تُضاف وتُعدَّل من الشاشة — لا نصّ حرّ. */
    public function test_admin_can_add_a_coded_violation(): void
    {
        $admin = $this->grant($this->makeUser(), 'volunteer_central_settings.edit');

        $this->actingAs($admin)
            ->post(route('admin.volunteer.rep.violations.save'), [
                'code' => 'BV99',
                'label_ar' => 'إخلال بتعهّد للفريق',
                'default_value' => rep_rule('behavior.warning'),
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('behavior_violations', ['code' => 'BV99']);
    }

    private function repOf(User $user): float
    {
        return round((float) $user->balance(BehaviorLedger::REP), 2);
    }
}
