<?php

namespace Tests\Feature\Volunteer\Retention;

use App\Models\BehaviorTransaction;
use App\Models\BehaviorViolation;
use App\Models\Escalation;
use App\Models\User;
use App\Services\Admin\Volunteer\BehaviorLedger;
use App\Services\Volunteer\Escalation\CaseCatalog;
use App\Services\Volunteer\Escalation\EscalationEngine;
use App\Services\Wallet\LedgerService;
use RuntimeException;

/**
 * أقفاص معاملة السلوك (13.4-ن-هـ):
 *  · «سوبرفايزر فأعلى **لداونلاينه داخل كيانه فقط**»
 *  · «**معاملة واحدة لكلّ واقعة**»
 *  · «الجسيمة (−1) **بموافقة مستوى أعلى — حالة على محرّك التصعيد بنافذة 24 ساعة**»
 */
class BehaviorGuardrailsTest extends RetentionTestCase
{
    /** ⭐ المانح لا يسجّل إلّا على داونلاينه داخل عضويّته النشطة. */
    public function test_a_granter_cannot_record_outside_his_downline(): void
    {
        $entity = $this->makeEntity('قسم التدريب');

        $supervisor = $this->makeUser('سوبرفايزر');
        $supervisorMembership = $this->makeMembership($supervisor, $entity, null, 'supervisor');
        $this->grant($supervisor, 'rep_manual.create', 'SUBTREE', $supervisorMembership);

        $downline = $this->makeUser('عضو الفريق');
        $this->makeMembership($downline, $entity, $supervisorMembership);

        $stranger = $this->makeUser('عضو قسم تاني');
        $this->makeMembership($stranger, $this->makeEntity('قسم تاني'));

        $violation = BehaviorViolation::where('requires_higher_approval', false)->firstOrFail();

        // داخل النطاق: تمرّ
        $record = BehaviorLedger::record($supervisor, $downline, $violation, 'تكرار عدم الالتزام بالمواعيد رغم التنبيه.');
        $this->assertSame('applied', $record->status);

        // خارج النطاق: تُرَدّ
        $this->expectException(RuntimeException::class);
        BehaviorLedger::record($supervisor, $stranger, $violation, 'محاولة تسجيل خارج نطاق الإشراف.');
    }

    /** والأدمن ومشرف عام التطوّع بلا سقف — كما هو منصوص. */
    public function test_admin_and_general_volunteer_supervisor_are_uncapped(): void
    {
        $admin = $this->makeUser('مشرف عام التطوّع');
        $this->grant($admin, 'rep_manual.approve', 'ALL');

        $anyone = $this->makeUser('متطوّع بعيد');
        $this->makeMembership($anyone, $this->makeEntity('قسم بعيد'));

        $violation = BehaviorViolation::where('requires_higher_approval', false)->firstOrFail();

        $record = BehaviorLedger::record($admin, $anyone, $violation, 'واقعة موثّقة رفعها قسم المتطوّعين.');

        $this->assertSame('applied', $record->status);
        $this->assertNull(BehaviorLedger::remainingQuota($admin), 'بلا سقف شهريّ');
    }

    /** ⭐ معاملة واحدة لكلّ واقعة — بمرجع الواقعة. */
    public function test_the_same_incident_cannot_be_recorded_twice(): void
    {
        [$granter, $target] = $this->pair();
        $violation = BehaviorViolation::where('requires_higher_approval', false)->firstOrFail();

        BehaviorLedger::record($granter, $target, $violation, 'تأخّر عن اجتماع الفريق بلا اعتذار.', null, null, 'MEET-2026-08-01');

        try {
            BehaviorLedger::record($granter, $target, $violation, 'نفس الواقعة اتسجّلت تاني بالغلط.', null, null, 'MEET-2026-08-01');
            $this->fail('كان لازم يُرفَض تسجيل نفس الواقعة مرّتين');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('معاملة واحدة لكلّ واقعة', $e->getMessage());
        }

        $this->assertSame(1, BehaviorTransaction::query()->where('user_id', $target->id)->count());

        // وواقعة أخرى بمرجع آخر تمرّ عاديّ
        BehaviorLedger::record($granter, $target, $violation, 'واقعة تانية مستقلّة بمرجعها.', null, null, 'MEET-2026-08-09');
        $this->assertSame(2, BehaviorTransaction::query()->where('user_id', $target->id)->count());
    }

