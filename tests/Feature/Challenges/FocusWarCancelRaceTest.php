<?php

namespace Tests\Feature\Challenges;

use App\Models\FocusWar;
use App\Models\User;
use App\Services\Gamification\WalletGateway;
use App\Services\Gamification\Wars\Exceptions\WarRuleException;
use App\Services\Gamification\Wars\FocusWarService;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Support\Facades\DB;

/**
 * ⭐ **الإلغاء عمليّةٌ ذرّيّة** — القفل بنيةٌ لا تحسين.
 *
 * النصّ الحاكم حرفيًّا (15.2-1): «**قفل ذرّي (Atomic Lock):** بمجرد بدء تحدٍّ
 * يُقفَل الطرفان فورًا من البركة (**Transaction**) لمنع تحدّيهما من شخصين في
 * نفس اللحظة». و(15.2-6): «**منع الفارمينج:** الرابح **+2** والخاسر **−2**
 * (**محصّلة صفرية**)».
 *
 * **العطب:** `FocusWarService::cancel()` كان يفحص الحالة والرصيد **خارج
 * المعاملة** ولا يقفل صفّ الحرب — خلافًا لأخيه `WarMatchService::settle()`.
 * فطلبَا إلغاءٍ متزامنان يقرآن `status = active` معًا ⟵ **استرجاعٌ مضاعف** ⟵
 * تذاكر تُسَكّ من العدم.
 *
 * ⚠️ **ما يقيسه هذا الملفّ وما لا يقيسه — معلَنًا:**
 *  - **لا يقيس السباق نفسه.** السباق يحتاج خيطين متزامنين على قاعدةٍ تدعم
 *    القفل، وسويت الاختبار **أحاديّة الخيط على SQLite في الذاكرة**. وحارسٌ
 *    يدّعي قياس ما لا يقيسه أسوأ من غيابه.
 *  - **يقيس البنية التي تمنع السباق:** أنّ صفّ الحرب يُقرَأ **داخل معاملة**
 *    و**بـ`for update`** قبل أيّ استرجاع — بتسجيل استعلامات القاعدة نفسها
 *    (`DB::listen`) لا بقراءة الكود.
 *  - **ويقيس الأثر المرئيّ للذرّيّة:** الإلغاء الثاني يُرفَض، والمحصّلة صفريّة،
 *    والرفض لعجز الرصيد **لا يترك أثرًا** (المعاملة تُرجَع كاملة).
 *
 * والطفرات التي يمسكها:
 *  - نزعُ `lockForUpdate()` ⟵ يسقط `the_war_row_is_read_under_a_row_lock`.
 *  - إخراجُ القراءة من المعاملة ⟵ يسقط الاختبار نفسه.
 *  - إعادةُ فحص الحالة إلى خارج المعاملة ⟵ يسقط `the_status_check_happens_after_the_lock`.
 */
class FocusWarCancelRaceTest extends ChallengeTestCase
{
    /** علامةُ «هذا الاستعلام طلب قفل صفّ» في نصّ SQL — انظر `recordQueries()` */
    public const LOCK_MARK = 'lock:for-update';

    /** سويّة المعاملة **قبل** القياس — `RefreshDatabase` يلفّ الاختبار بمعاملةٍ أصلًا */
    private int $baselineTx = 0;

