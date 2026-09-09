<?php

namespace Tests\Feature\Volunteer\Goals;

use App\Models\Entity;
use App\Models\Track;

/**
 * الاعتماد الأوّل للمشروع التشغيليّ (23 — 1.8، هيدر السطر 5332):
 * «حالة الاعتماد الأوّل» في الهيدر كانت شارةً معلَّقة على `status` العامّ
 * (يُكتب `'active'` دائمًا لحظة الإنشاء) — فتظهر «تمّ» أبدًا بلا فعلٍ خلفها.
 * عمودٌ مستقلّ `approval_status` صار هو الحارس الحقيقيّ.
 */
class OperationalProjectApprovalTest extends GoalsTestCase
{
    public function test_a_new_operational_project_starts_pending_approval(): void
    {
        $entity = $this->makeEntity();
        ['project' => $project] = $this->makeOperationalProject($entity);

        $this->assertSame('pending', $project->fresh()->approval_status);
    }

    public function test_the_track_supervisor_can_approve_it(): void
    {
        $entity = $this->makeEntity();
        ['project' => $project] = $this->makeOperationalProject($entity);

        $supervisor = $this->makeUser('مشرف عام المسار');
        $membership = $this->makeMembership($supervisor, $entity, null, 'track_supervisor');
        $this->grant($supervisor, 'operational_projects.approve', 'TRACK', $membership);

        $this->actingAs($supervisor)
            ->post(route('volunteer.project.approve', $project))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $project->refresh();
        $this->assertSame('approved', $project->approval_status);
        $this->assertNotNull($project->approved_at);
        $this->assertSame($supervisor->id, $project->approved_by);
    }

    public function test_a_track_supervisor_from_a_different_track_cannot_approve_it(): void
    {
        $department = $this->makeEntity('قسم');
        ['project' => $project] = $this->makeOperationalProject($department);

        $governorate = Entity::create([
            'track_id' => Track::query()->where('key', 'governorate')->value('id'),
            'name_ar' => 'محافظة',
            'status' => 'active',
        ]);

        $outsider = $this->makeUser('مشرف مسار تاني');
        $membership = $this->makeMembership($outsider, $governorate, null, 'track_supervisor');
        $this->grant($outsider, 'operational_projects.approve', 'TRACK', $membership);

        $this->actingAs($outsider)
            ->post(route('volunteer.project.approve', $project))
            ->assertForbidden();

        $this->assertSame('pending', $project->fresh()->approval_status);
    }

    public function test_approving_twice_is_rejected(): void
    {
        $entity = $this->makeEntity();
        ['project' => $project] = $this->makeOperationalProject($entity);

        $top = $this->makeUser('مشرف عام التطوّع');
        $membership = $this->makeMembership($top, $entity, null, 'volunteer_gm');
        $this->grant($top, 'operational_projects.approve', 'ALL', $membership);

        $this->actingAs($top)->post(route('volunteer.project.approve', $project))->assertRedirect();
        $this->actingAs($top)->post(route('volunteer.project.approve', $project))->assertStatus(422);
    }

    public function test_a_coordinator_without_the_permission_cannot_approve(): void
    {
        $entity = $this->makeEntity();
        ['project' => $project] = $this->makeOperationalProject($entity);

        $coordinator = $this->makeUser('كوردنيتور');
        $this->makeMembership($coordinator, $entity, null, 'coordinator');

        $this->actingAs($coordinator)
            ->post(route('volunteer.project.approve', $project))
            ->assertForbidden();
    }

    public function test_the_approve_button_shows_only_while_pending_and_only_to_who_can_act(): void
    {
        $entity = $this->makeEntity();
        ['project' => $project] = $this->makeOperationalProject($entity);

        $top = $this->makeUser('مشرف عام التطوّع');
        $topMembership = $this->makeMembership($top, $entity, null, 'volunteer_gm');
        $this->grant($top, 'operational_projects.approve', 'ALL', $topMembership);
        $this->grant($top, 'operational_projects.view', 'ALL', $topMembership);

        $this->actingAs($top)
            ->get(route('volunteer.project', ['entity' => $entity->id]))
            ->assertOk()
            ->assertSee('اعتماد المشروع')
            ->assertSee('بانتظار الاعتماد الأوّل');

        $coordinator = $this->makeUser();
        $this->makeMembership($coordinator, $entity, null, 'coordinator');
        $this->grant($coordinator, 'operational_projects.view', 'ENTITY');

        $this->actingAs($coordinator)
            ->get(route('volunteer.project', ['entity' => $entity->id]))
            ->assertOk()
            ->assertDontSee('اعتماد المشروع');

        $this->actingAs($top)->post(route('volunteer.project.approve', $project))->assertRedirect();

        $this->actingAs($top)
            ->get(route('volunteer.project', ['entity' => $entity->id]))
            ->assertOk()
            ->assertDontSee('اعتماد المشروع')
            ->assertSee('الاعتماد الأوّل تمّ');
    }
}
