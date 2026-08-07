<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Setting;
use App\Models\User;
use App\Models\VideoComment;
use App\Services\Learning\AvailabilityService;
use Illuminate\Support\Facades\Cache;

/**
 * تبديلات إعدادات التعلّم (24.4): كلّ تبديلة هنا لها حارسٌ حقيقيّ في الخادم
 * (2.15-أ-7) لا إخفاءً بصريًّا وحده — المسار يردّ 404 حين تكون الميزة موقوفة.
 */
class LearningUxTogglesTest extends LearningTestCase
{
    private function setToggle(string $key, bool $on): void
    {
        Setting::query()->firstOrCreate(['key' => $key], ['group' => 'learning', 'label_ar' => $key, 'type' => 'bool', 'value' => '1', 'default_value' => '1']);
        Setting::query()->where('key', $key)->update(['value' => $on ? '1' : '0']);
        Cache::forget('settings');
    }

    // ------------------------------------------------------------ عرض الدرس

    public function test_the_resume_fab_only_renders_when_its_toggle_is_on(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 3, forcedOrder: false);
        $this->enroll($user, $course);

        $this->actingAs($user);

        $cta = (string) setting('learning.cta.resume_where_left');

        $this->setToggle('learning.ux.resume_enabled', true);
        $this->assertStringContainsString($cta, view('learning.partials.resume-fab')->render());

        $this->setToggle('learning.ux.resume_enabled', false);
        $this->assertStringNotContainsString($cta, view('learning.partials.resume-fab')->render());
    }

    public function test_the_half_point_banner_only_renders_when_its_toggle_is_on(): void
    {
        $user = $this->trainee();
        $this->actingAs($user);

        $half = ['reached' => true, 'at' => 2, 'remaining' => 3];

        $this->setToggle('learning.ux.half_banner_enabled', true);
        $this->assertStringContainsString('role="status"', view('learning.partials.half-banner', ['half' => $half])->render());

        $this->setToggle('learning.ux.half_banner_enabled', false);
        $this->assertStringNotContainsString('role="status"', view('learning.partials.half-banner', ['half' => $half])->render());
    }

    public function test_bookmarking_is_blocked_with_404_when_its_toggle_is_off(): void
    {
        [$user, $course, $lesson] = $this->videoLesson();

        $this->setToggle('learning.ux.bookmark_enabled', true);
        $this->actingAs($user)
            ->post(route('learning.lesson.bookmark', [$course, $lesson]))
            ->assertRedirect();

        $this->setToggle('learning.ux.bookmark_enabled', false);
        $this->actingAs($user)
            ->post(route('learning.lesson.bookmark', [$course, $lesson]))
            ->assertNotFound();

        // والزرّ نفسه يختفي من صفحة الدرس لا يظهر معطَّلًا (2.15-أ-7)
        $this->actingAs($user)
            ->get(route('learning.lesson', [$course, $lesson]))
            ->assertDontSee(route('learning.lesson.bookmark', [$course, $lesson]), false);
    }

    // ------------------------------------------------------------ التعليقات

    public function test_the_comments_section_and_its_routes_disappear_when_comments_are_disabled(): void
    {
        [$user, $course, $lesson] = $this->videoLesson();

        $this->setToggle('learning.comments.enabled', false);

        $this->actingAs($user)
            ->get(route('learning.lesson', [$course, $lesson]))
            ->assertOk()
            ->assertDontSee('id="comments"', false);

        $this->actingAs($user)
            ->post(route('learning.lesson.comments.store', [$course, $lesson]), ['body' => 'تعليق'])
            ->assertNotFound();

        $this->setToggle('learning.comments.enabled', true);

        $this->actingAs($user)
            ->get(route('learning.lesson', [$course, $lesson]))
            ->assertSee('id="comments"', false);
    }

    public function test_like_and_reply_are_blocked_when_their_toggle_is_off_but_posting_top_level_comments_still_works(): void
    {
        [$user, $course, $lesson] = $this->videoLesson();

        $comment = VideoComment::create(['lesson_id' => $lesson->id, 'user_id' => $user->id, 'body' => 'تعليق أصليّ']);

        $this->setToggle('learning.comments.like_reply_enabled', false);

        $this->actingAs($user)
            ->post(route('learning.lesson.comments.like', [$course, $lesson, $comment]))
            ->assertNotFound();

        $this->actingAs($user)
            ->post(route('learning.lesson.comments.store', [$course, $lesson]), ['body' => 'ردّ', 'parent_id' => $comment->id])
            ->assertSessionHasErrors('parent_id');

        // والتعليق المستقلّ (بلا parent_id) يبقى شغّالًا — التبديلة لا توقف التعليقات كلّها
        $this->actingAs($user)
            ->post(route('learning.lesson.comments.store', [$course, $lesson]), ['body' => 'تعليق مستقلّ جديد'])
            ->assertRedirect();

        $this->assertDatabaseHas('video_comments', ['lesson_id' => $lesson->id, 'body' => 'تعليق مستقلّ جديد']);
    }

    // ------------------------------------------------------------ الملاحظات

    public function test_notes_section_and_its_routes_disappear_when_notes_are_disabled(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 2, forcedOrder: false);
        $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();

        $this->setToggle('learning.notes.enabled', false);

        $this->actingAs($user)
            ->get(route('learning.lesson', [$course, $lesson]))
            ->assertOk()
            ->assertDontSee('id="course-notes"', false);

        $this->actingAs($user)
            ->post(route('learning.course.notes.save', $course), ['body' => 'ملاحظة'])
            ->assertNotFound();

        $this->setToggle('learning.notes.enabled', true);

        $this->actingAs($user)
            ->get(route('learning.lesson', [$course, $lesson]))
            ->assertSee('id="course-notes"', false);
    }

    // ------------------------------------------------------------ التوقيت والقفل

    public function test_the_daily_lock_window_is_ignored_entirely_when_its_toggle_is_off(): void
    {
        $course = $this->makeCourse(lessons: 1, overrides: [
            'daily_open_at' => '09:00',
            'daily_close_at' => '17:00',
        ]);

        $service = app(AvailabilityService::class);

        $this->setToggle('learning.lock.outside_hours_enabled', true);
        $this->assertNotNull($service->dailyWindow($course));

        $this->setToggle('learning.lock.outside_hours_enabled', false);
        $this->assertNull($service->dailyWindow($course));
    }

    /** @return array{0: User, 1: Course, 2: Lesson} */
    private function videoLesson(): array
    {
        $user = $this->trainee();
        $course = $this->makeCourse(lessons: 2, forcedOrder: false);
        $this->enroll($user, $course);

        $lesson = $this->lessonsOf($course)->first();
        $lesson->update(['type' => 'video', 'video_id' => 'dQw4w9WgXcQ']);

        return [$user, $course, $lesson->refresh()];
    }
}
