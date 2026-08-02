<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\RewardQuestion;

/**
 * شاشة «أسئلة المكافآت» في لوحة الإدارة (12.10-أ):
 * إنشاء ونشر بالرابط والـQR، وإغلاق فوريّ، ونتائج بعد الإغلاق —
 * وكلّ مسارٍ محروس بصلاحيّته (12.2.1).
 */
class AdminRewardQuestionsTest extends AdminVolunteerTestCase
{
    public function test_tab_renders_for_its_permission_and_is_forbidden_for_others(): void
    {
        $this->actingAs($this->makeUser('غريب'))
            ->get(route('admin.gamification.index', ['tab' => 'reward_questions']))
            ->assertForbidden();

        $admin = $this->grant($this->makeUser(), 'reward_questions.view');

        $this->actingAs($admin)
            ->get(route('admin.gamification.index', ['tab' => 'reward_questions']))
            ->assertOk()
            ->assertSee('بنك أسئلة المكافآت', false);
    }

    public function test_admin_creates_a_question_and_gets_a_shareable_link(): void
    {
        $admin = $this->grant($this->makeUser(), 'reward_questions.view', 'reward_questions.create', 'reward_questions.edit');

        $this->actingAs($admin)
            ->post(route('admin.gamification.reward-questions.save'), [
                'prompt' => 'كام تذكرة قبل نصف مهلة التدريب؟',
                'type' => 'choice',
                'options' => "تذكرة واحدة\nتذكرتان",
                'correct_answer' => 'تذكرتان',
                'reward_xp' => 40,
                'reward_tickets' => 1,
                'active_minutes' => 30,
                'status' => 'published',
            ])
            ->assertRedirect();

        $question = RewardQuestion::query()->latest('id')->firstOrFail();

        $this->assertNotEmpty($question->token, 'لكلّ سؤال مفتاح رابطٍ لا يُخمَّن.');
        $this->assertNotNull($question->closes_at, 'موعد الإغلاق يُحسَب من مدّة التفعيل.');

        // الشاشة تعرض الرابط والـQR ولا تعرض الإجابة إلّا بفتحٍ صريح
        $this->actingAs($admin)
            ->get(route('admin.gamification.index', ['tab' => 'reward_questions']))
            ->assertOk()
            ->assertSee(route('reward-questions.show', $question->token), false)
            ->assertSee('<svg', false)
            ->assertSee('مشاركة واتساب', false);
    }

    public function test_immediate_close_shuts_the_link(): void
    {
        $admin = $this->grant($this->makeUser(), 'reward_questions.view', 'reward_questions.edit');

        $question = RewardQuestion::create([
            'token' => 'adminrq1',
            'prompt' => 'سؤال',
            'type' => 'text',
            'correct_answer' => 'تمام',
            'reward_xp' => 10,
            'reward_tickets' => 0,
            'active_minutes' => 60,
            'opens_at' => now()->subMinute(),
            'closes_at' => now()->addMinutes(59),
            'status' => 'published',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.gamification.reward-questions.close', $question))
            ->assertRedirect();

        $this->assertTrue($question->refresh()->closes_at->lessThanOrEqualTo(now()));
    }

    public function test_results_screen_shows_counts_and_the_fastest_answer(): void
    {
        $admin = $this->grant($this->makeUser(), 'reward_questions.view');

        $question = RewardQuestion::create([
            'token' => 'adminrq2',
            'prompt' => 'سؤال النتائج',
            'type' => 'text',
            'correct_answer' => 'تمام',
            'reward_xp' => 10,
            'reward_tickets' => 0,
            'active_minutes' => 60,
            'opens_at' => now()->subHour(),
            'closes_at' => now()->subMinute(),
            'status' => 'published',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.gamification.reward-questions.results', $question))
            ->assertOk()
            ->assertSee('نسبة الصحّ', false)
            ->assertSee('أسرع مجيب', false);
    }
}
