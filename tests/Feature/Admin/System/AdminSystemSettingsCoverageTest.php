<?php

namespace Tests\Feature\Admin\System;

use App\Models\Setting;
use App\Services\Admin\System\SettingsCoverage;
use App\Services\Admin\System\SettingsRegistry;
use Database\Seeders\DemoSeeder;
use Database\Seeders\SettingSeeder;

/**
 * تغطية الإعدادات (2.13): **لكلّ مفتاح شاشةٌ يعدّله منها المالك**.
 *
 * إعدادٌ مزروع في قاعدة البيانات بلا شاشة هو رقمٌ محروق بخطوة إضافيّة: المالك
 * يراه في التصدير ولا يقدر على تغييره. هذه الاختبارات تفشل لحظةَ ظهور مجموعة
 * يتيمة — سواءٌ زرعها سيدر جديد أو أعلنها كتالوج ولمّا تُزرَع بعد.
 */
class AdminSystemSettingsCoverageTest extends SystemTestCase
{
    private const ADMIN = ['settings_general.view', 'settings_general.edit', 'maintenance.view'];

    /** ⭐ الاختبار الحارس: مجموعةٌ بلا تاب = مخالفة صريحة تُسقِط الـCI */
    public function test_no_settings_group_is_left_without_a_screen(): void
    {
        $this->seed(SettingSeeder::class);
        $this->seed(DemoSeeder::class);

        $orphans = app(SettingsCoverage::class)->orphanGroups();

        $this->assertTrue(
            $orphans->isEmpty(),
            'مجموعات إعدادات بلا تاب في لوحة الإدارة: '.$orphans->pluck('group')->implode(' · ').
            ' — أضِفها في SettingsRegistry::tabs() وgroupCatalog().',
        );

        $this->assertSame(100.0, app(SettingsCoverage::class)->coveragePercent());
    }

    /** ولكلّ مجموعة عنوانٌ عربيّ — المفتاح الإنجليزيّ ليس عنوانَ شاشة */
    public function test_every_group_has_an_arabic_label(): void
    {
        $this->seed(SettingSeeder::class);

        $unlabelled = app(SettingsCoverage::class)->unlabelledGroups();

        $this->assertTrue(
            $unlabelled->isEmpty(),
            'مجموعات بلا عنوان عربيّ: '.$unlabelled->pluck('group')->implode(' · '),
        );
    }

    /** كلّ تاب في السايد بار يفتح ويعرض كروت مجموعاته بعناوينها */
    public function test_every_tab_opens_and_renders_its_group_cards(): void
    {
        $this->seed(SettingSeeder::class);
        $admin = $this->admin(self::ADMIN);
        $registry = app(SettingsRegistry::class);

        foreach (array_keys($registry->tabsFor($admin)) as $tab) {
            $this->actingAs($admin)
                ->get(route('admin.settings.index', ['tab' => $tab]))
                ->assertOk();
        }

        // تاب مولَّد بالكامل من المولِّد العامّ — عنوانه العربيّ ومفاتيحه ظاهرة
        $this->actingAs($admin)
            ->get(route('admin.settings.index', ['tab' => 'dashboards']))
            ->assertOk()
            ->assertSee('لوحة المتدرّب', false)
            ->assertSee('dashboard.achievements.account.base', false);
    }

    /** ⭐ البحث الموحّد يوصّل لتابه الصحيح لا لتاب افتراضيّ */
    public function test_unified_search_points_each_key_to_the_tab_that_holds_it(): void
    {
        $this->seed(SettingSeeder::class);
        $admin = $this->admin(self::ADMIN);
        $registry = app(SettingsRegistry::class);
        $groupToTab = $registry->groupToTab();

        // العيّنة من المرئيّ لهذا الأدمن فقط — المحميّ لا يُفترَض أن يظهر له أصلًا
        $sample = Setting::query()
            ->where('is_owner_only', false)
            ->where('group', '!=', 'finance')
            ->inRandomOrder()
            ->limit(25)
            ->get();

        foreach ($sample as $setting) {
            $results = $registry->search($setting->key, $admin);
            $row = collect($results)->firstWhere('key', $setting->key);

            $this->assertNotNull($row, "البحث ما لقاش {$setting->key}");
            $this->assertSame(
                $groupToTab[$setting->group] ?? 'misc',
                $row['tab'],
                "المفتاح {$setting->key} بيوصّل لتاب غلط",
            );

            $this->assertContains(
                $setting->group,
                $registry->tabsFor($admin)[$row['tab']]['groups'] ?? [],
                "تاب {$row['tab']} مش فيه مجموعة {$setting->group}",
            );
        }
    }

