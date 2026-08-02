<?php

namespace Tests\Feature\Learning;

use App\Models\Currency;
use App\Models\LessonCompletion;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Gamification\LeaderboardService;
use App\Services\Learning\ProgressService;
use Illuminate\Support\Carbon;

/**
 * مكافآت إتمام الدرس (7 · 7.1 · 7.3):
 *  - **تذكرتان قبل نصف الديدلاين وتذكرة واحدة بعده** — وكانت الدالّة كودًا ميّتًا.
 *  - **XP يُكتَب في المصدر الموحّد** فيظهر في لوحة الصدارة — وكان يُكتَب في
 *    `enrollments.xp_earned` وحده فلا يراه الترتيب إطلاقًا.
 */
class LessonRewardsTest extends LearningTestCase
{
    /** قبل نصف الديدلاين: تذكرتان + XP في المحفظة وفي `users.xp` وفي التسجيل */
    public function test_completing_a_lesson_grants_two_tickets_before_half_deadline(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(2, false, ['xp_max' => 100]);
        $enrollment = $this->enroll($user, $course, Carbon::now()->subDay(), Carbon::now()->addDays(9));
        $lesson = $this->lessonsOf($course)->first();

        $result = app(ProgressService::class)->completeLesson($user, $course, $lesson, $enrollment);

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $result['tickets'], 'تذكرتان قبل نصف الديدلاين (7).');
        $this->assertGreaterThan(0, $result['xp']);

        $user->refresh();

        $this->assertSame(2.0, (float) $user->balance('tickets'));
        $this->assertSame($result['xp'], (int) $user->xp, 'XP يزيد في العمود الذي يقرأه الليدر بورد.');
        $this->assertSame((float) $result['xp'], (float) $user->balance('xp'), 'وفي محفظة XP كذلك.');
        $this->assertSame($result['xp'], (int) $enrollment->fresh()->xp_earned);
    }

    /** بعد نصف الديدلاين: تذكرة واحدة */
    public function test_completing_a_lesson_after_half_deadline_grants_one_ticket(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(2, false, ['xp_max' => 100]);
        // بدأ من 8 أيّام والديدلاين بعد يومين ⟵ تجاوزنا المنتصف
        $enrollment = $this->enroll($user, $course, Carbon::now()->subDays(8), Carbon::now()->addDays(2));
        $lesson = $this->lessonsOf($course)->first();

        $result = app(ProgressService::class)->completeLesson($user, $course, $lesson, $enrollment);

        $this->assertSame(1, $result['tickets'], 'تذكرة واحدة بعد نصف الديدلاين (7).');
        $this->assertSame(1.0, (float) $user->fresh()->balance('tickets'));
    }

    /** إعادة إتمام الدرس لا تمنح مرّتين — الحارس سجلّ الإكمال نفسه */
    public function test_rewards_are_granted_once_however_many_times_the_lesson_is_completed(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(2, false, ['xp_max' => 100]);
        $enrollment = $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();

        $service = app(ProgressService::class);
        $first = $service->completeLesson($user, $course, $lesson, $enrollment);

        foreach (range(1, 3) as $ignored) {
            $again = $service->completeLesson($user, $course, $lesson, $enrollment->refresh());
            $this->assertSame(0, $again['xp']);
            $this->assertSame(0, $again['tickets']);
        }

        $this->assertSame(1, LessonCompletion::query()
            ->where('user_id', $user->id)->where('lesson_id', $lesson->id)->count());

        $user->refresh();

        $this->assertSame($first['xp'], (int) $user->xp);
        $this->assertSame((float) $first['tickets'], (float) $user->balance('tickets'));
        $this->assertSame((float) $first['tickets'], (float) $user->balance('tickets'));
    }

    /** ما مُنِح يُسجَّل في سجلّ الإكمال نفسه — فالتدقيق لا يحتاج تخمينًا */
    public function test_completion_row_records_what_was_actually_granted(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(1, false, ['xp_max' => 80]);
        $enrollment = $this->enroll($user, $course);
        $lesson = $this->lessonsOf($course)->first();

        $result = app(ProgressService::class)->completeLesson($user, $course, $lesson, $enrollment);

        $this->assertDatabaseHas('lesson_completions', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'xp_awarded' => $result['xp'],
            'tickets_awarded' => $result['tickets'],
        ]);

        // ولكلّ منحة سطرٌ في دفتر الأستاذ (19)
        $xpId = Currency::where('code', 'xp')->value('id');
        $this->assertTrue(Transaction::query()
            ->where('user_id', $user->id)->where('currency_id', $xpId)->where('amount', $result['xp'])->exists());
    }

    /** ⭐ العطل الأخطر: إتمام درسٍ **يرفع ترتيب صاحبه في لوحة الصدارة** (7.3) */
    public function test_completing_a_lesson_lifts_the_user_up_the_leaderboard(): void
    {
        $learner = $this->trainee('المتعلّم المجتهد');
        $rival = $this->trainee('منافس');

        // المنافس متقدّم بـ40 نقطة قبل أن يبدأ المتعلّم
        $rival->forceFill(['xp' => 40])->save();

        $board = app(LeaderboardService::class);

        $this->assertSame(2, $this->rankOf($board, $learner), 'قبل التعلّم: المتعلّم خلف منافسه.');

        $course = $this->makeCourse(1, false, ['xp_max' => 100]);
        $enrollment = $this->enroll($learner, $course);
        $lesson = $this->lessonsOf($course)->first();

        app(ProgressService::class)->completeLesson($learner, $course, $lesson, $enrollment);

        $this->assertSame(1, $this->rankOf($board, $learner->refresh()), 'بعد التعلّم: صار الأوّل — التعلّم مرئيّ في اللوحة.');
    }

    private function rankOf(LeaderboardService $board, User $user): int
    {
        return (int) ($board->xp($user)['me']['rank'] ?? 0);
    }
}
