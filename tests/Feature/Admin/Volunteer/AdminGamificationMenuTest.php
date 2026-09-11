<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Http\Controllers\Admin\GamificationController;
use App\Models\Level;
use App\Models\User;

/**
 * وجهات «التلعيب والتحديات» العشر (12.0 · 12.10 موسّع).
 *
 * ⭐ **12.10 لم تعد ثلاث تبويبات.** نصُّها الأوّل (2026-07-23) كان «دروب-داون
 * بـ3 تبويبات»، ثمّ وسّعها الدستور نفسه: «**12.10 توسّعت من 3 تبويبات إلى 11
 * صفحة**»، والحادية عشرة (الألعاب) سقطت بإلغاء 7.5 — فبقيت عشر وجهاتٍ هي بعينها
 * بنود مجموعة 🎮 في خريطة سايد بار الإدارة المعتمَدة (12.0).
 *
 * وما يقيسه هذا الملفّ: أنّ **شريط الشاشة يعرض الوجهات العشر نفسها** لا ثمانية
 * تابات مسطّحة تسكت عن ثلاث شاشاتٍ من مجموعتها، وأنّ **بنك أسئلة الحروب شاشةٌ
 * بمسارها** لا مرساةً داخل صفحة، وأنّ الصلاحيّات تحرس ما كانت تحرسه حرفًا بحرف.
 */
class AdminGamificationMenuTest extends AdminVolunteerTestCase
{
    /** كلّ ما تفتحه مجموعة 12.10 لأدمن التلعيب الكامل */
    private const FULL = [
        'xp_rules.view', 'xp_rules.edit',
        'badges.view',
        'streaks.list',
        'leaderboards.view',
        'celebrations.view',
        'reward_questions.view',
        'wars_settings.view',
        'referrals.list',
        'positive_messages.list',
        'wars_bank.list',
        'achievements.edit',
    ];

    private function gamificationAdmin(): User
    {
        return $this->grant($this->makeUser('مسؤول التلعيب'), ...self::FULL);
    }

    /**
     * الوجهات العشر بترتيب 12.0 — والشريط يحملها كلّها، فيها الثلاث الشاشات
     * المستقلّة التي كان الشريط المسطّح يسكت عنها تمامًا.
     */
    public function test_the_screen_strip_carries_the_ten_destinations_of_the_map(): void
    {
        $page = $this->actingAs($this->gamificationAdmin())
            ->get(route('admin.gamification.index'))
            ->assertOk()
            ->getContent();

        $expected = [
            route('admin.gamification.index', ['tab' => 'xp']),
            route('admin.gamification.index', ['tab' => 'streaks']),
            route('admin.gamification.index', ['tab' => 'leaderboard']),
            route('admin.gamification.index', ['tab' => 'badges']),
            route('admin.referrals.index'),
            route('admin.positive.index'),
            route('admin.gamification.index', ['tab' => 'celebrations']),
            route('admin.gamification.index', ['tab' => 'reward_questions']),
            route('admin.wars.bank.index'),
            route('admin.gamification.index', ['tab' => 'wars']),
        ];

        $this->assertSame($expected, array_column(GamificationController::menuFor($this->gamificationAdmin()), 'href'));

        foreach ($expected as $href) {
            // مرّتان لا مرّة: بند السايد بار (12.0) وبند الشريط — من مصدرٍ واحد
            $this->assertGreaterThanOrEqual(
                2,
                substr_count($page, $href),
                "وجهة 12.10 «{$href}» لازم تكون في الدروب-داون وفي شريط الشاشة معًا.",
            );
        }

        // ⛔ ولا «ألعاب» — ملغاة بقرار المالك (v5.3 · 7.5)
        $this->assertStringNotContainsString('tab=games', $page);
    }

    /**
     * ⭐ بنك أسئلة الحروب **شاشةٌ قائمة بذاتها** (12.10-ب) لا تابًّا ولا مرساةً:
     * له مساره، ويُبلَغ من مجموعته، ولا يقبل أن يُطلَب كتابٍ في شاشة التلعيب.
     */
    public function test_the_war_question_bank_is_a_screen_of_its_own(): void
    {
        $admin = $this->gamificationAdmin();

        $this->actingAs($admin)->get(route('admin.wars.bank.index'))->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.gamification.index'))
            ->assertOk()
            ->assertSee(route('admin.wars.bank.index'), false);

        // ليس تابًّا: المفتاح غير موجود في قائمة التابات ولا يُفتَح بالعنوان
        $this->assertNotContains('wars_bank', GamificationController::TAB_KEYS);
        $this->actingAs($admin)
            ->get(route('admin.gamification.index', ['tab' => 'wars_bank']))
            ->assertNotFound();
    }

