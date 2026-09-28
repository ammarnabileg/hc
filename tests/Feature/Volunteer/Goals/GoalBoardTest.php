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

    /**
     * البحث بالاسم يعمل **داخل** النطاق المرشَّح: حزمةٌ مرشَّحة + كلمة ⟵ مهامّ
     * تلك الحزمة التي تحمل الكلمة وحدها. والكونترولر كان يقرأ `q` من البداية
     * بلا حقلٍ في الشاشة — فالحارس هنا يثبّت أنّ الحقل موجودٌ وأنّه لا يقفز
     * إلى الهدف كلّه حين تكون حزمةٌ مرشَّحة.
     */
    public function test_searching_by_name_stays_inside_the_filtered_package(): void
    {
        [$user, $tree] = $this->boardFixture();

        $this->makeTask($tree['item'], TaskStatus::IN_PROGRESS, $user, 10)
            ->forceFill(['title' => 'كتابة السكربت الأوّل'])->save();

        $this->makeTask($tree['item'], TaskStatus::IN_PROGRESS, $user, 10)
            ->forceFill(['title' => 'مونتاج الحلقة'])->save();

        // حزمةٌ ثانية فيها مهمّةٌ تحمل نفس الكلمة — يجب ألّا تظهر تحت ترشيح الأولى
        $second = WorkPackage::create([
            'milestone_id' => $tree['milestone']->id,
            'entity_id' => $tree['package']->entity_id,
            'name' => 'الحزمة الثانية',
        ]);
        $secondItem = WorkItem::create(['work_package_id' => $second->id, 'name' => 'بند ثانٍ', 'vxp_pool' => 100]);
        $this->makeTask($secondItem, TaskStatus::IN_PROGRESS, $user, 10)
            ->forceFill(['title' => 'كتابة السكربت الثاني'])->save();

        $response = $this->actingAs($user)->get(route('volunteer.goals.show', [
            'goal' => $tree['goal'], 'package' => $tree['package']->id, 'q' => 'السكربت',
        ]));

        $response->assertOk();
        $response->assertSee('name="q"', false);
        $response->assertSee('كتابة السكربت الأوّل');
        $response->assertDontSee('مونتاج الحلقة');
        $response->assertDontSee('كتابة السكربت الثاني');
    }

    /**
     * ⭐ «مين شغّال على إيه»: رقاقةٌ لكلّ مسؤول بحِمله **المفتوح** لا بكلّ ما مرّ
     * به، والنقر عليها يرشّح اللوحة إلى مهامّه. وتبقى رقائق الآخرين ظاهرةً
     * وأنت واقفٌ على واحدة — وإلّا فلا سبيل للرجوع عنها إلّا بمسح الرابط.
     */
    public function test_owner_chips_count_open_work_and_filter_the_board(): void
    {
        [$user, $tree] = $this->boardFixture();
        $peer = $this->makeUser('زميل مشغول');

        // للمستخدم: مهمّةٌ مفتوحة ومهمّةٌ معتمَدة — الحِمل المفتوح واحد لا اثنان
        $this->makeTask($tree['item'], TaskStatus::IN_PROGRESS, $user, 10)
            ->forceFill(['title' => 'مهمّتي المفتوحة'])->save();
        $this->makeTask($tree['item'], TaskStatus::APPROVED, $user, 10)
            ->forceFill(['title' => 'مهمّتي المعتمَدة'])->save();

        $this->makeTask($tree['item'], TaskStatus::IN_PROGRESS, $peer, 10)
            ->forceFill(['title' => 'مهمّة الزميل'])->save();

        $all = $this->actingAs($user)->get(route('volunteer.goals.show', $tree['goal']));
        $all->assertOk();
        $all->assertSee('زميل مشغول');
        $all->assertSee('مهمّتي المفتوحة');
        $all->assertSee('مهمّة الزميل');

        $mine = $this->actingAs($user)->get(route('volunteer.goals.show', [
            'goal' => $tree['goal'], 'owner' => $user->id,
        ]));

        $mine->assertOk();
        $mine->assertSee('مهمّتي المفتوحة');
        $mine->assertDontSee('مهمّة الزميل');
        // رقاقة الزميل ما زالت هناك للرجوع أو الانتقال إليه
        $mine->assertSee('زميل مشغول');
    }

    /**
     * ⭐ الفعل من الكارت: زرّا «تسليم» و«متعثّر» على **مهامّ المستخدم نفسه** وحدها
     * وفي الحالات المفتوحة، يشيران إلى مسارَي الصفحة نفسهما — لا مسارًا جديدًا.
     * ومهمّة زميلٍ لا زرّ عليها، ومهمّةٌ معتمَدة لا زرّ عليها ولو كانت له.
     */
    public function test_own_open_tasks_carry_the_two_actions_and_others_do_not(): void
    {
        [$user, $tree] = $this->boardFixture();
        $peer = $this->makeUser('زميل');

        $mine = $this->makeTask($tree['item'], TaskStatus::IN_PROGRESS, $user, 10);
        $done = $this->makeTask($tree['item'], TaskStatus::APPROVED, $user, 10);
        $theirs = $this->makeTask($tree['item'], TaskStatus::IN_PROGRESS, $peer, 10);

        $response = $this->actingAs($user)->get(route('volunteer.goals.show', $tree['goal']));
        $response->assertOk();

        // مهمّتي المفتوحة: زرّان يشيران إلى مسارَي التسليم والتعثّر الحقيقيّين
        $response->assertSee('data-url="'.route('volunteer.tasks.deliver', $mine).'"', false);
        $response->assertSee('data-url="'.route('volunteer.tasks.block', $mine).'"', false);

        // لا فعل على المعتمَدة ولا على مهمّة الزميل
        $response->assertDontSee('data-url="'.route('volunteer.tasks.deliver', $done).'"', false);
        $response->assertDontSee('data-url="'.route('volunteer.tasks.deliver', $theirs).'"', false);

        // والبوب-أبان المشتركان موجودان مرّةً واحدة بحقول الصفحة نفسها
        $response->assertSee('id="board-deliver"', false);
        $response->assertSee('id="board-block"', false);
        $response->assertSee('name="reason"', false);
        $response->assertSee('name="link"', false);
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
