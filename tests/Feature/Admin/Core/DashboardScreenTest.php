<?php

namespace Tests\Feature\Admin\Core;

use App\Models\AuditLog;
use App\Models\Currency;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Access\AccessEngine;
use Database\Seeders\AdminCoreDemoSeeder;
use Database\Seeders\CoreSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * لوحة القيادة (12.3 · 24.1) — البنود التي كانت مرصودةً ناقصةً.
 *
 * وكلّ اختبار هنا **يسقط حين يقع الخلل** لا يمرّ دائمًا: يفحص الوسم الناتج
 * نفسه (هل الكارت `<a>`؟ هل الرقم النهائيّ داخل الوسم؟) أو يقارن رقمًا
 * محسوبًا بمصدره الحقيقيّ.
 */
class DashboardScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(AdminCoreDemoSeeder::class);
    }

    // ------------------------------------------------------------------ أدوات

    private function makeUser(string $name = 'مستخدم', string $status = 'active'): User
    {
        return User::create([
            'name' => $name,
            'email' => str()->random(10).'@test.local',
            'password' => 'secret-password',
            'code' => str()->upper(str()->random(8)),
            'status' => $status,
        ]);
    }

    private function owner(): User
    {
        $user = $this->makeUser('مالك الاختبار');
        $user->assignRole('platform_owner');
        app(AccessEngine::class)->forget();

        return $user;
    }

    private function supportAdmin(): User
    {
        $user = $this->makeUser('مسؤول الدعم');
        $user->assignRole('support_admin');
        app(AccessEngine::class)->forget();

        return $user;
    }

    private function setting(string $key, string $value, string $type = 'string'): void
    {
        Setting::updateOrCreate(['key' => $key], [
            'group' => 'admin_dashboard',
            'label_ar' => $key,
            'type' => $type,
            'value' => $value,
        ]);

        Cache::forget('settings');
    }

    private function paidOrder(User $user, float $total, ?string $itemTitle = null): Order
    {
        $order = Order::create([
            'number' => 'ORD-'.str()->upper(str()->random(8)),
            'user_id' => $user->id,
            'subtotal' => $total,
            'total' => $total,
            'currency_id' => Currency::where('code', 'coins')->firstOrFail()->id,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        if ($itemTitle !== null) {
            $product = Product::create([
                'slug' => str()->slug('p-'.str()->random(6)),
                'name_ar' => $itemTitle,
                'type' => 'digital',
                'price_coins' => $total,
                'status' => 'published',
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'purchasable_type' => Product::class,
                'purchasable_id' => $product->id,
                'title' => $itemTitle,
                'price' => $total,
            ]);
        }

        return $order;
    }

    /** كلّ وسوم كروت الـKPI في الصفحة: [مفتاح الكارت => اسم الوسم] */
    private function cardTags(string $html): array
    {
        preg_match_all('/<(\w+)[^>]*data-kpi-card="([^"]*)"/u', $html, $matches, PREG_SET_ORDER);

        $tags = [];

        foreach ($matches as $match) {
            $tags[$match[2]] = $match[1];
        }

        return $tags;
    }

    // ------------------------------------------------- 12.3-4 · الكارت قابل للنقر

    /**
     * ⭐ **كلّ كارت KPI قابل للنقر** وينقل لشاشته التفصيليّة (12.3-4).
     *
     * والفحص على الوسم الناتج نفسه: لو عاد الكارت `<div>` بلا `href` يسقط —
     * وهذا بالضبط ما كان عليه الحال.
     */
    public function test_every_kpi_card_is_a_link_to_its_detail_screen(): void
    {
        $html = $this->actingAs($this->owner())->get(route('admin.dashboard'))->assertOk()->getContent();

        $tags = $this->cardTags($html);

        $this->assertNotEmpty($tags, 'اللوحة لازم تعرض كروت KPI');

        foreach ($tags as $key => $tag) {
            $this->assertSame('a', $tag, "كارت «{$key}» لازم يكون رابطًا قابلًا للنقر (12.3-4)");
        }

        // وكارت المستخدمين ينقل لقائمة المستخدمين بالفعل لا لرابطٍ صوريّ
        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="[^"]*'.preg_quote(parse_url(route('admin.users.index'), PHP_URL_PATH), '/').'"[^>]*data-kpi-card="users"/u',
            $html,
        );
    }

    /** ومَن لا يملك الشاشة التفصيليّة لا يُوعَد بها: الكارت يبقى رقمًا بلا رابط (2.15-أ-7). */
    public function test_a_card_without_permission_keeps_the_number_and_drops_the_link(): void
    {
        $html = $this->actingAs($this->supportAdmin())->get(route('admin.dashboard'))->assertOk()->getContent();

        $tags = $this->cardTags($html);

        $this->assertSame('a', $tags['users'] ?? null, 'مسؤول الدعم يملك قائمة المستخدمين فالكارت رابط');
        $this->assertSame('div', $tags['courses'] ?? null, 'ولا يملك إدارة التدريبات فالكارت رقمٌ بلا رابط');
    }

    // ------------------------------------------------- 12.3-2 · مقارنة بالفترة السابقة

    /**
     * ⭐ كارت قيمته في الفترة السابقة صفرٌ حرفيًّا (كـ«النشطون الآن» الذي يمرّر
     * previous=0 بنيويًّا) لازم يعرض المقارنة برضه لا يسقط منها: صفر ← قيمةٌ
     * موجبة صعودٌ كاملٌ 100% لا "غياب مقارنة" (12.3-2).
     */
    public function test_kpi_card_with_zero_previous_baseline_still_shows_the_comparison(): void
    {
        // مستخدمون "نشطون الآن" فعلًا — كارت "online" يمرّر previous=0 دائمًا
        // (لا تاريخ سابق لهذا المؤشّر) فقيمته الحاليّة وحدها تكفي لإثبات الفجوة.
        User::factory()->count(2)->create(['last_seen_at' => now()]);

        // "online" سادس كارتٍ ترتيبًا، وسقف تاب الملخّص الافتراضيّ 4 كروت
        // فيهاجر الزائد لتاب "تفاصيل" (2.15-أ-3) — نوسّع السقف ليبقى بالملخّص.
        $this->setting('admin.dashboard.kpi_max_cards', '6', 'number');

        $html = $this->actingAs($this->owner())
            ->get(route('admin.dashboard', ['compare' => 1]))
            ->assertOk()
            ->getContent();

        if (! preg_match('/data-kpi-card="online".*?<\/a>/us', $html, $match)) {
            $this->fail('كارت "online" لازم يكون موجودًا وقابلًا للنقر');
        }

        $cardHtml = $match[0];

        $this->assertStringContainsString('▲', $cardHtml, 'خطّ أساسٍ صفريّ مع قيمةٍ موجبة لازم يعرض سهم صعود');
        $this->assertStringContainsString('100%', $cardHtml, 'صفر ← قيمة موجبة يعني صعودًا كاملًا 100%');
    }

    // ------------------------------------------- 12.3-5 · التحديث التلقائيّ وآخر تحديث

    /** ⭐ «آخر تحديث HH:MM» + التحديث التلقائيّ بفترته من الإعدادات (12.3-5). */
    public function test_dashboard_shows_last_update_time_and_auto_refresh(): void
    {
        $this->setting('admin.dashboard.refresh_seconds', '90', 'number');

        $this->actingAs($this->owner())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('آخر تحديث '.now()->format('H:i'))
            ->assertSee('بيتحدّث كلّ 90 ثانية')
            ->assertSee('window.location.reload()', false);
    }

    /** وإطفاء التحديث التلقائيّ من الإعدادات يشيل السكربت — فالإعداد يعمل فعلًا (2.13). */
    public function test_auto_refresh_can_be_turned_off_from_settings(): void
    {
        $this->setting('admin.dashboard.auto_refresh', '0', 'bool');

        $this->actingAs($this->owner())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('آخر تحديث')
            ->assertDontSee('window.location.reload()', false);
    }

    // --------------------------------------- فلتر الفترة موحَّد مع 12.8 (من/إلى)

    /** ⭐ الفلتر «من/إلى» نفسه المستعمَل في 12.8 — لا قائمة أيّامٍ مستقلّة. */
    public function test_period_filter_is_unified_with_statistics_from_to(): void
    {
        $response = $this->actingAs($this->owner())->get(route('admin.dashboard'))->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('name="from"', $html);
        $this->assertStringContainsString('name="to"', $html);
        $this->assertStringNotContainsString('name="days"', $html, 'قائمة الأيّام المستقلّة اتشالت لصالح من/إلى');
    }

    /** والتاريخان يغيّران الأرقام فعلًا: مسجّلٌ خارج النافذة لا يُحسَب. */
    public function test_from_and_to_actually_narrow_the_numbers(): void
    {
        $old = $this->makeUser('مسجّل قديم');
        $old->forceFill(['created_at' => now()->subDays(200)])->save();

        $fresh = $this->makeUser('مسجّل جديد');
        $fresh->forceFill(['created_at' => now()->subDay()])->save();

        $wide = $this->actingAs($this->owner())->get(route('admin.dashboard', [
            'from' => now()->subDays(300)->toDateString(),
            'to' => now()->toDateString(),
        ]))->assertOk();

        $narrow = $this->actingAs($this->owner())->get(route('admin.dashboard', [
            'from' => now()->subDays(3)->toDateString(),
            'to' => now()->toDateString(),
        ]))->assertOk();

        $signups = fn ($response) => collect($response->viewData('kpis'))->firstWhere('key', 'signups')['value'];

        $this->assertGreaterThan($signups($narrow), $signups($wide));
        $this->assertGreaterThanOrEqual(1, $signups($narrow));
    }

    // ------------------------------------------------------ 🔒 الماليّات للمالك وحده

    /** ⭐ كروت الإيرادات وAOV والأعلى مبيعًا **لا تظهر لغير مالك المنصّة** (12.7 · 2.15-أ-7). */
    public function test_financial_cards_are_hidden_from_non_owners(): void
    {
        $buyer = $this->makeUser('مشترٍ');
        $this->paidOrder($buyer, 500, 'دليل أسئلة المقابلات');

        $ownerHtml = $this->actingAs($this->owner())
            ->get(route('admin.dashboard', ['tab' => 'details']))
            ->assertOk()
            ->assertSee('🔒 الإيرادات')
            ->assertSee('🔒 متوسّط قيمة الطلب')
            ->assertSee('🔒 الأعلى مبيعًا')
            ->getContent();

        $this->assertStringContainsString('دليل أسئلة المقابلات', $ownerHtml);

        $this->actingAs($this->supportAdmin())
            ->get(route('admin.dashboard', ['tab' => 'details']))
            ->assertOk()
            ->assertDontSee('🔒 الإيرادات')
            ->assertDontSee('🔒 متوسّط قيمة الطلب')
            ->assertDontSee('🔒 الأعلى مبيعًا')
            ->assertDontSee('دليل أسئلة المقابلات');
    }

    /** والحساب نفسه لا يُجرى لغير المالك — الكارت محذوف من المصدر لا مخفيّ بالـCSS. */
    public function test_non_owner_card_list_has_no_financial_keys(): void
    {
        $keys = collect($this->actingAs($this->supportAdmin())
            ->get(route('admin.dashboard', ['tab' => 'details']))
            ->assertOk()
            ->viewData('details')['cards'])->pluck('key')->all();

        foreach (['revenue', 'aov', 'withdrawals', 'referral'] as $financial) {
            $this->assertNotContains($financial, $keys);
        }
    }

    // ------------------------------------------------- 12.3-10 · الهدف الشهريّ

    /** ⭐ هدف شهريّ قابل للتخصيص، ومتحقّقُه **محسوبٌ من الطلبات المدفوعة** لا مكتوبًا. */
    public function test_monthly_target_progress_is_computed_from_real_paid_orders(): void
    {
        $this->setting('admin.dashboard.monthly_target', '1000', 'number');
        $this->paidOrder($this->makeUser('مشترٍ'), 250);

        $target = $this->actingAs($this->owner())
            ->get(route('admin.dashboard', ['tab' => 'details']))
            ->assertOk()
            ->assertSee('الهدف الشهريّ')
            ->viewData('details')['target'];

        $this->assertSame(1000.0, $target['target']);
        $this->assertSame(250.0, $target['achieved']);
        $this->assertSame(25.0, $target['percent']);
    }

    /** والهدف رقمٌ ماليّ — فلا يظهر لغير المالك. */
    public function test_monthly_target_is_owner_only(): void
    {
        $this->setting('admin.dashboard.monthly_target', '1000', 'number');

        $this->actingAs($this->supportAdmin())
            ->get(route('admin.dashboard', ['tab' => 'details']))
            ->assertOk()
            ->assertDontSee('الهدف الشهريّ');
    }

    // --------------------------------- 12.3-13/14/15/11 · اللوحات العميقة الغائبة

    /** ⭐ الخريطة الحراريّة وصحّة التلعيب وDrop-off والحروب الجارية — كلّها في «تفاصيل». */
    public function test_details_tab_carries_the_deep_panels(): void
    {
        $this->actingAs($this->owner())
            ->get(route('admin.dashboard', ['tab' => 'details']))
            ->assertOk()
            ->assertSee('الخريطة الحراريّة الجغرافيّة')
            ->assertSee('صحّة التلعيب')
            ->assertSee('ستريكات نشطة')
            ->assertSee('نادي الخامسة اليوم')
            ->assertSee('تذاكر متداولة')
            ->assertSee('أكثر التدريبات تعثّرًا (Drop-off)')
            ->assertSee('الحروب الجارية');
    }

    /** والخريطة تعدّ المستخدمين فعلًا لا تعرض صفوفًا فارغة. */
    public function test_geo_heatmap_counts_real_users(): void
    {
        $rows = $this->actingAs($this->owner())
            ->get(route('admin.dashboard', ['tab' => 'details']))
            ->assertOk()
            ->viewData('details')['geo'];

        $this->assertSame(
            User::whereNull('deleted_at')->count(),
            collect($rows)->sum('value'),
            'مجموع صفوف الخريطة = عدد المستخدمين الحقيقيّ',
        );
    }

    // ------------------------------------------------- 12.3-3 · تخصيص اللوحة لكلّ دور

    /** ⭐ ترتيب/إخفاء الكروت **ولكلّ دور ترتيبه** — والتخصيص يسري فورًا. */
    public function test_role_layout_reorders_and_hides_cards(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('admin.dashboard.layout'), [
            'role' => 'platform_owner',
            'order' => ['courses', 'users', 'signups'],
            'hidden' => ['signups'],
        ])->assertRedirect();

        $keys = collect($this->actingAs($owner)->get(route('admin.dashboard'))->assertOk()->viewData('kpis'))
            ->pluck('key')->all();

        $this->assertSame('courses', $keys[0], 'أوّل كارت يتبع ترتيب الدور');
        $this->assertNotContains('signups', $keys, 'المخفيّ لا يظهر');

        // ودورٌ آخر لا يتأثّر بتخصيص غيره
        $supportKeys = collect($this->actingAs($this->supportAdmin())->get(route('admin.dashboard'))->assertOk()->viewData('kpis'))
            ->pluck('key')->all();

        $this->assertSame('users', $supportKeys[0]);
    }

    /** ومَن لا يملك تعديل الإعدادات لا يرى زرّ التخصيص ولا يصل لمساره (2.15-أ-7). */
    public function test_layout_customization_is_gated(): void
    {
        $this->actingAs($this->supportAdmin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('تخصيص اللوحة');

        $this->actingAs($this->supportAdmin())
            ->post(route('admin.dashboard.layout'), ['role' => 'support_admin', 'order' => ['users']])
            ->assertForbidden();
    }

    // ------------------------------------------------- 12.3-20 · تصدير سجلّ النشاطات

    /** ⭐ تصدير سجلّ النشاطات — كان الفلتر موجودًا والتصدير غائبًا. */
    public function test_activity_log_export_returns_a_csv_of_the_filtered_period(): void
    {
        $owner = $this->owner();

        // نشاطٌ خارج النافذة الافتراضيّة لا يدخل الملفّ — فالفلتر يعمل لا يتزيّن
        AuditLog::create([
            'user_id' => $owner->id,
            'action' => 'segment.created',
            'auditable_type' => (new User)->getMorphClass(),
            'auditable_id' => $owner->id,
            'created_at' => now()->subDays(120),
            'updated_at' => now()->subDays(120),
        ]);

        $response = $this->actingAs($owner)->get(route('admin.dashboard.activity.export'))->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('الإجراء', $csv);
        $this->assertStringContainsString('تعديل صلاحيّات دور', $csv);
        $this->assertStringNotContainsString('إنشاء شريحة', $csv, 'اللي برّه الفترة مايتصدّرش');
    }

    /** وزرّ التصدير وصلاحيّته المستقلّة: مَن لا يملكها لا يراه ولا يفتح المسار. */
    public function test_activity_export_is_gated_by_its_own_permission(): void
    {
        $this->actingAs($this->owner())->get(route('admin.dashboard'))->assertOk()->assertSee('تصدير السجلّ');

        $this->actingAs($this->supportAdmin())
            ->get(route('admin.dashboard.activity.export'))
            ->assertForbidden();
    }

    // ---------------------------------------------------- 2.17-أ · العدّاد التصاعديّ

    /** ⭐ الرقم النهائيّ **مخدَّم من الخادم داخل الوسم** فيظهر صحيحًا مهما تعثّر الجافاسكربت. */
    public function test_counter_carries_the_final_server_number_inside_the_markup(): void
    {
        $owner = $this->owner();
        $expected = number_format(User::count());

        $html = $this->actingAs($owner)->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/data-count-to="'.preg_quote($expected, '/').'"[^>]*>'.preg_quote($expected, '/').'</u',
            $html,
            'قيمة العدّاد ونصّه لازم يكونا نفس الرقم النهائيّ من الخادم',
        );
    }

    // ---------------------------------------------------- 12.14-هـ · استخراج كصورة

    /** [استخراج كصورة] بالمكوّن المشترك القائم لا بمكوّنٍ ثانٍ. */
    public function test_dashboard_offers_the_shared_export_image_button(): void
    {
        $this->actingAs($this->owner())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('استخراج كصورة');
    }

    // ------------------------------------------------------------ 2.15 · البساطة

    /** أربعة كروت ظاهرة بحدّ أقصى، والزائد **يُنقَل** لتاب «تفاصيل» ولا يُحذَف. */
    public function test_extra_cards_move_to_details_instead_of_being_dropped(): void
    {
        $owner = $this->owner();

        $overview = $this->actingAs($owner)->get(route('admin.dashboard'))->assertOk();
        $details = $this->actingAs($owner)->get(route('admin.dashboard', ['tab' => 'details']))->assertOk();

        $shown = collect($overview->viewData('kpis'))->pluck('key');
        $moved = collect($details->viewData('details')['cards'])->pluck('key');

        $this->assertLessThanOrEqual(4, $shown->count());
        $this->assertGreaterThan(0, $moved->count(), 'الزائد اتنقل مااتحذفش');
        $this->assertEmpty($shown->intersect($moved), 'ولا كارت مكرّر في التابين');
    }
}
