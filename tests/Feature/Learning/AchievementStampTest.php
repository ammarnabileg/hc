<?php

namespace Tests\Feature\Learning;

/**
 * ختم الإنجاز المشترك (الفكرة #9): ختمٌ هندسيّ واحد في احتفال إكمال التدريب
 * وفي بطاقة المشاركة، ولا يحلّ محلّ كود التحقّق في الشهادة.
 */
class AchievementStampTest extends LearningTestCase
{
    public function test_the_stamp_component_is_a_self_drawn_svg_hidden_from_screen_readers_by_default(): void
    {
        $html = view('components.achievement-stamp', ['size' => 40, 'label' => null, 'attributes' => new \Illuminate\View\ComponentAttributeBag])->render();

        $this->assertStringContainsString('data-achievement-stamp', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('width="40"', $html);
        $this->assertSame(24, substr_count($html, '<line '));
    }

    public function test_course_completion_celebration_carries_the_stamp_while_level_up_keeps_its_icon(): void
    {
        $base = ['tier' => 3, 'label' => 'تدريب', 'message' => 'مبروك', 'sound' => false, 'sound_path' => null];

        $course = view('learning.partials.celebration', ['celebration' => $base + ['key' => 'course.completed']])->render();
        $this->assertStringContainsString('data-achievement-stamp', $course);

        $level = view('learning.partials.celebration', ['celebration' => $base + ['key' => 'level.up']])->render();
        $this->assertStringNotContainsString('data-achievement-stamp', $level);

        // إتمام التدريب درجته الثانية (شريط مختصر) ويحمل الختم صغيرًا
        $compact = view('learning.partials.celebration', ['celebration' => ['tier' => 2, 'key' => 'course.completed'] + $base])->render();
        $this->assertStringContainsString('data-achievement-stamp', $compact);
        $this->assertStringContainsString('width="22"', $compact);
    }

    public function test_the_share_card_of_a_completed_course_shows_the_same_stamp(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(1);
        $enrollment = $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();
        app(\App\Services\Learning\ProgressService::class)->completeLesson($user, $course, $lesson, $enrollment);

        $html = $this->actingAs($user)->get(route('learning.course', $course))->assertOk()->getContent();

        $this->assertStringContainsString(setting('learning.share.title'), $html);
        $this->assertStringContainsString('data-achievement-stamp', $html);
    }
}
