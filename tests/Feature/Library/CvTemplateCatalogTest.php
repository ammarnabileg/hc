<?php

namespace Tests\Feature\Library;

use App\Models\Cv;
use App\Models\CvTemplate;
use App\Services\Library\CvBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View as ViewFactory;

/**
 * ⭐ كتالوج قوالب الـCV = التصاميم الموجودة فعلًا، لا أسماء فوقها (9 · 24.5).
 *
 * كان الكتالوج يبذر خمسة أسماء وتحتها تصميمان فقط: «تنفيذيّ» و«مبدع» يشيران
 * إلى `modern` و«أكاديميّ» يشير إلى `classic`. فمن يدفع في «مبدع» — وكان
 * **أغلى بتذكرة** — يستلم مخرَج «مودرن» الأرخص نفسه حرفًا بحرف: وعدٌ مدفوع
 * بلا منتجٍ خلفه. وهذه الاختبارات تقفل الباب على عودته:
 *
 *   • عدد القوالب = عدد ملفّات التصميم الحقيقيّة، لا أكثر.
 *   • لا قالبان يتقاسمان `view_path` واحدًا — وهو جوهر العيب.
 *   • لا قالب يسقط إلى `classic` عبر تراجع `CvBuilder::sheet()`.
 *   • والمخرَجان مختلفان فعلًا عند العرض، لا بالاسم وحده.
 */
class CvTemplateCatalogTest extends LibraryTestCase
{
    /** التصاميم الموجودة فعلًا على القرص — مصدر الحقيقة الذي يُقاس عليه الكتالوج */
    private function designsOnDisk(): array
    {
        return collect(glob(resource_path('views/cv/templates/*.blade.php')) ?: [])
            ->map(fn (string $path) => str_replace('.blade.php', '', basename($path)))
            ->sort()
            ->values()
            ->all();
    }

    public function test_catalog_offers_exactly_the_two_templates_that_have_a_real_design(): void
    {
        $this->assertSame(['classic', 'modern'], $this->designsOnDisk());

        $templates = app(CvBuilder::class)->templates();

        $this->assertCount(2, $templates);
        $this->assertSame(['كلاسيك', 'مودرن'], $templates->pluck('name')->all());
        $this->assertSame(2, CvTemplate::count(), 'لا قوالب موقوفة مخبّأة خارج الكتالوج كذلك');
    }

    /** الأسماء الثلاثة بلا تصميم لم تعد تُبذَر — لا نشطةً ولا موقوفة */
    public function test_the_three_designless_names_are_gone_from_the_catalog(): void
    {
        foreach (['تنفيذيّ', 'أكاديميّ', 'مبدع'] as $removed) {
            $this->assertNull(
                CvTemplate::where('name', $removed)->first(),
                "القالب «{$removed}» بلا تصميمٍ خاصّ به — لا يجوز بذره",
            );
        }
    }

    /**
     * ⭐ جوهر العيب: اسمان مختلفان فوق تصميمٍ واحد يجعلان الأغلى نسخةً من
     * الأرخص. فكلّ قالبٍ في الكتالوج يملك `view_path` لا يشاركه فيه غيره.
     */
    public function test_no_two_templates_share_the_same_design(): void
    {
        $views = app(CvBuilder::class)->templates()->pluck('view_path');

        $this->assertSame(
            $views->count(),
            $views->unique()->count(),
            'قالبان يتقاسمان تصميمًا واحدًا ⇐ الأغلى يعيد بيع الأرخص',
        );
    }

    /**
     * ولا قالبٌ يسقط إلى `classic` عبر تراجع `CvBuilder::sheet()` — فالتراجع
     * شبكة أمانٍ لبيانات تالفة، لا وسيلةٌ لبيع قالبٍ بلا ملفّ عرض.
     */
    public function test_every_template_resolves_to_its_own_view_without_the_classic_fallback(): void
    {
        $builder = app(CvBuilder::class);
        $user = $this->trainee('UCVCAT01');

        foreach ($builder->templates() as $template) {
            $expected = 'cv.templates.'.$template->view_path;

            $this->assertTrue(
                ViewFactory::exists($expected),
                "القالب «{$template->name}» يشير إلى ملفّ عرضٍ غير موجود: {$expected}",
            );

            $this->assertSame(
                $expected,
                $builder->sheet($user, [], $template)['view'],
                "القالب «{$template->name}» تراجع إلى قالبٍ آخر بدل تصميمه",
            );
        }
    }

