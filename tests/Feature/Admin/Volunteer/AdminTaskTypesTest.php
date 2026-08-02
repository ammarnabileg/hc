<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\TaskType;

/**
 * شاشة أنواع المهامّ (23-0.3): الأنواع الثمانية موجودة بقوالبها،
 * والشاشة تضيف وتعدّل — فلا يبقى الكتالوج رقمًا محروقًا في سيدر (2.13).
 */
class AdminTaskTypesTest extends AdminVolunteerTestCase
{
    /** الأنواع الثمانية المنصوصة تُزرَع في مسار الإنتاج بتشيك ليست وقيم مقترحة */
    public function test_the_eight_constitutional_types_ship_with_their_templates(): void
    {
        foreach (['execution', 'content', 'design', 'recruitment', 'followup', 'support', 'research', 'training'] as $key) {
            $this->assertDatabaseHas('task_types', ['key' => $key]);
        }

        $content = TaskType::query()->where('key', 'content')->firstOrFail();

        $this->assertSame(['مسودّة', 'مراجعة لغويّة', 'مطابقة الهويّة'], $content->checklist);
        $this->assertSame('file', $content->default_delivery_kind);
        $this->assertNotEmpty($content->default_deliverable_spec);
    }

    public function test_the_screen_opens_and_saves_a_new_type_with_a_checklist(): void
    {
        $admin = $this->grant($this->makeUser(), 'task_types.list', 'task_types.manage');

        $this->actingAs($admin)
            ->get(route('admin.volunteer.task-types.index'))
            ->assertOk()
            ->assertSee('أنواع المهامّ');

        $this->actingAs($admin)
            ->post(route('admin.volunteer.task-types.save'), [
                'key' => 'field_visit',
                'name_ar' => 'زيارة ميدانيّة',
                'checklist' => "تحضير\nالزيارة\nتقرير",
                'default_vxp' => 120,
                'default_delivery_kind' => 'file',
            ])
            ->assertRedirect(route('admin.volunteer.task-types.index'));

        $type = TaskType::query()->where('key', 'field_visit')->firstOrFail();

        $this->assertSame(['تحضير', 'الزيارة', 'تقرير'], $type->checklist);
        $this->assertSame('120.00', (string) $type->default_vxp);
    }

    /** ⛔ ولا حذف: المهامّ القديمة موسومة به — الإيقاف وحده */
    public function test_a_type_is_switched_off_not_deleted(): void
    {
        $admin = $this->grant($this->makeUser(), 'task_types.list', 'task_types.manage');
        $type = TaskType::query()->where('key', 'support')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.volunteer.task-types.toggle', $type))
            ->assertRedirect();

        $this->assertFalse((bool) $type->refresh()->is_active);
        $this->assertDatabaseHas('task_types', ['key' => 'support']);
    }

    public function test_the_screen_is_hidden_from_whoever_does_not_own_it(): void
    {
        $this->actingAs($this->makeUser())
            ->get(route('admin.volunteer.task-types.index'))
            ->assertForbidden();
    }
}
