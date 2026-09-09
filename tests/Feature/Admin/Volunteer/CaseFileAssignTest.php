<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Entity;
use App\Models\FileInviteLink;
use App\Models\Membership;
use App\Models\Position;
use App\Models\Track;

/**
 * «دعوة أعضاء لملفٍّ مفتوحٍ بالفعل» (case_files.assign — الدستور سطر 1517):
 * كانت `FileDrafts::generateInviteLink()` جاهزةً للنداء المباشر بلا أيّ زرٍّ
 * يستدعيها بعد فتح الملفّ — «صلاحيّةٌ بلا شاشة» بالضبط.
 */
class CaseFileAssignTest extends AdminVolunteerTestCase
{
    private function caseFileEntity(string $status = 'active'): Entity
    {
        return Entity::create([
            'track_id' => Track::where('key', 'case_file')->value('id'),
            'name_ar' => 'ملفّ اختباريّ',
            'status' => $status,
            'opened_at' => $status === 'active' ? now()->subDays(5) : null,
        ]);
    }

    private function position(): Position
    {
        return Position::where('key', 'coordinator')->firstOrFail();
    }

    // ------------------------------------------------------------ بابا الحارس: مشرف المسار أو صاحب case_files.assign

    /** ⭐ مشرف عام مسار الملفّات (canCreate القديم) يقدر — الباب الأصليّ لم ينكسر */
    public function test_the_track_supervisor_can_generate_an_invite_link(): void
    {
        $caseFile = $this->caseFileEntity();
        $actor = $this->grant($this->makeUser(), 'org_chart.edit', 'work_packages.create');

        $this->actingAs($actor)->post(route('admin.volunteer.org.case-files.assign', $caseFile), [
            'position_id' => $this->position()->id,
        ])->assertRedirect();

        $this->assertSame(1, FileInviteLink::query()->where('entity_id', $caseFile->id)->count());
    }

    /** ⭐ [الجديد] صاحب `case_files.assign` على هذا الملفّ بعينه يقدر أيضًا — بلا حاجة لإشراف المسار كلّه */
    public function test_a_case_files_assign_holder_without_track_supervision_can_also_assign(): void
    {
        $caseFile = $this->caseFileEntity();
        $actor = $this->grant($this->makeUser(), 'org_chart.edit', 'case_files.assign');

        $this->actingAs($actor)->post(route('admin.volunteer.org.case-files.assign', $caseFile), [
            'position_id' => $this->position()->id,
        ])->assertRedirect();

        $this->assertSame(1, FileInviteLink::query()->where('entity_id', $caseFile->id)->count());
    }

    /** بلا أيّ البابين ⟵ 403 — لا رابط، ولا عضويّة */
    public function test_a_user_with_neither_door_is_forbidden(): void
    {
        $caseFile = $this->caseFileEntity();
        $actor = $this->grant($this->makeUser(), 'org_chart.edit');

        $this->actingAs($actor)->post(route('admin.volunteer.org.case-files.assign', $caseFile), [
            'position_id' => $this->position()->id,
        ])->assertForbidden();

        $this->assertSame(0, FileInviteLink::query()->where('entity_id', $caseFile->id)->count());
    }

    // ------------------------------------------------------------ الإضافة المباشرة بالكود

    public function test_direct_add_by_code_creates_an_active_membership_immediately(): void
    {
        $caseFile = $this->caseFileEntity();
        $actor = $this->grant($this->makeUser(), 'org_chart.edit', 'case_files.assign');
        $invitee = $this->makeUser('مدعوّ مباشرةً');

        $this->actingAs($actor)->post(route('admin.volunteer.org.case-files.assign', $caseFile), [
            'position_id' => $this->position()->id,
            'user_code' => $invitee->code,
        ])->assertRedirect();

        $this->assertDatabaseHas('memberships', [
            'entity_id' => $caseFile->id, 'user_id' => $invitee->id, 'status' => 'active',
        ]);
    }

    public function test_an_unknown_code_reports_the_reason_without_creating_a_membership(): void
    {
        $caseFile = $this->caseFileEntity();
        $actor = $this->grant($this->makeUser(), 'org_chart.edit', 'case_files.assign');

        $this->actingAs($actor)->post(route('admin.volunteer.org.case-files.assign', $caseFile), [
            'position_id' => $this->position()->id,
            'user_code' => 'KOD-MISH-MAWGOOD',
        ])->assertRedirect();

        $this->assertSame(0, Membership::query()->where('entity_id', $caseFile->id)->count());
    }

    public function test_adding_an_existing_member_again_is_rejected(): void
    {
        $caseFile = $this->caseFileEntity();
        $actor = $this->grant($this->makeUser(), 'org_chart.edit', 'case_files.assign');
        $member = $this->makeUser('عضو بالفعل');

        Membership::create([
            'user_id' => $member->id, 'entity_id' => $caseFile->id,
            'position_id' => $this->position()->id, 'is_primary' => false,
            'status' => 'active', 'started_at' => now(),
        ]);

        $this->actingAs($actor)->post(route('admin.volunteer.org.case-files.assign', $caseFile), [
            'position_id' => $this->position()->id,
            'user_code' => $member->code,
        ])->assertSessionHasErrors('user_code');

        $this->assertSame(1, Membership::query()->where('entity_id', $caseFile->id)->where('user_id', $member->id)->count());
    }

    /** ملفٌّ ما زال مسودّةً (لم يُفتَح بعد) — الإضافة المباشرة ممنوعة 404 */
    public function test_a_draft_file_cannot_receive_a_direct_add(): void
    {
        $draft = $this->caseFileEntity('draft');
        $actor = $this->grant($this->makeUser(), 'org_chart.edit', 'case_files.assign');
        $invitee = $this->makeUser();

        $this->actingAs($actor)->post(route('admin.volunteer.org.case-files.assign', $draft), [
            'position_id' => $this->position()->id,
            'user_code' => $invitee->code,
        ])->assertNotFound();
    }

    // ------------------------------------------------------------ الزرّ يُخفى لا يُعطَّل (2.15-أ-7)

    public function test_the_invite_button_is_hidden_without_either_door(): void
    {
        $this->caseFileEntity();
        $actor = $this->grant($this->makeUser(), 'org_chart.view', 'org_chart.edit');

        $this->actingAs($actor)->get(route('admin.volunteer.org'))
            ->assertOk()
            ->assertDontSee(setting('admin.volunteer.org.daawt_ado', 'دعوة عضو'));
    }

    public function test_the_invite_button_shows_for_a_case_files_assign_holder(): void
    {
        $this->caseFileEntity();
        $actor = $this->grant($this->makeUser(), 'org_chart.view', 'org_chart.edit', 'case_files.assign');

        $this->actingAs($actor)->get(route('admin.volunteer.org'))
            ->assertOk()
            ->assertSee(setting('admin.volunteer.org.daawt_ado', 'دعوة عضو'));
    }
}
