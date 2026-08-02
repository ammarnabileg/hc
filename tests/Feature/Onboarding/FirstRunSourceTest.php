<?php

namespace Tests\Feature\Onboarding;

use App\Models\Setting;
use App\Services\Admin\Ops\OnboardingContent;
use App\Services\Ui\FirstRunScreens;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * «شاشة أوّل مرّة» (2.15-د) — **مصدر حقيقة واحد**.
 *
 * العطل الذي أُصلِح: الأدمن يكتب شرائحه في جدول `onboarding_slides` (وله شاشة
 * إدارة كاملة)، والمستخدم كان يقرأ من إعداد `ux.first_time.content` الذي لا
 * يكتب فيه أحد — فيكتب الأدمن ثلاث شرائح ولا يرى المستخدم شيئًا. المصدر الآن
 * الجدول وحده، وهذه الاختبارات تمنع عودة المصدر الثاني.
 */
class FirstRunSourceTest extends OnboardingTestCase
{
    private function enableScreen(string $screen): void
    {
        // كتالوج الشاشات المتاحة كما تعرضه لوحة الإدارة، ثمّ تفعيل واحدة منها
        Setting::updateOrCreate(
            ['key' => 'onboarding.first_time.screens'],
            [
                'group' => 'onboarding',
                'label_ar' => 'الشاشات المتاحة لـ«أوّل مرّة»',
                'type' => 'json',
                'value' => json_encode([$screen => 'الرئيسيّة'], JSON_UNESCAPED_UNICODE),
            ],
        );

        Setting::where('key', 'ux.first_time.enabled_screens')
            ->update(['value' => json_encode([$screen], JSON_UNESCAPED_UNICODE)]);

        Cache::forget('settings');
    }

    private function slide(string $screen, string $title, string $body, int $order = 1, bool $active = true): int
    {
        return (int) DB::table('onboarding_slides')->insertGetId([
            'screen' => $screen,
            'title_ar' => $title,
            'body_ar' => $body,
            'sort_order' => $order,
            'is_active' => $active,
            'from_template' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_what_the_admin_writes_is_what_the_user_reads(): void
    {
        $this->enableScreen('dashboard');

        $this->slide('dashboard', 'أهلًا بيك 👋', 'دي رئيسيّتك.', 1);
        $this->slide('dashboard', 'كمّل اللي وقفت عنده', 'الكارت الأوّل بيرجّعك لآخر درس.', 2);
        $this->slide('dashboard', 'شريحة متوقّفة', 'مش المفروض تظهر.', 3, active: false);

        $steps = app(FirstRunScreens::class)->stepsFor('dashboard');

        // ⭐ كان `stepsFor('dashboard')` يرجع [] رغم وجود شرائح مفعَّلة
        $this->assertCount(2, $steps);
        $this->assertSame('أهلًا بيك 👋', $steps[0]['title']);
        $this->assertSame('كمّل اللي وقفت عنده', $steps[1]['title']);
    }

    public function test_the_admin_order_is_the_order_the_user_sees(): void
    {
        $this->enableScreen('dashboard');

        $first = $this->slide('dashboard', 'واحد', 'أ', 1);
        $second = $this->slide('dashboard', 'اتنين', 'ب', 2);

        app(OnboardingContent::class)->reorder('dashboard', [$second, $first], null);

        $steps = app(FirstRunScreens::class)->stepsFor('dashboard');

        $this->assertSame(['اتنين', 'واحد'], array_column($steps, 'title'));
    }

    public function test_the_popup_on_the_page_renders_the_admin_slides(): void
    {
        $this->enableScreen('dashboard');
        $this->slide('dashboard', 'عنوان من لوحة الإدارة', 'نصّ من لوحة الإدارة.');

        $this->actingAs($this->member())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-first-run', false)
            ->assertSee('عنوان من لوحة الإدارة');
    }

    public function test_the_orphan_setting_key_is_gone_from_the_catalog(): void
    {
        // لا يبقى مفتاحٌ في اللوحة يوهم المالك أنّه يُقرَأ وهو لا يُقرَأ (2.13)
        $this->assertDatabaseMissing('settings', ['key' => 'ux.first_time.content']);
        $this->assertDatabaseMissing('settings', ['key' => 'ux.first_time.default_template']);

        // والقالب الجاهز صار في مجموعة الـOnboarding مع بقيّة قوالبها
        $this->assertDatabaseHas('settings', ['key' => 'onboarding.first_time.default_template', 'group' => 'onboarding']);
    }

    public function test_a_screen_without_slides_shows_nothing(): void
    {
        $this->enableScreen('dashboard');

        // حالة فارغة صادقة: لا قالبٌ لم يطبّقه أحد يظهر كأنّه محتوى الأدمن
        $this->assertSame([], app(FirstRunScreens::class)->stepsFor('dashboard'));
        $this->assertFalse(app(FirstRunScreens::class)->shouldShow($this->member(), 'dashboard'));
    }
}
