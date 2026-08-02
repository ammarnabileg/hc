<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\LearningPath;
use App\Models\User;
use App\Services\Learning\ProgressService;

/**
 * اختبار Feature لكلّ شاشة رئيسيّة في المجال (دليل البناء 6):
 * تدريباتي · صفحة التدريب · صفحة الدرس · المسارات · صفحة المسار.
 */
class LearningScreensTest extends LearningTestCase
{
    public function test_my_courses_shows_an_empty_state_with_one_line_and_one_button(): void
    {
        $this->actingAs($this->trainee())
            ->get(route('learning.courses'))
            ->assertOk()
            ->assertSee(setting('learning.courses.title'))
            ->assertSee(setting('learning.empty.courses'));
    }

    public function test_my_courses_lists_owned_courses_with_progress_and_deadline(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 4);
        $this->enroll($user, $course);

        $this->actingAs($user)
            ->get(route('learning.courses'))
            ->assertOk()
            ->assertSee($course->name_ar)
            ->assertSee(setting('learning.deadline.left_prefix'))
            ->assertSee(setting('learning.xp.suffix'));
    }

    public function test_course_page_shows_roadmap_exam_block_and_report_action(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 3);
        $this->enroll($user, $course);

        $this->actingAs($user)
            ->get(route('learning.course', $course))
            ->assertOk()
            ->assertSee(setting('learning.course.outline_title'))
            ->assertSee(setting('learning.exam.block_title'))
            ->assertSee(setting('learning.report.cta'))
            ->assertSee(setting('learning.ghost.title'));
    }

    public function test_lesson_page_renders_the_player_panel_and_navigation(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 3, forcedOrder: false);
        $this->enroll($user, $course);
        $lessons = $this->lessonsOf($course);

        $this->actingAs($user)
            ->get(route('learning.lesson', [$course, $lessons[1]]))
            ->assertOk()
            ->assertSee($lessons[1]->title_ar)
            ->assertSee(setting('learning.lesson.panel_title'))
            ->assertSee(setting('learning.cta.next'))
            ->assertSee(setting('learning.cta.previous'));
    }

    public function test_youtube_lesson_embeds_an_iframe_without_any_external_sdk(): void
    {
        $user = $this->trainee();
        $course = Course::where('slug', 'maharat-al-tawasul')->firstOrFail();
        $this->enroll($user, $course);

        $response = $this->actingAs($user)
            ->get(route('learning.lesson', [$course, $this->firstLessonId($course)]))
            ->assertOk();

        $response->assertSee('<iframe', false);
        $response->assertSee(setting('learning.video.embed_base'), false);
        $response->assertDontSee('youtube.com/iframe_api', false);
    }

    public function test_a_user_cannot_open_a_course_they_do_not_own(): void
    {
        $course = $this->makeCourse(lessons: 2);

        $this->actingAs($this->trainee())
            ->get(route('learning.course', $course))
            ->assertNotFound();
    }

    public function test_another_users_enrollment_does_not_open_the_course(): void
    {
        $owner = $this->trainee('المالك');
        $other = $this->trainee('غيره');
        $course = $this->makeCourse(lessons: 2);
        $this->enroll($owner, $course);

        $this->actingAs($other)
            ->get(route('learning.course', $course))
            ->assertNotFound();
    }

    public function test_paths_screen_hides_the_certificate_exam_cta_before_one_hundred_percent(): void
    {
        $user = $this->trainee();
        $path = LearningPath::where('slug', 'usus-al-amal-al-tatawui')->firstOrFail();
        $course = $this->makeCourse(lessons: 2);
        $this->attachToPath($course, $path);
        $this->enroll($user, $course);

        $this->actingAs($user)
            ->get(route('learning.paths'))
            ->assertOk()
            ->assertSee($path->name_ar)
            ->assertDontSee(setting('learning.paths.exam_cta'));
    }

    public function test_path_page_lists_its_courses_in_order(): void
    {
        $user = $this->trainee();
        $path = LearningPath::where('slug', 'usus-al-amal-al-tatawui')->firstOrFail();
        $course = $this->makeCourse(lessons: 2);
        $this->attachToPath($course, $path, 9);
        $this->enroll($user, $course);

        $this->actingAs($user)
            ->get(route('learning.path', $path))
            ->assertOk()
            ->assertSee(setting('learning.paths.contents_title'))
            ->assertSee($course->name_ar)
            ->assertSee(setting('learning.paths.exam_locked_hint'));
    }

    public function test_reporting_a_lesson_problem_opens_a_support_ticket(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 2);
        $this->enroll($user, $course);

        $this->actingAs($user)
            ->post(route('learning.course.report', $course), [
                'problem_type' => 'مشكلة في الفيديو',
                'body' => 'الفيديو لا يعمل من بداية الدقيقة الثالثة.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('complaints', [
            'user_id' => $user->id,
            'category' => setting('learning.report.category'),
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('learning.courses'))->assertRedirect(route('login'));
    }

    public function test_user_without_the_permission_is_forbidden(): void
    {
        $user = User::create([
            'name' => 'بلا صلاحيّة',
            'email' => 'no-perm@test.local',
            'password' => 'secret-password',
            'code' => 'UNOPERM1',
            'status' => 'active',
        ]);

        $this->actingAs($user)->get(route('learning.courses'))->assertForbidden();
    }

    private function firstLessonId(Course $course): int
    {
        return (int) app(ProgressService::class)
            ->orderedLessons($course)
            ->first()
            ->id;
    }
}
