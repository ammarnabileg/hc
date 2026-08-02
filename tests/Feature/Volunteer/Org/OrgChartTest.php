<?php

namespace Tests\Feature\Volunteer\Org;

/**
 * كانفاس الهيكل (13.4-م-3): يفتح موسّطًا عليّ، ويحمل حِمل الأبلاين ومؤشّر الإشغال،
 * وعلى الموبايل قائمة شجريّة — وكلّ الرسم بأيدينا بلا أيّ مكتبة خارجيّة.
 */
class OrgChartTest extends OrgTestCase
{
    public function test_chart_marks_the_viewer_node_for_centering(): void
    {
        $user = $this->actorWithRole('VOL-TL1', 'team_leader');

        $chart = $this->actingAs($user)->get(route('volunteer.org'))->viewData('chart');
        $me = collect($chart['nodes'])->firstWhere('is_me', true);

        $this->assertNotNull($chart['me']);
        $this->assertSame($chart['me'], $me['id']);
    }

    public function test_nodes_carry_load_occupancy_and_club_frame(): void
    {
        $user = $this->actorWithRole('VOL-DIR', 'director');

        $nodes = collect($this->actingAs($user)->get(route('volunteer.org'))->viewData('chart')['nodes']);
        $director = $nodes->firstWhere('code', 'VOL-DIR');
        $club = $nodes->firstWhere('code', 'VOL-SUP1');

        $this->assertSame(3, $director['load'], 'حِمل الأبلاين = عدد مَن تحته مباشرةً');
        $this->assertIsInt($director['occupancy']);
        $this->assertContains($director['occupancy_state'], ['ok', 'warn', 'danger']);
        $this->assertTrue($club['is_club'], 'حدّ ذهبيّ لعضو نادي التميّز');
    }

    public function test_collapse_threshold_and_default_depth_come_from_settings(): void
    {
        $user = $this->actorWithRole('VOL-DIR', 'director');

        $chart = $this->actingAs($user)->get(route('volunteer.org'))->viewData('chart');

        $this->assertSame((int) setting('volunteer.org.collapse_threshold'), $chart['collapse_threshold']);
        $this->assertSame((int) setting('volunteer.org.default_depth'), $chart['default_depth']);
    }

    public function test_mobile_tree_is_rendered_as_a_collapsible_list(): void
    {
        $user = $this->actorWithRole('VOL-DIR', 'director');

        $response = $this->actingAs($user)->get(route('volunteer.org'));
        $tree = $response->viewData('tree');

        $this->assertNotEmpty($tree);
        $this->assertArrayHasKey('children', $tree[0]);
        $response->assertSee('<details', false);
    }

    /** ممنوع أيّ مكتبة رسم أو كانفاس خارجيّة (2.16-ج) */
    public function test_page_loads_no_external_drawing_library(): void
    {
        $user = $this->actorWithRole('VOL-DIR', 'director');

        $html = $this->actingAs($user)->get(route('volunteer.org'))->getContent();

        foreach (['d3.', 'cytoscape', 'jsplumb', 'gojs', 'mermaid', 'chart.js', 'cdn.jsdelivr', 'unpkg.com'] as $needle) {
            $this->assertStringNotContainsString($needle, mb_strtolower($html));
        }
    }

    public function test_node_popup_is_scoped_to_the_department(): void
    {
        $user = $this->actorWithRole('VOL-DIR', 'director');
        $target = $this->membershipOf('VOL-C3');

        $this->actingAs($user)
            ->getJson(route('volunteer.org.node', $target))
            ->assertOk()
            ->assertJsonStructure(['name', 'code', 'position', 'service_duration', 'profile_url']);
    }
}
