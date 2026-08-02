<?php

namespace Tests\Feature\Admin\System;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\System\SettingKeyScanner;
use App\Services\Admin\System\SettingsRegistry;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SettingDefinitionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⭐ الحارس الحقيقيّ للقاعدة الذهبيّة 2.13:
 * **كلّ مفتاح يقرؤه الكود له صفٌّ بعد `DatabaseSeeder` وحده.**
 *
 * لماذا `DatabaseSeeder` وحده لا `DemoSeeder` معه؟ لأنّ التنصيب الحقيقيّ لا
 * يشغّل بيانات العرض. وكان كلّ مجال يزرع إعداداته في `<Area>DemoSeeder` ولا
 * أحد يستدعيه، فالمالك يفتح لوحته على عشرات الحقول بينما الكود يقرأ ألوفًا —
 * والفارق يأخذ الافتراضيّ المكتوب في الكود، أيْ **رقمًا محروقًا بخطوة إضافيّة**.
 *
 * ولا يرث هذا الاختبار `SystemTestCase`: ذاك يزرع سيدر عرضٍ في `setUp`، وهنا
 * المقصود بالضبط أن نرى **ما يراه المالك على تنصيبٍ نظيف** لا أكثر.
 */
class SettingKeyCoverageTest extends TestCase
{
    use RefreshDatabase;

    /** ⭐ مفتاحٌ يقرؤه الكود بلا صفّ = قيمة محروقة تكسر الـCI */
    public function test_every_key_the_code_reads_has_a_row_after_the_production_seeder(): void
    {
        $this->seed(DatabaseSeeder::class);

        $missing = app(SettingKeyScanner::class)->missing(Setting::query()->pluck('key')->all());

        $this->assertTrue(
            $missing->isEmpty(),
            "مفاتيح يقرؤها الكود ولا صفّ لها بعد DatabaseSeeder — كلّ واحدٍ منها قيمةٌ محروقة (2.13-ب):\n  ".
            $missing->keys()->implode("\n  ").
            "\n\nالإصلاح: عرّفها في ميثود `settings()` بسيدر مجالك — يزرعها SettingDefinitionsSeeder في مسار الإنتاج.",
        );
    }

    /** والمفتاح المركَّب وقت التشغيل يُفحَص بنمطه: لا بدّ من صفٍّ واحد يطابقه */
    public function test_every_runtime_composed_pattern_matches_at_least_one_row(): void
    {
        $this->seed(DatabaseSeeder::class);

        $scanner = app(SettingKeyScanner::class);
        $unmatched = $scanner->unmatchedDynamicPatterns(Setting::query()->pluck('key')->all());

        $this->assertTrue(
            $unmatched->isEmpty(),
            'أنماط مركَّبة بلا أيّ صفّ يطابقها: '.$unmatched->keys()->implode(' · '),
        );

        // ولو خلا المشروع من الأنماط لصار الفحص فارغًا يمرّ بلا معنى
        $this->assertNotEmpty($scanner->dynamicPatterns());
    }

    /** والقيمة المزروعة = ما يقرؤه الكود اليوم: الزرع لا يغيّر سلوكًا */
    public function test_seeding_does_not_change_what_the_code_reads(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, setting('rep.objection.window_days'));
        $this->assertSame('Africa/Cairo', setting('system.timezone'));
        $this->assertSame(4, setting('ux.kpi.max_cards'));
        $this->assertTrue(setting('question_bank.server_side_grading'));
    }

    /** والاكتشاف الآليّ يمسك مجالات المنصّة كلّها لا حفنةً منها */
    public function test_definition_sources_are_discovered_across_domains(): void
    {
        $sources = SettingDefinitionsSeeder::sources();

        $this->assertGreaterThan(20, count($sources));

        foreach ($sources as [$class, $method]) {
            $this->assertTrue(method_exists($class, $method), "{$class}::{$method}() مش موجودة");
        }
    }

    /**
     * ⭐ التحقّق بالتشغيل: المالك يفتح لوحته على تنصيبٍ نظيف فيجد المفتاح
     * **ظاهرًا وقابلًا للتعديل** — لا مزروعًا في القاعدة بلا شاشة تصل إليه.
     */
    public function test_a_newly_seeded_key_is_visible_and_editable_on_a_clean_install(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::create([
            'name' => 'مالك المنصّة',
            'email' => 'owner@coverage.test',
            'password' => 'secret-password',
            'code' => 'OWNCOV01',
            'status' => 'active',
        ]);
        $owner->assignRole(Role::query()->where('key', config('access.owner_role'))->firstOrFail());

        // مفتاحٌ كان محروقًا في الكود قبل هذا العمل — ولا صفّ له على تنصيبٍ نظيف
        $key = 'certificates.labels.download';
        $setting = Setting::query()->where('key', $key)->firstOrFail();

        $registry = app(SettingsRegistry::class);
        $tab = $registry->groupToTab()[$setting->group] ?? null;

        $this->assertNotNull($tab, "المفتاح {$key} مزروعٌ بلا تابٍ يصل إليه");

        $this->actingAs($owner)
            ->get(route('admin.settings.index', ['tab' => $tab, 'key' => $key]))
            ->assertOk()
            ->assertSee($key, false);

        $this->actingAs($owner)
            ->postJson(route('admin.settings.field'), ['key' => $key, 'value' => 'نزّل شهادتك'])
            ->assertOk()
            ->assertJsonFragment(['saved' => true]);

        $this->assertSame('نزّل شهادتك', $setting->refresh()->value);
        $this->assertSame('تحميل', $setting->default_value, 'الافتراضيّ يبقى مرساةً لزرّ الـReset');
    }

    /** وإعادة التشغيل لا تدهس قيمةً عدّلها المالك (2.13-د) */
    public function test_reseeding_keeps_the_value_the_owner_chose(): void
    {
        $this->seed(DatabaseSeeder::class);

        $setting = Setting::query()->where('key', 'ux.kpi.max_cards')->firstOrFail();
        $setting->update(['value' => '2']);

        $this->seed(SettingDefinitionsSeeder::class);

        $this->assertSame('2', $setting->refresh()->value);
        $this->assertSame('4', $setting->default_value, 'الافتراضيّ مرجعُ زرّ الـReset فلا يتبع القيمة');
    }
}
