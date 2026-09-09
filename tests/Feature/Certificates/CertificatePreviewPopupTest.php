<?php

namespace Tests\Feature\Certificates;

use App\Models\CertificateType;
use Tests\Feature\Admin\Content\AdminContentTestCase;

/**
 * ⭐ [2026-09-10] «معاينة قبل الإصدار (بوب-أب، الشهادات تحت بعضها)»
 * (سطر 4660 · 12.5-ج) — كانت صفحةً كاملة لا بوب-أب. صار لها وجهان: صفحةٌ
 * كاملة كمسارٍ مباشر (كما كانت)، وجزءٌ عارٍ (`?fragment=1`) يُحقَن في
 * بوب-أب من شاشة الإصدار — نفس الفيو لا نسخة ثانية تتباعد (2.15).
 */
class CertificatePreviewPopupTest extends AdminContentTestCase
{
    private function type(): CertificateType
    {
        return CertificateType::query()->where('key', 'course')->firstOrFail();
    }

    public function test_the_full_page_still_renders_the_admin_chrome(): void
    {
        $holder = $this->makeUser(['name' => 'معاينة صفحة كاملة']);

        $response = $this->actingAs($this->admin())->post(route('admin.certificates.preview'), [
            'certificate_type_id' => $this->type()->id,
            'language' => 'ar',
            'codes' => $holder->code,
        ])->assertOk();

        $response->assertSee('data-sidebar', false);
        $response->assertSee(setting('admin.certificates.preview.maayna_qbl_alisdar', 'معاينة قبل الإصدار — '), false);
    }

    /** ⭐ الوجه العاري يخلو من هيكل اللوحة كلّه — يصلح ليُحقَن في بوب-أب لا يفتح لوحةً داخل لوحة */
    public function test_the_fragment_face_has_no_admin_chrome(): void
    {
        $holder = $this->makeUser(['name' => 'معاينة جزء عارٍ']);

        $response = $this->actingAs($this->admin())->post(route('admin.certificates.preview', ['fragment' => 1]), [
            'certificate_type_id' => $this->type()->id,
            'language' => 'ar',
            'codes' => $holder->code,
        ])->assertOk();

        $response->assertDontSee('data-sidebar', false);
        $response->assertDontSee(setting('admin.certificates.preview.maayna_qbl_alisdar', 'معاينة قبل الإصدار — '), false);
    }

    /** والمحتوى نفسه يبقى كاملًا في الوجهين: الاسم الحقيقيّ وفورم الإصدار الحقيقيّ */
    public function test_the_fragment_still_carries_the_real_preview_and_issue_form(): void
    {
        $holder = $this->makeUser(['name' => 'محتوى الجزء العاري']);

        $response = $this->actingAs($this->admin())->post(route('admin.certificates.preview', ['fragment' => 1]), [
            'certificate_type_id' => $this->type()->id,
            'language' => 'ar',
            'codes' => $holder->code,
        ])->assertOk();

        $response->assertSee($holder->name);
        $response->assertSee(route('admin.certificates.issue'), false);
        // اللوحة الحقيقيّة مرسومة — نفس الوحدات التي يرسم بها الخادم (data-canvas)، لا أسماء وأكواد فقط
        $response->assertSee('data-canvas', false);
    }

    /** زرّ فتح البوب-أب وحاوية جسمه موجودان فعليًّا في شاشة الإصدار */
    public function test_the_issue_screen_wires_the_preview_popup(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.certificates.index', ['tab' => 'issue']))->assertOk();

        $response->assertSee('data-issue-preview-open', false);
        $response->assertSee('id="issue-preview-modal"', false);
    }
}
