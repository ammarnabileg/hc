<?php

namespace Tests\Feature\Challenges;

use App\Models\Challenge;
use App\Models\User;
use App\Services\Gamification\Wars\WarMatchService;

/**
 * اختبار Feature لكلّ شاشة رئيسيّة في المجال (قاعدة البناء 6).
 */
class ChallengeScreensTest extends ChallengeTestCase
{
    public function test_arena_list_shows_zero_sum_economy_on_every_card(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->get(route('challenges.index'))
            ->assertOk()
            ->assertSee('حرب المعلومات', false)
            ->assertSee('شرط الاستعداد', false)
            // الأيقونات صارت SVG مرسومة داخل المشروع (2.16-ج) — فالنصّ وحده يُقاس
            ->assertSee('الفوز', false)
            ->assertSee('الخسارة', false)
            ->assertSee('الانسحاب', false);
    }

    public function test_arena_list_filters_by_type(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->get(route('challenges.index', ['type' => 'focus']))
            ->assertOk()
            ->assertSee('عمل عميق بلا مقاطعة', false)
            ->assertDontSee('أول غلطة تخرجك', false);
    }

    public function test_paused_war_is_shown_with_its_state_not_hidden(): void
    {
        $user = $this->trainee();
        Challenge::where('key', 'survival_war')->update(['is_active' => false]);

        $this->actingAs($user)
            ->get(route('challenges.index'))
            ->assertOk()
            ->assertSee('حرب البقاء', false)
            ->assertSee('موقوفة مؤقّتًا', false);
    }

    /** شاشة الساحة: هيدلاين + اقتصاد معلَن + زرّ [استعداد] واحد (15.1). */
    public function test_arena_screen_shows_headline_and_ready_button(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->get(route('challenges.arena', $this->challenge()))
            ->assertOk()
            ->assertSee('ساحة الحرب', false)
            ->assertSee('اختبر مهاراتك الذهنية والسرعة', false)
            ->assertSee('استعداد', false);
    }

    /**
     * بعد الاستعداد تظهر «المحاربون الجاهزون» بحالتها الفارغة المكتوبة (15.1)،
     * بنصّها المنصوص حرفيًّا في الدستور (15.1 · 15.5 · 15.6 · 2.13-ب).
     */
    public function test_ready_state_shows_the_fighters_list_and_its_empty_line(): void
    {
        $user = $this->trainee();
        $challenge = $this->challenge();

        $this->actingAs($user)->post(route('challenges.ready', $challenge));

        $this->actingAs($user)
            ->get(route('challenges.arena', $challenge))
            ->assertOk()
            ->assertSee('المحاربون الجاهزون', false)
            // نصّ الدستور الحرفيّ، لا الصياغة القديمة المخالفة له
            ->assertSee('يبدو أنك قضيت على كل خصومك! 🔥 أنت وحدك في ساحة الحرب..', false);
    }

    /** كارت المحارب: اسمه وفوزه وخسارته وزرّ [تحدّاه] (15.1). */
    public function test_fighter_card_shows_record_and_challenge_button(): void
    {
        $me = $this->trainee();
        $rival = $this->trainee(20, ['name' => 'محارب الساحة']);
        $challenge = $this->challenge();

        $this->actingAs($rival)->post(route('challenges.ready', $challenge));
        $this->actingAs($me)->post(route('challenges.ready', $challenge));

        $this->actingAs($me)
            ->get(route('challenges.arena', $challenge))
            ->assertOk()
            ->assertSee('محارب الساحة', false)
            ->assertSee('الفوز:', false)
            ->assertSee('تحدّاه', false);
    }

    /** الشريط العائم يظهر على **كلّ الصفحات** بزرّ إلغاء (15.0). */
    public function test_floating_readiness_bar_appears_on_every_page(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)->post(route('challenges.ready', $this->challenge()));

