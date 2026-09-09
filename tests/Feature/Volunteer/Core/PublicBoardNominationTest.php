<?php

namespace Tests\Feature\Volunteer\Core;

/**
 * اعتماد/رفض ترشيح «مهمّة عامّة» (23 — 1.8 · 8.1):
 * الترشيح وحده لا يغيّر شيئًا — الاعتماد وحده يحوّل `audience_mode` فعليًّا،
 * ومشرف عام التطوّع والأدمن (`public_board.create`) هما مَن يقرّران، لا القادة.
 */
class PublicBoardNominationTest extends VolunteerCoreTestCase
{
    public function test_pending_nominations_are_visible_to_approvers_only(): void
    {
        $entity = $this->makeEntity();
        $item = $this->makeWorkItem($entity);
        $item->forceFill(['is_public_board_candidate' => true, 'name' => 'بوست أسبوعيّ'])->save();

        $approver = $this->makeUser();
        $this->makeMembership($approver, $entity);
        $this->grant($approver, ['public_board.list', 'public_board.create']);

        $this->actingAs($approver)
            ->get(route('volunteer.tasks.board'))
            ->assertOk()
            ->assertSee('ترشيحات معلَّقة')
            ->assertSee('بوست أسبوعيّ')
            ->assertSee('اعتماد')
            ->assertSee('رفض');

        $lead = $this->makeUser();
        $this->makeMembership($lead, $entity);
        $this->grant($lead, ['public_board.list']);

        $this->actingAs($lead)
            ->get(route('volunteer.tasks.board'))
            ->assertOk()
            ->assertDontSee('ترشيحات معلَّقة');
    }

    public function test_approving_a_nomination_makes_the_item_a_real_public_task_audience(): void
    {
        $entity = $this->makeEntity();
        $item = $this->makeWorkItem($entity);
        $item->forceFill(['is_public_board_candidate' => true])->save();

        $approver = $this->makeUser();
        $this->makeMembership($approver, $entity);
        $this->grant($approver, ['public_board.list', 'public_board.create']);

        $this->actingAs($approver)
            ->post(route('volunteer.tasks.nominations.approve', $item))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $item->refresh();
        $this->assertSame('public_board', $item->audience_mode);
        $this->assertFalse($item->is_public_board_candidate);
    }

    public function test_rejecting_a_nomination_clears_the_flag_without_touching_the_audience(): void
    {
        $entity = $this->makeEntity();
        $item = $this->makeWorkItem($entity);
        $item->forceFill(['is_public_board_candidate' => true, 'audience_mode' => 'rotation'])->save();

        $approver = $this->makeUser();
        $this->makeMembership($approver, $entity);
        $this->grant($approver, ['public_board.list', 'public_board.create']);

        $this->actingAs($approver)
            ->post(route('volunteer.tasks.nominations.reject', $item))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $item->refresh();
        $this->assertSame('rotation', $item->audience_mode);
        $this->assertFalse($item->is_public_board_candidate);
    }

    public function test_a_lead_without_public_board_create_cannot_approve_or_reject(): void
    {
        $entity = $this->makeEntity();
        $item = $this->makeWorkItem($entity);
        $item->forceFill(['is_public_board_candidate' => true])->save();

        $lead = $this->makeUser();
        $this->makeMembership($lead, $entity);
        $this->grant($lead, ['public_board.list', 'work_packages.edit']);

        $this->actingAs($lead)
            ->post(route('volunteer.tasks.nominations.approve', $item))
            ->assertForbidden();

        $this->actingAs($lead)
            ->post(route('volunteer.tasks.nominations.reject', $item))
            ->assertForbidden();

        $this->assertTrue($item->fresh()->is_public_board_candidate);
    }

    /** بند اتعمد ترشيحه خلاص (audience_mode=public_board بالفعل) ما يترشّحش تاني في اللستة */
    public function test_the_nomination_picker_excludes_items_already_approved_as_public(): void
    {
        $entity = $this->makeEntity();

        $already = $this->makeWorkItem($entity);
        $already->forceFill(['name' => 'بند عامّ بالفعل', 'audience_mode' => 'public_board'])->save();

        $eligible = $this->makeWorkItem($entity);
        $eligible->forceFill(['name' => 'بند قابل للترشيح'])->save();

        $lead = $this->makeUser();
        $this->makeMembership($lead, $entity);
        $this->grant($lead, ['public_board.list', 'work_packages.edit']);

        $response = $this->actingAs($lead)->get(route('volunteer.tasks.board'));

        $response->assertOk();
        $response->assertSee('بند قابل للترشيح');
        $response->assertDontSee('بند عامّ بالفعل');
    }
}
