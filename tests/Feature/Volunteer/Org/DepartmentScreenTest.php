<?php

namespace Tests\Feature\Volunteer\Org;

use App\Models\User;

/**
 * الأعضاء والبوزشنز (24.4-7): القسم كاملًا حتى لو كنتُ في فرعيّ،
 * وبلا أرقام أداء تفصيليّة لغير المخوَّل.
 */
class DepartmentScreenTest extends OrgTestCase
{
    public function test_member_in_a_sub_entity_sees_the_whole_department(): void
    {
        // كوردنيتور في «التصميم» — ويجب أن يرى أعضاء «المونتاج» و«كتابة المحتوى» كذلك
        $user = $this->actorWithRole('VOL-C1', 'coordinator');

        $response = $this->actingAs($user)->get(route('volunteer.department'));

        $response->assertOk();

        $entities = collect($response->viewData('cards'))->pluck('entity')->unique();

        $this->assertTrue($entities->contains('التصميم'));
        $this->assertTrue($entities->contains('المونتاج'));
        $this->assertTrue($entities->contains('كتابة المحتوى'));
    }

    public function test_absence_and_acting_badges_are_exposed_to_the_view(): void
    {
        $user = $this->actorWithRole('VOL-C1', 'coordinator');

        $cards = collect($this->actingAs($user)->get(route('volunteer.department'))->viewData('cards'));

        $absent = $cards->firstWhere('code', 'VOL-TL2');
        $acting = $cards->firstWhere('code', 'VOL-SUP3');

        $this->assertNotNull($absent['absent_until'], 'شارة الغياب لازم تحمل تاريخ العودة');
        $this->assertNotNull($absent['delegate'], 'شارة الغياب لازم تحمل اسم البديل');
        $this->assertTrue($acting['is_acting'], 'وسم «قائم بأعمال» لازم يظهر');
    }

    public function test_club_member_gets_the_gold_frame_flag(): void
    {
        $user = $this->actorWithRole('VOL-C1', 'coordinator');

        $cards = collect($this->actingAs($user)->get(route('volunteer.department'))->viewData('cards'));

        $this->assertTrue($cards->firstWhere('code', 'VOL-SUP1')['is_club']);
        $this->assertFalse($cards->firstWhere('code', 'VOL-C3')['is_club']);
    }

    /**
     * الرقم المقنّع لا يُترَك فراغًا — ومعه زرّ الطلب (13.4-م-2).
     *
     * ⭐ الزميل هنا **دايركتور الكيان** لا كوردنيتور: بعد أن صار النطاق يُقيَّم على
     * الهدف (12.2.1-ب) لم يعد `org_chart.view@SELF` — سقف الكوردنيتور المنصوص في
     * 12.2.3-ب-16 — يفتح **عضويّة غيره**. والمقيس هنا هو التقنيع لا النطاق: زميلٌ
     * يغطّيه نطاقُه (ENTITY) وليس من سلسلة أبلاين الهدف ⟵ الرقم مقنّع.
     */
    public function test_phone_is_masked_for_a_peer_and_visible_for_the_upline(): void
    {
        $peer = $this->actorWithRole('VOL-C1', 'director');
        $upline = $this->actorWithRole('VOL-TL1', 'team_leader');
        $target = $this->membershipOf('VOL-C2');

        $masked = $this->actingAs($peer)
            ->getJson(route('volunteer.department.member', $target))
            ->assertOk()
            ->json('contact');

        $this->assertFalse($masked['visible']);
        $this->assertStringContainsString('•', $masked['display']);
        $this->assertNull($masked['whatsapp']);

        $visible = $this->actingAs($upline)
            ->getJson(route('volunteer.department.member', $target))
            ->assertOk()
            ->json('contact');

        $this->assertTrue($visible['visible'], 'الأبلاين يرى الرقم بلا موافقة — حقّ نظاميّ');
        $this->assertNotNull($visible['whatsapp']);
    }

    public function test_consent_request_is_recorded_and_answer_is_neutral(): void
    {
        // نفس السبب: النطاق يُقيَّم على الهدف، و`@SELF` لا يفتح عضويّة غيره
        $peer = $this->actorWithRole('VOL-C1', 'director');
        $target = $this->membershipOf('VOL-C2');

        $this->actingAs($peer)
            ->post(route('volunteer.department.consent', $target))
            ->assertRedirect();

        $this->assertDatabaseHas('consent_requests', [
            'requester_id' => $peer->id,
            'owner_id' => $target->user_id,
            'field' => 'phone',
            'status' => 'pending',
        ]);
    }

    /** مبدّل العرض: كروت أو جدول — والجدول يتحوّل كروتًا على الموبايل (2.15-ج) */
    public function test_table_view_renders_with_the_agreed_columns(): void
    {
        $user = $this->actorWithRole('VOL-C1', 'coordinator');

        $this->actingAs($user)
            ->get(route('volunteer.department', ['view' => 'table']))
            ->assertOk()
            ->assertSee('البوزشن')
            ->assertSee('الأبلاين')
            ->assertSee('الحالة');
    }

    public function test_screen_is_hidden_from_a_user_without_the_permission(): void
    {
        $stranger = User::create([
            'name' => 'زائر', 'email' => 'stranger@demo.local', 'password' => 'secret-password',
            'code' => 'NOPE-1', 'status' => 'active',
        ]);

        $this->actingAs($stranger)->get(route('volunteer.department'))->assertForbidden();
    }
}