    /**
     * ⭐ **القياس المباشر للقفل**: صفّ `focus_wars` يُقرَأ **داخل معاملة**
     * و**بطلب قفلٍ صريح** — قبل أن يتحرّك مليمٌ واحد.
     */
    public function test_the_war_row_is_read_under_a_row_lock(): void
    {
        [$owner, $war] = $this->warWithOneJoiner();

        $log = $this->recordQueries(fn () => app(FocusWarService::class)->cancel($owner, $war));

        $locked = $this->indexOf($log, fn (array $q) => str_contains($q['sql'], 'from "focus_wars"')
            && str_contains($q['sql'], self::LOCK_MARK));

        $this->assertNotNull($locked,
            'صفّ الحرب يُقرَأ بلا `lockForUpdate()` — فطلبان متزامنان يمرّان معًا ويسترجعان مرّتين (15.2-1 · 15.2-6).');

        /*
         | ⚠️ **السويّة تُقارَن بخطّ الأساس لا بالصفر**: `RefreshDatabase` يلفّ كلّ
         |    اختبارٍ في معاملةٍ أصلًا، فـ«داخل معاملة» بالمعنى المطلق صحيحةٌ دائمًا
         |    ولا تقيس شيئًا. المقياس الصحيح: معاملةٌ **زادت** فوق ما كان.
         */
        $this->assertGreaterThan($this->baselineTx, $log[$locked]['tx'],
            'القراءة المقفولة وقعت **خارج معاملة الإلغاء** — والقفل خارجها يُرفَع فورًا فلا يحمي شيئًا (15.2-1).');

        // وأوّل تحويلٍ للمحفظة يقع **بعد** القفل لا قبله
        $firstWrite = $this->indexOf($log, fn (array $q) => str_contains($q['sql'], 'insert into "transactions"'));

        $this->assertNotNull($firstWrite, 'لم يقع استرجاعٌ أصلًا — فالاختبار لا يقيس الترتيب.');
        $this->assertGreaterThan($locked, $firstWrite,
            'الاسترجاع بدأ قبل قفل صفّ الحرب — فالقفل جاء متأخّرًا عن الضرر.');
    }

    /** ⭐ وفحص الحالة نفسه **داخل** المعاملة وبعد القفل، لا لقطةً قديمة قبله. */
    public function test_the_status_check_happens_after_the_lock(): void
    {
        [$owner, $war] = $this->warWithOneJoiner();

        // نسخةٌ في اليد تقول «نشط» بينما القاعدة تقول «ملغيّ» — تمامًا كما يرى
        // الطلبُ الثاني الحربَ لحظة دخوله، قبل أن ينتهي الأوّل
        $stale = FocusWar::query()->whereKey($war->id)->first();
        FocusWar::query()->whereKey($war->id)->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $this->assertSame('active', $stale->status, 'النسخة في اليد لازم تبقى قديمةً وإلّا لا يقيس الاختبار شيئًا.');

        $before = $this->systemTickets();

        $this->expectException(WarRuleException::class);

        try {
            app(FocusWarService::class)->cancel($owner, $stale);
        } finally {
            $this->assertEqualsWithDelta($before, $this->systemTickets(), 0.001,
                'استرجاعٌ وقع رغم أنّ الحرب ملغيّةٌ في القاعدة — وهو عين الاسترجاع المضاعف.');
        }
    }

    /** ⭐ الأثر المرئيّ: إلغاءٌ ثانٍ **لا يسترجع مرّةً أخرى** والمحصّلة صفريّة. */
    public function test_a_second_cancel_refunds_nothing(): void
    {
        [$owner, $war, $joiner] = $this->warWithOneJoiner();

        $system = $this->systemTickets();

        $this->assertSame(1, app(FocusWarService::class)->cancel($owner, $war));
        $afterFirst = [$this->ticketsOf($owner), $this->ticketsOf($joiner)];

        try {
            app(FocusWarService::class)->cancel($owner, $war->fresh());
            $this->fail('الإلغاء الثاني مرّ — فالحماية شكليّة.');
        } catch (WarRuleException) {
            // متوقَّع
        }

        $this->assertSame($afterFirst, [$this->ticketsOf($owner), $this->ticketsOf($joiner)],
            'الإلغاء الثاني حرّك رصيدًا — استرجاعٌ مضاعف (خرق 15.2-6).');

        $this->assertEqualsWithDelta($system, $this->systemTickets(), 0.001,
            'مجموع تذاكر النظام تغيّر — والاسترجاع تحويلٌ لا سكّ (15.3).');
    }

