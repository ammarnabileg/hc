<?php

namespace Tests\Feature\Volunteer\People;

use App\Models\InternalLibraryItem;
use App\Models\Interview;
use App\Models\LearningPath;
use App\Models\RecruitmentCandidate;

/**
 * كلّ شاشة رئيسيّة في المجال تُفتَح لمن يملكها، وتُمنَع عمّن لا يملكها (12.2.1 · BUILD §6).
 */
class PeopleScreensTest extends PeopleTestCase
{
    public function test_interviews_screen_renders(): void
    {
        $user = $this->userWith(['interviews.list', 'interviews.view', 'interviews.create', 'scorecards.view']);

        $this->actingAs($user)->get(route('volunteer.interviews'))->assertOk()->assertSee('المقابلات');
    }

    public function test_scorecard_screen_renders_with_criteria(): void
    {
        $user = $this->userWith(['scorecards.view', 'scorecards.create', 'scorecards.edit']);

        $candidate = RecruitmentCandidate::create([
            'user_id' => $this->makeUser('مرشّح')->id,
            'stage' => 'interview',
            'applied_at' => now()->subDay(),
        ]);

        $interview = Interview::create([
            'recruitment_candidate_id' => $candidate->id,
            'interviewer_id' => $user->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $this->actingAs($user)
            ->get(route('volunteer.interviews.scorecard', $interview))
            ->assertOk()
            ->assertSee('الأقسام المناسبة')
            ->assertSee('الدرجة الإجماليّة');
    }

    public function test_placement_screen_renders_both_columns(): void
    {
        $user = $this->userWith(['placements.list', 'placements.create']);

        RecruitmentCandidate::create([
            'user_id' => $this->makeUser('مرشّح نهائيّ')->id,
            'stage' => 'final_list',
            'qualifying_score' => 90,
            'applied_at' => now()->subDays(9),
        ]);

        $this->actingAs($user)
            ->get(route('volunteer.placement'))
            ->assertOk()
            ->assertSee('القائمة النهائيّة والتسكين')
            ->assertSee('الأحدث أوّلًا');
    }

    public function test_academy_screens_render(): void
    {
        $user = $this->userWith(['academy_paths.list', 'academy_paths.view', 'academy_recordings.list', 'academy_recordings.view']);
        $this->makeMembership($user, $this->makeEntity('قسم الأكاديمية'));

        $this->actingAs($user)->get(route('volunteer.academy'))->assertOk()->assertSee('التدريبات');
        $this->actingAs($user)->get(route('volunteer.academy.recordings'))->assertOk()->assertSee('التسجيلات');

        $path = LearningPath::create([
            'slug' => 'p-'.str()->random(6),
            'name_ar' => 'مسار للعرض',
            'status' => 'published',
            'is_academy' => true,
        ]);

        $this->actingAs($user)->get(route('volunteer.academy.path', $path))->assertOk()->assertSee('مسار للعرض');
    }

    public function test_internal_library_screen_renders_and_says_indexing_is_automatic(): void
    {
        $user = $this->userWith(['internal_library.list', 'internal_library.view']);

        $this->actingAs($user)
            ->get(route('volunteer.library'))
            ->assertOk()
            ->assertSee('الفهرسة آليّة لحظة الاعتماد')
            ->assertDontSee('رفع ملفّ');

        $item = InternalLibraryItem::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('volunteer.library.show', $item))
            ->assertOk()
            ->assertSee($item->title);
    }

    public function test_candidate_detail_fragment_renders_for_the_recruitment_team(): void
    {
        $user = $this->userWith(['candidates.list', 'candidates.view']);

        $candidate = RecruitmentCandidate::create([
            'user_id' => $this->makeUser('مرشّح التفاصيل')->id,
            'stage' => 'screening',
            'qualifying_score' => 77,
            'applied_at' => now()->subDays(6),
        ]);

        $this->actingAs($user)
            ->get(route('volunteer.recruitment.show', $candidate))
            ->assertOk()
            ->assertSee('مرشّح التفاصيل')
            ->assertSee('مدّة الانتظار');
    }

    public function test_kudos_and_thanks_wall_render(): void
    {
        $user = $this->userWith(['kudos.view', 'kudos.create', 'thanks_wall.view', 'thanks_wall.create']);

        $this->actingAs($user)->get(route('volunteer.kudos'))->assertOk()->assertSee('Kudos');
        $this->actingAs($user)->get(route('volunteer.kudos.wall'))->assertOk()->assertSee('حائط الشكر');
    }

    public function test_screens_are_hidden_behind_permissions(): void
    {
        $plain = $this->makeUser('بلا صلاحيّات');

        foreach ([
            route('volunteer.interviews'),
            route('volunteer.placement'),
            route('volunteer.academy'),
            route('volunteer.library'),
            route('volunteer.kudos'),
            route('volunteer.kudos.wall'),
        ] as $url) {
            $this->actingAs($plain)->get($url)->assertForbidden();
        }
    }
}
