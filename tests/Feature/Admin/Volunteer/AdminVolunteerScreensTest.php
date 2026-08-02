<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Offboarding;
use App\Models\Setting;
use App\Services\Admin\Volunteer\Integrations;
use App\Services\Admin\Volunteer\OffboardingService;
use RuntimeException;

/**
 * الشاشات الرئيسيّة: كلّ مسار محروس بصلاحيّته (12.2.1)،
 * ومحتوى صفحة التطوّع يُدار بالكامل، والإقصاء عبر سلّم العتبات وحده.
 */
class AdminVolunteerScreensTest extends AdminVolunteerTestCase
{
    /** كلّ شاشة تفتح لصاحب صلاحيّتها وتُمنَع عن غيره. */
    public function test_every_screen_is_guarded_by_its_permission(): void
    {
        $screens = [
            'admin.volunteer.index' => 'volunteer_central_settings.view',
            'admin.volunteer.org' => 'org_chart.view',
            'admin.volunteer.rep' => 'rep_transactions.view',
            'admin.volunteer.offboarding' => 'offboarding.view',
            'admin.volunteer.reentries' => 'offboarding.view',
            'admin.volunteer.certificates' => 'volunteer_certificates.view',
            'admin.volunteer.analytics' => 'reports_volunteer.view',
            'admin.gamification.index' => 'xp_rules.view',
            'admin.rewards.index' => 'manual_rewards.list',
            'admin.events.index' => 'events.list',
        ];

        $stranger = $this->makeUser('بلا صلاحيّة');

        foreach ($screens as $route => $permission) {
            $this->actingAs($stranger)->get(route($route))->assertForbidden();

            $allowed = $this->grant($this->makeUser(), $permission);
            $this->actingAs($allowed)->get(route($route))->assertOk();
        }
    }

    /** تابات التلعيب السبعة تفتح كلّها بلا خطأ. */
    public function test_all_gamification_tabs_render(): void
    {
        $admin = $this->grant($this->makeUser(), 'xp_rules.view');

        foreach (['xp', 'badges', 'streaks', 'leaderboard', 'levels', 'wars', 'celebrations'] as $tab) {
            $this->actingAs($admin)
                ->get(route('admin.gamification.index', ['tab' => $tab]))
                ->assertOk();
        }
    }

    /** ⭐ محتوى صفحة التطوّع: إضافة وتعديل وحذف من لوحة الإدارة. */
    public function test_volunteer_landing_page_content_is_fully_managed(): void
    {
        $admin = $this->grant($this->makeUser(), 'volunteer_central_settings.view', 'volunteer_page.edit', 'volunteer_page.manage');

        $before = count(json_decode((string) Setting::where('key', 'volunteer_page.blocks')->value('value'), true) ?? []);

        $this->actingAs($admin)->post(route('admin.volunteer.page.block.save'), [
            'type' => 'faq',
            'title' => 'إزاي أبدأ؟',
            'body' => 'من زرّ «ابدأ التدريب التأهيليّ» في أعلى الصفحة.',
        ])->assertRedirect();

        $blocks = json_decode((string) Setting::where('key', 'volunteer_page.blocks')->value('value'), true);
        $this->assertCount($before + 1, $blocks);

        $this->actingAs($admin)->post(route('admin.volunteer.page.block.save'), [
            'index' => $before,
            'type' => 'faq',
            'title' => 'إزاي أبدأ رحلتي؟',
            'body' => 'من زرّ «ابدأ التدريب التأهيليّ».',
        ])->assertRedirect();

        $blocks = json_decode((string) Setting::where('key', 'volunteer_page.blocks')->value('value'), true);
        $this->assertSame('إزاي أبدأ رحلتي؟', $blocks[$before]['title']);

        $this->actingAs($admin)->post(route('admin.volunteer.page.block.delete'), ['index' => $before])->assertRedirect();

        $blocks = json_decode((string) Setting::where('key', 'volunteer_page.blocks')->value('value'), true);
        $this->assertCount($before, $blocks);
    }

    /** ⭐ الإقصاء لا يُفتَح إلّا عبر سلّم العتبات — لا فصل بقرار فرديّ. */
    public function test_exclusion_requires_reaching_the_threshold_ladder(): void
    {
        $admin = $this->grant($this->makeUser(), 'offboarding.create');
        $target = $this->makeUser('متطوّع');

        $this->expectException(RuntimeException::class);

        OffboardingService::open($target, 'exclusion', 'قرار فرديّ', $admin);
    }

    /** بلوغ عتبة التعليق يفتح الإقصاء — ومهلة الإشعار تُصفَّر فيه. */
    public function test_exclusion_opens_once_the_suspension_threshold_is_reached(): void
    {
        $admin = $this->grant($this->makeUser(), 'offboarding.create');
        $target = $this->makeUser('متطوّع');

        /*
         | يومٌ لكلّ دفعة: حدّ الخسارة اليوميّ −2 يمنع الهبوط من 0 إلى −10 في يوم
         | واحد عمدًا (13.4-ن-و)، فنمشي بالزمن كما يحدث فعلًا لا كما نشتهي.
         */
        for ($day = 0; $day < 6; $day++) {
            $this->travel($day)->days();
            Integrations::post($target, 'rep', -2, 'behavior', 'اختبار العتبات', $admin, null, 'volunteer');
        }

        $target = $target->fresh();

        $this->assertTrue(OffboardingService::reachedExclusionThreshold($target));

        $record = OffboardingService::open($target, 'exclusion', 'بلغ سلّم العتبات', $admin);

        $this->assertSame('exclusion', $record->type);
        $this->assertNull($record->cooldown_until, 'الإقصاء = لا عودة إلّا بقرار مشرف عام التطوّع.');
    }

    /** لا إنهاء قبل اكتمال التصفية الإلزاميّة. */
    public function test_offboarding_cannot_complete_before_clearance(): void
    {
        $admin = $this->grant($this->makeUser(), 'offboarding.create', 'offboarding.approve');
        $target = $this->makeUser('مستقيل');

        $record = OffboardingService::open($target, 'resignation', 'ظروف دراسة', $admin);

        $this->expectException(RuntimeException::class);

        OffboardingService::complete($record, $admin);
    }

    /** الاستقالة الطوعيّة: خروج مشرَّف بشهادة خبرة وتبريد شهر. */
    public function test_resignation_completes_with_honorable_certificate(): void
    {
        $admin = $this->grant($this->makeUser(), 'offboarding.create', 'offboarding.approve');
        $target = $this->makeUser('مستقيل');

        $checklist = array_fill(0, count(OffboardingService::clearanceItems()), true);
        $record = OffboardingService::open($target, 'resignation', 'ظروف دراسة', $admin, $checklist);

        OffboardingService::complete($record, $admin);

        $record = Offboarding::findOrFail($record->id);

        $this->assertNotNull($record->completed_at);
        $this->assertTrue((bool) $record->honorable_certificate_issued);
        $this->assertNotNull($record->cooldown_until);
        // ⭐ السبب لا يُنشَر للفريق — يبقى في السجلّ الإداريّ وحده
        $this->assertSame('ظروف دراسة', $record->reason);
    }
}
