<?php

namespace Tests\Feature\Admin\Content;

use App\Models\PlacementTestQuestion;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * `PlacementTest::isEnabled()` (2.5-د-2) ترجع `false` بصمت حين لا سؤال نشِط
 * في البنك — سواء عطّل المالك الخطوة عمدًا أو فرغ البنك بالخطأ (آخر سؤال
 * أُوقِف/اتحذف). شاشة `placement.admin.index` وحدها ترى الفرق، فهي تعرض
 * تحذيرًا صريحًا بدل التخطّي الصامت حين يكون الإعداد مفعَّلًا وبلا سؤال نشِط.
 */
class PlacementTestSkippedWarningTest extends AdminContentTestCase
{
    private function question(array $overrides = []): PlacementTestQuestion
    {
        return PlacementTestQuestion::create(array_merge([
            'prompt' => 'سؤال اختبار',
            'media_kind' => 'none',
            'type' => 'text',
            'reward_xp' => 5,
            'reward_tickets' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ], $overrides));
    }

    public function test_warning_shows_when_enabled_but_no_active_question_exists(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.placement-test.index'));

        $response->assertOk();
        $response->assertSee(setting('onboarding.placement.admin.skipped_title', 'الاختبار التمهيديّ متوقّف فعليًّا الآن'), false);
    }

    public function test_warning_still_shows_when_every_question_is_paused(): void
    {
        $this->question(['is_active' => false]);

        $response = $this->actingAs($this->admin())->get(route('admin.placement-test.index'));

        $response->assertOk();
        $response->assertSee(setting('onboarding.placement.admin.skipped_title', 'الاختبار التمهيديّ متوقّف فعليًّا الآن'), false);
    }

    public function test_warning_is_hidden_once_an_active_question_exists(): void
    {
        $this->question();

        $response = $this->actingAs($this->admin())->get(route('admin.placement-test.index'));

        $response->assertOk();
        $response->assertDontSee(setting('onboarding.placement.admin.skipped_title', 'الاختبار التمهيديّ متوقّف فعليًّا الآن'), false);
    }

    public function test_warning_is_hidden_when_the_step_is_explicitly_disabled(): void
    {
        Setting::query()->where('key', 'onboarding.placement.enabled')->update(['value' => '0']);
        Cache::forget('settings');

        $response = $this->actingAs($this->admin())->get(route('admin.placement-test.index'));

        $response->assertOk();
        $response->assertDontSee(setting('onboarding.placement.admin.skipped_title', 'الاختبار التمهيديّ متوقّف فعليًّا الآن'), false);
    }
}
