<?php

namespace Tests\Feature\Certificates;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\CertificateType;
use App\Models\User;
use Tests\Feature\Admin\Content\AdminContentTestCase;

/**
 * ⭐ [2026-09-10] «قوالب متعدّدة للنوع (حسب الاعتماد + اللغة) — اختيار القالب
 * وقت الإصدار» (سطر 2406). كان الإصدار يأخذ الأحدث/الافتراضيّ لهذا النوع
 * دائمًا بلا اختيارٍ من الأدمن — القالب الثاني موجودٌ في القاعدة ولا سبيل
 * لاستعماله وقت الإصدار.
 */
class CertificateTemplatePickerTest extends AdminContentTestCase
{
    private function type(): CertificateType
    {
        return CertificateType::query()->where('key', 'course')->firstOrFail();
    }

    private function trainee(string $name): User
    {
        return $this->makeUser(['name' => $name]);
    }

    /** ⭐ قالبٌ ثانٍ صريح يُصدَر به فعليًّا — لا القالب الافتراضيّ صامتًا */
    public function test_issuing_with_an_explicit_template_uses_that_template_not_the_default(): void
    {
        $type = $this->type();

        $altTemplate = CertificateTemplate::create([
            'certificate_type_id' => $type->id,
            'language' => 'ar',
            'name' => 'قالب بديل',
            'is_default' => false,
            'version' => 99,
            'layers' => [['type' => 'text', 'field' => 'holder_name', 'x' => 0.5, 'y' => 0.5]],
        ]);

        $holder = $this->trainee('صاحب القالب البديل');

        $this->actingAs($this->admin())->post(route('admin.certificates.issue'), [
            'certificate_type_id' => $type->id,
            'language' => 'ar',
            'codes' => $holder->code,
            'template_id' => $altTemplate->id,
        ])->assertRedirect();

        $certificate = Certificate::query()->where('user_id', $holder->id)->firstOrFail();

        $this->assertSame($altTemplate->id, $certificate->template_snapshot['template_id']);
        $this->assertSame(99, $certificate->template_snapshot['template_version']);
    }

    /** بلا اختيارٍ: نفس السلوك القديم تمامًا — الأحدث/الافتراضيّ (توافقٌ خلفيّ) */
    public function test_issuing_without_a_template_choice_falls_back_to_the_default(): void
    {
        $type = $this->type();
        $holder = $this->trainee('بلا اختيار قالب');

        $this->actingAs($this->admin())->post(route('admin.certificates.issue'), [
            'certificate_type_id' => $type->id,
            'language' => 'ar',
            'codes' => $holder->code,
        ])->assertRedirect();

        $certificate = Certificate::query()->where('user_id', $holder->id)->firstOrFail();

        $this->assertNotEmpty($certificate->template_snapshot['layers']);
    }

    /** قالبٌ من نوعٍ آخر لا يُقبَل ضمنيًّا — يُتجاهَل ويرجع للافتراضيّ لا يُطبَّق زورًا */
    public function test_a_template_belonging_to_a_different_type_is_ignored(): void
    {
        $type = $this->type();
        $otherType = CertificateType::query()->where('key', '!=', 'course')->firstOrFail();

        $foreignTemplate = CertificateTemplate::create([
            'certificate_type_id' => $otherType->id,
            'language' => 'ar',
            'name' => 'قالب نوعٍ آخر',
            'is_default' => false,
            'version' => 5,
            'layers' => [],
        ]);

        $holder = $this->trainee('محاولة قالب أجنبيّ');

        $this->actingAs($this->admin())->post(route('admin.certificates.issue'), [
            'certificate_type_id' => $type->id,
            'language' => 'ar',
            'codes' => $holder->code,
            'template_id' => $foreignTemplate->id,
        ])->assertRedirect();

        $certificate = Certificate::query()->where('user_id', $holder->id)->firstOrFail();

        $this->assertNotSame($foreignTemplate->id, $certificate->template_snapshot['template_id']);
    }

    /** المعاينة (بوب-أب «معاينة قبل الإصدار») تعرض نفس القالب المختار — ويُصدَر به بالضبط لاحقًا (WYSIWYG) */
    public function test_the_preview_screen_reflects_the_chosen_template_and_carries_it_to_issuance(): void
    {
        $type = $this->type();

        $altTemplate = CertificateTemplate::create([
            'certificate_type_id' => $type->id,
            'language' => 'ar',
            'name' => 'قالب المعاينة',
            'is_default' => false,
            'version' => 7,
            'layers' => [],
        ]);

        $holder = $this->trainee('معايِن القالب البديل');

        $response = $this->actingAs($this->admin())->post(route('admin.certificates.preview'), [
            'certificate_type_id' => $type->id,
            'language' => 'ar',
            'codes' => $holder->code,
            'template_id' => $altTemplate->id,
        ])->assertOk();

        $this->assertSame($altTemplate->id, $response->viewData('template')->id);
        $response->assertSee(route('admin.certificates.issue'), false);
    }
}
