<?php

namespace Tests\Feature\Admin\Volunteer;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\CertificateType;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\Position;
use App\Models\User;
use App\Services\Admin\Content\TemplateDesigner;
use App\Services\Admin\Volunteer\CertificateEligibility;

/**
 * إعادة بناء شاشة شهادات التطوّع (13.4-ع · 24.2): تابا القوالب/السجلّ،
 * الإصدار اليدويّ لتقدير استثنائيّة (متكرّرٌ بلا Dedup)، الفلاتر، والتصدير.
 */
class AdminVolunteerCertificatesRebuildTest extends AdminVolunteerTestCase
{
    private function beneficiary(): User
    {
        return $this->makeUser('مستفيد التقدير');
    }

    // ------------------------------------------------------------ تابا الشاشة

    public function test_templates_tab_shows_all_four_fixed_types_with_designer_links(): void
    {
        $admin = $this->grant($this->makeUser(), 'volunteer_certificates.view', 'certificate_templates.view');

        $response = $this->actingAs($admin)
            ->get(route('admin.volunteer.certificates', ['tab' => 'templates']))
            ->assertOk();

        foreach (CertificateEligibility::types() as $label) {
            $response->assertSee($label);
        }
    }

    public function test_ledger_tab_is_the_default_and_shows_the_ledger_toggle(): void
    {
        $admin = $this->grant($this->makeUser(), 'volunteer_certificates.view');

        $this->actingAs($admin)
            ->get(route('admin.volunteer.certificates'))
            ->assertOk()
            ->assertSee('السجلّ الصادر');
    }

    // ------------------------------------------------------------ إصدار يدويّ (تقدير استثنائيّة)

    public function test_manual_appreciation_issuance_creates_a_certificate(): void
    {
        $user = $this->beneficiary();
        $admin = $this->grant($this->makeUser(), 'volunteer_certificates.view', 'volunteer_certificates.create');

        $this->actingAs($admin)
            ->post(route('admin.volunteer.certificates.issue-appreciation'), [
                'code' => $user->code,
                'reason' => 'إنجاز خاصّ في حملة التبرّع بالدم هذا الشهر.',
            ])
            ->assertRedirect();

        $type = CertificateType::query()->where('key', 'volunteer_appreciation')->first();
        $this->assertSame(1, Certificate::query()->where('user_id', $user->id)->where('certificate_type_id', $type->id)->count());
    }

    /**
     * ⭐ متكرّرة بطبيعتها (13.4-ع-4): إصدارٌ ثانٍ لنفس المستخدم — لسببٍ مختلف —
     * ينشئ شهادةً **جديدة** لا يعيد الأولى. هذا الحارس الوحيد الذي كان سيفشل
     * لو بقي Dedup الافتراضيّ فعّالًا على `subject_id IS NULL`.
     */
    public function test_a_second_appreciation_certificate_for_the_same_user_is_not_deduplicated(): void
    {
        $user = $this->beneficiary();
        $admin = $this->grant($this->makeUser(), 'volunteer_certificates.view', 'volunteer_certificates.create');

        $this->actingAs($admin)->post(route('admin.volunteer.certificates.issue-appreciation'), [
            'code' => $user->code,
            'reason' => 'مشرف الشهر — يناير.',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.volunteer.certificates.issue-appreciation'), [
            'code' => $user->code,
            'reason' => 'مشرف الشهر — فبراير.',
        ])->assertRedirect();

        $type = CertificateType::query()->where('key', 'volunteer_appreciation')->first();
        $this->assertSame(2, Certificate::query()->where('user_id', $user->id)->where('certificate_type_id', $type->id)->count());
    }