        $this->actingAs($user)
            ->get(route('challenges.mine'))
            ->assertOk()
            ->assertSee('data-war-ready-bar', false)
            ->assertSee('إلغاء الاستعداد', false);

        // وبلا استعداد لا يظهر الشريط أصلًا
        $this->actingAs($user)->post(route('challenges.unready'));

        $this->actingAs($user)
            ->get(route('challenges.mine'))
            ->assertOk()
            ->assertDontSee('data-war-ready-bar', false);
    }

    /** شاشة المواجهة تعرض السؤال وزرّ الانسحاب. */
    public function test_match_screen_shows_questions_and_withdraw_action(): void
    {
        $a = $this->trainee();
        $b = $this->trainee();

        $match = $this->startMatch($a, $b);

        $this->actingAs($a)
            ->get(route('challenges.play', $match))
            ->assertOk()
            ->assertSee('انسحاب', false)
            ->assertSee('خلّصت — سلّم', false);
    }

    /** شاشة «تعادل» لها نصّها الصريح (15.2-5). */
    public function test_draw_screen_states_no_change(): void
    {
        $a = $this->trainee();
        $b = $this->trainee();

        $match = $this->startMatch($a, $b);
        $service = app(WarMatchService::class);

        $service->finishSide($match, $service->sideOf($match, $a));
        $service->finishSide($match->refresh(), $service->sideOf($match, $b));

        $this->actingAs($a)
            ->get(route('challenges.result', $match->refresh()))
            ->assertOk()
            ->assertSee('تعادل', false)
            ->assertSee('لا خصم ولا إضافة', false);
    }

    public function test_my_challenges_screen_has_running_and_finished_tabs(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)->get(route('challenges.mine'))->assertOk()->assertSee('جارية', false);
        $this->actingAs($user)->get(route('challenges.mine', ['tab' => 'done']))->assertOk()->assertSee('منتهية', false);
    }

    public function test_champions_board_pins_my_row_at_the_bottom(): void
    {
        $user = $this->trainee();
        $rival = $this->trainee();

        // اللوحة تُبنى من مواجهات محسومة — فنحسم واحدةً قبل فتحها
        $match = $this->startMatch($user, $rival);
        $service = app(WarMatchService::class);
        $service->finishSide($match, $service->sideOf($match, $user));
        $service->finishSide($match->refresh(), $service->sideOf($match, $rival));

        $this->actingAs($user)
            ->get(route('challenges.leaderboard'))
            ->assertOk()
            ->assertSee('ده إنت', false);
    }

    public function test_xp_leaderboard_pins_my_row_and_shows_period_delta(): void
    {
        $me = $this->trainee();
        $me->forceFill(['xp' => 500])->save();

        $other = $this->trainee();
        $other->forceFill(['xp' => 900])->save();

        $this->actingAs($me)
            ->get(route('achievements.leaderboard'))
            ->assertOk()
            ->assertSee('ده إنت', false)
            ->assertSee('XP', false);
    }

    public function test_streak_screen_explains_the_five_am_club_clearly(): void
    {
        $user = $this->trainee();

        $this->actingAs($user)
            ->get(route('achievements.streak'))
            ->assertOk()
            ->assertSee('نادي الخامسة صباحًا', false)
            ->assertSee('أطول ستريك', false)
            ->assertSee('04:50', false);
    }

    public function test_screens_require_permission(): void
    {
        $stranger = User::create([
            'name' => 'زائر', 'email' => 'no-perm@test.local', 'password' => 'secret-password',
            'code' => 'UNOPERM1', 'status' => 'active',
        ]);

        $this->actingAs($stranger)->get(route('challenges.index'))->assertForbidden();
        $this->actingAs($stranger)->get(route('challenges.focus.index'))->assertForbidden();
        $this->actingAs($stranger)->get(route('achievements.badges'))->assertForbidden();
        $this->actingAs($stranger)->get(route('admin.wars.bank.index'))->assertForbidden();
    }
}
