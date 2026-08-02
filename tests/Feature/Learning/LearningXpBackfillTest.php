<?php

namespace Tests\Feature\Learning;

use App\Models\Currency;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * مايجريشن الترميم: XP التعلّم القديم كان حبيس `enrollments.xp_earned`
 * فلا يراه الليدر بورد (7.3) — والترميم ينقله للمصدر الموحّد **بتاريخه الأصليّ**
 * حتى لا تتلوّث فروق آخر 7/30 يومًا بقفزةٍ وهميّة.
 */
class LearningXpBackfillTest extends LearningTestCase
{
    public function test_backfill_moves_old_enrollment_xp_into_the_single_source(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(1, false, ['xp_max' => 100]);
        $enrollment = $this->enroll($user, $course);

        // الحالة القديمة: XP في التسجيل وحده، و`users.xp` صفر
        $enrollment->forceFill(['xp_earned' => 250])->save();
        DB::table('enrollments')->where('id', $enrollment->id)
            ->update(['updated_at' => Carbon::now()->subMonths(6)]);
        $user->forceFill(['xp' => 0])->save();

        $this->runBackfill();

        $user->refresh();

        $this->assertSame(250, (int) $user->xp, 'انتقل XP إلى العمود الذي يرتّب اللوحة.');
        $this->assertSame(250.0, (float) $user->balance('xp'), 'وإلى محفظة XP كذلك.');

        $xpId = Currency::where('code', 'xp')->value('id');
        $row = Transaction::query()->where('user_id', $user->id)->where('currency_id', $xpId)->first();

        $this->assertNotNull($row, 'الترميم يترك سطرًا يشرح الحركة (19).');
        $this->assertTrue($row->created_at->lessThan(Carbon::now()->subMonths(5)), 'بتاريخه الأصليّ لا بتاريخ اليوم.');
    }

    public function test_backfill_is_safe_to_run_twice(): void
    {
        $user = $this->trainee();
        $course = $this->makeCourse(1, false, ['xp_max' => 100]);
        $enrollment = $this->enroll($user, $course);

        $enrollment->forceFill(['xp_earned' => 120])->save();
        $user->forceFill(['xp' => 0])->save();

        $this->runBackfill();
        $this->runBackfill();

        $this->assertSame(120, (int) $user->fresh()->xp, 'لا تكرار للترميم مهما أُعيد تشغيله.');
        $this->assertSame(120.0, (float) $user->fresh()->balance('xp'));
    }

    private function runBackfill(): void
    {
        $migration = require database_path('migrations/2026_08_06_100030_gamification_backfill_learning_xp.php');

        $migration->up();
    }
}
