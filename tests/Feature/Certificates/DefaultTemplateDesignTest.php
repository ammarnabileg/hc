<?php

namespace Tests\Feature\Certificates;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\CertificateType;
use App\Models\User;
use App\Services\Admin\Content\TemplateDesigner;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Certificates\CertificateRenderer;
use App\Services\Certificates\CertificateSignature;
use App\Support\Access\AccessEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Exams\ExamTestCase;

/**
 * ⭐ 12.5-ب — «**تصميم افتراضيّ جاهز لكلّ نوع شهادة** — يعمل من أوّل يوم بلا
 * تصميم، **والأدمن يعدّله** أو يستبدله بخلفيّته».
 *
 * المرصود قبل الإصلاح: القوالب الثمانية (النسخة العربيّة) بـ`layers = []`،
 * فيفتح الأدمن الراسم على صندوقٍ خالٍ يبدأ منه من الصفر.
 *
 * وكلّ اختبارٍ هنا يقفل معه بابًا أخطر: **تجميد نسخة التصميم** (12.5-ج)
 * و**سلامة التوقيع** (12.5-هـ) — فتعديل القالب يجب ألّا يغيّر شكل ورقةٍ صدرت
 * ولا يجعلها «مزوَّرة».
 */
class DefaultTemplateDesignTest extends ExamTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CertificateType::query()->update(['is_active' => true]);
    }

    // ------------------------------------------- 1) لكلّ نوعٍ تصميمٌ لا فراغ

    /** ⭐ لا قالبَ واحدًا يفتح فارغًا — «الحالات: فارغة (يظهر التصميم الافتراضيّ الجاهز فورًا)» (24.1) */
    public function test_every_type_opens_on_a_real_design_not_an_empty_box(): void
    {
        $designer = app(TemplateDesigner::class);
        $types = CertificateType::query()->orderBy('id')->get();

        $this->assertCount(8, $types, 'الأنواع الثمانية المعتمَدة');

        foreach ($types as $type) {
            foreach ($designer->templatesFor($type) as $language => $template) {
                $layers = $designer->fromStorage($template->layers);

                $this->assertNotEmpty(
                    $layers,
                    "قالب {$type->key} ({$language}) بلا طبقة واحدة — صندوق فارغ لا تصميم.",
                );

                // وليس مجرّد طبقاتٍ فارغة: فيها نصٌّ ظاهر وحقلٌ مربوط وQR
                $texts = array_filter($layers, fn ($l) => ($l['type'] ?? '') === 'text' && trim((string) ($l['text'] ?? '')) !== '');
                $fields = array_filter($layers, fn ($l) => ($l['field'] ?? null) !== null);
                $qr = array_filter($layers, fn ($l) => ($l['type'] ?? '') === 'qr');

                $this->assertNotEmpty($texts, "قالب {$type->key} ({$language}) بلا نصٍّ ثابت.");
                $this->assertNotEmpty($fields, "قالب {$type->key} ({$language}) بلا حقلٍ مربوط.");
                $this->assertNotEmpty($qr, "قالب {$type->key} ({$language}) بلا QR.");
            }
        }
    }

    /** ⭐ «لكلّ **نوع**» لا تصميمًا واحدًا للثمانية: شهادة التقدير لا تقول «قد أتمّ بنجاح» */
    public function test_the_default_design_differs_from_one_type_to_another(): void
    {
        $designer = app(TemplateDesigner::class);
        $headings = [];

        foreach (CertificateType::query()->orderBy('id')->get() as $type) {
            $layers = $designer->defaultLayers('ar', $type);
            $headings[$type->key] = collect($layers)->firstWhere('id', 'heading')['text'] ?? '';
        }

        $this->assertCount(8, $headings);
        $this->assertCount(
            8,
            array_unique($headings),
            'ثمانية أنواع بعنوانٍ واحد ليست «تصميمًا افتراضيًّا لكلّ نوع».',
        );
    }

    /** والراسم يفتح فعلًا على هذا التصميم — HTTP لا نظريّة */
    public function test_the_designer_screen_shows_the_type_design(): void
    {
        $type = CertificateType::query()->where('key', 'volunteer_appreciation')->firstOrFail();
        $heading = app(TemplateDesigner::class)->defaultDesignRecipe($type, 'ar')['heading'];

        $this->assertNotSame('', $heading);

        $this->actingAs($this->certificatesAdmin())
            ->get(route('admin.certificates.designer', ['type' => $type->id, 'lang' => 'ar']))
            ->assertOk()
            ->assertSee($heading, false);
    }

    /** «إعادة القالب للتصميم الافتراضيّ» (24.1) ترجعه إلى تصميم **نوعه** لا إلى الفراغ */
    public function test_reset_restores_the_type_design(): void
    {
        $designer = app(TemplateDesigner::class);
        $type = CertificateType::query()->where('key', 'event')->firstOrFail();
        $template = $designer->templatesFor($type)['ar'];

        $template->forceFill(['layers' => []])->save();

        $designer->reset($template->fresh());

        $layers = $designer->fromStorage($template->fresh()->layers);
        $this->assertNotEmpty($layers);
        $this->assertSame(
            $designer->defaultDesignRecipe($type, 'ar')['heading'],
            collect($layers)->firstWhere('id', 'heading')['text'] ?? '',
        );
    }

    /** والقالب الفارغ الموروث يُملأ عند أوّل فتحٍ — لا يبقى فراغًا لأنّ صفَّه موجود */
    public function test_a_blank_inherited_template_is_filled_not_left_empty(): void
    {
        $designer = app(TemplateDesigner::class);
        $type = CertificateType::query()->where('key', 'course')->firstOrFail();

        $template = $designer->templatesFor($type)['ar'];
        $template->forceFill(['layers' => []])->save();

        $this->assertTrue($designer->isBlank($template->fresh()));

        $refreshed = $designer->templatesFor($type->fresh())['ar'];

        $this->assertFalse($designer->isBlank($refreshed));
        $this->assertNotEmpty($designer->fromStorage($refreshed->layers));
    }

    // ------------------------------------------- 2) التجميد (12.5-ج)

    /**
     * ⭐ **«لو اتغيّر القالب لاحقًا تفضل القديمة بشكلها»** (12.5-ج) — بمقارنة
     * بايت-ببايت لصورة الشهادة قبل تعديل القالب وبعده، والكاش مُبطَل في المرّتين
     * كي يكون كلّ رسمٍ رسمًا حقيقيًّا لا ملفًّا محفوظًا.
     */
    public function test_editing_a_template_does_not_change_a_certificate_already_issued(): void
    {
        $renderer = app(CertificateRenderer::class);
        $certificate = app(CertificateIssuer::class)->issue($this->trainee('هالة منصور'), 'course');

        $renderer->forget($certificate);
        $before = hash('sha256', $renderer->png($certificate));
        $frozen = $certificate->fresh()->template_snapshot;

        // الأدمن يقلب القالب رأسًا على عقب
        $designer = app(TemplateDesigner::class);
        foreach (CertificateTemplate::query()->get() as $template) {
            $layers = $designer->fromStorage($template->layers);

            foreach ($layers as $i => $layer) {
                $layers[$i]['x'] = 0.05;
                $layers[$i]['y'] = 0.05;
                $layers[$i]['color'] = '#ff00ff';
                $layers[$i]['rotate'] = 90;

                if (($layer['type'] ?? 'text') === 'text' && ($layer['text'] ?? '') !== '') {
                    $layers[$i]['text'] = 'تصميم مختلف تمامًا';
                }
            }

            $template->update([
                'layers' => $designer->forStorage($designer->sanitizeLayers($layers)),
                'width_px' => 900,
                'height_px' => 1600,
                'version' => (int) $template->version + 1,
            ]);
        }

        $certificate = $certificate->fresh();
        $renderer->forget($certificate);

        $this->assertSame($frozen, $certificate->template_snapshot, 'اللقطة المجمَّدة اتغيّرت — التجميد اتكسر.');
        $this->assertSame($before, hash('sha256', $renderer->png($certificate)), 'شكل شهادةٍ صدرت اتغيّر بتعديل القالب.');
    }

    /**
     * ⭐ **والتوقيع لا يُبرَم**: بصمة `template_snapshot` داخلةٌ في حمولة التوقيع،
     * فلمسُ اللقطة يجعل شهادةً ساريةً تُوسَم «التوقيع لا يطابق». نُثبت هنا أنّ
     * تعديل القوالب **لا** يمسّها — وأنّ الصفحة العامّة ما زالت تعلنها سارية.
     */
    public function test_existing_certificates_stay_signature_matching_after_templates_change(): void
    {
        $issuer = app(CertificateIssuer::class);
        $signature = app(CertificateSignature::class);

        foreach (['course', 'path', 'event', 'qualifying'] as $key) {
            $issuer->issue($this->trainee('حائز '.$key), $key);
        }

        $issued = Certificate::query()->count();
        $this->assertSame(4, $issued);
        $this->assertSame($issued, $this->matching($signature));

        $designer = app(TemplateDesigner::class);
        foreach (CertificateTemplate::query()->get() as $template) {
            $template->update([
                'layers' => $designer->forStorage($designer->defaultLayers($template->language)),
                'version' => (int) $template->version + 5,
            ]);
        }

        $this->assertSame($issued, $this->matching($signature), 'شهادة سارية اتوسمت «التوقيع لا يطابق» بعد تعديل القوالب.');

        $certificate = Certificate::query()->first();
        $this->get(route('verify.certificate', ['code' => $certificate->code]))
            ->assertOk()
            ->assertSee(setting('certificates.verify.signature_ok'), false)
            ->assertDontSee(setting('certificates.verify.unverified_badge'), false);

        /*
         | ⭐ **شاهدُ عدلٍ على الاختبار نفسه:** «ما زالت مطابقة» لا تعني شيئًا ما لم
         | يكن **عدم المطابقة ممكنًا**. فنرتكب هنا عمدًا الغلطة التي كان يمكن أن
         | ترتكبها هجرةٌ ساذجة — تحديث `template_snapshot` بالقالب الجديد «كي تظهر
         | الشهادات القديمة بالتصميم الجديد» — ونتأكّد أنّها **تُسقِط التوقيع**.
         | فلو مرّت هذه الخطوة صامتةً لكان الحارس كلّه وهمًا.
         */
        $victim = Certificate::query()->orderByDesc('id')->first();
        $live = CertificateTemplate::query()
            ->where('certificate_type_id', $victim->certificate_type_id)
            ->where('language', $victim->language)
            ->first();

        $victim->forceFill(['template_snapshot' => array_merge(
            (array) $victim->template_snapshot,
            ['layers' => $live->layers, 'template_version' => $live->version],
        )])->saveQuietly();

        $this->assertSame(
            CertificateSignature::MISMATCH,
            $signature->verdict($victim->fresh()->load('certificate_type')),
            'لمسُ اللقطة المجمَّدة لم يكسر التوقيع — فالتوقيع لا يغطّي ما يُعرَض، والحارس وهميّ.',
        );

        $this->get(route('verify.certificate', ['code' => $victim->code]))
            ->assertOk()
            ->assertSee(setting('certificates.verify.unverified_badge'), false);
    }

    // ------------------------------------------------------------ مساعدات

    private function matching(CertificateSignature $signature): int
    {
        return Certificate::query()->with('certificate_type')->get()
            ->filter(fn (Certificate $c) => $signature->verdict($c) === CertificateSignature::MATCH)
            ->count();
    }

    private function certificatesAdmin(): User
    {
        $user = $this->trainee('مسؤول الشهادات');

        foreach (['certificate_templates.view', 'certificate_ledger.view'] as $key) {
            $permission = $this->permission($key, explode('.', $key)[0]);

            $user->permissionOverrides()->attach($permission->id, ['scope' => 'ALL', 'effect' => 'allow']);
        }

        app(AccessEngine::class)->forget($user);

        return $user->fresh();
    }
}
