<?php

namespace Tests\Feature\Volunteer\Core;

use App\Models\Task;

/**
 * لوحة المهام العامّة (24.4-2):
 * ⛔ **لا زرّ إضافة هنا** — الإضافة للأدمن ومشرف عام التطوّع حصرًا، وللقادة ترشيح بند.
 * ⭐ وبلوغ السقف يترك الكروت مقروءة والزرّ معطَّلًا بسطر يشرح.
 */
class PublicBoardTest extends VolunteerCoreTestCase
{
    public function test_board_has_no_add_button_and_shows_cards(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);
        $this->grant($user, ['public_board.list', 'public_board.view', 'tasks.list', 'tasks.view']);

        Task::create([
            'title' => 'غطِّ فعاليّة السبت',
            'entity_id' => $entity->id,
            'status' => 'in_progress',
            'source' => 'public_board',
            'deadline_at' => now()->addDays(3),
            'vxp_value' => 30,
        ]);

        $response = $this->actingAs($user)->get(route('volunteer.tasks.board'));

        $response->assertOk();
        $response->assertSee('غطِّ فعاليّة السبت');
        $response->assertSee('اسحب المهمّة');
        $response->assertDontSee('مهمّة جديدة');
        $response->assertDontSee('أضِف مهمّة عامّة');
    }

    public function test_reaching_the_cap_keeps_cards_readable_and_disables_the_pull_button(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity); // كوردنيتور: سقفه 3
        $this->grant($user, ['public_board.list', 'public_board.view', 'tasks.list', 'tasks.view']);

        for ($i = 0; $i < 3; $i++) {
            $this->makeTask($user, $entity);
        }

        Task::create([
            'title' => 'فرّغ محضر الاجتماع',
            'entity_id' => $entity->id,
            'status' => 'in_progress',
            'source' => 'public_board',
            'deadline_at' => now()->addDays(2),
        ]);

        $response = $this->actingAs($user)->get(route('volunteer.tasks.board'));

        $response->assertOk();
        $response->assertSee('فرّغ محضر الاجتماع'); // الكارت مقروء
        $response->assertSee('disabled', false);
        $response->assertSee('وصلت لسقف انشغالك (3/3)', false);
    }

    public function test_nomination_button_is_hidden_without_permission(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);
        $this->grant($user, ['public_board.list', 'tasks.list']);

        $this->actingAs($user)
            ->get(route('volunteer.tasks.board'))
            ->assertOk()
            ->assertDontSee('رشّح بندًا');
    }

    public function test_claiming_works_below_the_cap(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);
        $this->grant($user, ['public_board.list', 'public_board.view', 'tasks.list', 'tasks.view']);

        $task = Task::create([
            'title' => 'مهمّة متاحة',
            'entity_id' => $entity->id,
            'status' => 'in_progress',
            'source' => 'public_board',
            'deadline_at' => now()->addDays(3),
        ]);

        $this->actingAs($user)
            ->post(route('volunteer.tasks.claim', $task))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('volunteer.tasks.show', $task));

        $this->assertSame($user->id, $task->refresh()->owner_id);
    }

    /**
     * ⭐ بند متكرّر رُشِّح «مهمّة عامّة» (23 — 1.8): `RecurringGenerator` يولّد
     * صفّه بـ`source='recurring'` لا `'public_board'` — عمدًا، فيبقى مرئيًّا
     * لـ`markMissed()` لو فات — لكنّه يبقى **بلا مالك** بالضبط كالمهمّة العامّة
     * المباشرة، فيستحقّ نفس الظهور ونفس السحب.
     */
    public function test_a_general_recurring_item_appears_on_the_board_and_can_be_claimed(): void
    {
        $user = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);
        $this->grant($user, ['public_board.list', 'public_board.view', 'tasks.list', 'tasks.view']);

        $task = Task::create([
            'title' => 'بوست يوميّ على الصفحة',
            'entity_id' => $entity->id,
            'status' => 'in_progress',
            'source' => 'recurring',
            'deadline_at' => now()->addDay(),
            'vxp_value' => 25,
        ]);

        $this->actingAs($user)
            ->get(route('volunteer.tasks.board'))
            ->assertOk()
            ->assertSee('بوست يوميّ على الصفحة');

        $this->actingAs($user)
            ->post(route('volunteer.tasks.claim', $task))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('volunteer.tasks.show', $task));

        $this->assertSame($user->id, $task->refresh()->owner_id);
    }

    /** ومهمّة متكرّرة عاديّة (مُسنَدة لصاحبها) لا تظهر على اللوحة ولا تُسحَب — الحارس هو غياب المالك لا نوع المصدر وحده */
    public function test_a_recurring_task_with_an_owner_is_not_claimable_from_the_board(): void
    {
        $user = $this->makeUser();
        $owner = $this->makeUser();
        $entity = $this->makeEntity();
        $this->makeMembership($user, $entity);
        $this->grant($user, ['public_board.list', 'public_board.view', 'tasks.list', 'tasks.view']);

        $task = Task::create([
            'title' => 'تقرير أسبوعيّ للمحتوى',
            'entity_id' => $entity->id,
            'owner_id' => $owner->id,
            'status' => 'in_progress',
            'source' => 'recurring',
            'deadline_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->get(route('volunteer.tasks.board'))
            ->assertOk()
            ->assertDontSee('تقرير أسبوعيّ للمحتوى');

        $this->actingAs($user)
            ->post(route('volunteer.tasks.claim', $task))
            ->assertSessionHasErrors('claim');
    }
}
