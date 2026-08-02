<?php

namespace Tests\Feature\Onboarding;

use App\Models\PlacementTestAnswer;
use App\Models\PlacementTestQuestion;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\AccountApproval;
use Illuminate\Support\Facades\Cache;

/**
 * 2.5-د — رحلة ما بعد التسجيل **بالترتيب**:
 * تعليمات ⟵ اختبار تمهيديّ ⟵ تحت المراجعة ⟵ تمّ قبول حسابك.
 *
 * الاختبارات تحرس الترتيب نفسه: لا قفزَ فوق خطوة، ولا حبسَ في خطوةٍ أُتمّت.
 */
class JourneyStepsTest extends OnboardingTestCase
{
    private function question(array $overrides = []): PlacementTestQuestion
    {
        return PlacementTestQuestion::create([
            'prompt' => 'إيه أهمّ قيمة عندنا؟',
            'type' => 'choice',
            'options' => ['السرعة', 'الاحترام'],
            'correct_answer' => 'الاحترام',
            'reward_xp' => 20,
            'reward_tickets' => 1,
            'sort_order' => 1,
            'is_active' => true,
            ...$overrides,
        ]);
    }

    // -------------------------------------------------- د-1) صفحة «تعليمات»

    public function test_new_account_lands_on_the_instructions_page_not_on_pending(): void
    {
        $user = $this->newcomer();

        // ⭐ العطل الذي أُصلِح: كانت التحويلة تذهب مباشرةً إلى /pending
        $this->actingAs($user)->get('/pending')->assertRedirect(route('onboarding.instructions'));

        $this->actingAs($user)->get(route('onboarding.instructions'))
            ->assertOk()
            ->assertSee(setting('onboarding.instructions.agree_label', 'موافق'));
    }

    public function test_instructions_content_comes_from_the_admin_panel(): void
    {
        Setting::where('key', 'onboarding.instructions.html')
            ->update(['value' => '<h2>قوانين المكان</h2><p>نصّ كتبه الأدمن بنفسه.</p>']);
        Cache::forget('settings');

        $this->actingAs($this->newcomer())->get(route('onboarding.instructions'))
            ->assertOk()
            ->assertSee('نصّ كتبه الأدمن بنفسه', false);
    }

    public function test_the_journey_does_not_advance_without_pressing_agree(): void
    {
        $user = $this->newcomer();

        // بلا موافقة: الخطوات التالية مقفولة ويُردّ للتعليمات
        $this->actingAs($user)->get(route('onboarding.placement'))
            ->assertRedirect(route('onboarding.instructions'));

        $this->actingAs($user)->post(route('onboarding.instructions.agree'), ['agreed' => '0'])
            ->assertSessionHasErrors('agreed');

        $this->assertNull($user->fresh()->instructions_agreed_at);
    }

    public function test_agreeing_stamps_the_account_and_moves_to_the_next_step(): void
    {
        $user = $this->newcomer();
        $this->question();

        $this->actingAs($user)->post(route('onboarding.instructions.agree'), ['agreed' => '1'])
            ->assertRedirect(route('onboarding.placement'));

        $this->assertNotNull($user->fresh()->instructions_agreed_at);

        // ولا تُعاد عليه: مَن وافق لا يرى الشاشة ثانيةً
        $this->actingAs($user->fresh())->get(route('onboarding.instructions'))
            ->assertRedirect(route('onboarding.placement'));
    }

    // ------------------------------------------ د-2) الاختبار التمهيديّ

    public function test_placement_shows_its_questions_and_never_leaks_the_answer(): void
    {
        $user = $this->newcomer(['instructions_agreed_at' => now()]);
        $this->question();

        $response = $this->actingAs($user)->get(route('onboarding.placement'))->assertOk();

        $response->assertSee('إيه أهمّ قيمة عندنا؟');
        $response->assertSee('السرعة');
        // المكافأة بجانب السؤال (2.5-د-2)
        $response->assertSee('20 XP');
        // ⛔ ولا تخرج الإجابة الصحيحة في سمة أو حقل مخفيّ
        $response->assertDontSee('correct_answer');
    }

