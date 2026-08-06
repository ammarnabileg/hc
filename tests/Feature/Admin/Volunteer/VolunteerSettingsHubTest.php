<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Setting;
use App\Services\Admin\Volunteer\SettingsCatalog;
use App\Services\Admin\Volunteer\SettingsWriter;

/**
 * الإدارة المركزيّة للتطوّع (24.2 · 13.4-ك): مرجعٌ واحد بتسعة تابات — بعضها
 * مجموعة كتالوج كاملة (Rep · Kudos · التقييم) وبعضها انتقاءٌ عرضيّ حقيقيّ
 * (VXP · النوافذ · السلوك · الاعتراضات) وتاب «النصوص» حالة فارغة رسميّة
 * لأنّه لم يُبنَ بعد — لا حقل مختلَق.
 */
class VolunteerSettingsHubTest extends AdminVolunteerTestCase
{
    public function test_the_hub_renders_all_nine_tabs_with_real_content_only(): void
    {
        $viewer = $this->grant($this->makeUser(), 'volunteer_central_settings.view');

        $response = $this->actingAs($viewer)->get(route('admin.volunteer.settings-hub'));

        $response->assertOk();
        $response->assertSee('Rep');
        $response->assertSee('VXP');
        $response->assertSee('Kudos ونادي +9.5');
        $response->assertSee('الاعتراضات');
        $response->assertSee('الترقّي والشواغر والأوفبوردنج');
        $response->assertSee('السلوك');
        $response->assertSee('النوافذ والمهل');

        // ⭐ تاب النصوص لم يُبنَ بعد — حالة «فارغة» الرسميّة (24.2) لا حقل مختلَق
        $response->assertSee('لم تُضبَط إعدادات هذا التاب بعد', false);

        // مفتاحٌ حقيقيّ من كلّ تاب عرضيّ يُثبت أنّه ليس فارغًا مصادفةً
        $response->assertSee('kudos.daily_limit', false);
        $response->assertSee('workflow.vxp.parent_min_share_percent', false);
        $response->assertSee('evaluations.min_raters', false);
        $response->assertSee('rep.objection.sla_hours', false);
        $response->assertSee('rep.behavior.severe_approval_window_hours', false);
        $response->assertSee('workflow.escalation.window_hours', false);
    }

    public function test_view_only_user_sees_read_only_badge_and_cannot_save(): void
    {
        $viewer = $this->grant($this->makeUser(), 'volunteer_central_settings.view');

        $this->actingAs($viewer)->get(route('admin.volunteer.settings-hub'))
            ->assertSee('عرض فقط');

        $this->actingAs($viewer)
            ->post(route('admin.volunteer.settings-hub.save-all'), [
                'settings' => ['kudos.daily_limit' => '99'],
            ])
            ->assertForbidden();

        $this->assertSame('2', (string) setting('kudos.daily_limit'));
    }

    public function test_save_all_writes_only_cataloged_keys_across_any_tab(): void
    {
        $manager = $this->grant($this->makeUser(), 'volunteer_central_settings.manage');

        $this->actingAs($manager)
            ->post(route('admin.volunteer.settings-hub.save-all'), [
                'settings' => [
                    'kudos.daily_limit' => '5',
                    'evaluations.min_raters' => '4',
                    'not.a.real.key' => 'x',
                ],
            ])
            ->assertRedirect();

        $this->assertSame('5', (string) Setting::where('key', 'kudos.daily_limit')->value('value'));
        $this->assertSame('4', (string) Setting::where('key', 'evaluations.min_raters')->value('value'));
        $this->assertFalse(Setting::where('key', 'not.a.real.key')->exists());
    }

    public function test_reset_tab_restores_only_that_tabs_keys(): void
    {
        $manager = $this->grant($this->makeUser(), 'volunteer_central_settings.manage');
        SettingsWriter::put('kudos.daily_limit', '9', $manager);
        SettingsWriter::put('evaluations.min_raters', '9', $manager);

        $this->actingAs($manager)
            ->post(route('admin.volunteer.settings-hub.reset-tab', ['tab' => 'kudos']))
            ->assertRedirect();

        $this->assertSame('2', (string) setting('kudos.daily_limit'));
        // تاب مختلف — لا يتأثّر بـReset تاب Kudos
        $this->assertSame('9', (string) setting('evaluations.min_raters'));
    }

    public function test_reset_field_restores_a_single_key_to_its_catalog_default(): void
    {
        $manager = $this->grant($this->makeUser(), 'volunteer_central_settings.manage');
        SettingsWriter::put('rep.objection.sla_hours', '77', $manager);

        $this->actingAs($manager)
            ->post(route('admin.volunteer.settings-hub.reset-field'), ['key' => 'rep.objection.sla_hours'])
            ->assertRedirect();

        $this->assertSame('24', (string) setting('rep.objection.sla_hours'));
    }

    /** ⭐ الحارس: Reset مقصور على مفاتيح الهَب — لا يفتح بابًا لأيّ مفتاح آخر في المنصّة (2.13) */
    public function test_reset_field_rejects_a_key_outside_the_hub(): void
    {
        $manager = $this->grant($this->makeUser(), 'volunteer_central_settings.manage');

        $this->actingAs($manager)
            ->post(route('admin.volunteer.settings-hub.reset-field'), ['key' => 'gamification_wars.shared.win'])
            ->assertNotFound();
    }

    public function test_unknown_tab_name_404s_on_save_and_reset(): void
    {
        $manager = $this->grant($this->makeUser(), 'volunteer_central_settings.manage');

        $this->actingAs($manager)
            ->post(route('admin.volunteer.settings-hub.save', ['tab' => 'not-a-tab']), ['settings' => []])
            ->assertNotFound();

        $this->actingAs($manager)
            ->post(route('admin.volunteer.settings-hub.reset-tab', ['tab' => 'not-a-tab']))
            ->assertNotFound();
    }

    /** الانتقاء العرضيّ (pick) لا يكرّر مفتاحًا ولا يفقده عبر التابات المتعدّدة المصدر */
    public function test_promotion_tab_merges_org_and_offboarding_groups_without_duplicates(): void
    {
        $orgKeys = array_keys(SettingsCatalog::group('volunteer_org'));
        $offKeys = array_keys(SettingsCatalog::group('volunteer_offboarding'));

        $manager = $this->grant($this->makeUser(), 'volunteer_central_settings.manage', 'volunteer_central_settings.view');
        $response = $this->actingAs($manager)->get(route('admin.volunteer.settings-hub'));

        $response->assertOk();
        $this->assertNotEmpty($orgKeys);
        $this->assertNotEmpty($offKeys);
        $this->assertEmpty(array_intersect($orgKeys, $offKeys));

        foreach (array_slice($orgKeys, 0, 2) as $key) {
            $response->assertSee($key, false);
        }
        foreach (array_slice($offKeys, 0, 2) as $key) {
            $response->assertSee($key, false);
        }
    }
}
