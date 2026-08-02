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
}
