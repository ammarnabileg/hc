<?php

namespace Tests\Feature\Volunteer\Org;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Position;
use App\Models\Track;
use App\Models\User;
use App\Support\Access\AccessEngine;

/**
 * ⭐ **«قسمي» تُحرَس بالعضويّة لا بمشي النطاق** (24.4-7).
 *
 * الالتفاف الذي تقيسه هذه الحالات: بعد أن صار النطاق يُقاس على الطلب (أ-3) صار
 * `org_chart.view@SELF` — سقف الكوردنيتور (12.2.3-ب-16) — يعني «نفسه» حرفيًّا،
 * فانقفلت الشاشة في وجه أصحابها: الكوردنيتور لم يعد يفتح بوب-أب زميله ولا يطلب
 * رقمه (13.4-م-2). ونصّ 24.4-7 يجعلها «تاب لكلّ **عضوٍ في القسم**».
 *
 * ولذلك تُقاس هنا **الحدّتان معًا**: العضو يفتح زميله، ومن قسمٍ آخر يُردّ — فلا
 * يُقرَأ الإصلاح فتحًا عامًّا ولا نقضًا لأ-3.
 */
class DepartmentMembershipGuardTest extends OrgTestCase
{
    /** ⭐ الحدّ الأوّل: عضوٌ في القسم يفتح بوب-أب زميله — وسقفُه `@SELF` (24.4-7). */
    public function test_a_coordinator_opens_the_popup_of_a_colleague_in_his_department(): void
    {
        $viewer = $this->actorWithRole('VOL-C1', 'coordinator');
        $colleague = $this->membershipOf('VOL-C2');

        // مقدّمة الاختبار: نطاقه فعلًا SELF — فالفتح ليس بمشي النطاق
        $this->assertSame('SELF', app(AccessEngine::class)->widestScope($viewer, 'org_chart.view'));
        $this->assertFalse(
            app(AccessEngine::class)->allowsOnRecord($viewer, 'org_chart.view', $colleague),
            'لو صار النطاق يغطّي الزميل فالاختبار ما عاد يقيس حارس العضويّة',
        );

        $this->actingAs($viewer)
            ->getJson(route('volunteer.department.member', $colleague))
            ->assertOk()
            ->assertJsonPath('code', 'VOL-C2');
    }

    /** وفي فرعيٍّ آخر من نفس القسم كذلك — «القسم كاملًا حتى لو كنتُ في فرعيّ». */
    public function test_the_whole_department_is_reachable_across_sub_entities(): void
    {
        $viewer = $this->actorWithRole('VOL-C1', 'coordinator');   // التصميم
        $other = $this->membershipOf('VOL-C3');                    // المونتاج

        $this->actingAs($viewer)
            ->getJson(route('volunteer.department.member', $other))
            ->assertOk()
            ->assertJsonPath('code', 'VOL-C3');
    }

    /** ⭐ الحدّ الثاني: عضوٌ من قسمٍ آخر — ومعه نفس المفتاح — يُردّ بـ403. */
    public function test_a_member_of_another_department_is_refused(): void
    {
        $outsider = $this->outsiderCoordinator();
        $target = $this->membershipOf('VOL-C2');

        $this->actingAs($outsider)
            ->getJson(route('volunteer.department.member', $target))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('volunteer.department.consent', $target))
            ->assertForbidden();

        $this->assertDatabaseMissing('consent_requests', ['requester_id' => $outsider->id]);
    }

    /** «اطلب إظهار الرقم» رجع لصاحبه: العضو يطلب رقم زميله (13.4-م-2). */
    public function test_a_colleague_can_ask_to_reveal_the_number(): void
    {
        $viewer = $this->actorWithRole('VOL-C1', 'coordinator');
        $colleague = $this->membershipOf('VOL-C2');

        $this->actingAs($viewer)
            ->post(route('volunteer.department.consent', $colleague))
            ->assertRedirect();

        $this->assertDatabaseHas('consent_requests', [
            'requester_id' => $viewer->id,
            'owner_id' => $colleague->user_id,
            'field' => 'phone',
            'status' => 'pending',
        ]);
    }

    /** ⚠️ 13.4-م باقٍ: الفتح ليس كشفًا — الرقم مقنّع والواتساب مقفول للزميل. */
    public function test_opening_the_popup_does_not_unmask_contact_data(): void
    {
        $viewer = $this->actorWithRole('VOL-C1', 'coordinator');
        $colleague = $this->membershipOf('VOL-C2');

        $contact = $this->actingAs($viewer)
            ->getJson(route('volunteer.department.member', $colleague))
            ->assertOk()
            ->json('contact');

        $this->assertFalse($contact['visible'], 'الزميل لا يرى الرقم بلا موافقة');
        $this->assertStringContainsString('•', $contact['display']);
        $this->assertNull($contact['whatsapp']);
    }

    /** ⛔ ولا بابَ خلفيًّا: عضويّةُ القسم لا تصنع صلاحيّة لمن لا يملك المفتاح. */
    public function test_membership_alone_does_not_open_the_screen(): void
    {
        $member = User::where('code', 'VOL-C1')->firstOrFail();   // بلا أيّ دور
        $colleague = $this->membershipOf('VOL-C2');

        $this->actingAs($member)
            ->getJson(route('volunteer.department.member', $colleague))
            ->assertForbidden();
    }

    /**
     * ⭐ **وأ-3 لم يُنقَض:** «الهيكل التنظيميّ» شجرةٌ تُمشى بالنطاق — فنفس
     * الكوردنيتور الذي فتح بوب-أب «قسمي» يُردّ عن عقدة زميله في الكانفاس.
     */
    public function test_the_org_chart_node_stays_on_the_scope_engine(): void
    {
        $viewer = $this->actorWithRole('VOL-C1', 'coordinator');

        $this->actingAs($viewer)
            ->getJson(route('volunteer.org.node', $this->membershipOf('VOL-C2')))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson(route('volunteer.org.node', $this->membershipOf('VOL-C1')))
            ->assertOk();
    }

    // ------------------------------------------------------------------ أدوات

    /** كوردنيتور في **قسمٍ آخر** — نفس المفتاح ونفس النطاق، وكيانٌ مختلف */
    private function outsiderCoordinator(): User
    {
        $track = Track::query()->where('key', 'department')->firstOrFail();

        $root = Entity::create([
            'track_id' => $track->id,
            'parent_id' => null,
            'name_ar' => 'قسم الفعاليّات',
            'status' => 'active',
            'opened_at' => now()->subYear(),
        ]);

        $user = User::create([
            'name' => 'غريب عن القسم',
            'email' => 'outsider@demo.local',
            'password' => 'secret-password',
            'code' => 'VOL-OUT',
            'status' => 'active',
        ]);

        $membership = Membership::create([
            'user_id' => $user->id,
            'entity_id' => $root->id,
            'position_id' => Position::query()->where('key', 'coordinator')->value('id'),
            'upline_id' => null,
            'is_primary' => true,
            'started_at' => now()->subMonths(3),
            'status' => 'active',
        ]);

        $user->assignRole('coordinator', $membership);
        app(AccessEngine::class)->forget($user);

        return $user;
    }
}
