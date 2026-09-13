<?php

namespace Tests\Feature\Volunteer\People;

use App\Models\Interview;
use App\Models\RecruitmentCandidate;
use App\Services\Volunteer\People\InterviewScheduler;

/**
 * إلغاء المقابلة (12.2.2 · `interviews.delete` · الحالة = مجدولة):
 * «إلغاء المقابلة بسبب إلزاميّ مع إشعار الطرفين» — صلاحيّة **مستقلّة** عن
 * `interviews.edit` (التي تحرس إعادة الجدولة/تعديل الرابط قبل الديدلاين وحدها).
 */
class InterviewCancellationTest extends PeopleTestCase
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

    /** (أ) صلاحيّة `interviews.edit` وحدها لا تكفي لإلغاء المقابلة. */
    public function test_a_user_with_only_edit_permission_is_forbidden_from_cancelling(): void
    {
        $interview = $this->interview();
        $user = $this->userWith(['interviews.edit']);

        $response = $this->actingAs($user)->post(route('volunteer.interviews.status', $interview), [
            'status' => 'cancelled',
            'reason' => 'اعتذار المرشّح',
        ]);

        $response->assertForbidden();
        $this->assertSame('scheduled', $interview->fresh()->status, 'الحالة لازم تفضل زي ما هي عند الرفض.');
    }

    /** (ب) صاحب `interviews.delete` يقدر يلغي فعلًا. */
    public function test_a_user_with_delete_permission_can_cancel(): void
    {
        $interview = $this->interview();
        $user = $this->userWith(['interviews.edit', 'interviews.delete']);

        $response = $this->actingAs($user)->post(route('volunteer.interviews.status', $interview), [
            'status' => 'cancelled',
            'reason' => 'اعتذار المرشّح',
        ]);

        $response->assertRedirect();
        $this->assertSame('cancelled', $interview->fresh()->status);
        $this->assertSame('اعتذار المرشّح', $interview->fresh()->cancel_reason);
    }

    /** (ج) الإلغاء يبعت إشعارًا لكلٍّ من المُقابِل والمرشّح — لا للمُقابِل وحده. */
    public function test_cancelling_notifies_both_the_interviewer_and_the_candidate(): void
    {
        $interview = $this->interview();
        $interview->load(['interviewer', 'recruitment_candidate.user']);
        $actor = $this->makeUser('منسّق');

        app(InterviewScheduler::class)->setStatus($interview, 'cancelled', 'اعتذار المرشّح', $actor);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $interview->interviewer_id,
            'category' => 'recruitment',
        ]);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $interview->recruitment_candidate->user_id,
            'category' => 'recruitment',
        ]);
    }

    /** (د) حارس السبب الإلزاميّ ما زال يعمل — اختبار حماية من الرجوع للخلف. */
    public function test_cancelling_without_a_written_reason_is_still_rejected(): void
    {
        $interview = $this->interview();
        $actor = $this->makeUser('منسّق');

        $this->expectException(\InvalidArgumentException::class);

        app(InterviewScheduler::class)->setStatus($interview, 'cancelled', '  ', $actor);
    }
}
