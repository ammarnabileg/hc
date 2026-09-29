<?php

namespace Tests\Feature\Ui;

use App\Services\Ui\CommandIndex;
use Tests\Feature\Volunteer\Goals\GoalsTestCase;

/**
 * ⭐ البحث الموحّد (Ctrl+K) يعرف شاشات التطوّع كما يعرفها السايد بار (2.15-د · 13.4-ح).
 *
 * ⛔ ما قبله: الفهرس لم يحمل من التطوّع إلّا «لوحة التطوّع»، فكتابة «مهامي» أو
 * «الأهداف» كانت تنتهي إلى «مفيش نتيجة» — والمطابقة كانت تشترط الحركات حرفًا
 * بحرف، فـ«الاهداف» بلا همزة لا تجد «الأهداف والمَعالِم».
 */
class CommandIndexVolunteerTest extends GoalsTestCase
{
    public function test_volunteer_screens_are_found_by_a_member_who_holds_their_keys(): void
    {
        $entity = $this->makeEntity();
        $user = $this->makeUser();
        $membership = $this->makeMembership($user, $entity, null, 'director');
        $this->grant($user, 'user_profile.view'); // باب البحث الموحّد نفسه
        $this->grant($user, 'tasks.list', 'ENTITY', $membership);
        $this->grant($user, 'goals.list', 'ENTITY', $membership);

        // «مهام» بلا شدّة تجد «مهامّي»، ومجموعتها تظهر تلميحًا لا كلمة «صفحة» العامّة
        $pages = collect($this->actingAs($user)->getJson(route('ui.palette', ['q' => 'مهام']))->json('pages'));
        $mine = $pages->firstWhere('url', route('volunteer.tasks.index'));

        $this->assertNotNull($mine, 'مهامّي غائبة عن نتائج البحث الموحّد');
        $this->assertSame(setting('nav.volunteer.group_tasks', 'المهام'), $mine['hint']);

        // «الاهداف» بلا همزة تجد «الأهداف والمَعالِم»
        $goals = collect($this->actingAs($user)->getJson(route('ui.palette', ['q' => 'الاهداف']))->json('pages'));
        $this->assertTrue($goals->contains('url', route('volunteer.goals')));
    }

    public function test_volunteer_screens_stay_hidden_from_a_member_without_their_keys(): void
    {
        $entity = $this->makeEntity();
        $user = $this->makeUser();
        $membership = $this->makeMembership($user, $entity, null, 'director');
        $this->grant($user, 'user_profile.view');
        $this->grant($user, 'goals.list', 'ENTITY', $membership);

        // يملك الأهداف لا المهامّ: «مهام» لا تعرض «مهامّي» ولا «لوحة المهام العامّة»
        $pages = collect($this->actingAs($user)->getJson(route('ui.palette', ['q' => 'مهام']))->json('pages'));

        $this->assertFalse($pages->contains('url', route('volunteer.tasks.index')));
        $this->assertFalse($pages->contains('url', route('volunteer.tasks.board')));
    }

    public function test_the_index_mirrors_every_row_of_the_volunteer_sidebar(): void
    {
        // السايد بار مصدر الحقيقة: كلّ مسارٍ فيه له سطرٌ في الفهرس والعكس
        $sidebar = file_get_contents(resource_path('views/partials/sidebar-volunteer.blade.php'));
        preg_match_all("/'route' => '(volunteer\\.[a-z_.]+)'/", $sidebar, $m);

        $indexed = array_keys(app(CommandIndex::class)->volunteerPages());

        $this->assertNotEmpty($m[1]);
        $this->assertEqualsCanonicalizing(array_unique($m[1]), $indexed);
    }

    public function test_normalization_ignores_marks_and_unifies_letters(): void
    {
        $this->assertSame('الاهداف والمعالم', CommandIndex::normalize('الأهداف والمَعالِم'));
        $this->assertSame('مهامي', CommandIndex::normalize('مهامّي'));
        $this->assertSame('مكتبه', CommandIndex::normalize('مكتبة'));
    }
}
