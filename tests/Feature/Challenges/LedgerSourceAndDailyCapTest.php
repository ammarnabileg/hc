<?php

namespace Tests\Feature\Challenges;

use App\Models\RewardQuestion;
use App\Models\StreakDay;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\Volunteer\SettingsWriter;
use App\Services\Events\LedgerBridge;
use App\Services\Gamification\EconomyLedger;
use App\Services\Gamification\RewardQuestionService;
use App\Services\Gamification\StreakService;
use App\Services\Gamification\WalletGateway;
use App\Services\Gamification\Wars\FocusWarService;
use App\Services\Gamification\Wars\WarMatchService;
use Carbon\CarbonImmutable;

/**
 * «المصدر» مفتاحٌ و«السبب» جملة — وعليهما يقوم الحدّ اليوميّ (24.2 · 19.2 · 2.13).
 *
 * **العطل الذي يقفله هذا الملفّ:** `WalletGateway` كان يمرّر وسائطه لدفتر
 * الأستاذ **بالترتيب**، وترتيبه `(…, $source, $reference, $layer, $reason)` —
 * فنزلت جملة السبب في خانة `source` وبقي `reason` فارغًا. فصار في القاعدة
 * `source = "مواجهة حرب #1 — فوز"` و`"درع تجميد السلسلة"` و`"إنشاء تحدّي تركيز"`،
 * فتفتّتت التقارير التي تجمّع بالمصدر، وعمي عنها `withinDailyCap()` لأنّه
 * يرشّح بـ`where('source', …)` ⟵ **الحدّ اليوميّ المعلَن في اللوحة بلا أثر**.
 *
 * والدستور نصّه في **24.2 «XP والتذاكر»**: «**الفلاتر والبحث:** بحث **بالمصدر/الـKey**»
 * و«**العرض: جدولان:** [**الكسب**: **المصدر** … · القيمة (XP/تذاكر) · **حدّ يوميّ** …]»،
 * وفي **19.2** جدول المعاملات أعمدته «# / العملة / الكمية / من ← إلى / **السبب** /
 * ملاحظات / التاريخ» — خانتان لا خانة.
 */
class LedgerSourceAndDailyCapTest extends ChallengeTestCase
{
    /**
     * ⭐ لا جملة عربيّة في خانة «المصدر» بعد مسارات البوّابة كلّها،
     * ولا صفٌّ بلا «سبب» — وهذان وجها العطل الواحد.
     */
    public function test_gateway_paths_write_a_key_source_and_an_arabic_reason(): void
    {
        $this->runEveryGatewayPath();

        $rows = Transaction::query()->get(['id', 'source', 'reason']);

        $this->assertGreaterThanOrEqual(8, $rows->count(), 'المسارات لازم تكون كتبت في الدفتر فعلًا');

        foreach ($rows as $row) {
            $this->assertDoesNotMatchRegularExpression(
                '/\p{Arabic}/u',
                (string) $row->source,
                'المصدر مفتاحٌ يُبحَث به (24.2) لا جملةً للمستخدم — الصفّ #'.$row->id.': '.$row->source,
            );

            $this->assertNotNull(
                $row->reason,
                'كلّ حركة لها «سبب» يقرؤه صاحبها في تاب المعاملات (19.2) — الصفّ #'.$row->id,
            );
        }
    }

    /** والمصادر تتجمّع في دلاءٍ معدودة لا تتفتّت بعدد الجُمَل */
    public function test_reports_that_group_by_source_do_not_fragment(): void
    {
        $this->runEveryGatewayPath();

        $sources = Transaction::query()->distinct()->pluck('source')->all();

        $this->assertEqualsCanonicalizing(['challenge', 'grant', 'streak'], $sources);
    }

