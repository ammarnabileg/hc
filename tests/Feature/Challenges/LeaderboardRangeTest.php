<?php

namespace Tests\Feature\Challenges;

use App\Models\User;
use App\Services\Gamification\LeaderboardService;
use App\Services\Gamification\WalletGateway;
use Illuminate\Support\Carbon;

/**
 * الليدر بورد ونطاقاته الزمنيّة (7.3).
 *
 * الدستور يجعل النطاق الزمنيّ **قلب اللوحة** لأنّه ما يتيح للوافد الجديد أن
 * ينافس. وكان الترتيب يتمّ بعمود `users.xp` **التراكميّ منذ التسجيل**، والفترة
 * تُستعمَل في حساب الفارق المعروض وحده — فأعطى `days=7` و`days=30` و`days=90`
 * ترتيبًا متطابقًا حرفيًّا، وصارت اللوحة أبديّةً لا يظهر فيها جديدٌ مهما اجتهد.
 */
class LeaderboardRangeTest extends ChallengeTestCase
{
    /** يمنح XP بتاريخٍ ماضٍ — لأنّ النافذة تُقاس بتاريخ المعاملة لا بلحظة الاختبار */
    private function grantXp(User $user, float $amount, int $daysAgo): void
    {
        Carbon::setTestNow(Carbon::now()->subDays($daysAgo));
        app(WalletGateway::class)->credit($user, 'xp', $amount, 'خبرة اختبار');
        Carbon::setTestNow();

        $user->forceFill(['xp' => (int) $user->xp + (int) $amount])->save();
    }

    /**
     * قديمٌ كنز خبرته من زمن، وجديدٌ اجتهد هذا الأسبوع.
     *
     * @return array{0:User,1:User}
     */
    private function twoRivals(): array
    {
        $veteran = $this->trainee(tickets: 0, attributes: ['name' => 'المخضرم أنس']);
        $newcomer = $this->trainee(tickets: 0, attributes: ['name' => 'الوافدة هدى']);

        // المخضرم: كنزٌ قديم خارج نافذة الأسبوع + فتاتٌ داخلها
        $this->grantXp($veteran, 500, daysAgo: 60);
        $this->grantXp($veteran, 10, daysAgo: 1);

        // الوافدة: اجتهاد أسبوعٍ واحد
        $this->grantXp($newcomer, 300, daysAgo: 3);

        return [$veteran->fresh(), $newcomer->fresh()];
    }

    public function test_a_seven_day_board_ranks_differently_than_a_ninety_day_board(): void
    {
        [$veteran, $newcomer] = $this->twoRivals();
        $board = app(LeaderboardService::class);

        $week = $board->xp($veteran, days: 7)['rows']->pluck('user.id')->all();
        $quarter = $board->xp($veteran, days: 90)['rows']->pluck('user.id')->all();

        $this->assertNotSame($quarter, $week, 'النطاقات الزمنيّة لازم تغيّر الترتيب فعلًا لا شكلًا.');

        $this->assertSame($newcomer->id, $week[0], 'في نافذة الأسبوع تتصدّر الوافدة باجتهادها.');
        $this->assertSame($veteran->id, $quarter[0], 'وفي نافذة الفصل يعود المخضرم لصدارته.');
    }

    /** ونفس الحكم من الشاشة نفسها — لأنّ المستخدم يرى الصفحة لا الخدمة */
    public function test_the_screen_reorders_when_the_period_changes(): void
    {
        [$veteran, $newcomer] = $this->twoRivals();
        // متفرّجٌ محايد: كارت صاحب الحساب في الأعلى (7.3) يسبق الصفوف، فلو
        // نظرنا بعين أحد المتنافسين لسبق اسمُه نفسَه في الصفحة بلا علاقة بالترتيب.
        $watcher = $this->trainee(tickets: 0, attributes: ['name' => 'متفرّجة محايدة']);

        $order = function (int $days) use ($watcher): array {
            return $this->actingAs($watcher)
                ->get(route('achievements.leaderboard', ['days' => $days]))
                ->assertOk()
                ->viewData('board')['rows']
                ->pluck('user.id')
                ->all();
        };

        $week = $order(7);
        $quarter = $order(90);

        $this->assertNotSame($quarter, $week, 'الشاشة نفسها لازم ترتّب مختلفًا باختلاف `days`.');
        $this->assertSame($newcomer->id, $week[0], 'نافذة الأسبوع: الوافدة قبل المخضرم.');
        $this->assertSame($veteran->id, $quarter[0], 'نافذة الفصل: المخضرم قبل الوافدة.');
    }

    /** ⭐ فترة يحدّدها المستخدم بنفسه لا قائمةً مقفولة على 7/30/90 (7.3) */
    public function test_the_user_can_ask_for_a_period_of_their_own(): void
    {
        [$veteran] = $this->twoRivals();

        $this->actingAs($veteran)
            ->get(route('achievements.leaderboard', ['days' => 45]))
            ->assertOk();

        $this->assertSame(45, app(LeaderboardService::class)->xp($veteran, days: 45)['days']);

        // ومع ذلك لا تُقبَل فترةٌ بلا معنى — الحدّ من الإعدادات لا من الرابط
        $this->assertSame(
            (int) setting('leaderboard.max_range_days', 365),
            app(LeaderboardService::class)->xp($veteran, days: 99999)['days'],
        );
    }
}
