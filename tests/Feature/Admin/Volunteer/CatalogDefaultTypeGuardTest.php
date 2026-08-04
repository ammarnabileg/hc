<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Setting;
use App\Services\Admin\Volunteer\SettingsCatalog;
use App\Services\Admin\Volunteer\SettingsWriter;
use App\Services\AdminScreens\ScreenSettings;
use Illuminate\Support\Facades\Cache;

/**
 * حارس: **افتراضيّ الكتالوج يبقى نصًّا مهما كان نوع صفّه في القاعدة**.
 *
 * صار افتراضيّ كلّ صفٍّ يُقرأ من `setting()` تطبيقًا لـ2.13، و`setting()` يحكمه
 * **النوع المعلَن في القاعدة**: صفٌّ نوعه `json` يعود **مصفوفةً**. وصيغة الكتالوج
 * تَعِد بنصّ، فأوّل قارئ (`SettingsWriter::groupRows()`) كان ينفجر بـ
 * «Array to string conversion» وتصير ستّ شاشات إدارة **500**.
 *
 * فالحلّ **بحسب النوع المعلَن في الصفّ نفسه** لا بقسرٍ أعمى — وهذا الاختبار
 * يزرع العيب (مفتاح افتراضيّه JSON بنوع `json`) ويطالب بالشاشة **200**.
 */
class CatalogDefaultTypeGuardTest extends AdminVolunteerTestCase
{
    /** مفاتيح افتراضيّها JSON — نجعل نوعها `json` كما تفعل شبكة `SettingGapSeeder` */
    private function poisonJsonTypedDefaults(): array
    {
        $keys = [
            'rewards.settings_catalog.rewards_15',
            'events.settings_catalog.events_4',
            'volunteer_offboarding.settings_catalog.offboarding_8',
            'exams.screen_settings.catalog_20',
        ];

        foreach ($keys as $key) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => 'volunteer',
                'label_ar' => $key,
                'type' => 'json',
                'value' => '{"a":"أ","b":"ب"}',
                'default_value' => '{"a":"أ","b":"ب"}',
            ]);
        }

        Cache::forget('settings');

        return $keys;
    }

    public function test_json_typed_default_keeps_the_catalog_row_a_string(): void
    {
        $this->poisonJsonTypedDefaults();

        foreach (SettingsCatalog::all() as $key => $row) {
            $this->assertIsString($row[3], "الافتراضيّ لازم يفضل نصًّا: {$key}");
            $this->assertIsString($row[1], "اللافتة لازم تفضل نصًّا: {$key}");
        }

        foreach (ScreenSettings::catalog() as $key => $row) {
            $this->assertIsString($row[4], "الافتراضيّ لازم يفضل نصًّا: {$key}");
        }

        // والصورة النصّيّة هي **ترميز JSON** لا `Array` ولا نصٌّ بين اقتباسين
        $this->assertSame('{"a":"أ","b":"ب"}', SettingsCatalog::defaultOf('rewards.reasons'));
    }

    /** الباب نفسه الذي انفجر: `groupRows()` ⟵ ومنه الشاشات الستّ */
    public function test_group_rows_survive_a_json_typed_default(): void
    {
        $this->poisonJsonTypedDefaults();

        $rows = SettingsWriter::groupRows('rewards');

        $this->assertNotEmpty($rows);
        $this->assertIsString($rows['rewards.reasons']['default']);
    }

    /** والشاشة نفسها ترجع 200 لا 500 */
    public function test_admin_rewards_screen_opens_with_a_json_typed_default(): void
    {
        $this->poisonJsonTypedDefaults();

        $this->actingAs($this->platformOwner())
            ->get(route('admin.rewards.index'))
            ->assertOk();
    }
}
