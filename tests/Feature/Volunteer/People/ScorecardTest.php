<?php

namespace Tests\Feature\Volunteer\People;

use App\Models\Interview;
use App\Models\InterviewCriterion;
use App\Models\InterviewScorecard;
use App\Models\RecruitmentCandidate;
use App\Services\Volunteer\People\InterviewScheduler;
use App\Services\Volunteer\People\ScorecardEngine;

/** الـScorecard (13.4-د): درجة موزونة تلقائيّة · حقول ناقصة · معيار مؤرشف بوسمه. */
class ScorecardTest extends PeopleTestCase
{
    private function interview(): Interview
    {
        $candidate = RecruitmentCandidate::create([
            'user_id' => $this->makeUser('مرشّح المقابلة')->id,
            'stage' => 'interview',
            'applied_at' => now()->subDays(3),
        ]);

        return Interview::create([
            'recruitment_candidate_id' => $candidate->id,
            'interviewer_id' => $this->makeUser('مُقابِل')->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);
    }

    public function test_total_is_a_weighted_average_of_active_criteria(): void
    {
        InterviewCriterion::query()->delete();

        $a = InterviewCriterion::create(['label_ar' => 'معيار ثقيل', 'weight' => 3, 'sort_order' => 1]);
        $b = InterviewCriterion::create(['label_ar' => 'معيار خفيف', 'weight' => 1, 'sort_order' => 2]);

        $engine = app(ScorecardEngine::class);

        // (10×3 + 6×1) / 4 = 9
        $this->assertSame(9.0, $engine->total([$a->id => 10, $b->id => 6]));
    }

    public function test_archived_criterion_is_shown_with_its_score_and_tag_but_excluded_from_the_total(): void
    {
        InterviewCriterion::query()->delete();

        $live = InterviewCriterion::create(['label_ar' => 'معيار حيّ', 'weight' => 1, 'sort_order' => 1]);
        $old = InterviewCriterion::create(['label_ar' => 'معيار قديم', 'weight' => 1, 'is_archived' => true, 'sort_order' => 2]);

        $engine = app(ScorecardEngine::class);

        $card = InterviewScorecard::create([
            'interview_id' => $this->interview()->id,
            'criteria_scores' => [$live->id => 8, $old->id => 2],
        ]);

        $rows = collect($engine->rows($card));

        $archivedRow = $rows->firstWhere('archived', true);

        $this->assertNotNull($archivedRow, 'المعيار المؤرشف يُعرَض بدرجته لا يختفي.');
        $this->assertSame(2.0, $archivedRow['score']);

        // ولا يدخل حساب اليوم — فالإجماليّ = درجة الحيّ وحده
        $this->assertSame(8.0, $engine->total([$live->id => 8, $old->id => 2]));
        $this->assertStringContainsString(ScorecardEngine::ARCHIVED_TAG, $engine->summary($card));
    }

    public function test_decision_is_blocked_until_required_fields_are_complete(): void
    {
        InterviewCriterion::query()->delete();
        $criterion = InterviewCriterion::create(['label_ar' => 'معيار', 'weight' => 1]);

        $engine = app(ScorecardEngine::class);
        $interview = $this->interview();
        $actor = $this->makeUser('مُقيِّم');

        $card = $engine->autosave($interview, ['criteria_scores' => [$criterion->id => 7]], $actor);

        $this->assertNotEmpty($engine->missing($card));

        try {
            $engine->decide($card, 'passed', null, $actor);
            $this->fail('القرار ما ينفعش يتحفظ والحقول الإجباريّة ناقصة.');
        } catch (\InvalidArgumentException) {
            // متوقَّع
        }

        $card = $engine->autosave($interview, [
            'skills_notes' => 'نظّم فعاليّة لـ200 شخص ووثّق كلّ خطوة.',
            'personality_notes' => 'هادئ تحت الضغط ويطلب المساعدة بدري.',
            'criteria_scores' => [$criterion->id => 7],
        ], $actor);

        $this->assertSame([], $engine->missing($card));

        $engine->decide($card, 'passed', null, $actor);

        $this->assertFalse((bool) $card->fresh()->is_draft);
        $this->assertSame('final_list', $interview->recruitment_candidate->fresh()->stage);
    }

    public function test_rejection_requires_a_written_reason(): void
    {
        InterviewCriterion::query()->delete();

        $engine = app(ScorecardEngine::class);
        $interview = $this->interview();
        $actor = $this->makeUser('مُقيِّم');

        $card = $engine->autosave($interview, [
            'skills_notes' => 'مهارات محدودة في الأدوات المطلوبة.',
            'personality_notes' => 'محتاج وقت أطول في العمل الجماعيّ.',
        ], $actor);

        $this->expectException(\InvalidArgumentException::class);
        $engine->decide($card, 'rejected', '  ', $actor);
    }

    public function test_scheduler_refuses_a_conflicting_slot_for_the_same_interviewer(): void
    {
        $scheduler = app(InterviewScheduler::class);
        $interviewer = $this->makeUser('مُقابِل مشغول');
        $actor = $this->makeUser('منسّق');

        $first = RecruitmentCandidate::create([
            'user_id' => $this->makeUser('أوّل')->id, 'stage' => 'interview', 'applied_at' => now(),
        ]);
        $second = RecruitmentCandidate::create([
            'user_id' => $this->makeUser('تانٍ')->id, 'stage' => 'interview', 'applied_at' => now(),
        ]);

        $at = now()->addDays(2)->setTime(14, 0);

        $scheduler->schedule($first, $interviewer, $at, null, $actor);

        $this->expectException(\RuntimeException::class);
        $scheduler->schedule($second, $interviewer, $at->copy()->addMinutes(10), null, $actor);
    }
}
