<?php

namespace Tests\Feature\Volunteer\People;

use App\Models\AuditLog;
use App\Models\Interview;
use App\Models\InterviewCriterion;
use App\Models\InterviewScorecard;
use App\Models\RecruitmentCandidate;
use App\Services\Volunteer\People\ScorecardEngine;

/**
 * ⭐ إعادة فتح نتيجة مغلقة — `scorecards.restore` (12.2.2 · شرط «الحالة = مغلقة»).
 * كان المفتاح معرَّفًا في المصفوفة بلا مسار ولا شاشة يستعملانه، و`ScorecardEngine::decide()`
 * يقفل `is_draft` نهائيًّا بلا أيّ طريق للرجوع.
 */
class ScorecardRestoreTest extends PeopleTestCase
{
    private function closedCard(): InterviewScorecard
    {
        InterviewCriterion::query()->delete();
        $criterion = InterviewCriterion::create(['label_ar' => 'معيار', 'weight' => 1]);

        $candidate = RecruitmentCandidate::create([
            'user_id' => $this->makeUser('مرشّح المقابلة')->id,
            'stage' => 'interview',
            'applied_at' => now()->subDays(3),
        ]);

        $interview = Interview::create([
            'recruitment_candidate_id' => $candidate->id,
            'interviewer_id' => $this->makeUser('مُقابِل')->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $engine = app(ScorecardEngine::class);
        $actor = $this->makeUser('مُقيِّم');

        $card = $engine->autosave($interview, [
            'skills_notes' => 'نظّم فعاليّة لـ200 شخص ووثّق كلّ خطوة.',
            'personality_notes' => 'هادئ تحت الضغط ويطلب المساعدة بدري.',
            'criteria_scores' => [$criterion->id => 7],
        ], $actor);

        $engine->decide($card, 'passed', null, $actor);

        return $card->fresh();
    }

    public function test_a_user_with_scorecards_restore_can_reopen_a_closed_scorecard(): void
    {
        $card = $this->closedCard();
        $this->assertFalse((bool) $card->is_draft, 'لازم تكون النتيجة مقفولة الأوّل.');

        $actor = $this->userWith(['scorecards.restore']);

        $response = $this->actingAs($actor)
            ->post(route('volunteer.interviews.scorecard.restore', $card->interview_id));

        $response->assertRedirect(route('volunteer.interviews.scorecard', $card->interview_id));

        $card->refresh();
        $this->assertTrue((bool) $card->is_draft, 'إعادة الفتح لازم ترجع البطاقة مسودّة قابلة للتعديل.');

        // القرار المحفوظ نفسه ما بيتلمسش — الفتح للتعديل لا لمحو القرار
        $this->assertSame('passed', $card->decision);

        $log = AuditLog::query()
            ->where('auditable_type', $card->getMorphClass())
            ->where('auditable_id', $card->id)
            ->where('action', 'scorecard.reopened')
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'إعادة الفتح لازم تسجَّل في سجلّ التدقيق.');
        $this->assertSame($actor->id, $log->user_id);
        $this->assertFalse((bool) $log->old_values['is_draft']);
        $this->assertTrue((bool) $log->new_values['is_draft']);
    }

    public function test_a_user_without_scorecards_restore_cannot_reopen_a_closed_scorecard(): void
    {
        $card = $this->closedCard();

        // يملك تحرير الـScorecard العاديّ لكن لا صلاحيّة إعادة الفتح المستقلّة
        $actor = $this->userWith(['scorecards.view', 'scorecards.create', 'scorecards.edit']);

        $this->actingAs($actor)
            ->post(route('volunteer.interviews.scorecard.restore', $card->interview_id))
            ->assertForbidden();

        $this->assertFalse((bool) $card->fresh()->is_draft, 'البطاقة لازم تفضل مقفولة.');
    }

    public function test_a_closed_scorecard_is_read_only_until_reopened(): void
    {
        $card = $this->closedCard();

        $editor = $this->userWith(['scorecards.view', 'scorecards.create', 'scorecards.edit']);

        // محاولة حفظ تلقائيّ على بطاقة مقفولة تُرفَض برسالة، لا تكتب فوق القرار المحفوظ
        $this->actingAs($editor)
            ->postJson(route('volunteer.interviews.scorecard.autosave', $card->interview_id), [
                'skills_notes' => 'تعديل بعد الإغلاق — ما ينفعش.',
            ])
            ->assertStatus(422);

        $this->assertNotSame('تعديل بعد الإغلاق — ما ينفعش.', $card->fresh()->skills_notes);

        // شاشة النتيجة نفسها تعرض الفورم للقراءة فقط، وزرّ إعادة الفتح لمن يملكها
        $restorer = $this->userWith(['scorecards.view', 'scorecards.restore']);

        $this->actingAs($restorer)
            ->get(route('volunteer.interviews.scorecard', $card->interview_id))
            ->assertOk()
            ->assertSee((string) setting('volunteer.people_interviews_scorecard.restore_action', 'إعادة فتح'));
    }
}
