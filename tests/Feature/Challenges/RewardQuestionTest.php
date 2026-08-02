<?php

namespace Tests\Feature\Challenges;

use App\Models\RewardQuestion;
use App\Models\RewardQuestionAnswer;
use App\Services\Gamification\RewardQuestionService;

/**
 * بنك أسئلة المكافأة (12.10-أ) — كلّ اختبار يقابل قاعدةً منصوصةً في الدستور.
 */
class RewardQuestionTest extends ChallengeTestCase
{
    /** صفحة السؤال تفتح بالتايمر فوقه — **والإجابة الصحيحة لا تُرسَل للمتصفّح** */
    public function test_question_page_shows_the_timer_and_never_leaks_the_answer(): void
    {
        $user = $this->trainee();

        // سؤال نصّيّ: إجابته لا تظهر ضمن اختيارات، فغيابها عن الصفحة دليلٌ قاطع
        $question = $this->question([
            'type' => 'text',
            'options' => null,
            'prompt' => 'اكتب اسم العاصمة',
            'correct_answer' => 'سرّ-الإجابة-المحميّة',
        ]);

        $this->actingAs($user)
            ->get(route('reward-questions.show', $question->token))
            ->assertOk()
            ->assertSee($question->prompt, false)
            ->assertSee('data-countdown', false)
            ->assertDontSee('سرّ-الإجابة-المحميّة', false);
    }

    /** الإجابة الصحيحة تمنح XP وتذاكر، والـXP يظهر في `users.xp` (7.3) */
    public function test_correct_answer_grants_xp_and_tickets_once(): void
    {
        $user = $this->trainee();
        $question = $this->question();
        $ticketsBefore = $this->ticketsOf($user);

        $this->actingAs($user)
            ->post(route('reward-questions.answer', $question->token), ['answer' => 'تذكرتان'])
            ->assertRedirect(route('reward-questions.show', $question->token));

        $user->refresh();

        $this->assertSame(50, (int) $user->xp, 'XP يُكتَب في العمود الذي يقرأه الليدر بورد.');
        $this->assertSame($ticketsBefore + 1, $this->ticketsOf($user));
        $this->assertDatabaseHas('reward_question_answers', [
            'reward_question_id' => $question->id,
            'user_id' => $user->id,
            'is_correct' => true,
            'xp_awarded' => 50,
            'tickets_awarded' => 1,
        ]);
    }

    /** إجابة واحدة لكلّ مستخدم — ولا صرف مكرّر مهما تكرّر الإرسال (12.10-أ) */
    public function test_only_one_answer_per_user_is_rewarded(): void
    {
        $user = $this->trainee();
        $question = $this->question();

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($user)
                ->post(route('reward-questions.answer', $question->token), ['answer' => 'تذكرتان']);
        }

        $this->assertSame(1, RewardQuestionAnswer::query()
            ->where('reward_question_id', $question->id)->where('user_id', $user->id)->count());

        $this->assertSame(50, (int) $user->fresh()->xp, 'المكافأة تُصرَف مرّة واحدة لا ثلاثًا.');
    }

    /** الإجابة الخاطئة تُسجَّل بلا مكافأة وبرسالة تشجّع ولا تعاتب (2.17-ج) */
    public function test_wrong_answer_is_recorded_without_reward(): void
    {
        $user = $this->trainee();
        $question = $this->question();

        $this->actingAs($user)
            ->post(route('reward-questions.answer', $question->token), ['answer' => 'ثلاث تذاكر']);

        $this->assertSame(0, (int) $user->fresh()->xp);
        $this->assertDatabaseHas('reward_question_answers', [
            'reward_question_id' => $question->id,
            'user_id' => $user->id,
            'is_correct' => false,
            'xp_awarded' => 0,
        ]);
    }

    /** بعد انتهاء المدّة يقفل الرابط ويظهر «انتهى وقت الإجابة» ولا تُقبَل إجابة */
    public function test_closed_question_refuses_answers_and_says_time_is_over(): void
    {
        $user = $this->trainee();
        $question = $this->question(['opens_at' => now()->subDay(), 'closes_at' => now()->subHours(2)]);

        $this->actingAs($user)
            ->get(route('reward-questions.show', $question->token))
            ->assertOk()
            ->assertSee(setting('reward_questions.closed_text', 'انتهى وقت الإجابة'), false);

        $this->actingAs($user)
            ->post(route('reward-questions.answer', $question->token), ['answer' => 'تذكرتان']);

        $this->assertSame(0, RewardQuestionAnswer::query()->count(), 'الرابط المقفول لا يقبل إجابة.');
        $this->assertSame(0, (int) $user->fresh()->xp);
    }

    /** المدّة تحكم الإغلاق: `closes_at` تُحسَب من مدّة التفعيل لا تُكتَب يدويًّا */
    public function test_active_minutes_drive_the_closing_time(): void
    {
        $service = app(RewardQuestionService::class);

        $question = $service->save([
            'prompt' => 'سؤال بمدّة',
            'type' => 'text',
            'correct_answer' => 'تمام',
            'active_minutes' => 45,
            'opens_at' => now(),
            'status' => 'published',
        ]);

        $this->assertEqualsWithDelta(45 * 60, $question->opens_at->diffInSeconds($question->closes_at), 2);
        $this->assertTrue($service->isOpen($question));
    }

    /** الإغلاق الفوريّ من لوحة الإدارة يقفل الرابط في الحال */
    public function test_closing_now_shuts_the_link(): void
    {
        $service = app(RewardQuestionService::class);
        $question = $this->question();

        $service->closeNow($question);

        $this->assertFalse($service->isOpen($question->refresh()));
        $this->assertSame('closed', $service->liveState($question)['key']);
    }

    /** المسودّة لا تُفتَح أصلًا — الرابط لا يعمل قبل النشر */
    public function test_draft_question_is_not_reachable(): void
    {
        $user = $this->trainee();
        $question = $this->question(['status' => 'draft']);

        $this->actingAs($user)
            ->get(route('reward-questions.show', $question->token))
            ->assertNotFound();
    }

    // ------------------------------------------------------------ أدوات

    private function question(array $overrides = []): RewardQuestion
    {
        return RewardQuestion::create(array_merge([
            'token' => 'rqtest'.str()->lower(str()->random(6)),
            'prompt' => 'كام تذكرة بتاخدها قبل نصف مهلة التدريب؟',
            'type' => 'choice',
            'options' => ['تذكرة واحدة', 'تذكرتان', 'ثلاث تذاكر'],
            'correct_answer' => 'تذكرتان',
            'reward_xp' => 50,
            'reward_tickets' => 1,
            'active_minutes' => 60,
            'opens_at' => now()->subMinute(),
            'closes_at' => now()->addMinutes(59),
            'status' => 'published',
        ], $overrides));
    }
}
