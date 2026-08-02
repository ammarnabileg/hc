<?php

namespace Tests\Feature\Volunteer\Retention;

use App\Models\AppNotification;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Volunteer\Retention\ActivityTracker;
use App\Services\Volunteer\Retention\InactivityLadder;
use App\Services\Wallet\LedgerService;

/**
 * سلّم الخمول (13.4-س-ب): «**21 يومًا بلا نشاط ⟵ تنبيه**، ثمّ **−0.5 من Rep
 * أسبوعيًّا** ما دام خاملًا حتى يعود أو يبلغ العتبات».
 */
class InactivityLadderTest extends RetentionTestCase
{
    /** التنبيه بعد العتبة، **مرّة واحدة** لا كلّ يوم. */
    public function test_alert_fires_once_after_the_threshold_and_never_repeats(): void
    {
        $user = $this->idleVolunteer();

        $this->artisan('volunteers:inactivity')->assertSuccessful();

        $this->assertSame(1, $this->alertsOf($user), 'تنبيه واحد بعد بلوغ العتبة');
        $this->assertSame(0.0, $this->repOf($user), 'التنبيه أوّلًا — ولا خصم في يومه');

        // اليوم التالي: نفس الخمول ولا تنبيه جديد
        $this->artisan('volunteers:inactivity')->assertSuccessful();

        $this->assertSame(1, $this->alertsOf($user), 'التنبيه لا يتكرّر يوميًّا');
    }

    /** ⭐ الخصم الأسبوعيّ بقيمة `rep_rule('inactivity.weekly')` وبمصدر `inactivity`. */
    public function test_weekly_deduction_is_applied_from_the_rep_table_with_the_inactivity_source(): void
    {
        $user = $this->idleVolunteer();

        $this->artisan('volunteers:inactivity')->assertSuccessful();
        $this->assertSame(0.0, $this->repOf($user));

        // بعد أسبوع من التنبيه ⟵ أوّل خصم
        $this->travel(7)->days();
        $this->artisan('volunteers:inactivity')->assertSuccessful();

        $expected = rep_rule('inactivity.weekly');
        $this->assertSame(round($expected, 2), $this->repOf($user));

        $movement = Transaction::query()->where('user_id', $user->id)->where('source', 'inactivity')->first();
        $this->assertNotNull($movement, 'الحركة تُكتَب بمصدر inactivity في دفتر الأستاذ');

        // في اليوم التالي مباشرةً لا خصم — الدورة أسبوعيّة لا يوميّة
        $this->travel(1)->days();
        $this->artisan('volunteers:inactivity')->assertSuccessful();
        $this->assertSame(round($expected, 2), $this->repOf($user));

        // وبعد أسبوعٍ آخر ⟵ خصمٌ ثانٍ ما دام خاملًا
        $this->travel(7)->days();
        $this->artisan('volunteers:inactivity')->assertSuccessful();
        $this->assertSame(round($expected * 2, 2), $this->repOf($user));
    }

    /** ⭐ يتوقّف **فور عودة النشاط** — والنشاط أوسع من آخر ظهور. */
    public function test_the_ladder_stops_the_moment_activity_returns(): void
    {
        $user = $this->idleVolunteer();

        $this->artisan('volunteers:inactivity')->assertSuccessful();
        $this->travel(7)->days();
        $this->artisan('volunteers:inactivity')->assertSuccessful();

        $afterFirst = $this->repOf($user);
        $this->assertLessThan(0.0, $afterFirst);

        // رجع: تسليم مهمّة يكفي بذاته حتى لو لم يفتح المنصّة
        $user->forceFill(['last_seen_at' => now()->subDays(60)])->save();
        $this->submitSomething($user);

        $this->assertTrue(
            app(ActivityTracker::class)->lastActivityAt($user->fresh())->isToday(),
            'التسليم نشاطٌ معتبَر — لا آخر ظهور وحده'
        );

        $this->artisan('volunteers:inactivity')->assertSuccessful();

        // صفّ السلّم يُمحى فور العودة، فلو خمل ثانيةً بدأ بتنبيهٍ لا بخصم
        $this->assertDatabaseCount('volunteer_inactivity_states', 0);

        // ومرّ أسبوعٌ كامل — موعد الخصم التالي — بلا أيّ خصم
        $this->travel(7)->days();
        $this->artisan('volunteers:inactivity')->assertSuccessful();

        $this->assertSame($afterFirst, $this->repOf($user), 'لا خصم بعد العودة');
        $this->assertDatabaseCount('volunteer_inactivity_states', 0);
    }

    /** العتبة والقيمة إعدادان — تغييرهما يغيّر السلوك بلا لمس كود (2.13). */
    public function test_threshold_and_value_come_from_settings_not_from_code(): void
    {
        $ladder = app(InactivityLadder::class);

        $this->assertSame((int) setting('rep.inactivity.days_before_alert'), $ladder->alertDays());
        $this->assertSame(rep_rule('inactivity.weekly'), $ladder->weeklyValue());
    }

    // ------------------------------------------------------------------ أدوات

    private function idleVolunteer(): User
    {
        $user = $this->makeUser('خالد الغائب');
        $this->makeMembership($user, $this->makeEntity());

        $user->forceFill([
            'last_seen_at' => now()->subDays((int) setting('rep.inactivity.days_before_alert') + 3),
        ])->save();

        return $user;
    }

    /** تسليم حقيقيّ على مهمّة — أثرٌ يكفي وحده لاعتبار العضو عائدًا */
    private function submitSomething(User $user): void
    {
        $task = Task::create([
            'title' => 'مهمّة العودة',
            'owner_id' => $user->id,
            'status' => 'in_progress',
            'source' => 'assigned',
        ]);

        TaskSubmission::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'version' => 1,
            'body' => 'التسليم الأوّل',
        ]);
    }

    private function alertsOf(User $user): int
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->where('title', 'like', '%بلا نشاط%')
            ->count();
    }

    private function repOf(User $user): float
    {
        return round(app(LedgerService::class)->balance($user->fresh(), LedgerService::REP), 2);
    }
}
