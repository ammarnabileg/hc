<?php

namespace Tests\Feature\Volunteer\Goals;

use App\Models\Currency;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\Volunteer\Goals\Integrations;
use App\Services\Volunteer\Goals\VxpDistributionService;
use App\Services\Volunteer\Tasks\TaskWorkflow;
use App\Services\Wallet\LedgerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ **شريحة الأب = وعاء مهمّته − ما وزّعه منه** (الدستور 23 — 3.9-٥).
 *
 * النصّ: «كلّ أب يوزّع على صب-تاسكاته **من وعاء مهمّته** **ويحتفظ بشريحة الدمج
 * والإشراف لنفسه**… **والزيادة فوق الوعاء لا تأتي إلا من رصيد الأب الشخصي
 * بموافقته الصريحة**».
 *
 * وكان المبنيّ يمنح الأب `task.vxp_value` **كاملًا** عند التسليم مهما وزّع —
 * فتُدفَع النقطة الواحدة مرّتين (للابن على شريحته وللأب على الوعاء كلّه)،
 * ويصير قيدُ التوزيع كلُّه **حارسًا على رقمٍ لا يُترجَم إلى نقود**.
 */
class GoalsParentSliceTest extends GoalsTestCase
{
    /** الحالة ١: **لم يوزّع** ⟵ الوعاء كلّه شريحته */
    public function test_a_parent_who_distributed_nothing_earns_the_whole_pool(): void
    {
        [$owner, $parent] = $this->parentWithPool(100);

        $this->assertSame(100.0, app(VxpDistributionService::class)->parentEarning($parent));
        $this->assertEqualsWithDelta(100.0, $this->deliverAndMeasure($parent, $owner), 0.001);
    }

    /** الحالة ٢: **وزّع داخل الوعاء** ⟵ يقبض الباقي لا الوعاء */
    public function test_a_parent_earns_the_pool_minus_what_he_distributed(): void
    {
        [$owner, $parent] = $this->parentWithPool(100);
        [$a, $b] = $this->childrenOf($parent, 2);

        app(VxpDistributionService::class)->distribute($parent, [$a->id => 40, $b->id => 20], payer: $owner);

        $this->assertSame(40.0, app(VxpDistributionService::class)->parentEarning($parent->fresh()));

        $earned = $this->deliverAndMeasure($parent->fresh(), $owner);

        $this->assertEqualsWithDelta(
            40.0,
            $earned,
            0.001,
            'الأب قبض غير شريحته — والنصّ: «يوزّع من وعاء مهمّته **ويحتفظ بشريحة الدمج والإشراف لنفسه**».',
        );
    }

    /**
     * ⭐ الحالة ٣ (المنصوصة): **وزّع فوق الوعاء بموافقة صريحة**.
     *
     * الزيادة خرجت من **جيبه** لحظة الحفظ، فالمنصرف من الوعاء = الوعاء كلّه،
     * وشريحته **صفر لا سالب**. ولو حُسِبت «الوعاء − ما وزّعه» لَخرجت −20: خصمٌ
     * ثانٍ على نفس العشرين — **خصم مزدوج على فعلٍ واحد** وهو ممنوع (23-6).
     */
    public function test_distributing_above_the_pool_with_consent_leaves_the_parent_at_zero_not_negative(): void
    {
        // الأرضيّة صفرًا هي شرط انفتاح هذا المسار أصلًا (القيد «ب» لا يُشترى بموافقة)
        $this->setMinSharePercent(0);

        [$owner, $parent] = $this->parentWithPool(100);
        [$a, $b] = $this->childrenOf($parent, 2);

        $this->credit($owner, 500);

        $check = app(VxpDistributionService::class)
            ->distribute($parent, [$a->id => 70, $b->id => 50], consentPersonal: true, payer: $owner);

        $this->assertSame(20.0, $check['overflow']);
        $this->assertEqualsWithDelta(20.0, (float) $parent->fresh()->personal_vxp_top_up, 0.001);

        // الزيادة نزلت من رصيده فعلًا لحظة الحفظ
        $this->assertEqualsWithDelta(480.0, $this->balance($owner), 0.001);

        $this->assertSame(0.0, app(VxpDistributionService::class)->parentEarning($parent->fresh()));

        $earned = $this->deliverAndMeasure($parent->fresh(), $owner);

        $this->assertEqualsWithDelta(0.0, $earned, 0.001, 'قبض الأب شيئًا وقد صرف وعاءه كلّه وزاد من جيبه.');
        $this->assertEqualsWithDelta(480.0, $this->balance($owner), 0.001, 'وقع خصمٌ ثانٍ على نفس الزيادة — خصم مزدوج ممنوع (23-6).');
    }

