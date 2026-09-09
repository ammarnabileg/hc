<?php

namespace Tests\Feature\Admin\Content;

use App\Models\CertificateType;

/**
 * ⭐ [2026-09-10] «عناصر أمان بصريّة اختياريّة (Guilloché/Microtext)» (سطر
 * 2407 · 4644 · 12.5-ب) — Toggle الفورم بحاجة عمودٍ يحفظه فعليًّا لا حقلًا
 * زخرفيًّا. الرسم نفسه مُختبَرٌ في `tests/Feature/Certificates/
 * CertificateSecurityElementsTest.php`.
 */
class CertificateSecurityElementsFormTest extends AdminContentTestCase
{
    private function type(): CertificateType
    {
        return CertificateType::query()->where('key', 'course')->firstOrFail();
    }

    public function test_the_type_form_persists_the_toggle(): void
    {
        $type = $this->type();

        $this->actingAs($this->admin())->put(route('admin.certificates.types.update', $type), [
            'name_ar' => $type->name_ar,
            'name_en' => $type->name_en,
            'accreditation_id' => $type->accreditation_id,
            'security_elements_enabled' => '1',
        ])->assertRedirect();

        $this->assertTrue((bool) $type->fresh()->security_elements_enabled);
    }

    /** الزرّ نفسه موجودٌ فعلًا في فورم إضافة النوع */
    public function test_the_type_form_shows_the_toggle(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.certificates.index', ['tab' => 'types']))->assertOk();

        $response->assertSee('name="security_elements_enabled"', false);
    }
}