    /** ⭐ الجسيمة تفتح حالةً على المحرّك بنافذة `workflow.escalation.window_hours`. */
    public function test_a_severe_violation_opens_an_escalation_case_within_the_configured_window(): void
    {
        [$granter, $target] = $this->pair();
        $violation = BehaviorViolation::where('requires_higher_approval', true)->firstOrFail();

        $record = BehaviorLedger::record($granter, $target, $violation, 'تجاوز موثّق في التعامل مع زميل داخل الاجتماع.');

        $this->assertSame('pending_approval', $record->status);
        $this->assertSame(0.0, $this->repOf($target), 'لا أثر على Rep قبل الموافقة');

        $case = Escalation::query()->findOrFail($record->fresh()->escalation_id);

        $this->assertSame(CaseCatalog::BEHAVIOR_SEVERE, $case->case_type);
        $this->assertSame('open', $case->status);

        $hours = (float) setting('workflow.escalation.window_hours');
        $this->assertEqualsWithDelta($hours, now()->diffInHours($case->window_due_at, absolute: true), 0.05);
    }

    /** ⭐ فوات النافذة = **رفض**، ولا تُطبَّق على درجة الالتزام. */
    public function test_missing_the_window_settles_the_case_as_a_rejection_without_touching_rep(): void
    {
        [$granter, $target] = $this->pair();
        $violation = BehaviorViolation::where('requires_higher_approval', true)->firstOrFail();

        $record = BehaviorLedger::record($granter, $target, $violation, 'تجاوز موثّق في التعامل مع زميل داخل الاجتماع.');

        $this->travel((int) setting('workflow.escalation.window_hours') + 1)->hours();

        app(EscalationEngine::class)->run();

        $case = Escalation::query()->findOrFail($record->fresh()->escalation_id);

        $this->assertSame('auto_settled', $case->status);
        $this->assertSame('rejected', $case->decision);
        $this->assertSame('rejected', $record->fresh()->status);
        $this->assertSame(0.0, $this->repOf($target), 'الصمت لا يُنشئ عقوبة');

        // وزرّ الاعتماد بعد الفوات لا يحييها
        BehaviorLedger::approve($granter, $record->fresh());
        $this->assertSame(0.0, $this->repOf($target));
    }

    /** والاعتماد داخل النافذة يمرّ من المحرّك ويُطبَّق مرّةً واحدة. */
    public function test_approval_inside_the_window_applies_the_value_exactly_once(): void
    {
        [$granter, $target] = $this->pair();
        $violation = BehaviorViolation::where('requires_higher_approval', true)->firstOrFail();

        $record = BehaviorLedger::record($granter, $target, $violation, 'تجاوز موثّق في التعامل مع زميل داخل الاجتماع.');

        $approver = $this->makeUser('مشرف عام');
        $this->grant($approver, 'rep_manual.approve', 'ALL');

        BehaviorLedger::approve($approver, $record->fresh());

        $this->assertSame('applied', $record->fresh()->status);
        $this->assertSame(round(rep_rule('behavior.severe'), 2), $this->repOf($target));

        // نداء ثانٍ لا يخصم مرّة أخرى
        BehaviorLedger::approve($approver, $record->fresh());
        $this->assertSame(round(rep_rule('behavior.severe'), 2), $this->repOf($target));

        $this->assertSame('decided', Escalation::query()->find($record->fresh()->escalation_id)->status);
    }

    // ------------------------------------------------------------------ أدوات

    /** @return array{0:User,1:User} مانح داخل نطاقه وهدفٌ من داونلاينه */
    private function pair(): array
    {
        $entity = $this->makeEntity('قسم العمليّات');

        $granter = $this->makeUser('مانح');
        $granterMembership = $this->makeMembership($granter, $entity, null, 'supervisor');
        $this->grant($granter, 'rep_manual.create', 'SUBTREE', $granterMembership);

        $target = $this->makeUser('متطوّع');
        $this->makeMembership($target, $entity, $granterMembership);

        return [$granter, $target];
    }

    private function repOf(User $user): float
    {
        return round(app(LedgerService::class)->balance($user->fresh(), LedgerService::REP), 2);
    }
}