    public function test_manual_appreciation_issuance_rejects_an_unknown_code(): void
    {
        $admin = $this->grant($this->makeUser(), 'volunteer_certificates.view', 'volunteer_certificates.create');

        $this->actingAs($admin)
            ->post(route('admin.volunteer.certificates.issue-appreciation'), [
                'code' => 'NOPE-NOPE',
                'reason' => 'مبرّرٌ كافٍ لعشرة أحرف على الأقلّ.',
            ])
            ->assertRedirect();

        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_manual_appreciation_issuance_requires_a_reason(): void
    {
        $user = $this->beneficiary();
        $admin = $this->grant($this->makeUser(), 'volunteer_certificates.view', 'volunteer_certificates.create');

        $this->actingAs($admin)
            ->post(route('admin.volunteer.certificates.issue-appreciation'), ['code' => $user->code, 'reason' => 'قصير'])
            ->assertSessionHasErrors('reason');
    }

    // ------------------------------------------------------------ الفلاتر

    public function test_ledger_type_filter_narrows_results(): void
    {
        $admin = $this->grant($this->makeUser(), 'volunteer_certificates.view', 'volunteer_certificates.list', 'volunteer_certificates.create');
        $membership = $this->positionMembership();

        CertificateEligibility::issueForMembership($membership);
        $this->actingAs($admin)->post(route('admin.volunteer.certificates.issue-appreciation'), [
            'code' => $this->beneficiary()->code,
            'reason' => 'إنجاز خاصّ يستحقّ التقدير هذا الشهر.',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.volunteer.certificates', ['tab' => 'ledger', 'view' => 'ledger', 'type' => 'volunteer_appreciation']))
            ->assertOk();

        $this->assertSame(1, $response->viewData('total'));
    }

    public function test_ledger_status_filter_narrows_to_revoked_only(): void
    {
        $admin = $this->grant($this->makeUser(), 'volunteer_certificates.view', 'volunteer_certificates.list', 'volunteer_certificates.create', 'volunteer_certificates.edit');
        $membership = $this->positionMembership();
        $issued = CertificateEligibility::issueForMembership($membership);
        CertificateEligibility::revoke($issued['certificate'], 'تزوير مثبَت بقرار موثّق من مشرف عام التطوّع.');

        $response = $this->actingAs($admin)
            ->get(route('admin.volunteer.certificates', ['tab' => 'ledger', 'view' => 'ledger', 'status' => 'revoked']))
            ->assertOk();

        $this->assertSame(1, $response->viewData('total'));
    }

    // ------------------------------------------------------------ تصدير CSV

    public function test_export_requires_the_list_permission(): void
    {
        $admin = $this->grant($this->makeUser(), 'volunteer_certificates.view');

        $this->actingAs($admin)
            ->get(route('admin.volunteer.certificates.export'))
            ->assertForbidden();

        $exporter = $this->grant($this->makeUser(), 'volunteer_certificates.view', 'volunteer_certificates.list');

        $this->actingAs($exporter)
            ->get(route('admin.volunteer.certificates.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    // ------------------------------------------------------------ تفعيل/إيقاف النوع

    public function test_toggling_a_certificate_type_flips_its_enabled_flag(): void
    {
        $admin = $this->grant($this->makeUser(), 'volunteer_certificates.view', 'volunteer_certificates.edit');

        $this->assertTrue(CertificateEligibility::enabledTypes()['volunteer_appreciation']['enabled']);

        $this->actingAs($admin)
            ->post(route('admin.volunteer.certificates.types.toggle', 'volunteer_appreciation'))
            ->assertRedirect();

        $this->assertFalse(CertificateEligibility::enabledTypes()['volunteer_appreciation']['enabled']);
    }

    public function test_toggling_an_unknown_type_key_is_rejected(): void
    {
        $admin = $this->grant($this->makeUser(), 'volunteer_certificates.view', 'volunteer_certificates.edit');

        $this->actingAs($admin)
            ->post(route('admin.volunteer.certificates.types.toggle', 'not_a_real_type'))
            ->assertNotFound();
    }

    // ------------------------------------------------------------ نسخ القالب بين اللغتين

    public function test_duplicating_a_template_copies_layers_to_the_sibling_language(): void
    {
        $admin = $this->grant($this->makeUser(), 'certificate_templates.view', 'certificate_templates.edit');
        $type = CertificateType::query()->where('key', 'volunteer_appreciation')->firstOrFail();

        // القالبان يُنشآن كسولًا بتصميمهما الافتراضيّ عند أوّل زيارة (12.5-ب)
        app(TemplateDesigner::class)->templatesFor($type);

        $ar = CertificateTemplate::query()->where('certificate_type_id', $type->id)->where('language', 'ar')->first();
        $en = CertificateTemplate::query()->where('certificate_type_id', $type->id)->where('language', 'en')->first();

        $ar->update(['background_path' => 'media/appreciation-ar.png']);

        $this->actingAs($admin)
            ->post(route('admin.certificates.designer.duplicate', $ar))
            ->assertRedirect();

        $this->assertSame('media/appreciation-ar.png', $en->fresh()->background_path);
    }

    // ------------------------------------------------------------ دواخل

    private function positionMembership(): Membership
    {
        $entity = Entity::query()->where('name_ar', 'فريق المونتاج')->firstOrFail();
        $position = Position::query()->where('key', 'coordinator')->firstOrFail();

        return Membership::create([
            'user_id' => $this->makeUser('مرشّح شهادة بوزشن')->id,
            'entity_id' => $entity->id,
            'position_id' => $position->id,
            'started_at' => now()->subDays(90),
            'status' => 'active',
        ])->load(['user', 'entity', 'position']);
    }
}
