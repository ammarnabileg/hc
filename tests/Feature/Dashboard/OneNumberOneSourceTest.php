<?php

namespace Tests\Feature\Dashboard;

use App\Models\Course;
use App\Models\Currency;
use App\Models\Enrollment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletBalance;
use App\Services\Account\AchievementTracks;
use App\Services\Dashboard\DashboardService;
use App\Services\Dashboard\DashboardStatsService;
use App\Services\Gamification\LevelResolver;
use App\Services\Gamification\TicketsAccount;
use Database\Seeders\CoreSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ⭐ **رقمٌ واحد ⟵ مصدرٌ واحد** (ن-2) — الدستور 7 · 7.1 · 10 · 10.1 · 24.5-أ.
 *
 * العطل المرصود: نفس المستخدم في اللحظة نفسها يُعرَض بأرقامٍ متناقضة —
 * «المستوى 3» في كارت الـKPI و«مستوى 4» في رادار الصفحة نفسها، وثلاثة أرقامٍ
 * للتذاكر بلا ما يفسّر الفارق، وبار XP في السايد بار ثابتٌ على 30٪ لكلّ البشر.
 *
 * وكلّ اختبارٍ هنا **حارسٌ يسقط** إن عاد رقمٌ يُحسَب في موضعين.
 */
class OneNumberOneSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        // التسميات والعتبات كلّها إعدادات (2.13) — فبلا زرعها تُقرَأ المفاتيح لا الأسماء
        $this->seed(SettingSeeder::class);

        Permission::firstOrCreate(
            ['key' => 'enrollments.view'],
            [
                'resource' => 'enrollments', 'action' => 'view',
                'group' => 'الأكاديمية', 'label_ar' => 'تسجيلاتي',
                'allowed_scopes' => ['SELF', 'ALL'],
            ],
        );
    }

    // ------------------------------------------------------------ أدوات

    private function trainee(int $xp = 0, int $tickets = 0): User
    {
        $role = Role::firstOrCreate(['key' => 'trainee'], ['name_ar' => 'متدرّب', 'layer' => 'platform']);

        DB::table('permission_role')->insertOrIgnore([
            'role_id' => $role->id,
            'permission_id' => Permission::where('key', 'enrollments.view')->value('id'),
            'scope' => 'SELF',
            'effect' => 'allow',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::create([
            'name' => 'متدرّب اختبار',
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => 'active',
            'xp' => $xp,
        ]);

        $user->assignRole($role);

        if ($xp > 0) {
            $this->wallet($user, 'xp', $xp, $xp, 0);
        }

        if ($tickets > 0) {
            $this->wallet($user, 'tickets', $tickets, $tickets, 0);
        }

        return $user->fresh();
    }

    /** تسجيلٌ واحد — بدونه تعرض اللوحة الحالة الفارغة بلا تابات ولا رادار */
    private function enroll(User $user): void
    {
        $course = Course::create([
            'slug' => str()->random(10),
            'name_ar' => 'تدريب اختبار',
            'status' => 'published',
            'xp_max' => 300,
        ]);

        Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'started_at' => now()->subDays(10),
            'deadline_at' => now()->addDays(10),
            'xp_earned' => 0,
            'status' => 'active',
        ]);
    }

    private function wallet(User $user, string $code, float $balance, float $earned, float $spent): void
    {
        WalletBalance::updateOrCreate(
            ['user_id' => $user->id, 'currency_id' => Currency::where('code', $code)->value('id')],
            ['balance' => $balance, 'lifetime_earned' => $earned, 'lifetime_spent' => $spent],
        );
    }

    private function ticketMove(User $user, float $amount, string $source = 'academy'): void
    {
        Transaction::create([
            'user_id' => $user->id,
            'currency_id' => Currency::where('code', 'tickets')->value('id'),
            'amount' => $amount,
            'layer' => 'training',
            'source' => $source,
            'reason' => $amount > 0 ? 'إكمال درس' : 'دخول امتحان',
        ]);
    }

    // ------------------------------------------------- 1) المستوى: رقمٌ واحد لكلّ العارضين

    /**
     * ⭐ الحارس الأوّل: **المستوى نفسه في كلّ موضع**.
     *
     * كارت الـKPI · رادار الإنجازات · بطاقة السايد بار · هيدر البروفايل ·
     * العمود المخبَّأ `users.level` · وشرط الشارات — كلّها رقمٌ واحد.
     * وطفرةُ إعادة الحساب في أيّ واحدٍ منها تُسقط هذا الاختبار.
     */
    public function test_the_account_level_is_one_number_in_every_viewer(): void
    {
        // 3,370 XP: عتبات 10.1 ⟵ L4 يبدأ 2,250 و L5 يبدأ 3,500 — فالمستوى 4
        $user = $this->trainee(xp: 3370);
        $this->enroll($user);

        $expected = app(LevelResolver::class)->levelFor(3370);
        $this->assertSame(4, $expected, 'عتبة 10.1: 2,250 ≤ 3,370 < 3,500 ⟵ المستوى 4');

        // (أ) كارت الـKPI
        $levelCard = collect(app(DashboardService::class)->kpis($user))->firstWhere('icon', 'xp');
        $this->assertStringContainsString('المستوى '.$expected, (string) $levelCard['hint']);
        $this->assertSame(3370, $levelCard['value']);

        // (ب) رادار الإنجازات — مسار «مستوى الحساب»
        $radar = app(DashboardStatsService::class)->achievementsRadar($user);
        $account = collect($radar['axes'])->first();
        $this->assertSame($expected, $account['level']);
        $this->assertSame(3370, $account['value']);

        // (ج) مسارات الإنجازات كما يقرؤها البروفايل
        $track = collect(app(AchievementTracks::class)->forUser($user))->firstWhere('key', 'account');
        $this->assertSame($expected, $track['level']);

        // (د) العمود المخبَّأ بعد المزامنة — وهو ما يقرؤه هيدر البروفايل والإفادة
        app(LevelResolver::class)->sync($user);
        $this->assertSame($expected, (int) $user->fresh()->level);

        // (هـ) الـDOM: الرقم نفسه في السايد بار وفي كارت الـKPI على الصفحة الواحدة
        $html = $this->actingAs($user)->get('/dashboard?tab=overview')->assertOk()->getContent();
        $this->assertStringContainsString('المستوى '.$expected, $html);
        $this->assertStringNotContainsString('المستوى '.($expected + 1).' —', $html);

        $stats = $this->actingAs($user)->get('/dashboard?tab=stats')->assertOk()->getContent();
        $this->assertStringContainsString('مستوى الحساب: مستوى '.$expected, $stats);

        /*
         | ⭐ وفوق سقف جدول الأسماء (ثمانية صفوف) يبقى الرقم واحدًا كذلك.
         | فأيّ عارضٍ يعود يقرأ عتبات الجدول سيقف عند «8» بينما الصيغة تمضي —
         | وهنا **بالضبط** ينكشف المصدر الثاني ولو تصادفت العتبات تحت السقف.
         */
        $veteran = $this->trainee(xp: 50_000);
        $this->enroll($veteran);

        $expectedTop = app(LevelResolver::class)->levelFor(50_000);
        $this->assertGreaterThan(8, $expectedTop);

        $topCard = collect(app(DashboardService::class)->kpis($veteran))->firstWhere('icon', 'xp');
        $topRadar = collect(app(DashboardStatsService::class)->achievementsRadar($veteran)['axes'])->first();

        $this->assertStringContainsString('المستوى '.$expectedTop, (string) $topCard['hint']);
        $this->assertSame($expectedTop, $topRadar['level']);
    }

    /**
     * ⭐ عتبات 10.1 حرفيًّا — **والمستويات مفتوحة بلا سقف بنفس المعادلة**.
     * فلا جدولَ ذو ثمانية صفوف يقصّ العدّ عند «أسطورة».
     */
    public function test_level_thresholds_match_10_1_and_never_cap(): void
    {
        $levels = app(LevelResolver::class);

        // جدول «التراكمي L1 → L10» في 10.1 لعمود «الحساب (XP)»
        foreach ([499 => 1, 500 => 2, 1250 => 3, 2250 => 4, 3500 => 5, 5000 => 6, 6750 => 7, 8750 => 8, 11000 => 9, 13500 => 10] as $xp => $level) {
            $this->assertSame($level, $levels->levelFor($xp), "XP {$xp} يوافق المستوى {$level} في جدول 10.1");
        }

        // «المستويات مفتوحة بلا سقف» — فما بعد آخر اسمٍ معرَّف يستمرّ العدّ
        $this->assertGreaterThan(8, $levels->levelFor(50_000));
        $this->assertNotSame('', $levels->nameFor($levels->levelFor(50_000)));
    }

    // ------------------------------------------------- 2) بار XP: يُحسَب لا يُحرَق

    /**
     * ⭐ الحارس الثاني: **مستخدمان مختلفان ⟵ بارَا XP مختلفان**.
     * كان البار `$u->xp_percent ?? 30` و`xp_percent` غير معرَّف أصلًا،
     * فيرى كلّ مستخدمي المنصّة **البار نفسه**. وهذا الاختبار يمنع عودته.
     */
    public function test_two_users_see_two_different_xp_bars(): void
    {
        // 750 XP: داخل المستوى 2 (500 → 1,250) ⟵ 250 من 750 = 33٪
        $a = $this->trainee(xp: 750);
        // 3,370 XP: داخل المستوى 4 (2,250 → 3,500) ⟵ 1,120 من 1,250 = 90٪
        $b = $this->trainee(xp: 3370);
        $this->enroll($a);
        $this->enroll($b);

        $levels = app(LevelResolver::class);
        $pa = $levels->forUser($a);
        $pb = $levels->forUser($b);

        $this->assertSame(33, $pa['percent']);
        $this->assertSame(90, $pb['percent']);
        $this->assertNotSame($pa['percent'], $pb['percent']);

        // والـDOM يعرض ما حُسِب لا رقمًا ثابتًا
        $htmlA = $this->actingAs($a)->get('/dashboard?tab=overview')->assertOk()->getContent();
        $htmlB = $this->actingAs($b)->get('/dashboard?tab=overview')->assertOk()->getContent();

        $this->assertStringContainsString('data-xp-percent="33"', $htmlA);
        $this->assertStringContainsString('data-xp-percent="90"', $htmlB);
        $this->assertStringNotContainsString('data-xp-percent="30"', $htmlA);
    }

    /** لا `xp_percent` ولا 30 محروقة في قالب السايد بار — الطفرة تُمسَك في المصدر */
    public function test_the_sidebar_xp_bar_has_no_hardcoded_fallback(): void
    {
        $blade = (string) file_get_contents(resource_path('views/partials/sidebar.blade.php'));

        // التعليقات تشرح الطفرة القديمة، فتُنزَع قبل الفحص كي لا يُمسَك الشرح مكان الكود
        $code = preg_replace(['/\{\{--.*?--\}\}/s', '/\/\*.*?\*\//s'], '', $blade);

        $this->assertStringNotContainsString('xp_percent ?? 30', (string) $code);
        $this->assertStringNotContainsString('$u->xp_percent', (string) $code);
    }

    // ------------------------------------------------- 3) التذاكر: ثلاثة مقادير بميزانٍ منغلق

    /**
     * ⭐ الحارس الثالث: **مكتسب − مصروف = رصيد** — دائمًا.
     *
     * على اللوحة ثلاثة أرقامٍ للتذاكر (رصيد الـKPI · مكتسب الرادار · بارات المدى)،
     * وهي ثلاثة **مقادير** لا ثلاث إجابات. فإن لم ينغلق الميزان صارت تناقضًا.
     */
    public function test_the_three_ticket_numbers_close_the_books(): void
    {
        $user = $this->trainee(xp: 100);

        // دفترٌ حقيقيّ: كسبٌ وصرفٌ ثمّ رصيدٌ مطابق
        $this->wallet($user, 'tickets', balance: 6, earned: 22, spent: 16);
        $this->ticketMove($user, 12);
        $this->ticketMove($user, 10);
        $this->ticketMove($user, -16, 'purchase');

        $tickets = app(TicketsAccount::class);
        $sheet = $tickets->snapshot($user);

        $this->assertSame(22, $sheet['earned']);
        $this->assertSame(16, $sheet['spent']);
        $this->assertSame(6, $sheet['balance']);
        $this->assertSame($sheet['balance'], $sheet['earned'] - $sheet['spent'], 'الميزان لا ينغلق');

        // الرادار يقرأ **المكتسب** (10)، وكارت الـKPI يقرأ **الرصيد** (10.0-أ)
        $track = collect(app(AchievementTracks::class)->forUser($user))->firstWhere('key', 'tickets');
        $this->assertSame(22, $track['value']);

        $ticketCard = collect(app(DashboardService::class)->kpis($user))->firstWhere('icon', 'ticket');
        $this->assertSame(6, $ticketCard['value']);

        // والاسم يفرّق بين المقدارين صراحةً فلا يُقرآن تناقضًا
        $this->assertSame('رصيد التذاكر', $ticketCard['label']);
        $this->assertSame('التذاكر المكتسبة', $track['label']);
    }

    /**
     * ⭐ ولو تناقض المخزن مع الدفتر (بيانات ترحيل أو تمهيد) **يبقى الميزان منغلقًا**:
     * الفارق يُنسَب إلى رصيدٍ افتتاحيّ مسمّى، ولا يُعرَض رقمٌ بلا تفسير.
     */
    public function test_a_drifted_wallet_row_still_closes_the_books(): void
    {
        $user = $this->trainee(xp: 100);

        // عدّاد `lifetime_earned` مكذوب (6) بينما الدفتر يقول 22 كسبًا و10 صرفًا
        $this->wallet($user, 'tickets', balance: 6, earned: 6, spent: 29);
        $this->ticketMove($user, 22);
        $this->ticketMove($user, -10, 'purchase');

        $sheet = app(TicketsAccount::class)->snapshot($user);

        $this->assertSame(6, $sheet['balance']);
        $this->assertSame($sheet['balance'], $sheet['earned'] - $sheet['spent']);
        $this->assertGreaterThanOrEqual(22, $sheet['earned'], 'المكتسب لا يقلّ عمّا سجّله الدفتر');

        // ⭐ ومسار الرادار يقرأ من الميزان نفسه لا من العدّاد المكذوب في المخزن
        $track = collect(app(AchievementTracks::class)->forUser($user))->firstWhere('key', 'tickets');
        $this->assertSame($sheet['earned'], $track['value']);
        $this->assertNotSame(6, $track['value'], 'الرادار عاد يقرأ lifetime_earned بدل الدفتر');
    }

    /** بارات المدى من الدفتر نفسه — فمجموعها لا يتجاوز المكتسب الكلّيّ أبدًا */
    public function test_the_range_bars_never_exceed_the_lifetime_totals(): void
    {
        $user = $this->trainee(xp: 100);

        $this->wallet($user, 'tickets', balance: 4, earned: 10, spent: 6);
        $this->ticketMove($user, 10);
        $this->ticketMove($user, -6, 'purchase');

        $tickets = app(TicketsAccount::class);
        $sheet = $tickets->snapshot($user);
        $bars = $tickets->flow($user, 30);

        $this->assertLessThanOrEqual($sheet['earned'], array_sum(array_column($bars, 'earned')));
        $this->assertLessThanOrEqual($sheet['spent'], array_sum(array_column($bars, 'spent')));
    }

    // ------------------------------------------------- 4) لا مصدر ثانٍ في الكود

    /**
     * ⭐ الحارس الأخير: **لا عارضَ يعيد الحساب**.
     * أيّ قراءةٍ لعتبات `levels.min_xp` كمصدرٍ للمستوى، أو لعمود `lifetime_earned`
     * كمصدرٍ للمكتسب، تُعيد التناقض غدًا — فتُمسَك هنا في المصدر نفسه.
     */
    public function test_no_second_computation_survives_in_the_readers(): void
    {
        $readers = [
            app_path('Services/Dashboard/DashboardService.php'),
            app_path('Services/Dashboard/DashboardStatsService.php'),
            app_path('Services/Account/AchievementTracks.php'),
            app_path('Services/Account/ProfileTabs.php'),
        ];

        foreach ($readers as $file) {
            $code = preg_replace('/\/\*.*?\*\/|\/\/[^\n]*/s', '', (string) file_get_contents($file));

            $this->assertStringNotContainsString("'min_xp'", $code, basename($file).' يقرأ عتبات المستوى بنفسه');
            $this->assertStringNotContainsString('lifetime_earned', $code, basename($file).' يقرأ المكتسب بنفسه');
        }
    }
}