    public function test_placement_scores_rewards_and_records_the_result(): void
    {
        $user = $this->newcomer(['instructions_agreed_at' => now()]);
        $question = $this->question();
        $wrong = $this->question(['prompt' => 'سؤال تاني', 'correct_answer' => 'الاحترام', 'sort_order' => 2]);

        $this->actingAs($user)->post(route('onboarding.placement.submit'), [
            'answers' => [$question->id => 'الاحترام', $wrong->id => 'السرعة'],
        ])->assertRedirect(route('account.pending'));

        $user->refresh();

        // النتيجة تُسجَّل على الحساب فيراها الأدمن قبل قرار الاعتماد (2.5-د-3)
        $this->assertNotNull($user->placement_completed_at);
        $this->assertSame(50, $user->placement_score);

        $this->assertDatabaseHas('placement_test_answers', [
            'user_id' => $user->id,
            'placement_test_question_id' => $question->id,
            'is_correct' => true,
            'xp_awarded' => 20,
            'tickets_awarded' => 1,
        ]);

        $this->assertDatabaseHas('placement_test_answers', [
            'user_id' => $user->id,
            'placement_test_question_id' => $wrong->id,
            'is_correct' => false,
            'xp_awarded' => 0,
        ]);

        // المكافأة تمرّ من المصدر الموحّد: `users.xp` والمحفظة معًا (7 · 19)
        $this->assertSame(20, (int) $user->xp);
        $this->assertSame(1.0, $user->balance('tickets'));
    }

    public function test_placement_cannot_be_submitted_twice(): void
    {
        $user = $this->newcomer(['instructions_agreed_at' => now()]);
        $question = $this->question();

        $payload = ['answers' => [$question->id => 'الاحترام']];

        $this->actingAs($user)->post(route('onboarding.placement.submit'), $payload);
        $this->actingAs($user->fresh())->post(route('onboarding.placement.submit'), $payload)
            ->assertRedirect(route('account.pending'));

        $this->assertSame(1, $user->fresh()->answers_count ?? PlacementTestAnswer::where('user_id', $user->id)->count());
        $this->assertSame(20, (int) $user->fresh()->xp);
    }

    // ------------------------------------------ د-3) و د-4) المراجعة والقبول

    public function test_review_page_renders_the_html_written_by_the_admin(): void
    {
        Setting::where('key', 'onboarding.review.html')
            ->update(['value' => '<p>ملفّك وصلنا وهنراجعه.</p>']);
        Cache::forget('settings');

        $user = $this->newcomer(['instructions_agreed_at' => now(), 'placement_completed_at' => now()]);

        $this->actingAs($user)->get('/pending')->assertOk()->assertSee('ملفّك وصلنا وهنراجعه', false);
    }

    public function test_accepted_page_exists_with_admin_html_and_is_seen_once(): void
    {
        Setting::where('key', 'onboarding.accepted.html')
            ->update(['value' => '<p>أهلًا بيك رسميًّا.</p>']);
        Cache::forget('settings');

        $user = $this->newcomer(['instructions_agreed_at' => now(), 'placement_completed_at' => now()]);

        $admin = User::create([
            'name' => 'أدمن', 'email' => 'approver@test.local', 'password' => 'secret-password',
            'code' => 'UADMIN01', 'status' => 'active',
        ]);
        $admin->assignRole(Role::where('key', 'trainee')->firstOrFail());

        app(AccountApproval::class)->approve($admin, $user);

        // ⭐ العطل الذي أُصلِح: كان الاعتماد يطلق حدث احتفال بلا صفحة تحمله
        $this->actingAs($user->fresh())->get(route('onboarding.accepted'))
            ->assertOk()
            ->assertSee(setting('onboarding.accepted.title', 'تمّ قبول حسابك 🎉'))
            ->assertSee('أهلًا بيك رسميًّا', false);

        $this->actingAs($user->fresh())->post(route('onboarding.accepted.enter'))
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->acceptance_seen_at);

        // ولا تتكرّر بعد رؤيتها
        $this->actingAs($user->fresh())->get(route('onboarding.accepted'))
            ->assertRedirect(route('dashboard'));
    }

    // ------------------------------------------------------ أ) شاشة الدعوة

    public function test_referral_screen_asks_before_registration_and_can_be_skipped(): void
    {
        $this->get(route('register'))->assertRedirect(route('onboarding.referral'));

        $this->get(route('onboarding.referral'))
            ->assertOk()
            ->assertSee(setting('onboarding.referral.title', 'هل دعاك شخص ما؟'));

        $this->post(route('onboarding.referral.skip'))->assertRedirect(route('register'));
        $this->get(route('register'))->assertOk();
    }

    public function test_invite_link_skips_the_referral_screen_and_keeps_the_code(): void
    {
        $host = $this->member(['code' => 'UHOST123']);

        // «دخول عبر رابط دعوة: تُتخطّى الشاشة (آيدي الداعي محفوظ في السيشن)» (2.5-أ)
        $this->get(route('register', ['offer' => $host->code]))
            ->assertOk()
            ->assertSee($host->code, false);
    }

    public function test_unknown_referral_code_says_what_happened_and_what_to_do(): void
    {
        $this->post(route('onboarding.referral.apply'), ['code' => 'UNOPE999'])
            ->assertSessionHasErrors('code');

        $host = $this->member(['code' => 'UHOST777']);

        $this->post(route('onboarding.referral.apply'), ['code' => $host->code])
            ->assertRedirect(route('register'))
            ->assertSessionHas('referral_celebrate');
    }
}