    /**
     * ⭐ **الحدّ اليوميّ يعمل عبر المسارات لا داخل مسارٍ واحد:** XP نزل من
     * البوّابة بمصدر `reward_question` **يُحسَب** على حدّ `reward.question`.
     * وهذا هو الصفّ الذي كان يختفي: بجملةٍ في خانة المصدر لا يراه الترشيح.
     */
    public function test_the_daily_cap_sees_earnings_that_came_through_the_gateway(): void
    {
        $this->capRewardQuestionAt(120);

        $user = $this->trainee(tickets: 0);

        // ١٠٠ XP من البوّابة — مصدرها مفتاحٌ فيقع تحت نفس الدلو
        app(WalletGateway::class)->credit($user, 'xp', 100, 'reward_question', 'منحة سابقة اليوم');

        $result = app(RewardQuestionService::class)->answer($user->refresh(), $this->publishedQuestion(100), 'صح');

        $this->assertSame(20, $result['xp'], 'المتبقّي من الحدّ 20 فقط — والباقي يُرَدّ');

        $this->assertSame(
            120.0,
            (float) Transaction::query()->where('user_id', $user->id)->where('source', 'reward_question')
                ->whereHas('currency', fn ($q) => $q->where('code', app(EconomyLedger::class)->xpCode()))
                ->sum('amount'),
            'مجموع اليوم لا يتجاوز الحدّ المعلَن مهما تعدّدت المسارات',
        );
    }

    /** وكسبٌ ثانٍ فوق الحدّ في نفس المسار ⟵ يُرَدّ بلا صفٍّ في الدفتر */
    public function test_a_second_earning_beyond_the_cap_is_refused(): void
    {
        $this->capRewardQuestionAt(120);

        $user = $this->trainee(tickets: 0);
        $service = app(RewardQuestionService::class);

        $this->assertSame(100, $service->answer($user->refresh(), $this->publishedQuestion(100), 'صح')['xp']);
        $this->assertSame(20, $service->answer($user->refresh(), $this->publishedQuestion(100), 'صح')['xp']);
        $this->assertSame(0, $service->answer($user->refresh(), $this->publishedQuestion(100), 'صح')['xp']);

        $this->assertSame(
            2,
            Transaction::query()->where('user_id', $user->id)->where('source', 'reward_question')->count(),
            'ما لم يُمنَح لا يُكتَب صفًّا — والصفران المكتوبان مجموعهما الحدّ بالضبط',
        );

        $this->assertSame(120, (int) $user->refresh()->xp);
    }

    /**
     * ⭐ والبوّابة تمرّ فعلًا بدفتر الأستاذ لا بكتابتها الاحتياطيّة:
     * `applied_amount` و`objection_deadline_at` لا يكتبهما إلّا الدفتر.
     */
    public function test_the_gateway_actually_delegates_to_the_ledger(): void
    {
        $user = $this->trainee(tickets: 0);

        app(WalletGateway::class)->credit($user, 'tickets', 3, 'grant', 'رصيد اختبار');

        $row = Transaction::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertNotNull($row->applied_amount, 'الدفتر وحده يكتب «المطبَّق» — غيابه يعني أنّ البوّابة كتبت بنفسها');
        $this->assertNotNull($row->objection_deadline_at);
    }

    /**
     * ⭐ **الغلاف الثاني بالعطل نفسه:** `Events\LedgerBridge` كان يفوّض للدفتر
     * **بالترتيب** `[…, $source, $reason, $reference]` بينما توقيع الدفتر
     * `(…, $source, ?Model $reference, $layer, ?string $reason)` — فيُرمى
     * `TypeError` ويبتلعه `catch (Throwable)`، فلا يُستدعى الدفتر **أبدًا**
     * وتُكتَب صفوفٌ بلا «مطبَّق» ولا مهلة اعتراض ولا سقف عملة.
     */
    public function test_the_events_bridge_actually_delegates_to_the_ledger(): void
    {
        $user = $this->trainee(tickets: 0);

        $this->assertTrue(
            app(LedgerBridge::class)->credit($user, 'tickets', 4, 'event', 'حضور فعاليّة'),
        );

        $row = Transaction::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('event', $row->source);
        $this->assertSame('حضور فعاليّة', $row->reason);
        $this->assertNotNull($row->applied_amount, 'الدفتر وحده يكتب «المطبَّق» — غيابه يعني أنّ الجسر كتب بنفسه');
        $this->assertNotNull($row->objection_deadline_at);
    }

