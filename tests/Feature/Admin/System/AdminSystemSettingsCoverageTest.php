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

        /*
         | ولا حتى في كروت التابات المولَّدة — و`$limit` صريحٌ هنا **عمدًا**:
         | `forTab()` صارت تُرجع دفعةً واحدة افتراضيًّا بعد أن صار التاب يُحمَّل
         | كسولًا (2.15-ب)، وحارسُ العزل لا يُقاس على دفعةٍ بل على **كلّ** ما
         | يمكن أن يصل إليه الناظر في التاب. فلو ضاق الحدّ ضاق الحارس معه.
         */
        foreach (array_keys($registry->tabsFor($admin)) as $tab) {
            $keys = $registry->forTab($tab, $admin, '', PHP_INT_MAX)->pluck('key');

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

    /**
     * الأيتام الحقيقيّون من `settings:coverage --dead` (2026-08-05) لا يعودون:
     * حُذفوا بمايجريشن **ومن السيدرات معًا** — وإلّا عادوا في أوّل `migrate:fresh`.
     */
    public function test_swept_dead_orphans_do_not_come_back(): void
    {
        $this->seed(SettingSeeder::class);
        $this->seed(DemoSeeder::class);

        $swept = [
            // رقمٌ ناقص من تسلسل مُرقَّم — تجربة استخراج نصٍّ محروق سابقة
            'volunteer.contributions.field_22',
            'volunteer.contributions_invite_form.legend_2',
            'volunteer.escalations_arbitrations_file.option_3',
            'volunteer.overview_report.text_13',
            'volunteer.performance_rep.text_8',
            'volunteer.performance_rep.text_9',
            'volunteer.profile_report.text_5',
            'volunteer.tasks_action_modals.option_2',
            'volunteer.tasks_action_modals.text_9',
            'volunteer.tasks_show.text_12',
            // لا وجود له في أيّ سيدر أو ملفّ بيانات — تجربة أولى مُجهَضة
            'cv.attestations_page.js_1',
            'cv.attestations_page.js_2',
            'cv.index.js_1',
            'cv.index.js_2',
            'cv.index.js_3',
            'cv.index.js_4',
            'cv.step_certificates.js_1',
            'cv.templates_partial.js_1',
            'exams.take.js_1',
            'exams.take.js_2',
            'exams.take.js_3',
            // استُبدِل باسمٍ آخر يُقرَأ فعلًا
            'bundles.anchoring',
            'cv.template.buy_title',
            'cv.template.price_title',
            'cv.template.balance_before',
            'cv.template.balance_after',
            'cv.template.confirm_label',
            'features.ui.col.key',
            'volunteer.qualifying.path_slug',
        ];

        foreach ($swept as $key) {
            $this->assertDatabaseMissing('settings', ['key' => $key]);
        }

        // والمعتمَد الذي حلّ محلّ بعضها ما زال يقرأه القارئ الحقيقيّ
        $this->assertDatabaseHas('settings', ['key' => 'store.bundle.anchoring_enabled']);
        $this->assertDatabaseHas('settings', ['key' => 'volunteer.qualifying.path_id']);
    }

    /**
     * ⭐ الطفرة: كتالوجٌ مُسجَّل في `deadKeys()` (`BundleLanding::TEXTS` هنا) —
     * فُصِلَ تسجيله ⟵ يعود `store.bundle.hero_badge` «ميّتًا» زورًا رغم قراءته
     * فعلًا. يثبت أنّ التسجيل هو ما يمنع البلاغ الكاذب لا صدفة.
     */
    public function test_unregistering_a_catalog_makes_the_false_dead_report_return(): void
    {
        $this->seed(SettingSeeder::class);
        $this->seed(DemoSeeder::class);

        $before = app(SettingsCoverage::class)->deadKeys();
        $this->assertFalse($before->contains('store.bundle.hero_badge'));

        // نطفئ التسجيل يدويًّا بمحاكاة القارئ بلا كتالوجه، لا بتعديل الكود:
        // النسخة المصغّرة من نفس منطق deadKeys() بلا سطر تسجيل BundleLanding.
        $scanner = app(\App\Services\Admin\System\SettingKeyScanner::class);
        $read = array_flip(array_keys($scanner->keys()));
        $viaCatalogWithoutBundle = array_flip(array_merge(
            array_keys(\App\Services\Admin\Volunteer\SettingsCatalog::all()),
            array_keys(\App\Services\AdminScreens\ScreenSettings::catalog()),
            array_keys(\App\Services\Ads\AdEvents::catalog()),
        ));

        $stillFalselyDead = ! isset($read['store.bundle.hero_badge'])
            && ! isset($viaCatalogWithoutBundle['store.bundle.hero_badge']);

        $this->assertTrue(
            $stillFalselyDead,
            'دون تسجيل BundleLanding في الكتالوج، store.bundle.hero_badge كان سيُبلَّغ ميّتًا زورًا — وهذا يثبت أنّ التسجيل هو الإصلاح الفعليّ.',
        );
    }
}
