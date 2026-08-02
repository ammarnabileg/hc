<?php

namespace Tests\Feature\Volunteer\Goals;

use App\Services\Volunteer\Goals\RollupService;

/**
 * الصعود الآليّ للنِّسَب مع استبعاد المُغلَقة (الدستور 23 — 1.7 · 24.4).
 */
class GoalsRollupTest extends GoalsTestCase
{
    private function rollup(): RollupService
    {
        return app(RollupService::class);
    }

    /** ⭐ النسبة تصعد مهمّة ⟵ بند ⟵ حزمة ⟵ مَعلَم ⟵ هدف بلا أيّ تقرير بشريّ */
    public function test_percentage_rolls_up_from_task_to_goal(): void
    {
        $tree = $this->makeTree($this->makeEntity());

        $this->makeTask($tree['item'], 'approved');
        $task = $this->makeTask($tree['item'], 'in_progress');

        $this->rollup()->recalcFromTask($task);

        $this->assertSame(50.0, (float) $tree['item']->fresh()->progress_percent);
        $this->assertSame(50.0, (float) $tree['package']->fresh()->progress_percent);
        $this->assertSame(50.0, (float) $tree['milestone']->fresh()->progress_percent);
        $this->assertSame(50.0, (float) $tree['goal']->fresh()->progress_percent);
    }

    /** ⭐ المهمّة المُغلَقة تُستبعَد من المقام — فلا تُثقِل النسبة ولا تُحتسَب إنجازًا */
    public function test_closed_task_is_excluded_from_denominator(): void
    {
        $tree = $this->makeTree($this->makeEntity());

        $this->makeTask($tree['item'], 'approved');
        $this->makeTask($tree['item'], 'in_progress');
        $closed = $this->makeTask($tree['item'], 'closed');

        $this->rollup()->recalcFromTask($closed);

        $counts = $this->rollup()->taskCounts($tree['item']->fresh());

        // ثلاث مهامّ، والمقام اثنتان فقط ⟵ 50% لا 33%
        $this->assertSame(1, $counts['closed']);
        $this->assertSame(2, $counts['denominator']);
        $this->assertSame(50.0, (float) $tree['item']->fresh()->progress_percent);
    }

    /** المُغلَقة موسومة صراحةً في العدّاد كي لا تبدو النسبة مجمَّلةً بلا سبب */
    public function test_closed_count_is_reported_explicitly(): void
    {
        $tree = $this->makeTree($this->makeEntity());

        $this->makeTask($tree['item'], 'approved');
        $this->makeTask($tree['item'], 'closed');
        $this->makeTask($tree['item'], 'closed');

        $counts = $this->rollup()->packageTaskCounts($tree['package']);

        $this->assertSame(2, $counts['closed']);
        $this->assertSame(1, $counts['denominator']);
        $this->assertSame(100.0, $this->rollup()->recalcWorkPackage($tree['package']));
    }

    /** بند كلّ مهامّه مُغلَقة: المقام صفر ⟵ النسبة صفر لا قسمة على صفر */
    public function test_all_closed_yields_zero_without_division_error(): void
    {
        $tree = $this->makeTree($this->makeEntity());

        $this->makeTask($tree['item'], 'closed');

        $this->assertSame(0.0, $this->rollup()->recalcWorkItem($tree['item']));
    }

    /** المَعلَم بمعيار رقميّ يُعلَّم متحقّقًا آليًّا عند اكتمال النسبة (23 — 1.7) */
    public function test_numeric_milestone_is_auto_verified_at_hundred_percent(): void
    {
        $tree = $this->makeTree($this->makeEntity());

        $task = $this->makeTask($tree['item'], 'approved');
        $this->rollup()->recalcFromTask($task);

        $this->assertTrue((bool) $tree['milestone']->fresh()->is_verified);
    }
}
