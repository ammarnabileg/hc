<?php

namespace Tests\Feature\Volunteer\Goals;

use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkPackage;
use App\Services\Volunteer\Tasks\TaskStatus;

/**
 * ⭐ **لوحة الهدف** — صفحة الهدف الواحد.
 *
 * ⛔ ما قبلها: الهدف بلا عنوانٍ يُفتَح. تفاصيله مطويّة في بطاقةٍ داخل القائمة،
 * ومهامّه لا تُرى إلّا بفتح صفحة حزمةٍ ثمّ توسيع بند. والطبقات الخمس باقيةٌ
 * كما هي في المنطق، لكنّها هنا **مرشِّحات** فوق لوحةٍ واحدة لا تعشيشٌ في النقر.
 */
class GoalBoardTest extends GoalsTestCase
{
    /** حاجز القائمة نفسه يحرس الصفحة: لا شيء قبل «إرسال للتنفيذ» (23 — القسم 1). */
    public function test_a_goal_still_being_built_has_no_board(): void
    {
        $entity = $this->makeEntity();
        $user = $this->makeUser();
        $membership = $this->makeMembership($user, $entity, null, 'director');
        $this->grant($user, 'goals.list', 'ENTITY', $membership);
        $this->grant($user, 'goals.view', 'ENTITY', $membership);

        $draft = $this->makeTree($entity, 'draft');

        $this->actingAs($user)
            ->get(route('volunteer.goals.show', $draft['goal']))
            ->assertNotFound();
    }

    /** المهامّ تُعرَض موزّعةً على حالاتها، والمنتهية مجموعةٌ في عمودٍ واحد. */
    public function test_the_board_groups_the_tasks_by_status(): void
    {
        [$user, $tree] = $this->boardFixture();

        $this->makeTask($tree['item'], TaskStatus::IN_PROGRESS, $user, 50);
        $this->makeTask($tree['item'], TaskStatus::DELIVERED, $user, 40);
        $this->makeTask($tree['item'], TaskStatus::APPROVED, $user, 30);

        $response = $this->actingAs($user)->get(route('volunteer.goals.show', $tree['goal']));

        $response->assertOk();
        $response->assertSee($tree['goal']->name);

        // عمودٌ لكلّ حالة مفتوحة بنصّها، والمنتهية خلف عنوانٍ واحد
        foreach ([TaskStatus::IN_PROGRESS, TaskStatus::BLOCKED, TaskStatus::DELIVERED, TaskStatus::RETURNED] as $status) {
            $response->assertSee(TaskStatus::label($status));
        }

        $response->assertSee('منتهية');
    }

    /**
     * ⭐ المرشِّح يضيّق فعلًا: حزمةٌ أخرى ⟵ مهامّها وحدها. وهذا هو بديل التعشيش —
     * الطبقة باقية، لكنّها نقرةٌ واحدة لا صفحةٌ جديدة.
     */
    public function test_filtering_by_a_package_narrows_the_board_to_its_tasks(): void
    {
        [$user, $tree] = $this->boardFixture();

        $mine = $this->makeTask($tree['item'], TaskStatus::IN_PROGRESS, $user, 10);
        $mine->forceFill(['title' => 'مهمّة الحزمة الأولى'])->save();

        // حزمةٌ ثانيةٌ تحت نفس المَعلَم، ولها بندها ومهمّتها
        $second = WorkPackage::create([
            'milestone_id' => $tree['milestone']->id,
            'entity_id' => $tree['package']->entity_id,
            'name' => 'الحزمة الثانية',
        ]);

        $secondItem = WorkItem::create([
            'work_package_id' => $second->id,
            'name' => 'بند الحزمة الثانية',
            'vxp_pool' => 100,
        ]);

        $other = $this->makeTask($secondItem, TaskStatus::IN_PROGRESS, $user, 10);
        $other->forceFill(['title' => 'مهمّة الحزمة الثانية'])->save();

        // بلا ترشيح: الاثنتان
        $all = $this->actingAs($user)->get(route('volunteer.goals.show', $tree['goal']));
        $all->assertSee('مهمّة الحزمة الأولى');
        $all->assertSee('مهمّة الحزمة الثانية');

        // بترشيح الحزمة الثانية: هي وحدها
        $filtered = $this->actingAs($user)
            ->get(route('volunteer.goals.show', ['goal' => $tree['goal'], 'package' => $second->id]));

        $filtered->assertOk();
        $filtered->assertSee('مهمّة الحزمة الثانية');
        $filtered->assertDontSee('مهمّة الحزمة الأولى');
    }

    /** @return array{0: User, 1: array<string, mixed>} */
    private function boardFixture(): array
    {
        $entity = $this->makeEntity();
        $user = $this->makeUser();
        $membership = $this->makeMembership($user, $entity, null, 'director');
        $this->grant($user, 'goals.list', 'ENTITY', $membership);
        $this->grant($user, 'goals.view', 'ENTITY', $membership);

        return [$user, $this->makeTree($entity)];
    }
}
