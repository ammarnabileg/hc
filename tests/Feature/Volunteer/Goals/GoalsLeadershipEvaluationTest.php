<?php

namespace Tests\Feature\Volunteer\Goals;

use App\Services\Volunteer\Goals\LeadershipService;
use App\Services\Volunteer\Goals\RepService;
use Illuminate\Validation\ValidationException;

/**
 * مؤشّر القيادة (الدستور 24.4 · 13.4-ن-د).
 * ⭐ لا يظهر المتوسّط إلّا بعتبة المقيّمين — حمايةً للسرّيّة.
 */
class GoalsLeadershipEvaluationTest extends GoalsTestCase
{
    private function service(): LeadershipService
    {
        return app(LeadershipService::class);
    }

    /** ⭐ أقلّ من العتبة ⟵ المتوسّط محجوب تمامًا */
    public function test_average_is_hidden_below_three_raters(): void
    {
        $this->makeCriteria();
        $entity = $this->makeEntity();

        $upline = $this->makeUser('الأبلاين');
        $uplineM = $this->makeMembership($upline, $entity, null, 'team_leader');

        foreach (range(1, 2) as $i) {
            $member = $this->makeUser('عضو '.$i);
            $this->makeMembership($member, $entity, $uplineM);
            $this->service()->submit($member, $upline, ['c1' => 9, 'c2' => 9]);
        }

        $summary = $this->service()->receivedSummary($upline);

        $this->assertSame(2, $summary['raters']);
        $this->assertFalse($summary['visible']);
        $this->assertNull($summary['average']);
    }

    /** ببلوغ العتبة يظهر المتوسّط وتفصيل المعايير */
    public function test_average_appears_at_threshold(): void
    {
        $this->makeCriteria();
        $entity = $this->makeEntity();

        $upline = $this->makeUser('الأبلاين');
        $uplineM = $this->makeMembership($upline, $entity, null, 'team_leader');

        foreach (range(1, $this->service()->minRaters()) as $i) {
            $member = $this->makeUser('عضو '.$i);
            $this->makeMembership($member, $entity, $uplineM);
            $this->service()->submit($member, $upline, ['c1' => 8, 'c2' => 10]);
        }

        $summary = $this->service()->receivedSummary($upline);

        $this->assertTrue($summary['visible']);
        $this->assertSame(9.0, (float) $summary['average']);
        $this->assertSame(8.0, (float) $summary['per_criterion']['c1']);
    }

    /** ⭐ أثر المؤشّر على Rep من `rep_rule()` — ويُطبَّق مرّةً عند بلوغ العتبة */
    public function test_impact_on_rep_uses_rep_rule_values(): void
    {
        $this->makeCriteria();
        $entity = $this->makeEntity();

        $upline = $this->makeUser('الأبلاين');
        $uplineM = $this->makeMembership($upline, $entity, null, 'team_leader');

        foreach (range(1, $this->service()->minRaters()) as $i) {
            $member = $this->makeUser('عضو '.$i);
            $this->makeMembership($member, $entity, $uplineM);
            $this->service()->submit($member, $upline, ['c1' => 10, 'c2' => 10]);
        }

        $rep = app(RepService::class);

        // متوسّط 10 ⟵ الشريحة ≥9 ⟵ قيمتها من جدول Rep لا محروقة
        $this->assertSame('leadership.ge_9', $rep->leadershipRuleKeyFor(10));
        $this->assertSame(rep_rule('leadership.ge_9'), $rep->score($upline->fresh()));
    }

    /** التقييم مرّة واحدة أسبوعيًّا — ولا يقيّم أحدٌ نفسه */
    public function test_one_evaluation_per_week_and_never_self(): void
    {
        $this->makeCriteria();
        $entity = $this->makeEntity();

        $upline = $this->makeUser('الأبلاين');
        $uplineM = $this->makeMembership($upline, $entity, null, 'team_leader');
        $member = $this->makeUser('عضو');
        $this->makeMembership($member, $entity, $uplineM);

        $this->service()->submit($member, $upline, ['c1' => 7, 'c2' => 7]);

        $this->assertTrue($this->service()->alreadyEvaluated($member, $upline));

        $this->expectException(ValidationException::class);
        $this->service()->submit($member, $upline, ['c1' => 7, 'c2' => 7]);
    }

    /** ⭐ مجهول تمامًا: الملخّص لا يحمل أيّ معرّف للمقيّم */
    public function test_summary_never_exposes_evaluator_identity(): void
    {
        $this->makeCriteria();
        $entity = $this->makeEntity();

        $upline = $this->makeUser('الأبلاين');
        $uplineM = $this->makeMembership($upline, $entity, null, 'team_leader');

        foreach (range(1, $this->service()->minRaters()) as $i) {
            $member = $this->makeUser('عضو '.$i);
            $this->makeMembership($member, $entity, $uplineM);
            $this->service()->submit($member, $upline, ['c1' => 6, 'c2' => 6]);
        }

        $summary = $this->service()->receivedSummary($upline);

        $this->assertSame(['visible', 'raters', 'average', 'per_criterion'], array_keys($summary));
    }

    /** المصعد الصحيح فقط: من الداونلاين للأبلاين المباشر داخل العضويّة */
    public function test_upline_resolution_is_direct_only(): void
    {
        $entity = $this->makeEntity();

        $head = $this->makeUser('رئيس');
        $headM = $this->makeMembership($head, $entity, null, 'supervisor');
        $mid = $this->makeUser('وسيط');
        $midM = $this->makeMembership($mid, $entity, $headM, 'team_leader');
        $leaf = $this->makeUser('طرف');
        $this->makeMembership($leaf, $entity, $midM);

        $this->assertSame($mid->id, $this->service()->uplineOf($leaf)?->id);
        $this->assertNull($this->service()->uplineOf($head));
    }
}
