<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Challenge;
use App\Models\ChallengeParticipation;
use App\Services\Admin\Volunteer\WarSettingsService;

/**
 * ⭐ [2026-09-10] بطاقة الحرب (12.10-ج سطر 2553 · 4928): «أيقونة SVG + لون
 * مميّز + سويتش تفعيل/إيقاف + آخر تعديل». كان الموجود شارة «● مفعّلة»
 * للعرض فقط — التفعيل الحقيقيّ لم يكن إلّا داخل فورم التفاصيل الكامل.
 */
class AdminVolunteerWarCardSwitchTest extends AdminVolunteerTestCase
{
    private function war(): Challenge
    {
        return Challenge::where('key', 'knowledge_war')->firstOrFail();
    }

    /** المحظور يُخفى لا يُعطَّل (2.15-أ-7): بلا صلاحيّة التعديل تبقى شارةً للعرض */
    public function test_the_card_shows_a_switch_only_with_the_edit_permission(): void
    {
        $war = $this->war();

        $viewer = $this->grant($this->makeUser(), 'wars_settings.view');
        $viewerHtml = $this->actingAs($viewer)
            ->get(route('admin.gamification.index', ['tab' => 'wars']))
            ->assertOk()->getContent();

        // ⚠️ `hc-switch` مكوّنٌ عامّ (يظهر في هيدر أيّ صفحة إدارة كالمظهر/الصوت)،
        // وفورم `wars.save` نفسه يُطبَع أيضًا لفورم التفاصيل الكامل (زرّ حفظه
        // وحده محروسٌ لا الفورم) — فالعلامة الفريدة هنا سويتش البطاقة تحديدًا
        $this->assertStringNotContainsString('this.form.requestSubmit()', $viewerHtml,
            'مين بلا صلاحيّة تعديل شاف سويتش البطاقة الفعليّ — يُفترَض شارة عرضٍ فقط.');

        $editor = $this->grant($this->makeUser(), 'wars_settings.view', 'wars_settings.edit');
        $editorHtml = $this->actingAs($editor)
            ->get(route('admin.gamification.index', ['tab' => 'wars']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('hc-switch', $editorHtml);
        $this->assertStringContainsString('this.form.requestSubmit()', $editorHtml, 'صاحب الصلاحيّة ما شافش سويتش البطاقة الفعليّ.');
    }

    /**
     * ⭐ ضغطة السويتش تحفظ is_active وحده — بلا مسّ أيّ Override آخر (نفس
     * ضمان WarSettingsService::save() الحالي، مؤكَّدٌ هنا على المسار المصغَّر
     * الذي يرسله السويتش فعليًّا: حقلٌ واحد + CSRF).
     */
    public function test_toggling_the_switch_persists_is_active_without_touching_other_overrides(): void
    {
        $admin = $this->grant($this->makeUser(), 'wars_settings.view', 'wars_settings.edit');
        $war = $this->war();

        WarSettingsService::save($war, ['rewards' => ['win' => 7]], $admin);
        $this->assertNotEmpty(WarSettingsService::overrideOf($war->fresh(), 'rewards'));

        $this->actingAs($admin)
            ->post(route('admin.gamification.wars.save', $war), ['is_active' => '0'])
            ->assertRedirect();

        $war->refresh();
        $this->assertFalse((bool) $war->is_active);
        $this->assertSame(7, (int) WarSettingsService::overrideOf($war, 'rewards')['win'], 'التفعيل مسّ Override آخر — كان يُفترَض تجاهل الحقول الغائبة.');
    }

    /** والسويتش يُعطَّل (لا يُخفى) أثناء جولةٍ جارية — نفس قفل فورم التفاصيل بالضبط */
    public function test_the_switch_is_disabled_while_a_round_is_running(): void
    {
        $admin = $this->grant($this->makeUser(), 'wars_settings.view', 'wars_settings.edit');
        $war = $this->war();

        ChallengeParticipation::create([
            'challenge_id' => $war->id,
            'user_id' => $this->makeUser('محارب')->id,
            'started_at' => now(),
            'status' => 'running',
        ]);

        $html = $this->actingAs($admin)
            ->get(route('admin.gamification.index', ['tab' => 'wars']))
            ->assertOk()->getContent();

        // مربّع السويتش نفسه محمول على وسمٍ يحمل `disabled` حين القفل
        $this->assertMatchesRegularExpression('/name="is_active" value="1"[^>]*disabled/', $html);
    }
}
