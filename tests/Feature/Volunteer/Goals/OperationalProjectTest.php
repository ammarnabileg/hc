<?php

namespace Tests\Feature\Volunteer\Goals;

use App\Models\Entity;
use App\Models\Goal;
use App\Models\Project;
use App\Models\Track;
use App\Services\Volunteer\Goals\FileDrafts;
use App\Services\Volunteer\Goals\OperationalProject;

/**
 * ⭐ «المشروع التشغيليّ لقسم [الاسم]» (23 — 1.8): «واحد لكلّ كيان رئيسي بأيّ
 * مسار … يُنشأ تلقائيًّا مع إنشاء الكيان وموجود دائمًا لا يُغلَق». كان
 * الموازن والتوليد الآليّ ومسار عدم التسليم للفائتة (`RecurringGenerator` ·
 * `NoDeliverySweeper`) مبنيّين ومختبَرين بالكامل — ولا مسار إنشاء كيانٍ
 * حقيقيّ في المنصّة يفتح لها وعاءها، فتبقى شاشة المشروع التشغيليّ فارغةً
 * للأبد على أيّ تنصيبٍ حقيقيّ.
 */
class OperationalProjectTest extends GoalsTestCase
{
    private function subEntity(Entity $parent, string $name = 'فرعي'): Entity
    {
        return Entity::create([
            'track_id' => $parent->track_id,
            'parent_id' => $parent->id,
            'name_ar' => $name,
            'status' => 'active',
        ]);
    }

    public function test_a_main_entity_gets_a_permanent_operational_project(): void
    {
        $entity = $this->makeEntity('قسم الإعلام');

        $project = app(OperationalProject::class)->ensureFor($entity);

        $this->assertNotNull($project);
        $this->assertSame($entity->id, $project->entity_id);
        $this->assertSame('operational', $project->type);
        $this->assertTrue($project->is_permanent);
        $this->assertSame('active', $project->status);
        $this->assertStringContainsString('قسم الإعلام', $project->name);

        $this->assertDatabaseHas('work_packages', [
            'project_id' => $project->id,
            'entity_id' => $entity->id,
        ]);
    }

    public function test_a_sub_entity_gets_no_operational_project_of_its_own(): void
    {
        $main = $this->makeEntity('قسم رئيسي');
        $sub = $this->subEntity($main, 'قسم فرعي');

        $project = app(OperationalProject::class)->ensureFor($sub);

        $this->assertNull($project);
        $this->assertDatabaseMissing('projects', ['entity_id' => $sub->id]);
    }

    public function test_calling_it_twice_does_not_create_a_second_project(): void
    {
        $entity = $this->makeEntity();

        $first = app(OperationalProject::class)->ensureFor($entity);
        $second = app(OperationalProject::class)->ensureFor($entity);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Project::query()->where('entity_id', $entity->id)->where('type', 'operational')->count());
    }

    // ------------------------------------------------------------ الربط الحقيقيّ

    public function test_admin_creating_a_main_entity_gets_a_real_operational_project(): void
    {
        $top = $this->makeUser('مشرف عام التطوّع');
        $membership = $this->makeMembership($top, $this->makeEntity('قسم قائم'), null, 'volunteer_gm');
        $this->grant($top, 'org_chart.edit', 'ALL', $membership);

        $this->actingAs($top)->post(route('admin.volunteer.org.entity.save'), [
            'track_id' => Track::query()->where('key', 'department')->value('id'),
            'name_ar' => 'قسم جديد كليًّا',
        ])->assertRedirect();

        $entity = Entity::query()->where('name_ar', 'قسم جديد كليًّا')->firstOrFail();

        $this->assertDatabaseHas('projects', ['entity_id' => $entity->id, 'type' => 'operational']);
    }

    public function test_editing_an_existing_entity_does_not_duplicate_its_project(): void
    {
        $top = $this->makeUser('مشرف عام التطوّع');
        $entity = $this->makeEntity('قسم قائم بالفعل');
        $membership = $this->makeMembership($top, $entity, null, 'volunteer_gm');
        $this->grant($top, 'org_chart.edit', 'ALL', $membership);

        app(OperationalProject::class)->ensureFor($entity);
        $this->assertSame(1, Project::query()->where('entity_id', $entity->id)->count());

        $this->actingAs($top)->post(route('admin.volunteer.org.entity.save'), [
            'id' => $entity->id,
            'track_id' => $entity->track_id,
            'name_ar' => 'قسم قائم بالفعل — بعد التعديل',
        ])->assertRedirect();

        $this->assertSame(1, Project::query()->where('entity_id', $entity->id)->count());
    }

    /** ملفٌّ مؤقّت (23 — 1.2 · 1.6): المشروع التشغيليّ يُنشأ لحظة التفعيل — لا لحظة كتابة المسودّة */
    public function test_a_case_file_gets_its_operational_project_only_when_activated(): void
    {
        $department = $this->makeEntity('قسم الإعلام');
        $top = $this->makeUser('مشرف عام التطوّع');
        $topMembership = $this->makeMembership($top, $department, null, 'volunteer_gm');

        foreach (['goals.create', 'goals.view', 'goals.approve', 'milestones.edit', 'milestones.create', 'work_packages.create'] as $key) {
            $this->grant($top, $key, 'ALL', $topMembership);
        }

        $filesSupervisor = $this->makeUser('مشرف عام الملفّات');
        $fileTrackId = Track::query()->where('key', 'case_file')->value('id');
        $fileEntity = Entity::create([
            'track_id' => $fileTrackId,
            'name_ar' => 'ملفّ قائم',
            'status' => 'active',
            'opened_at' => now()->subMonth(),
        ]);
        $filesMembership = $this->makeMembership($filesSupervisor, $fileEntity, null, 'track_supervisor');

        foreach (['milestones.create', 'work_packages.create', 'milestones.edit', 'goals.view'] as $key) {
            $this->grant($filesSupervisor, $key, 'TRACK', $filesMembership);
        }

        $goal = Goal::create([
            'name' => 'هدف تغطية',
            'verification_type' => 'numeric',
            'target_from' => 0,
            'target_to' => 100,
            'end_date' => now()->addMonths(2)->toDateString(),
            'status' => 'draft',
        ]);

        $draft = app(FileDrafts::class)->create($filesSupervisor, $goal, 'ملفّ التغطية الميدانيّة');

        // المسودّة موجودة ولا مشروع تشغيليّ لها بعد
        $this->assertDatabaseMissing('projects', ['entity_id' => $draft->id]);

        app(FileDrafts::class)->activate($goal);

        $this->assertDatabaseHas('projects', ['entity_id' => $draft->id, 'type' => 'operational']);
    }
}
