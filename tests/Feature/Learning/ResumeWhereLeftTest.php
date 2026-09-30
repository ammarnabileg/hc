<?php

namespace Tests\Feature\Learning;

use App\Models\LessonVideoView;
use App\Services\Learning\VideoWatchService;

/**
 * أكمل دون بحث (الفكرة #11): «أكمل» يفتح الدرس الفعليّ لا قائمة التدريب،
 * والمشغّل يبدأ من آخر موضع محفوظ في الفيديو حين يكون هناك موضع يستحقّ.
 */
class ResumeWhereLeftTest extends LearningTestCase
{
    public function test_the_courses_page_resume_button_opens_the_actual_lesson(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(3);
        $this->enroll($user, $course);
        $first = $this->lessonsOf($course)->first();

        $html = $this->actingAs($user)->get(route('learning.courses'))->assertOk()->getContent();

        $this->assertStringContainsString(route('learning.lesson', [$course, $first]), $html);
    }

    public function test_the_video_player_starts_from_the_saved_position_when_it_is_worth_it(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(2);
        $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();
        $lesson->forceFill(['type' => 'video', 'video_id' => 'dQw4w9WgXcQ', 'duration_minutes' => 10])->save();

        LessonVideoView::create(['user_id' => $user->id, 'lesson_id' => $lesson->id, 'watched_seconds' => 95, 'duration_seconds' => 600]);

        $this->assertSame(95, app(VideoWatchService::class)->resumeSeconds($user, $lesson));

        $html = $this->actingAs($user)->get(route('learning.lesson', [$course, $lesson]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/embed\/dQw4w9WgXcQ\?[^"]*start=95/', $html);
        $this->assertStringContainsString('data-watch-start="95"', $html);
        $this->assertStringContainsString(str_replace(':time', '01:35', (string) setting('learning.video.resume_note', 'بنكمّل من الدقيقة :time')), $html);
    }

    public function test_no_resume_when_the_video_is_done_or_barely_started(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(2);
        $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();
        $lesson->forceFill(['type' => 'video', 'video_id' => 'dQw4w9WgXcQ', 'duration_minutes' => 10])->save();
        $service = app(VideoWatchService::class);

        $this->assertSame(0, $service->resumeSeconds($user, $lesson), 'بلا سجلّ لا استئناف');

        $view = LessonVideoView::create(['user_id' => $user->id, 'lesson_id' => $lesson->id, 'watched_seconds' => 12, 'duration_seconds' => 600]);
        $this->assertSame(0, $service->resumeSeconds($user, $lesson), 'أقلّ من العتبة لا استئناف');

        $view->forceFill(['watched_seconds' => 590, 'completed_at' => now()])->save();
        $this->assertSame(0, $service->resumeSeconds($user, $lesson), 'المشاهدة المكتملة تبدأ من الأوّل');

        $html = $this->actingAs($user)->get(route('learning.lesson', [$course, $lesson]))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/embed\/dQw4w9WgXcQ\?[^"]*start=/', $html);
        $this->assertStringContainsString('data-watch-start="0"', $html);
    }
}
