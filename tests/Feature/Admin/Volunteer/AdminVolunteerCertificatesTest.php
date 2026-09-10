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

    /** ⭐ 24.2: بحثٌ بلا نتائج في سجلّ الشهادات يقول كده صراحةً بدل «لا شهادات صادرة بعد». */
    public function test_a_search_with_no_matches_shows_a_filtered_empty_message(): void
    {
        $membership = $this->membership(days: 90);
        CertificateEligibility::issueForMembership($membership);

        $admin = $this->grant($this->makeUser(), 'volunteer_certificates.view');

        $response = $this->actingAs($admin)
            ->get(route('admin.volunteer.certificates', ['q' => 'zzzznotexist']))
            ->assertOk();

        $response->assertSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
        $response->assertDontSee(
            setting('admin.volunteer.certificates.la_shhadat_sadra_bad', 'لا شهادات صادرة بعد.'),
            false,
        );
    }

    /** وسجلّ الشهادات الفارغ فعليًّا (بلا شهاداتٍ ولا فلتر) يفضل يعرض رسالة البداية الأصليّة. */
    public function test_actually_empty_without_filters_keeps_the_original_start_message(): void
    {
        $admin = $this->grant($this->makeUser(), 'volunteer_certificates.view');

        $response = $this->actingAs($admin)
            ->get(route('admin.volunteer.certificates'))
            ->assertOk();

        $response->assertSee(
            setting('admin.volunteer.certificates.la_shhadat_sadra_bad', 'لا شهادات صادرة بعد.'),
            false,
        );
        $response->assertDontSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
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
