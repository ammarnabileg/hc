<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Entity;
use App\Models\Membership;
use App\Models\Position;
use App\Services\Admin\Volunteer\BehaviorLedger;
use App\Services\Admin\Volunteer\CertificateEligibility;
use App\Services\Admin\Volunteer\Integrations;
use App\Services\Admin\Volunteer\SettingsWriter;

/**
 * شهادات التطوّع (13.4-ع): شرطان لا ثالث لهما —
 * المدّة في البوزشن، وRep غير سالب وقت الإصدار. وشهادة واحدة لكلّ (بوزشن × كيان).
 */
class AdminVolunteerCertificatesTest extends AdminVolunteerTestCase
{
    /** المدّة أقلّ من الحدّ الأدنى ⟵ لا شهادة، بسببٍ مشروح. */
    public function test_certificate_respects_the_minimum_days_in_position(): void
    {
        $membership = $this->membership(days: 5);

        $check = CertificateEligibility::check($membership);

        $this->assertFalse($check['eligible']);
        $this->assertStringContainsString((string) CertificateEligibility::minDays(), $check['reason']);
    }

    /** تعديل `volunteer_cert.min_days_in_position` يغيّر الاستحقاق فورًا. */
    public function test_lowering_the_setting_makes_the_same_membership_eligible(): void
    {
        $membership = $this->membership(days: 5);

        $this->assertFalse(CertificateEligibility::check($membership)['eligible']);

        SettingsWriter::put('volunteer_cert.min_days_in_position', 3);

        $this->assertSame(3, CertificateEligibility::minDays());
        $this->assertTrue(CertificateEligibility::check($membership)['eligible']);
    }

    /** ⭐ Rep سالب وقت الإصدار ⟵ لا شهادة مهما طالت المدّة. */
    public function test_certificate_requires_non_negative_rep(): void
    {
        $membership = $this->membership(days: 90);
        $this->assertTrue(CertificateEligibility::check($membership)['eligible']);

        Integrations::post($membership->user, BehaviorLedger::REP, -1.5, 'behavior', 'اختبار', $membership->user, null, 'volunteer');

        $check = CertificateEligibility::check($membership->fresh(['user']));

        $this->assertFalse($check['eligible']);
        $this->assertStringContainsString('سالبة', $check['reason']);
    }

    /** شهادة واحدة لكلّ (بوزشن × كيان) — والثانية تُرفَض بسببٍ واضح. */
    public function test_only_one_certificate_per_position_and_entity(): void
    {
        $membership = $this->membership(days: 90);

        $first = CertificateEligibility::issueForMembership($membership);
        $this->assertTrue($first['issued']);

        $second = CertificateEligibility::issueForMembership($membership->fresh(['user', 'entity', 'position']));

        $this->assertFalse($second['issued']);
        $this->assertStringContainsString('من قبل', $second['reason']);
    }

    /** ⭐ الإلغاء للتزوير المثبَت وحده — ويظهر «ملغاة» في السجلّ. */
    public function test_revocation_marks_the_certificate_as_revoked(): void
    {
        $membership = $this->membership(days: 90);
        $issued = CertificateEligibility::issueForMembership($membership);

        $admin = $this->grant($this->makeUser(), 'volunteer_certificates.view', 'volunteer_certificates.edit');

        $this->actingAs($admin)
            ->post(route('admin.volunteer.certificates.revoke', $issued['certificate']), [
                'reason' => 'تزوير مثبَت بقرار موثّق من مشرف عام التطوّع.',
                'fraud_confirmed' => 1,
            ])
            ->assertRedirect();

        $this->assertSame('revoked', $issued['certificate']->fresh()->status);
    }

    /** الشاشة تفتح لمن يملك الصلاحيّة وتُمنَع عن غيره. */
    public function test_certificates_screen_permission(): void
    {
        $this->actingAs($this->makeUser())
            ->get(route('admin.volunteer.certificates'))
            ->assertForbidden();

        $admin = $this->grant($this->makeUser(), 'volunteer_certificates.view');

        $this->actingAs($admin)
            ->get(route('admin.volunteer.certificates'))
            ->assertOk()
            ->assertSee('شهادات التطوّع');
    }

    private function membership(int $days): Membership
    {
        $entity = Entity::query()->where('name_ar', 'فريق المونتاج')->firstOrFail();
        $position = Position::query()->where('key', 'coordinator')->firstOrFail();

        return Membership::create([
            'user_id' => $this->makeUser('مرشّح شهادة')->id,
            'entity_id' => $entity->id,
            'position_id' => $position->id,
            'started_at' => now()->subDays($days),
            'status' => 'active',
        ])->load(['user', 'entity', 'position']);
    }
}
