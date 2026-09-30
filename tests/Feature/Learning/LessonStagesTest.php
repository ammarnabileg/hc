<?php

namespace Tests\Feature\Learning;

use App\Models\LessonQuestion;
use App\Models\LessonQuestionAnswer;
use App\Models\LessonVideoView;
use App\Services\Learning\ProgressService;

/**
 * ثلاث حالات للدرس (الفكرة #18): بدأت المشاهدة، اكتملت المشاهدة، اجتاز الاختبار،
 * وكلّها من سجلّ الخادم؛ والإكمال النهائيّ يبقى «مكتمل» وحده.
 */
class LessonStagesTest extends LearningTestCase
{
    private function stageOf(array $outline, int $lessonId): ?string
    {
        foreach ($outline['sections'] as $section) {
            foreach ($section['lessons'] as $row) {
                if ($row['id'] === $lessonId) {
                    return $row['stage'];
                }
            }
        }

        $this->fail('lesson missing from outline');
    }

    public function test_the_outline_reports_watching_then_watched_then_quiz_passed(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(2, false);
        $enrollment = $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();
        $lesson->forceFill(['type' => 'video', 'video_id' => 'dQw4w9WgXcQ'])->save();
        $progress = app(ProgressService::class);

        $this->assertNull($this->stageOf($progress->outline($user, $course, $enrollment), $lesson->id));

        $view = LessonVideoView::create(['user_id' => $user->id, 'lesson_id' => $lesson->id, 'watched_seconds' => 40, 'duration_seconds' => 300]);
        $this->assertSame('watching', $this->stageOf($progress->outline($user, $course, $enrollment), $lesson->id));

        $view->forceFill(['completed_at' => now()])->save();
        $this->assertSame('watched', $this->stageOf($progress->outline($user, $course, $enrollment), $lesson->id));

        $question = LessonQuestion::create(['lesson_id' => $lesson->id, 'prompt' => 'س؟', 'type' => 'mcq', 'options' => ['أ', 'ب'], 'correct_answer' => 'أ', 'sort_order' => 1, 'is_active' => true]);
        LessonQuestionAnswer::create(['user_id' => $user->id, 'lesson_question_id' => $question->id, 'is_correct' => true, 'xp_awarded' => 0]);
        $this->assertSame('quiz_passed', $this->stageOf($progress->outline($user, $course, $enrollment), $lesson->id));

        $html = $this->actingAs($user)->get(route('learning.course', $course))->assertOk()->getContent();
        $this->assertStringContainsString('data-lesson-stage="quiz_passed"', $html);
        $this->assertStringContainsString(setting('learning.lesson.stage_quiz_passed', 'الاختبار اتعدّى'), $html);
    }

    public function test_completed_and_locked_lessons_carry_no_stage(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(2, true);
        $enrollment = $this->enroll($user, $course);
        [$first, $second] = $this->lessonsOf($course)->values()->all();

        LessonVideoView::create(['user_id' => $user->id, 'lesson_id' => $second->id, 'watched_seconds' => 40, 'duration_seconds' => 300]);
        $this->assertNull($this->stageOf(app(ProgressService::class)->outline($user, $course, $enrollment), $second->id), 'المقفول بلا مرحلة');

        app(ProgressService::class)->completeLesson($user, $course, $first, $enrollment);
        $outline = app(ProgressService::class)->outline($user, $course, $enrollment);
        $this->assertNull($this->stageOf($outline, $first->id), 'المكتمل بلا مرحلة');
        $this->assertSame('watching', $this->stageOf($outline, $second->id));
    }
}