    /** وعقد الجسر باقٍ: لا يُخصَم ما لا يوجد — ولا يُكتَب صفٌّ بنصف الثمن */
    public function test_the_events_bridge_still_refuses_a_debit_beyond_the_balance(): void
    {
        $user = $this->trainee(tickets: 2);

        $this->assertFalse(
            app(LedgerBridge::class)->debit($user, 'tickets', 10, 'event', 'تسجيل في فعاليّة'),
        );

        $this->assertSame(2.0, app(WalletGateway::class)->balance($user, 'tickets'));

        $this->assertSame(
            0,
            Transaction::query()->where('user_id', $user->id)->where('source', 'event')->count(),
            'الرفض لا يترك أثرًا في الدفتر',
        );
    }

    // ------------------------------------------------------------ مساعدات

    /** يشغّل كلّ مسار يكتب في الدفتر عبر `WalletGateway` */
    private function runEveryGatewayPath(): void
    {
        $owner = $this->trainee(tickets: 30);
        $joiner = $this->trainee(tickets: 30);

        // حرب التركيز: إنشاء · انضمام · إلغاء (استرجاع)
        $focus = app(FocusWarService::class);
        $war = $focus->create($owner, $this->challenge('focus_war'), 25, 'مذاكرة', true);
        $focus->join($joiner, $war);
        $focus->cancel($owner, $war->refresh());

        // الستريك: مكافأة السلسلة · درع التجميد
        $streak = app(StreakService::class);
        $today = CarbonImmutable::now();

        $streaker = $this->trainee(tickets: 10);
        foreach ([6, 5, 4, 3, 2, 1, 0] as $offset) {
            $this->recordDay($streaker, $today->subDays($offset)->toDateString());
        }
        $streak->recalculate($streaker);
        $streak->claimReward($streaker);

        $frozen = $this->trainee(tickets: 10);
        foreach ([0, 1, 3, 4] as $offset) {
            $this->recordDay($frozen, $today->subDays($offset)->toDateString());
        }
        $streak->recalculate($frozen);
        $streak->buyFreeze($frozen);

        // المواجهة: تحويل الرهان + حرق عقوبة الانسحاب
        $a = $this->trainee(tickets: 30);
        $b = $this->trainee(tickets: 30);
        $match = $this->startMatch($a, $b);
        app(WarMatchService::class)->withdraw($match->refresh(), $b);
    }

    private function recordDay(User $user, string $day): void
    {
        StreakDay::query()->firstOrCreate(
            ['user_id' => $user->id, 'day' => $day],
            ['club_5am' => false, 'xp_awarded' => 0, 'is_freeze' => false],
        );
    }

    /** الحدّ اليوميّ يُعلَن حيث يعلنه المالك: صفّ الكسب في `xp_rules.earn` (24.2) */
    private function capRewardQuestionAt(int $cap): void
    {
        SettingsWriter::put('xp_rules.earn', [
            ['key' => 'reward.question', 'label' => 'سؤال مكافأة', 'value' => 100, 'daily_cap' => $cap, 'enabled' => true],
        ]);
    }

    private function publishedQuestion(int $xp): RewardQuestion
    {
        return app(RewardQuestionService::class)->save([
            'prompt' => 'سؤال مكافأة رقم '.str()->random(5),
            'type' => 'text',
            'correct_answer' => 'صح',
            'reward_xp' => $xp,
            'reward_tickets' => 0,
            'active_minutes' => 60,
            'status' => 'published',
        ]);
    }
}
