<?php

namespace Tests\Feature\Learning;

use App\Services\Learning\ProgressService;

/**
 * قبل وبعد في سطر (الفكرة #23): بعد الإنجاز يقول الاحتفال «ترتيبك اتحسّن من 2 إلى 1»
 * رقمًا محسوبًا من لوحة XP لا تخمينًا، ولا يقول شيئًا حين لم يتقدّم الترتيب.
 */
class CelebrationRankLineTest extends LearningTestCase
{
    public function test_the_celebration_reports_the_rank_jump_when_the_award_moves_the_user_up(): void
    {
        $rival = $this->trainee('منافس');
        $rival->forceFill(['xp' => 5])->save();

        $user = $this->trainee('صاعد');
        $course = $this->makeCourse(2, true, ['xp_max' => 100]);
        $enrollment = $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();

        $result = app(ProgressService::class)->completeLesson($user, $course, $lesson, $enrollment);

        $this->assertNotNull($result['celebration'], 'الدرس المكتمل يطلق احتفالًا');
        $this->assertSame(['from' => 2, 'to' => 1], $result['celebration']['rank']);
    }

    public function test_no_rank_line_when_the_rank_did_not_change(): void
    {
        $user = $this->trainee('الأوّل أصلًا');
        $user->forceFill(['xp' => 5000])->save();
        $course = $this->makeCourse(2, true, ['xp_max' => 10]);
        $enrollment = $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();

        $result = app(ProgressService::class)->completeLesson($user, $course, $lesson, $enrollment);

        $this->assertNotNull($result['celebration']);
        $this->assertArrayNotHasKey('rank', $result['celebration']);
    }

    public function test_the_partial_prints_the_line_only_with_rank_data(): void
    {
        $base = ['key' => 'lesson.completed', 'tier' => 1, 'label' => 'درس', 'message' => 'مبروك', 'sound' => false, 'sound_path' => null];

        $with = view('learning.partials.celebration', ['celebration' => $base + ['rank' => ['from' => 42, 'to' => 31]]])->render();
        $this->assertStringContainsString('data-celebration-rank', $with);
        $this->assertStringContainsString(strtr(setting('learning.celebration.rank_line', 'ترتيبك اتحسّن من :from إلى :to'), [':from' => 42, ':to' => 31]), $with);

        $without = view('learning.partials.celebration', ['celebration' => $base])->render();
        $this->assertStringNotContainsString('data-celebration-rank', $without);
    }
}