    /** والنتيجة تفتح الصفحة على الحقل نفسه فيُبرَز */
    public function test_search_result_link_opens_the_field_on_its_tab(): void
    {
        $this->seed(SettingSeeder::class);
        $admin = $this->admin(self::ADMIN);

        $results = app(SettingsRegistry::class)->search('ux.state.warn.icon', $admin);
        $row = collect($results)->firstWhere('key', 'ux.state.warn.icon');

        $this->assertSame('platform', $row['tab']);

        $this->actingAs($admin)
            ->get(route('admin.settings.index', ['tab' => $row['tab'], 'key' => $row['key']]))
            ->assertOk()
            ->assertSee('ux.state.warn.icon', false);
    }

    /** 🔒 غير المالك لا يرى الإعدادات المحميّة — لا في تاب ولا في بحث ولا في تصدير */
    public function test_non_owner_never_sees_owner_only_settings(): void
    {
        $this->seed(SettingSeeder::class);
        $admin = $this->admin(self::ADMIN);
        $registry = app(SettingsRegistry::class);

        $protected = Setting::query()
            ->where(fn ($q) => $q->where('is_owner_only', true)->orWhere('group', 'finance'))
            ->pluck('key');

        $this->assertNotEmpty($protected, 'لازم يكون في إعدادات محميّة أصلًا عشان الاختبار يبقى له معنى');

        $exported = $registry->export($admin);

        foreach ($protected as $key) {
            $this->assertArrayNotHasKey($key, $exported);
            $this->assertEmpty(
                collect($registry->search($key, $admin))->firstWhere('key', $key),
                "البحث سرّب الإعداد المحميّ {$key}",
            );
        }

        // ولا حتى في كروت التابات المولَّدة
        foreach (array_keys($registry->tabsFor($admin)) as $tab) {
            $keys = $registry->forTab($tab, $admin)->pluck('key');

            $this->assertEmpty(
                $keys->intersect($protected),
                "تاب {$tab} كشف إعدادًا محميًّا لغير المالك",
            );
        }

        // والمالك يراها كاملةً — وإلّا كان العزل حجبًا عن الجميع لا حمايةً
        $this->assertArrayHasKey($protected->first(), $registry->export($this->owner()));
    }

    /** وحفظ المحميّ من غير المالك مرفوض برسالة تشرح لا بخطأ غامض */
    public function test_non_owner_cannot_save_a_protected_setting(): void
    {
        $this->seed(SettingSeeder::class);
        $admin = $this->admin(self::ADMIN);
        $setting = Setting::query()->where('group', 'finance')->firstOrFail();
        $before = $setting->value;

        $this->actingAs($admin)
            ->postJson(route('admin.settings.field'), ['key' => $setting->key, 'value' => '99'])
            ->assertStatus(422)
            ->assertJsonFragment(['saved' => false]);

        $this->assertSame($before, $setting->refresh()->value);
    }

    /** لا مفتاحين لمعنى واحد بعد مايجريشن التوحيد */
    public function test_merged_duplicate_keys_are_gone(): void
    {
        $this->seed(SettingSeeder::class);
        $this->seed(DemoSeeder::class);

        $retired = [
            'events.attendance_code_length',
            'streaks.club_5am.window_start',
            'streaks.club_5am.window_end',
            'streaks.reward.every_days',
            'rep.behavior.monthly_cap_per_grantor',
            'rep.reset.hour_cairo',
            'volunteer.org.occupancy.warn_percent',
            'volunteer.org.occupancy.danger_percent',
            'offboarding.notice_days',
            'offboarding.cooldown.resignation_days',
            'offboarding.cooldown.thresholds_days',
            'offboarding.inactivity.alert_days',
            'reentry.exam.required',
        ];

        foreach ($retired as $key) {
            $this->assertDatabaseMissing('settings', ['key' => $key]);
        }

        // والمعتمَد موجود — فلا معنًى ضاع في الترحيل
        foreach ([
            'events.attendance.code_length',
            'streaks.club5am.window_start',
            'streaks.reward_days',
            'rep.behavior.monthly_cap_per_granter',
            'rep.reset.hour',
            'volunteer.org.occupancy_warn_percent',
            'volunteer.offboarding.notice_days',
            'rep.inactivity.days_before_alert',
        ] as $key) {
            $this->assertDatabaseHas('settings', ['key' => $key]);
        }
    }
}
