<?php

namespace Tests\Feature\Access;

use App\Http\Controllers\Admin\GamificationController;
use App\Models\Currency;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\System\StatsService;
use App\Support\Access\AccessEngine;
use Database\Seeders\AdminSystemDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ⭐⭐ **الباب بسعة محتواه** (12.8 · 24.3-خامسًا · 12.2.1-أ).
 *
 * ================== النصّ الحاكم ==================
 * • **12.2.1-أ:** «**ممنوع صلاحيّة باسم شاشة** — الشاشة نتيجةٌ للصلاحيّات لا
 *   صلاحيّةً بذاتها».
 * • **24.3-خامسًا (الإحصائيّات) — الحالات:** «**بلا صلاحيّة: التاب نفسه لا
 *   يظهر**» — التابّ لا الصفحة.
 * • **12.2.2:** `reports_training.view` · `reports_volunteer.view` ·
 *   `reports_certificates.view` — شرطُ كلٍّ منها «**دائمًا**» لا «مالك المنصّة
 *   فقط»، ونطاقاتها تصل ALL.
 *
 * ================== العطب الذي تحرسه ==================
 * كان `/admin/stats` محروسًا بـ`reports_users.view` **وحدها**، فصاحبُ أيّ تقريرٍ
 * آخر يُردّ **بـ403 قبل أن يُسأل عن تابِّه**. وعولج بإخفاء البند من السايد بار —
 * وهو علاجٌ يمنع رسالة الخطأ ولا يعطي صاحبَ الحقّ حقَّه: القدرة موجودة في
 * المصفوفة، ممنوحةٌ لدورٍ منصوص، ولا مدخل لها في المنصّة كلّها.
 *
 * ونظيرُه في `/admin/gamification`: بابٌ بخمسة مفاتيح لثمانية تابات.
 */
class ReportTabsDoorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        // مفاتيح مجال النظام (منها `acquisition_sources`) وصفوف إعدادات الإحصائيّات
        $this->seed(AdminSystemDemoSeeder::class);
    }

    // ------------------------------------------------------------------ أدوات

    private function makeUser(string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => str()->lower(str()->random(12)).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => 'active',
        ]);
    }

    /** دورٌ مصنوع كما ينصّ 12.2.3: «إنشاء دور جديد = **نسخ قالب وتعديله**» */
    private function actorHolding(array $keysByScope, string $roleKey): User
    {
        $role = Role::create(['key' => $roleKey, 'name_ar' => $roleKey, 'layer' => 'platform']);

        foreach ($keysByScope as $key => $scope) {
            DB::table('permission_role')->insert([
                'role_id' => $role->id,
                'permission_id' => Permission::where('key', $key)->value('id'),
                'scope' => $scope,
                'effect' => 'allow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $user = $this->makeUser($roleKey);
        $user->assignRole($roleKey);
        app(AccessEngine::class)->forget($user);

        return $user;
    }

    private function actorWithRole(string $roleKey): User
    {
        $user = $this->makeUser($roleKey);
        $user->assignRole($roleKey);
        app(AccessEngine::class)->forget($user);

        return $user;
    }

    // -------------------------------------------- الإحصائيّات: يدخل ويرى تابَّه وحده

    /** ⭐ `reports_training.view` **وحدها** تفتح الصفحة — وكانت تُردّ بـ403 */
    public function test_a_training_reports_holder_gets_in_and_sees_only_his_tab(): void
    {
        $actor = $this->actorHolding(['reports_training.view' => 'ALL'], 'r_training_only');

        $this->actingAs($actor)->get('/admin/stats')->assertOk();

        $this->assertSame(
            ['training'],
            array_keys(app(StatsService::class)->tabsFor($actor)),
            'يرى تابَّه وحده — «بلا صلاحيّة: التاب نفسه لا يظهر» (24.3-خامسًا)',
        );
    }

    /** ومَن لا تابَّ له لا يجد مفتاحًا في القائمة فيُردّ عند الباب كما كان */
    public function test_a_holder_of_no_report_key_is_still_refused(): void
    {
        // «مدير المحتوى التعليميّ» (12.2.3-أ-3) — تغطيتُه المنصوصة بلا أيّ `reports_*`
        $this->actingAs($this->actorWithRole('content_admin'))
            ->get('/admin/stats')
            ->assertForbidden();
    }

    /** ⭐ ولا يفتح له تابٌّ لا يملكه: طلبُ `?tab=users` يعود إلى تابِّه هو */
    public function test_asking_for_a_tab_he_does_not_own_never_computes_it(): void
    {
        $actor = $this->actorHolding(['reports_volunteer.view' => 'ALL'], 'r_volunteer_only');

        $this->actingAs($actor)->get('/admin/stats?tab=users')->assertOk()
            // المعروض تابُّه هو — وعنوان جدوله دليلٌ لا يلتبس
            ->assertSee((string) setting('stats.volunteer.table.sla'), false)
            // ولا حسابَ ولا عرضَ لتابٍّ لا يملكه («قمع التحويل» من تاب المستخدمين)
            ->assertDontSee('قمع التحويل', false);

        // والتصدير أصرم: تابٌّ لا يملكه لا يخرج له ملفًّا
        $this->actingAs($actor)->get('/admin/stats/export?tab=users&format=csv')->assertForbidden();
        $this->actingAs($actor)->get('/admin/stats/export?tab=volunteer&format=csv')->assertOk();
    }

    /** 🔒 والتاب الماليّ يبقى معزولًا لمالك المنصّة وحده مهما اتّسع الباب (12.7) */
    public function test_widening_the_door_did_not_open_the_finance_tab(): void
    {
        $actor = $this->actorHolding(['reports_users.view' => 'ALL', 'finance.view' => 'ALL'], 'r_users_finance');

        $this->assertArrayNotHasKey(
            'sales',
            app(StatsService::class)->tabsFor($actor),
            '🔒 المبيعات والماليّات لمالك المنصّة وحده — شرط الملكيّة فوق فحص الصلاحيّة',
        );
    }

    // ------------------------------------------------ التابّان المبنيّان حديثًا

    /** «مسؤول الشهادات» (12.2.3-أ-4) يغطّي `reports_certificates` — وكان يُردّ */
    public function test_the_certificates_admin_reaches_his_certificates_tab(): void
    {
        $actor = $this->actorWithRole('certificates_admin');

        $this->actingAs($actor)->get('/admin/stats?tab=certificates')->assertOk();

        $this->assertSame(['certificates'], array_keys(app(StatsService::class)->tabsFor($actor)));
    }

    /** والتابّان يُحسَبان فعلًا — لا لافتتان فارغتان (12.2.2: التسكين · المهامّ · SLA) */
    public function test_the_two_new_tabs_actually_compute_their_reports(): void
    {
        $stats = app(StatsService::class);
        $period = $stats->period(null, null, false);

        // التابّان موجودان في القائمة أصلًا — «التطوّع · الشهادات» (24.3-خامسًا)
        $this->assertArrayHasKey('volunteer', $stats->tabs());
        $this->assertArrayHasKey('certificates', $stats->tabs());

        $volunteer = $stats->data('volunteer', $period);
        $this->assertCount(4, $volunteer['kpis']);
        foreach (['series', 'entities', 'sla'] as $block) {
            $this->assertArrayHasKey($block, $volunteer, "تاب التطوّع يحمل «{$block}» — التسكين · المهامّ · SLA المستويات (12.2.2)");
        }

        $certificates = $stats->data('certificates', $period);
        $this->assertCount(4, $certificates['kpis']);
        foreach (['series', 'accreditations', 'types'] as $block) {
            $this->assertArrayHasKey($block, $certificates, "تاب الشهادات يحمل «{$block}» — معدّل الإصدار · الإلغاءات · حسب الاعتماد (12.2.2)");
        }
    }

    // -------------------------------------------------- تقرير أثر المكافآت (12.9)

    /**
     * ⭐ 🔒 `manual_rewards.export` منصوصة «مالك المنصّة فقط» (12.2.2) — فمنحُها
     * وحدها لا يفتح التاب، تمامًا كتاب المبيعات (12.7).
     */
    public function test_the_rewards_tab_stays_owner_only_even_with_the_permission_granted(): void
    {
        // + `reports_volunteer.view` كي يدخل الباب أصلًا — الاختبار على تابِّ المكافآت وحده
        $actor = $this->actorHolding(
            ['manual_rewards.export' => 'ALL', 'reports_volunteer.view' => 'ALL'],
            'r_rewards_no_owner',
        );

        $this->assertArrayNotHasKey(
            'rewards',
            app(StatsService::class)->tabsFor($actor),
            '🔒 تقرير أثر المكافآت لمالك المنصّة وحده — شرط الملكيّة فوق فحص الصلاحيّة',
        );

        // طلب تابٍّ لا يملكه لا يُحسَب له — يعود لتابِّه هو (نظير test_asking_for_a_tab_he_does_not_own_never_computes_it)
        $this->actingAs($actor)->get('/admin/stats?tab=rewards')->assertOk()
            ->assertDontSee((string) setting('stats.rewards.table.currencies'), false);

        $this->actingAs($actor)->get('/admin/stats/export?tab=rewards&format=csv')->assertForbidden();
    }

    /** ومالك المنصّة يصل التابّ ويُحسَب فعلًا — إجماليّ الممنوح/المخصوم لكلّ عملة حرفيًّا (12.9) */
    public function test_the_platform_owner_reaches_the_rewards_tab_and_it_computes_real_ledger_rows(): void
    {
        $owner = $this->makeUser('مالك المنصّة');
        $owner->assignRole('platform_owner');
        app(AccessEngine::class)->forget();

        $recipient = $this->makeUser('مستلِم');
        $coins = Currency::where('code', 'coins')->firstOrFail();

        // منحة 100 + خصم 30 بعملة الكوينز — «إجماليّ الممنوح/المخصوم لكلّ عملة» (12.9)
        Transaction::create([
            'user_id' => $recipient->id, 'currency_id' => $coins->id,
            'amount' => 100, 'source' => 'admin', 'layer' => 'training',
        ]);
        Transaction::create([
            'user_id' => $recipient->id, 'currency_id' => $coins->id,
            'amount' => -30, 'source' => 'admin', 'layer' => 'training',
        ]);
        // معاملة من مصدرٍ آخر — يجب ألّا تدخل تقرير المنح اليدويّة
        Transaction::create([
            'user_id' => $recipient->id, 'currency_id' => $coins->id,
            'amount' => 9999, 'source' => 'purchase', 'layer' => 'training',
        ]);

        $this->actingAs($owner)->get('/admin/stats?tab=rewards')->assertOk()
            ->assertSee((string) setting('stats.rewards.table.currencies'), false);

        $stats = app(StatsService::class);
        $this->assertArrayHasKey('rewards', $stats->tabsFor($owner));
        $this->assertArrayHasKey('rewards', $stats->tabs());

        $data = $stats->data('rewards', $stats->period(null, null, false));
        $this->assertCount(4, $data['kpis']);

        $byLabel = collect($data['kpis'])->keyBy('label');
        $this->assertSame(100.0, $byLabel[(string) setting('stats.rewards.kpi.granted')]['value']);
        $this->assertSame(30.0, $byLabel[(string) setting('stats.rewards.kpi.deducted')]['value']);
        $this->assertSame(70.0, $byLabel[(string) setting('stats.rewards.kpi.net')]['value']);

        $this->assertCount(1, $data['currencies'], 'عملة واحدة تحرّكت — الكوينز');
        $this->assertSame('كوينز', $data['currencies'][0]['label']);
        $this->assertSame(100.0, $data['currencies'][0]['granted']);
        $this->assertSame(30.0, $data['currencies'][0]['deducted']);
        $this->assertSame(70.0, $data['currencies'][0]['net']);

        // والتصدير أصرم أيضًا: تابٌّ يملكه صاحبُه يخرج له ملفًّا
        $this->actingAs($owner)->get('/admin/stats/export?tab=rewards&format=csv')->assertOk();
    }

    // ------------------------------------------------------- لا انحراف بين الباب والمحتوى

    /**
     * ⭐ **الباب يُشتقّ من التابات لا يُنسَخ عنها.**
     * قائمةٌ ثانية تنسى التابَّ الجديد فيعود الباب أضيق من محتواه — وهو عين
     * العطب الأصليّ. فالحارس هنا على **القاعدة** لا على القائمة الحاليّة.
     */
    public function test_the_door_lists_exactly_the_permissions_of_the_tabs(): void
    {
        $fromTabs = array_values(array_unique(array_filter(
            array_column(app(StatsService::class)->tabs(), 'permission'),
        )));

        sort($fromTabs);
        $gate = StatsService::gateKeys();
        sort($gate);

        $this->assertSame($fromTabs, $gate);

        $middleware = collect(app('router')->getRoutes()->getByName('admin.stats.index')->gatherMiddleware())
            ->first(fn ($m) => is_string($m) && str_starts_with($m, 'permission:'));

        $this->assertNotNull($middleware, 'المسار محروسٌ بصلاحيّة (12.2.1)');

        $onRoute = explode(',', substr((string) $middleware, strlen('permission:')));
        sort($onRoute);

        $this->assertSame($gate, $onRoute, 'سطر الميدل-وير هو نفسه قائمة التابات — لا نسخةٌ تشيخ');
    }

    // ------------------------------------------------------------------ التلعيب

    /**
     * ⭐ نظير الإحصائيّات: `/admin/gamification` كان بخمسة مفاتيح لثمانية تابات،
     * فيُردّ صاحبُ **الستريك** أو **الليدر بورد** عن شاشتهما الوحيدة.
     * و12.2.2 تفرّق: `streaks.view@SELF` («**ستريكي**») مفتاح متدرّب، والمفتاح
     * الإداريّ `streaks.list@ALL` («**متابعة الستريكات النشطة**»).
     */
    public function test_a_streaks_admin_reaches_the_gamification_screen(): void
    {
        $this->actingAs($this->actorHolding(['streaks.list' => 'ALL'], 'r_streaks_only'))
            ->get('/admin/gamification?tab=streaks')
            ->assertOk();
    }

    public function test_a_leaderboard_admin_reaches_the_gamification_screen(): void
    {
        $this->actingAs($this->actorHolding(['leaderboards.export' => 'ALL'], 'r_leaderboard_only'))
            ->get('/admin/gamification?tab=leaderboard')
            ->assertOk();
    }

    /** والباب لم يصر مشرعًا: مَن لا مفتاح له من الثمانية يُردّ */
    public function test_the_gamification_door_still_refuses_a_stranger(): void
    {
        $this->actingAs($this->actorWithRole('certificates_admin'))
            ->get('/admin/gamification')
            ->assertForbidden();
    }

    /** والمفتاحان المنصوصان في 12.2.3-أ-8 مذكوران في الباب حرفيًّا */
    public function test_the_gamification_gate_names_streaks_and_leaderboards(): void
    {
        foreach (['streaks.view', 'streaks.list', 'leaderboards.view', 'leaderboards.export'] as $key) {
            $this->assertContains($key, GamificationController::GATE_KEYS);
        }

        $middleware = collect(app('router')->getRoutes()->getByName('admin.gamification.index')->gatherMiddleware())
            ->first(fn ($m) => is_string($m) && str_starts_with($m, 'permission:'));

        $this->assertSame(
            GamificationController::GATE_KEYS,
            explode(',', substr((string) $middleware, strlen('permission:'))),
        );
    }
}