    /** ⚠️ ورفضُ الإلغاء لعجز الرصيد **لا يترك أثرًا** — المعاملة تُرجَع كاملة. */
    public function test_a_rejected_cancel_leaves_no_trace(): void
    {
        [$owner, $war] = $this->warWithOneJoiner();

        // نفرّغ رصيد صاحب التحدّي فلا يغطّي التذكرة المستحقّة (15.3 — شرط التغطية)
        app(WalletGateway::class)
            ->debit($owner, 'tickets', $this->ticketsOf($owner), 'grant', 'تفريغ للاختبار');

        $system = $this->systemTickets();

        try {
            app(FocusWarService::class)->cancel($owner, $war);
            $this->fail('الإلغاء مرّ برصيدٍ لا يغطّي — خرق شرط التغطية (15.3).');
        } catch (WarRuleException) {
            // متوقَّع
        }

        $this->assertSame('active', $war->fresh()->status,
            'الحرب صارت ملغيّةً رغم رفض الإلغاء — أثرٌ نجا من معاملةٍ مرفوضة.');
        $this->assertEqualsWithDelta($system, $this->systemTickets(), 0.001);
    }

    // ------------------------------------------------------------------ أدوات

    /**
     * حربٌ نشطة فيها منضمٌّ واحد لم ينتهِ وقته — أي تذكرةٌ **مستحقّة الاسترجاع**.
     *
     * @return array{0: User, 1: FocusWar, 2: User}
     */
    private function warWithOneJoiner(): array
    {
        $owner = $this->trainee(tickets: 20);
        $joiner = $this->trainee(tickets: 20);

        $service = app(FocusWarService::class);
        $war = $service->create($owner, $this->challenge('focus_war'), 50, null, true);
        $service->join($joiner, $war);

        return [$owner, $war->fresh(), $joiner];
    }

    /**
     * تسجيل استعلامات القاعدة أثناء تنفيذٍ بعينه — القياس من القاعدة لا من الكود.
     *
     * ⚠️ **لماذا نحقن نحويًّا (Grammar) للقفل؟** لأنّ نحو SQLite يُسقِط
     *    `for update` من نصّ الاستعلام (`SQLiteGrammar::compileLock()` يرجع
     *    سلسلةً فارغة)، فطلبُ القفل يصير **غير مرئيّ في السجلّ** على قاعدة
     *    الاختبارات. فنُركّب نحوًا يرسم **طلبَ القفل** تعليقًا في نفس
     *    الاستعلام — والطلب هو ما نقيسه، ومصدره **بنّاء الخدمة نفسه** لا
     *    الاختبار. ولا يمسّ ذلك تنفيذ الاستعلام (تعليقٌ صالح في SQL).
     *
     * وسويّة المعاملة تُلتقَط **لحظة تنفيذ الاستعلام** لا بعده، لأنّ SQLite لا
     * يسجّل `begin` في `DB::listen` أصلًا — فالسويّة هي الدليل المتاح والمباشر.
     *
     * @return array<int, array{sql: string, tx: int}>
     */
    private function recordQueries(callable $run): array
    {
        $connection = DB::connection();
        $original = $connection->getQueryGrammar();

        $connection->setQueryGrammar(new class($connection) extends SQLiteGrammar
        {
            protected function compileLock(Builder $query, $value)
            {
                return $value === false ? '' : '/* '.FocusWarCancelRaceTest::LOCK_MARK.' */';
            }
        });

        $log = [];
        $this->baselineTx = $connection->transactionLevel();

        DB::listen(function ($query) use (&$log, $connection) {
            $log[] = ['sql' => strtolower($query->sql), 'tx' => $connection->transactionLevel()];
        });

        try {
            $run();
        } finally {
            $connection->setQueryGrammar($original);
        }

        return $log;
    }

    /** @param  array<int, array{sql: string, tx: int}>  $log */
    private function indexOf(array $log, callable $matches): ?int
    {
        foreach ($log as $index => $query) {
            if ($matches($query)) {
                return $index;
            }
        }

        return null;
    }
}
