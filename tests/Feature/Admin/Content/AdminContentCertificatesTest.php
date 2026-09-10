<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Certificate;
use App\Models\CertificateAccreditation;
use App\Models\CertificateReport;
use App\Models\CertificateTemplate;
use App\Models\CertificateType;
use App\Services\Admin\Content\CertificateBulkIssuer;
use App\Services\Admin\Content\TemplateDesigner;
use App\Services\Certificates\CertificateIssuer;

/**
 * إدارة الشهادات (12.5 · 24.1) — التجميد والترقيم ومنع التكرار قواعد لا تتفاوض.
 */
class AdminContentCertificatesTest extends AdminContentTestCase
{
    private function type(): CertificateType
    {
        return CertificateType::query()->where('key', 'course')->firstOrFail();
    }

    /** ⭐ اعتماد المنصّة **لا يُحذَف** (12.5-أ). */
    public function test_platform_accreditation_cannot_be_deleted(): void
    {
        $platform = CertificateAccreditation::query()->where('is_platform', true)->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('admin.certificates.accreditations.destroy', $platform))
            ->assertRedirect();

        $this->assertNotNull(CertificateAccreditation::query()->find($platform->id));
    }

    /** ⭐ **تجميد نسخة التصميم** عند الإصدار: تعديل القالب بعدها لا يغيّر الشهادة (12.5-ج). */
    public function test_issuing_freezes_the_template_snapshot(): void
    {
        $admin = $this->admin();
        $type = $this->type();
        $holder = $this->makeUser(['name' => 'حائز الشهادة']);

        $this->actingAs($admin)->post(route('admin.certificates.issue'), [
            'certificate_type_id' => $type->id,
            'language' => 'ar',
            'codes' => $holder->code,
        ])->assertRedirect();

        $certificate = Certificate::query()->where('user_id', $holder->id)->firstOrFail();
        $frozen = $certificate->template_snapshot;

        $this->assertNotEmpty($frozen['layers'] ?? [], 'لازم تتجمّد طبقات التصميم مع الشهادة');
        $frozenVersion = $frozen['template_version'];

        // نغيّر القالب بعد الإصدار
        $template = CertificateTemplate::query()
            ->where('certificate_type_id', $type->id)
            ->where('language', 'ar')
            ->firstOrFail();

        $this->actingAs($admin)->postJson(route('admin.certificates.designer.save', $type), [
            'language' => 'ar',
            'layers' => [[
                'id' => 'changed', 'type' => 'text', 'label' => 'عنوان مختلف تمامًا',
                'text' => 'تصميم جديد', 'x' => 0.1, 'y' => 0.1, 'size' => 20, 'align' => 'center', 'z' => 1,
            ]],
        ])->assertOk();

        $certificate->refresh();
        $template->refresh();

        $this->assertSame($frozen, $certificate->template_snapshot, 'الشهادة الصادرة تفضل بشكلها القديم');
        $this->assertGreaterThan($frozenVersion, (int) $template->version, 'نسخة القالب لازم تتقدّم بعد التعديل');
    }

    /** ⭐ **منع تكرار الإصدار** لنفس الشخص ونفس النوع (12.5-ج). */
    public function test_duplicate_issuing_is_prevented(): void
    {
        $admin = $this->admin();
        $type = $this->type();
        $holder = $this->makeUser();

        $this->actingAs($admin)->post(route('admin.certificates.issue'), [
            'certificate_type_id' => $type->id,
            'language' => 'ar',
            'codes' => $holder->code,
        ]);

        $this->actingAs($admin)->post(route('admin.certificates.issue'), [
            'certificate_type_id' => $type->id,
            'language' => 'ar',
            'codes' => $holder->code,
        ]);

        $this->assertSame(
            1,
            Certificate::query()->where('user_id', $holder->id)->where('certificate_type_id', $type->id)->count(),
            'ممنوع يتصدر لنفس الشخص شهادتان من نفس النوع',
        );

        // والتحقّق قبل الإصدار يحذّر صراحةً
        $rows = app(CertificateBulkIssuer::class)->verify([$holder->code], $type);
        $this->assertSame('warn', $rows->first()['state']);
    }

    /** ⭐ الترقيم **بلا فجوات**: تسلسل متّصل داخل البادئة والسنة (12.5-ب). */
    public function test_numbering_has_no_gaps(): void
    {
        $admin = $this->admin();
        $type = $this->type();

        $codes = collect(range(1, 4))->map(fn () => $this->makeUser()->code);

        $this->actingAs($admin)->post(route('admin.certificates.issue'), [
            'certificate_type_id' => $type->id,
            'language' => 'ar',
            'codes' => $codes->implode(' '),
        ])->assertRedirect();

        $prefix = ($type->numbering_prefix ?: 'HC').'-'.now()->year.'-';

        $sequence = Certificate::query()
            ->where('code', 'like', $prefix.'%')
            ->orderBy('id')
            ->pluck('code')
            ->map(fn (string $code) => (int) substr($code, strlen($prefix)))
            ->values();

        $this->assertSame(4, $sequence->count());
        $this->assertSame(range(1, 4), $sequence->all(), 'التسلسل لازم يبقى متّصلًا بلا فجوات');
    }

    /** الحالات الثلاث: سارية · منتهية · ملغاة — والإلغاء بسبب موثّق (12.5-د). */
    public function test_revoking_requires_a_reason_and_records_it(): void
    {
        $admin = $this->admin();
        $type = $this->type();
        $holder = $this->makeUser();

        $this->actingAs($admin)->post(route('admin.certificates.issue'), [
            'certificate_type_id' => $type->id,
            'language' => 'ar',
            'codes' => $holder->code,
        ]);

        $certificate = Certificate::query()->where('user_id', $holder->id)->firstOrFail();

        // بلا سبب: مرفوض
        $this->actingAs($admin)
            ->post(route('admin.certificates.revoke', $certificate), [])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)->post(route('admin.certificates.revoke', $certificate), [
            'reason' => 'تزوير مثبَت',
            'notify' => 1,
        ])->assertRedirect();

        $certificate->refresh();

        $this->assertSame('revoked', $certificate->status);
        $this->assertSame('تزوير مثبَت', $certificate->revoked_reason);
        $this->assertArrayHasKey('revoked', CertificateBulkIssuer::statuses());
        $this->assertArrayHasKey('expired', CertificateBulkIssuer::statuses());
        $this->assertArrayHasKey('valid', CertificateBulkIssuer::statuses());
    }

    /** ⭐ المصمّم: نسختان مستقلّتان (ع/إ) + ربط لا يخرج عن القائمة البيضاء (12.5-ب). */
    public function test_designer_saves_layers_per_language_and_rejects_unsafe_bindings(): void
    {
        $admin = $this->admin();
        $type = $this->type();

        $this->actingAs($admin)->get(route('admin.certificates.designer', $type))->assertOk();

        $this->actingAs($admin)->postJson(route('admin.certificates.designer.save', $type), [
            'language' => 'en',
            'layers' => [
                [
                    'id' => 'safe', 'type' => 'text', 'label' => 'اسم المتدرّب',
                    'field' => 'holder_name', 'x' => 0.5, 'y' => 0.4, 'size' => 40, 'align' => 'center', 'z' => 1,
                ],
                [
                    // ربط ممنوع: عمود خارج القائمة البيضاء — يُسقَط بلا كسر القالب
                    'id' => 'unsafe', 'type' => 'text', 'label' => 'سرّ',
                    'binding' => ['table' => 'users', 'column' => 'password'],
                    'x' => 0.5, 'y' => 0.9, 'size' => 20, 'align' => 'center', 'z' => 2,
                ],
            ],
        ])->assertOk();

        $designer = app(TemplateDesigner::class);
        $templates = $designer->templatesFor($type->refresh());

        $english = $designer->fromStorage($templates['en']->layers);
        $arabic = $designer->fromStorage($templates['ar']->layers);

        $this->assertCount(2, $english);
        $this->assertNull($english[1]['binding'], 'الربط الممنوع لازم يتشال');
        $this->assertNull($english[1]['field']);
        $this->assertGreaterThan(2, count($arabic), 'النسخة العربيّة مستقلّة ولا تتأثّر بحفظ الإنجليزيّة');
    }

    /** الطبقة المخفيّة لا تُرسَم — ومحتواها يرجع كما هو عند إظهارها (12.5-ب). */
    public function test_hidden_layer_is_not_rendered_but_content_is_kept(): void
    {
        $designer = app(TemplateDesigner::class);

        $layers = $designer->sanitizeLayers([[
            'id' => 'x', 'type' => 'text', 'label' => 'طبقة مخفيّة',
            'text' => 'نصّ محفوظ', 'field' => 'holder_name', 'visible' => false, 'z' => 1,
        ]]);

        $stored = $designer->forStorage($layers);

        $this->assertSame('', $stored[0]['text']);
        $this->assertNull($stored[0]['field']);

        $restored = $designer->fromStorage($stored);

        $this->assertSame('نصّ محفوظ', $restored[0]['text']);
        $this->assertSame('holder_name', $restored[0]['field']);
        $this->assertFalse($restored[0]['visible']);
    }

    /** تبويبات الشهادات الأربعة تفتح كلّها (12.5). */
    public function test_all_four_tabs_render(): void
    {
        $admin = $this->admin();

        foreach (['accreditations', 'types', 'issue', 'ledger'] as $tab) {
            $this->actingAs($admin)
                ->get(route('admin.certificates.index', ['tab' => $tab]))
                ->assertOk();
        }

        $this->actingAs($this->makeUser())
            ->get(route('admin.certificates.index'))
            ->assertForbidden();
    }

    /** ⭐ 24.2: بحثٌ بلا نتائج في سجلّ الصادر يقول كده صراحةً لا «مفيش شهادات صادرة أصلًا». */
    public function test_ledger_search_with_no_matches_shows_a_filtered_empty_message(): void
    {
        app(CertificateIssuer::class)->issue($this->makeUser(['name' => 'حائز الشهادة']), 'course');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.certificates.index', ['tab' => 'ledger', 'q' => 'zzzznotexist']))
            ->assertOk();

        $response->assertSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
        $response->assertDontSee(
            setting('admin.certificates.partials.ledger.mfysh_shhadat_sadra_asla', 'مفيش شهادات صادرة أصلًا.'),
            false,
        );
    }

    /** وسجلّ الصادر الفارغ فعليًّا (بلا شهاداتٍ ولا فلتر) يفضل يعرض رسالة البداية الأصليّة. */
    public function test_ledger_actually_empty_without_filters_keeps_the_original_start_message(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.certificates.index', ['tab' => 'ledger']))
            ->assertOk();

        $response->assertSee(
            setting('admin.certificates.partials.ledger.mfysh_shhadat_sadra_asla', 'مفيش شهادات صادرة أصلًا.'),
            false,
        );
        $response->assertDontSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
    }

    /** ⭐ 24.2: بحثٌ بلا نتائج في البلاغات يقول كده صراحةً لا «مفيش بلاغات — وده خبر كويّس». */
    public function test_reports_search_with_no_matches_shows_a_filtered_empty_message(): void
    {
        $certificate = app(CertificateIssuer::class)->issue($this->makeUser(['name' => 'صاحب الشهادة']), 'course');
        CertificateReport::create([
            'certificate_id' => $certificate->id,
            'code' => $certificate->code,
            'reason' => 'الورقة مريبة.',
            'status' => CertificateReport::NEW,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.certificates.index', ['tab' => 'verification', 'q' => 'zzzznotexist']))
            ->assertOk();

        $response->assertSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
        $response->assertDontSee(
            setting('certificates.reports.empty', 'مفيش بلاغات — وده خبر كويّس.'),
            false,
        );
    }

    /** والبلاغات الفارغة فعليًّا (بلا فلتر) تفضل تعرض الرسالة الإيجابيّة الأصليّة. */
    public function test_reports_actually_empty_without_filters_keeps_the_original_start_message(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.certificates.index', ['tab' => 'verification']))
            ->assertOk();

        $response->assertSee(
            setting('certificates.reports.empty', 'مفيش بلاغات — وده خبر كويّس.'),
            false,
        );
        $response->assertDontSee(
            setting('ux.empty_state.filtered_message', 'مفيش نتائج تطابق البحث/الفلتر الحاليّ — جرّب فلترًا تانيًا.'),
            false,
        );
    }
}
