<?php

namespace Tests\Feature\Challenges;

use App\Models\Country;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\Volunteer\SettingsCatalog;
use App\Services\Admin\Volunteer\SettingsWriter;
use App\Services\Gamification\EconomyLedger;
use App\Services\Gamification\EconomyRules;
use App\Services\Gamification\LevelResolver;
use App\Services\Gamification\StreakService;
use Carbon\CarbonImmutable;

/**
 * مصدر الحقيقة في اقتصاد التلعيب (2.13 · 7 · 7.2 · 7.3).
 *
 * عطلان يقفلهما هذا الملفّ:
 *  1. **XP النادي كان يُسَكّ خارج الدفتر**: يُودَع في المحفظة مباشرةً ويزيد
 *     `users.xp` بيده، فلا يمرّ بـ`EconomyLedger` ولا يُزامَن معه المستوى.
 *  2. **صفوفٌ في «مصادر كسب XP» بلا مستدعٍ**: يضبطها المالك فلا يتغيّر شيء —
 *     وأخطرها `five_am_club` لأنّ قيمته الحقيقيّة في **سلّم الحضور** (7.2).
 */
class EconomySourceOfTruthTest extends ChallengeTestCase
{
    /** ⭐ XP النادي يمرّ من الدفتر الموحّد كأيّ XP آخر (7.3). */
    public function test_club_xp_is_written_through_the_economy_ledger(): void
    {
        $user = $this->clubUser();

        app(StreakService::class)->checkIn($user, CarbonImmutable::parse('2026-07-01 05:00:00', 'Africa/Cairo'));

        $transaction = Transaction::query()
            ->where('user_id', $user->id)
            ->where('source', 'streak')
            ->whereHas('currency', fn ($q) => $q->where('code', app(EconomyLedger::class)->xpCode()))
            ->first();

        $this->assertNotNull($transaction, 'XP النادي لازم يتسجّل في دفتر الأستاذ لا في المحفظة وحدها');
        $this->assertSame(100.0, (float) $transaction->amount);
        $this->assertSame(100, (int) $user->refresh()->xp);
    }

    /** والمستوى يتزامن مع النقاط لحظة المنح — لا عمودٌ يتأخّر عن الدفتر (7.3). */
    public function test_club_xp_keeps_the_level_column_in_step(): void
    {
        $user = $this->clubUser();

        app(StreakService::class)->checkIn($user, CarbonImmutable::parse('2026-07-01 05:00:00', 'Africa/Cairo'));

        $user->refresh();

        $this->assertSame(
            app(LevelResolver::class)->levelFor((int) $user->xp),
            (int) $user->level,
            'المستوى دالّةٌ في XP — فلا يبقى العمود على قيمته القديمة بعد منحة النادي',
        );
    }

    /**
     * ⭐ **مصدرٌ واحد لقيمة XP النادي**: سلّم الحضور (7.2).
     * ولو أعاد أحدٌ صفّ `five_am_club` إلى جدول الكسب فلن يغيّر شيئًا —
     * وهذا بالضبط سبب تعليم الصفّ «بلا مستهلك» في الشاشة بدل تركه يخدع المالك.
     */
    public function test_a_flat_earn_row_cannot_override_the_club_ladder(): void
    {
        $user = $this->clubUser();

        SettingsWriter::put('xp_rules.earn', [
            ['key' => 'five_am_club', 'label' => 'حضور نادي الخامسة', 'value' => 7, 'daily_cap' => 0, 'enabled' => true],
        ]);

        $result = app(StreakService::class)->checkIn($user, CarbonImmutable::parse('2026-07-01 05:00:00', 'Africa/Cairo'));

        $this->assertSame(100, $result['xp'], 'قيمة النادي من السلّم وحده — لا من صفٍّ مسطّح في جدول الكسب');
    }

    /** ⭐ لا صفَّ نشحنه بلا مستدعٍ — «إعدادٌ بلا أثر» ممنوع (2.13). */
    public function test_every_shipped_earn_row_has_a_consumer(): void
    {
        [, , , $default] = SettingsCatalog::all()['xp_rules.earn'];

        $rows = json_decode((string) $default, true);

        $this->assertIsArray($rows);
        $this->assertNotSame([], $rows);

        foreach ($rows as $row) {
            $this->assertArrayHasKey(
                (string) $row['key'],
                EconomyRules::CONSUMED_EARN,
                'الصفّ `'.$row['key'].'` مالوش مستهلك في الكود — يتشال من الافتراضيّات أو يتوصّل بمستهلك',
            );
        }
    }

    /** والقائمة المعلَنة ليست حبرًا: كلّ مفتاح فيها يقرؤه الكود فعلًا. */
    public function test_the_declared_consumer_list_matches_the_code(): void
    {
        $rules = app(EconomyRules::class);

        foreach (array_keys(EconomyRules::CONSUMED_EARN) as $key) {
            $this->assertTrue($rules->earnHasConsumer($key));

            $this->assertTrue(
                $this->readSomewhereInServices($key),
                'المفتاح `'.$key.'` معلَنٌ كمستهلَك ولا أحد يقرؤه',
            );
        }
    }

    // ------------------------------------------------------------ مساعدات

    /** هل يذكر مفتاحَ الكسبِ ملفُّ خدمةٍ غير ملفّ القائمة نفسه؟ */
    private function readSomewhereInServices(string $key): bool
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Services'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php' || $file->getFilename() === 'EconomyRules.php') {
                continue;
            }

            if (str_contains((string) file_get_contents($file->getPathname()), "'".$key."'")) {
                return true;
            }
        }

        return false;
    }

    private function clubUser(): User
    {
        $country = Country::updateOrCreate(['iso2' => 'EG'], [
            'name_ar' => 'مصر', 'name_en' => 'Egypt', 'timezone' => 'Africa/Cairo', 'is_active' => true,
        ]);

        $user = $this->trainee();
        $user->forceFill(['country_id' => $country->id, 'xp' => 0, 'level' => 1])->save();

        return $user->refresh();
    }
}
