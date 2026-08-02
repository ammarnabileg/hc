<?php

namespace Tests\Feature\Volunteer\Org;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Position;
use App\Models\User;
use App\Services\Volunteer\Org\CapacityReport;
use App\Services\Volunteer\Org\DepartmentScope;

/**
 * السعة (13.4-ف): **مؤشّرات لا موانع** — لا توقف تسكينًا ولا ترقيةً ولا نقلًا.
 * والشاشة **تقرأ فقط**: لا شاشات ضبط قيم فيها.
 */
class CapacityIsNeverBlockingTest extends OrgTestCase
{
    public function test_regular_member_cannot_open_capacity(): void
    {
        $member = $this->actorWithRole('VOL-C1', 'coordinator');

        $this->actingAs($member)->get(route('volunteer.capacity'))->assertForbidden();
    }

    public function test_banner_states_that_capacity_never_blocks(): void
    {
        $director = $this->actorWithRole('VOL-DIR', 'director');

        $this->actingAs($director)
            ->get(route('volunteer.capacity'))
            ->assertOk()
            ->assertSee((string) setting('volunteer.capacity.banner'), false)
            ->assertSee((string) setting('volunteer.capacity.difference_line'), false);
    }

    /** التجاوز فوق `span_max` **تنبيهٌ فقط** — والتسكين يتمّ ويظهر في كلّ الشاشات */
    public function test_exceeding_span_of_control_still_allows_placement(): void
    {
        $director = $this->actorWithRole('VOL-DIR', 'director');
        $directorMembership = $this->membershipOf('VOL-DIR');
        $supervisor = Position::where('key', 'supervisor')->firstOrFail();
        $entity = Entity::where('name_ar', 'التصميم')->firstOrFail();
        $max = (int) Position::where('key', 'director')->value('span_max');

        // تسكين يتجاوز الحدّ الأقصى بوضوح — ولا شيء في النظام يمنعه
        for ($i = 1; $i <= $max + 3; $i++) {
            $extra = User::create([
                'name' => 'مشرف إضافيّ '.$i,
                'email' => 'extra'.$i.'@demo.local',
                'password' => 'secret-password',
                'code' => 'EXTRA-'.$i,
                'status' => 'active',
            ]);

            Membership::create([
                'user_id' => $extra->id,
                'entity_id' => $entity->id,
                'position_id' => $supervisor->id,
                'upline_id' => $directorMembership->id,
                'started_at' => now(),
                'status' => 'active',
            ]);
        }

        $this->assertDatabaseHas('memberships', ['user_id' => User::where('code', 'EXTRA-1')->value('id')]);

        $scope = app(DepartmentScope::class);
        $root = $scope->rootFor($director);
        $memberships = $scope->memberships($scope->entityIds($root));

        $holders = app(CapacityReport::class)->spanTable($memberships)
            ->firstWhere('position', 'دايركتور')['holders'];

        $this->assertSame('danger', collect($holders)->firstWhere('name', $director->shortName())['state']);
        $this->assertSame('تجاوز', collect($holders)->firstWhere('name', $director->shortName())['state_label']);

        // والأعضاء الجدد يظهرون عاديًّا — لا حجب ولا تعطيل
        $this->actingAs($director)->get(route('volunteer.capacity'))->assertOk();
        $this->actingAs($director)->get(route('volunteer.department'))->assertOk();
    }

    /** لا شاشات ضبط قيم هنا — الأرقام تُقرأ فقط (13.4-ف) */
    public function test_capacity_screen_has_no_write_actions(): void
    {
        $director = $this->actorWithRole('VOL-DIR', 'director');

        foreach (['span', 'occupancy', 'gaps', 'loads'] as $tab) {
            $html = $this->actingAs($director)->get(route('volunteer.capacity', ['tab' => $tab]))->getContent();

            $this->assertStringNotContainsString('method="post"', mb_strtolower($html));
        }
    }

    public function test_loads_put_the_least_loaded_first_as_a_suggestion_only(): void
    {
        $director = $this->actorWithRole('VOL-DIR', 'director');

        $loads = $this->actingAs($director)
            ->get(route('volunteer.capacity', ['tab' => 'loads']))
            ->viewData('loads');

        $teams = collect($loads)->pluck('team')->all();
        $sorted = $teams;
        sort($sorted);

        $this->assertSame($sorted, $teams, 'الأقلّ حملًا أوّلًا');
        $this->assertSame(
            (string) setting('volunteer.capacity.load_suggestion'),
            collect($loads)->first()['suggestion_note'],
        );
    }
}
