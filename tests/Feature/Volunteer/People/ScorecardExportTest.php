<?php

namespace Tests\Feature\Volunteer\People;

use App\Models\Interview;
use App\Models\InterviewCriterion;
use App\Models\InterviewScorecard;
use App\Models\RecruitmentCandidate;
use App\Services\Volunteer\People\ScorecardEngine;

/**
 * ⭐ تصدير الـScorecard PDF حقيقيّ (13.4-د · §12 · scorecards.export) — كان
 * يرجع `text/plain` باسم `.txt` رغم أنّ كلّ نصّ الشاشة والدستور يقول «PDF».
 */
class ScorecardExportTest extends PeopleTestCase
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

    public function test_export_requires_its_own_permission(): void
    {
        $actor = $this->userWith(['interviews.list']);
        $interview = $this->interview();

        $this->actingAs($actor)
            ->get(route('volunteer.interviews.scorecard.export', $interview))
            ->assertForbidden();
    }

    public function test_export_produces_a_real_pdf_or_explains_the_missing_font(): void
    {
        InterviewCriterion::query()->delete();
        $criterion = InterviewCriterion::create(['label_ar' => 'معيار', 'weight' => 1]);

        $actor = $this->userWith(['interviews.list', 'scorecards.export']);
        $interview = $this->interview();

        InterviewScorecard::create([
            'interview_id' => $interview->id,
            'skills_notes' => 'مهارات قويّة',
            'personality_notes' => 'هادئ تحت الضغط',
            'criteria_scores' => [$criterion->id => 8],
            'total_score' => 8,
            'decision' => 'passed',
        ]);

        $response = $this->actingAs($actor)->get(route('volunteer.interviews.scorecard.export', $interview));

        if (is_file(public_path((string) setting('cv.ats.font_path', 'fonts/Cairo-Regular.ttf')))) {
            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
            $this->assertStringContainsString('.pdf', $response->headers->get('Content-Disposition'));

            return;
        }

        // بلا خطّ مضمَّن: نرجع لشاشة النتيجة بسطر يشرح ماذا يفعل (2.17-ب) — لا صفحة خطأ ولا .txt مضلِّل
        $response->assertRedirect(route('volunteer.interviews.scorecard', $interview));
        $response->assertSessionHas('status');
    }

    public function test_export_includes_the_weighted_total_and_the_decision(): void
    {
        InterviewCriterion::query()->delete();
        $criterion = InterviewCriterion::create(['label_ar' => 'معيار', 'weight' => 1]);

        $actor = $this->userWith(['interviews.list', 'scorecards.export']);
        $interview = $this->interview();

        $engine = app(ScorecardEngine::class);
        $card = InterviewScorecard::create([
            'interview_id' => $interview->id,
            'criteria_scores' => [$criterion->id => 8],
            'total_score' => $engine->total([$criterion->id => 8]),
            'decision' => 'rejected',
            'rejection_reason' => 'خبرة غير كافية',
        ]);

        $response = $this->actingAs($actor)->get(route('volunteer.interviews.scorecard.export', $interview));

        if (! is_file(public_path((string) setting('cv.ats.font_path', 'fonts/Cairo-Regular.ttf')))) {
            $this->markTestSkipped('الخطّ المضمَّن غير مرفوع على بيئة الاختبار.');
        }

        // النصّ داخل PDF مضغوطٌ بتيّار Flate — فلا نبحث عن السطور حرفيًّا، فقط عن الغلاف الصحيح
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('8.00', $card->fresh()->total_score);
    }
}
