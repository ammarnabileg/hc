<?php

namespace Tests\Feature\Volunteer\People;

use App\Models\PlacementRequest;
use App\Models\Position;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use App\Services\Volunteer\People\PlacementService;

/**
 * الأقفال الثلاثة للتسكين (13.4-هـ) — كلّ اختبار يقابل قفلًا منصوصًا في الدستور.
 */
class PlacementLocksTest extends PeopleTestCase
{
    private function candidate(): RecruitmentCandidate
    {
        $user = $this->makeUser('مرشّح القفل');

        return RecruitmentCandidate::create([
            'user_id' => $user->id,
            'stage' => 'final_list',
            'qualifying_score' => 90,
            'applied_at' => now()->subDays(5),
        ]);
    }

    public function test_atomic_lock_blocks_two_simultaneous_placements(): void
    {
        $service = app(PlacementService::class);
        $candidate = $this->candidate();
        $entity = $this->makeEntity('قسم التسكين');
        $position = Position::firstWhere('key', 'coordinator');

        $first = $this->makeUser('مشرف أوّل');
        $second = $this->makeUser('مشرف تانٍ');

        $service->request($candidate, $entity, $position, $first);

        // القفل الذرّيّ: الثاني يُرفَض ولا يُنشئ طلبًا
        $this->expectException(\RuntimeException::class);

        try {
            $service->request($candidate->fresh(), $entity, $position, $second);
        } finally {
            $this->assertSame(1, PlacementRequest::where('recruitment_candidate_id', $candidate->id)->count());
        }
    }

    public function test_only_one_pending_request_per_candidate(): void
    {
        $service = app(PlacementService::class);
        $candidate = $this->candidate();
        $entity = $this->makeEntity('قسم أوّل');
        $other = $this->makeEntity('قسم تانٍ');
        $position = Position::firstWhere('key', 'coordinator');
        $actor = $this->makeUser('مشرف');

        $request = $service->request($candidate, $entity, $position, $actor);

        $this->assertNotNull($candidate->fresh()->pending_placement_request_id);

        // القفل المؤقّت: لا طلب آخر أثناء طلب معلَّق ولو لقسم مختلف
        try {
            $service->request($candidate->fresh(), $other, $position, $actor);
            $this->fail('كان لازم يُرفَض الطلب الثاني ما دام في طلب معلَّق.');
        } catch (\RuntimeException) {
            // متوقَّع
        }

        // وبعد سحب الأوّل يُفتَح الباب من جديد
        $service->withdraw($request, $actor);
        $this->assertNull($candidate->fresh()->pending_placement_request_id);

        $service->request($candidate->fresh(), $other, $position, $actor);
        $this->assertSame(2, PlacementRequest::where('recruitment_candidate_id', $candidate->id)->count());
    }

    public function test_response_deadline_returns_candidate_to_the_list(): void
    {
        $service = app(PlacementService::class);
        $candidate = $this->candidate();
        $entity = $this->makeEntity('قسم المهلة');
        $position = Position::firstWhere('key', 'coordinator');
        $actor = $this->makeUser('مشرف');

        $request = $service->request($candidate, $entity, $position, $actor);

        // المهلة من الإعدادات لا من الكود — والافتراضيّ 48 ساعة
        $this->assertSame(48, $service->responseHours());
        $this->assertTrue($request->respond_due_at->greaterThan(now()->addHours(47)));

        // بعد فوات المهلة: الطلب ينتهي والقفل يُحرَّر فيرجع المرشّح للقائمة
        $this->travel(49)->hours();
        $service->expireOverdue();

        $this->assertSame('expired', $request->fresh()->status);
        $this->assertNull($candidate->fresh()->pending_placement_request_id);

        // ويقبل طلبًا جديدًا بعدها بلا مشكلة
        $service->request($candidate->fresh(), $entity, $position, $actor);
        $this->assertSame(2, PlacementRequest::where('recruitment_candidate_id', $candidate->id)->count());
    }

    public function test_accepting_creates_membership_and_keeps_candidate_visible_but_inactive(): void
    {
        $service = app(PlacementService::class);
        $candidate = $this->candidate();
        $entity = $this->makeEntity('قسم القبول');
        $position = Position::firstWhere('key', 'coordinator');
        $actor = $this->makeUser('مشرف');

        $request = $service->request($candidate, $entity, $position, $actor);

        $service->respond($request, 'accepted', User::find($candidate->user_id));

        $fresh = $candidate->fresh();

        $this->assertSame('placed', $fresh->stage);
        // المُسكَّن لا يختفي — يصير غير مفعَّل ويظلّ في القائمة
        $this->assertFalse((bool) $fresh->is_active_in_list);
        $this->assertDatabaseHas('memberships', [
            'user_id' => $candidate->user_id,
            'entity_id' => $entity->id,
            'status' => 'active',
        ]);
    }

    public function test_full_entity_is_flagged_not_hidden(): void
    {
        $service = app(PlacementService::class);
        $parent = $this->makeEntity('كيان أب');
        $child = $this->makeEntity('قسم ممتلئ', $parent);
        $child->forceFill(['member_cap' => 1])->save();

        $this->makeMembership($this->makeUser('عضو'), $child);

        $rows = collect($service->occupancy($parent->id));
        $row = $rows->firstWhere('entity.id', $child->id);

        $this->assertNotNull($row, 'القسم الممتلئ لازم يفضل ظاهرًا في قائمة الاختيار — مؤشّر لا مانع.');
        $this->assertTrue($row['full']);
        $this->assertSame(100, $row['percent']);
    }
}