    /**
     * ⭐ **الحصيلة لا تتجاوز الوعاء أبدًا**: مجموع ما يقبضه الأب وأبناؤه على
     * تسليماتهم = الوعاء بالضبط — وهو معنى «التسعير من أعلى **مرّة واحدة**».
     */
    public function test_the_pool_is_paid_exactly_once_across_the_layer(): void
    {
        [$owner, $parent] = $this->parentWithPool(100);
        $childOwnerA = $this->makeUser('ابن أ');
        $childOwnerB = $this->makeUser('ابن ب');
        [$a, $b] = $this->childrenOf($parent, 2, [$childOwnerA, $childOwnerB]);

        app(VxpDistributionService::class)->distribute($parent, [$a->id => 40, $b->id => 20], payer: $owner);

        $paid = $this->deliverAndMeasure($a->fresh(), $childOwnerA)
            + $this->deliverAndMeasure($b->fresh(), $childOwnerB)
            + $this->deliverAndMeasure($parent->fresh(), $owner);

        $this->assertEqualsWithDelta(100.0, $paid, 0.001, 'الوعاء دُفِع أكثر من مرّة عبر الطبقة.');
    }

    /** ولا حركة أصلًا لمن شريحته صفر — سطرٌ بصفر ضجيجٌ في الكشف */
    public function test_no_movement_is_written_when_the_slice_is_zero(): void
    {
        $this->setMinSharePercent(0);

        [$owner, $parent] = $this->parentWithPool(100);
        [$a] = $this->childrenOf($parent, 1);

        app(VxpDistributionService::class)->distribute($parent, [$a->id => 100], payer: $owner);

        app(TaskWorkflow::class)->deliver($parent->fresh(), $owner, ['body' => 'مخرج']);

        $this->assertSame(0, DB::table('transactions')
            ->where('user_id', $owner->id)
            ->where('currency_id', Currency::query()->where('code', 'vxp')->value('id'))
            ->count());
    }

    // ------------------------------------------------------------------ أدوات

    /** @return array{0:User,1:Task} */
    private function parentWithPool(float $pool): array
    {
        $entity = $this->makeEntity();
        $tree = $this->makeTree($entity);
        $owner = $this->makeUser('صاحب الأب');
        $this->makeMembership($owner, $entity, position: 'team_leader');

        $parent = $this->makeTask($tree['item'], 'in_progress', $owner, $pool);

        return [$owner, $parent];
    }

    /**
     * @param  array<int,User>  $owners
     * @return array<int,Task>
     */
    private function childrenOf(Task $parent, int $count, array $owners = []): array
    {
        $item = $parent->work_item_id ? WorkItem::query()->find($parent->work_item_id) : null;

        $children = [];

        for ($i = 0; $i < $count; $i++) {
            $children[] = $this->makeTask($item, 'in_progress', $owners[$i] ?? $this->makeUser('منفّذ '.$i), 0, $parent);
        }

        return $children;
    }

    /** الفرق في رصيد VXP قبل التسليم وبعده — **مقروءًا من المحفظة لا من الكود** */
    private function deliverAndMeasure(Task $task, User $owner): float
    {
        $before = $this->balance($owner);

        app(TaskWorkflow::class)->deliver($task, $owner, ['body' => 'مخرج المهمّة']);

        return round($this->balance($owner) - $before, 2);
    }

    private function balance(User $user): float
    {
        return round(app(LedgerService::class)->balance($user->fresh(), 'vxp'), 2);
    }

    private function credit(User $user, float $amount): void
    {
        Integrations::credit($user, 'vxp', $amount, 'manual', null, 'رصيد ابتدائيّ للاختبار');
    }

    private function setMinSharePercent(float $percent): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'workflow.vxp.parent_min_share_percent'],
            [
                'group' => 'workflow',
                'label_ar' => 'أدنى شريحة محفوظة للأب (%)',
                'type' => 'number',
                'default_value' => '10',
                'value' => (string) $percent,
            ],
        );

        Cache::forget('settings');
    }
}