    /** والفرق حقيقيّ في المخرَج نفسه، لا في الاسم وحده */
    public function test_the_two_templates_render_visibly_different_output(): void
    {
        $user = $this->trainee('UCVCAT02');
        $this->giveTickets($user, 20);

        $rendered = [];

        foreach (CvTemplate::orderBy('sort_order')->get() as $template) {
            $this->actingAs($user)->postJson(route('cv.template', $template))->assertOk();

            $rendered[$template->name] = $this->actingAs($user)
                ->get(route('cv.preview'))
                ->assertOk()
                ->getContent();
        }

        $this->assertCount(2, array_unique($rendered), 'القالبان يخرجان بنفس الصفحة بايتًا ببايت');
    }

    /** وبقالبين اثنين يبقى باب الدخول المجّانيّ واحدًا والمدفوع مسعّرًا (21.2-ج · 24.5) */
    public function test_two_templates_still_mean_one_free_gateway_and_one_priced_option(): void
    {
        $builder = app(CvBuilder::class);

        $this->assertSame(1, CvTemplate::where('is_active', true)->where('is_free', true)->count());
        $this->assertSame('كلاسيك', $builder->freeTemplate()?->name);

        $paid = CvTemplate::where('is_active', true)->where('is_free', false)->get();

        $this->assertCount(1, $paid);
        $this->assertGreaterThan(0, $paid->first()->priceTickets(), 'القالب المدفوع بلا سعرٍ فعليّ');
    }

    /**
     * ⭐ الترحيلة لا تبتر: سيرةٌ اختارت اسمًا محذوفًا تنتقل إلى القالب الذي
     * كانت **تُعرَض به أصلًا**، ومن دفع فيه يملك بديله بعدها — فلا يضيع ما
     * دُفع ولا يُفرَّغ عمود القالب بصمت.
     */
    public function test_the_migration_remaps_legacy_rows_instead_of_orphaning_them(): void
    {
        $modern = CvTemplate::where('name', 'مودرن')->firstOrFail();
        $classic = CvTemplate::where('name', 'كلاسيك')->firstOrFail();
        $user = $this->trainee('UCVCAT03');

        // نُعيد بناء الحالة القديمة: ثلاثة أسماء بلا تصميمٍ خاصّ بها
        $legacy = [];

        foreach ([['تنفيذيّ', 'modern'], ['أكاديميّ', 'classic'], ['مبدع', 'modern']] as $i => [$name, $view]) {
            $legacy[$name] = CvTemplate::create([
                'name' => $name,
                'view_path' => $view,
                'is_free' => false,
                'is_active' => true,
                'sort_order' => 10 + $i,
            ])->id;
        }

        $cv = Cv::create([
            'user_id' => $user->id,
            'cv_template_id' => $legacy['مبدع'],
            'data' => ['purchased_templates' => [$legacy['مبدع'], $legacy['أكاديميّ']]],
        ]);

        $this->runTrimMigration();

        $cv->refresh();

        // السيرة انتقلت إلى التصميم الذي كانت تُعرَض به بالفعل
        $this->assertSame($modern->id, (int) $cv->cv_template_id);

        // وحقّ الشراء بقي: «مبدع» ⟵ «مودرن» و«أكاديميّ» ⟵ «كلاسيك»
        $owned = array_map('intval', $cv->data['purchased_templates']);

        sort($owned);
        $this->assertSame(collect([$classic->id, $modern->id])->sort()->values()->all(), $owned);
        $this->assertTrue(app(CvBuilder::class)->owns($modern, $cv->data));

        // والصفوف بلا تصميم اختفت، فلا مرجعٌ معلّق
        foreach (array_keys($legacy) as $name) {
            $this->assertNull(CvTemplate::where('name', $name)->first());
        }

        $this->assertSame(
            0,
            Cv::whereNotNull('cv_template_id')
                ->whereNotIn('cv_template_id', CvTemplate::pluck('id'))
                ->count(),
            'سيرةٌ تشير إلى قالبٍ غير موجود',
        );
    }

    /** لا شيء ينكسر حين لا توجد صفوفٌ قديمة أصلًا — الترحيلة قابلة للتكرار */
    public function test_the_migration_is_a_safe_no_op_on_an_already_trimmed_catalog(): void
    {
        $before = CvTemplate::orderBy('id')->pluck('name')->all();

        $this->runTrimMigration();

        $this->assertSame($before, CvTemplate::orderBy('id')->pluck('name')->all());
        $this->assertSame(0, DB::table('cvs')->whereNull('data')->count());
    }

    private function runTrimMigration(): void
    {
        (require database_path('migrations/2026_09_11_120000_trim_cv_templates_to_real_designs.php'))->up();
    }
}