    /** المحظور **يُخفى ولا يُعطَّل** (2.15-أ-7) — والشريط يحرس ما يحرسه السايد بار. */
    public function test_the_strip_hides_the_destinations_this_admin_may_not_open(): void
    {
        $warsOnly = $this->grant($this->makeUser('مسؤول الحروب'), 'wars_settings.view');

        $page = $this->actingAs($warsOnly)
            ->get(route('admin.gamification.index', ['tab' => 'wars']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('admin.gamification.index', ['tab' => 'wars']), $page);

        foreach ([
            route('admin.gamification.index', ['tab' => 'badges']),
            route('admin.gamification.index', ['tab' => 'reward_questions']),
            route('admin.wars.bank.index'),
            route('admin.referrals.index'),
            route('admin.positive.index'),
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $page,
                "بندٌ يفتح 403 أسوأ من إخفائه: {$forbidden}",
            );
        }
    }

    /**
     * ⭐ «المستويات» لم تكن وجهةً في 12.0 ولا بندًا في السايد بار — تابٌّ لا
     * يُبلَغ إلّا بكتابة العنوان. فصار **قسمًا في صفحة «XP والتذاكر»** (العتبة
     * رقم XP فموضعُها اقتصاد XP) — ولا قدرة ضاعت: العرض والإضافة والحذف كما هي.
     */
    public function test_levels_live_inside_the_xp_page_with_their_crud_intact(): void
    {
        $admin = $this->gamificationAdmin();

        Level::create(['level' => 97, 'name_ar' => 'مستوى تجريبيّ', 'min_xp' => 97000]);

        $this->actingAs($admin)
            ->get(route('admin.gamification.index', ['tab' => 'xp']))
            ->assertOk()
            ->assertSee('مستوى تجريبيّ', false)
            ->assertSee(route('admin.gamification.levels.save'), false);

        // الإضافة والحذف من موضعهما الجديد
        $this->actingAs($admin)->post(route('admin.gamification.levels.save'), [
            'level' => 98, 'name_ar' => 'مستوى رابع', 'min_xp' => 98000,
        ])->assertRedirect();

        $added = Level::query()->where('level', 98)->firstOrFail();

        /*
         | والحذف بـ`achievements.manage` وهي **في المجموعة المحميّة**
         | (12.2.1-ز-3): منحُها لا يفتحها — العزل يغلب الإسناد، فالفاعل مالك
         | المنصّة. وهذا حارسُها **قبل النقل وبعده** بلا تغيير.
         */
        $this->actingAs($admin)
            ->post(route('admin.gamification.levels.delete', $added))
            ->assertForbidden();

        $this->actingAs($this->platformOwner())
            ->post(route('admin.gamification.levels.delete', $added))
            ->assertRedirect();

        $this->assertNull(Level::query()->find($added->id));

        // والرابط القديم لا ينكسر — تابٌّ صالحٌ وإن لم يعد وجهةً في الشريط
        $this->actingAs($admin)
            ->get(route('admin.gamification.index', ['tab' => 'levels']))
            ->assertOk()
            ->assertSee('مستوى تجريبيّ', false);
    }

    /** لا قدرة ضاعت: كلّ تابات الشاشة الثمانية تفتح كما كانت. */
    public function test_every_original_tab_still_renders(): void
    {
        $admin = $this->gamificationAdmin();

        foreach (GamificationController::TAB_KEYS as $tab) {
            $this->actingAs($admin)
                ->get(route('admin.gamification.index', ['tab' => $tab]))
                ->assertOk();
        }
    }

    /**
     * حرّاس الوجهات **نسخةٌ حرفيّةٌ ممّا تحرسه المِدل-وير**: صاحب الستريك وحده
     * (مفتاحه الإداريّ `streaks.list`) يجد بنده، ولا يجد بند الشارات.
     */
    public function test_each_destination_keeps_the_guard_it_had(): void
    {
        $streaks = $this->grant($this->makeUser('مسؤول الستريك'), 'streaks.list');

        $hrefs = array_column(GamificationController::menuFor($streaks), 'href');

        $this->assertSame([route('admin.gamification.index', ['tab' => 'streaks'])], $hrefs);

        // وبلا أيّ مفتاحٍ من مفاتيح الباب: لا بند إطلاقًا
        $this->assertSame([], GamificationController::menuFor($this->makeUser('غريب')));
    }
}
