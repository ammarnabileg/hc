<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Learning\XpCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * القسم 7: XP يتناقص خطّيًّا حتى الصفر عند الديدلاين،
 * ونصف الديدلاين للتذاكر وحدها (2 قبله · 1 بعده).
 */
class XpDecayTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $user = User::create([
            'name' => 'دارس', 'email' => 'x@t.local', 'password' => 'secret-password',
            'code' => 'UXPD0001', 'status' => 'active',
        ]);

        $course = Course::create([
            'slug' => 'c-'.uniqid(), 'name_ar' => 'تدريب', 'xp_max' => 100,
            'tickets_before_half' => 2, 'tickets_after_half' => 1, 'status' => 'published',
        ]);

        $enrollment = Enrollment::create([
            'user_id' => $user->id, 'course_id' => $course->id,
            'started_at' => Carbon::parse('2026-01-01 00:00:00'),
            'deadline_at' => Carbon::parse('2026-01-11 00:00:00'), // 10 أيّام
        ]);

        return [$course, $enrollment, app(XpCalculator::class)];
    }

    public function test_xp_decays_linearly_to_zero_at_deadline(): void
    {
        [$course, $enrollment, $xp] = $this->scenario();

        // البداية: القيمة القصوى كاملة
        $this->assertSame(100, $xp->lessonXp($course, $enrollment, Carbon::parse('2026-01-01 00:00:00')));

        // المنتصف: النصف تقريبًا — والتناقص مستمرّ لا قفزة
        $this->assertSame(50, $xp->lessonXp($course, $enrollment, Carbon::parse('2026-01-06 00:00:00')));

        // بعد يوم واحد: 90%
        $this->assertSame(90, $xp->lessonXp($course, $enrollment, Carbon::parse('2026-01-02 00:00:00')));

        // عند الديدلاين: صفر
        $this->assertSame(0, $xp->lessonXp($course, $enrollment, Carbon::parse('2026-01-11 00:00:00')));

        // بعد الديدلاين: يبقى صفرًا ولا يصير سالبًا
        $this->assertSame(0, $xp->lessonXp($course, $enrollment, Carbon::parse('2026-01-20 00:00:00')));
    }

    public function test_tickets_follow_half_deadline_not_xp(): void
    {
        [$course, $enrollment, $xp] = $this->scenario();

        $this->assertSame(2, $xp->lessonTickets($course, $enrollment, Carbon::parse('2026-01-03 00:00:00')));
        $this->assertSame(1, $xp->lessonTickets($course, $enrollment, Carbon::parse('2026-01-09 00:00:00')));
    }

    public function test_no_deadline_means_no_decay(): void
    {
        [$course, $enrollment, $xp] = $this->scenario();
        $enrollment->deadline_at = null;

        $this->assertSame(100, $xp->lessonXp($course, $enrollment, Carbon::parse('2030-01-01 00:00:00')));
    }
}
