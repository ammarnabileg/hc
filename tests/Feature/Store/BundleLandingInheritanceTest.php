<?php

namespace Tests\Feature\Store;

use App\Models\Bundle;
use App\Services\Store\BundleLanding;

/**
 * ⭐ **الوراثة الحيّة لنصوص اللاندنج وسكشناتها** — أمرُ المالك:
 * «خلّي أيّ نصوص وأيّ سكشن في صفحة البندل قابل للتعديل من إعدادات نفس البندل».
 *
 * وكلّ اختبار هنا يقابل **طفرةً تسقطه**:
 *  - القالب يقرأ `setting()` مباشرةً ⟵ يسقط `the_landing_template_never_reads_a_setting_directly`.
 *  - نسخُ الافتراضيّ إلى صفّ البندل عند الحفظ ⟵ يسقط `inheritance_is_live_not_a_frozen_snapshot`.
 *  - جعلُ مبدّل السكشن ثنائيًّا ⟵ يسقط `a_section_has_three_states_not_two`.
 *  - ↺ يكتب الافتراضيّ بدل أن يمسح ⟵ يسقط `reverting_a_field_empties_the_row`.
 */
class BundleLandingInheritanceTest extends StoreTestCase
{
    // ============================================================ الحارس البنيويّ

    /**
     * ⭐ **الحارس**: لا `setting()` في قالب اللاندنج إطلاقًا.
     *
     * ولماذا حارسٌ نصّيّ لا اختبارٌ سلوكيّ؟ لأنّ سطرًا واحدًا يقرأ الإعداد مباشرةً
     * يجعل **ذلك النصّ وحده** غير قابل للتعديل من إعدادات البندل — ولا يسقط أيّ
     * اختبارٍ سلوكيّ، لأنّ الصفحة تظلّ تعرض شيئًا صحيحًا. فالعطب صامتٌ بطبعه،
     * ولا يمسكه إلّا مسحُ القالب.
     */
    public function test_the_landing_template_never_reads_a_setting_directly(): void
    {
        $template = (string) file_get_contents(resource_path('views/store/bundle.blade.php'));

        // التعليقات لا تُحسَب — الشرح ليس قراءةً
        $code = preg_replace('/\{\{--.*?--\}\}/su', '', $template);

        $this->assertDoesNotMatchRegularExpression('/(?<![\w>])setting\s*\(/u', (string) $code,
            'قالب اللاندنج يقرأ `setting()` مباشرةً — فالنصّ ده مش قابل للتعديل من إعدادات البندل. '
            .'كلّ نصّ لازم يمرّ بـ`BundleLanding::text()` عبر `$landing[\'texts\']`.');
    }

    /** وكلّ نصٍّ في الجرد له لافتةٌ يحرّرها الأدمن — فلا حقلَ بلا اسم في الفورم */
    public function test_every_landing_text_key_has_a_form_label(): void
    {
        $labels = (array) setting('store.admin.bundles.text_labels', []);

        foreach (array_keys(BundleLanding::TEXTS) as $key) {
            $this->assertArrayHasKey($key, $labels, "النصّ «{$key}» بلا لافتة في فورم البندل — حقلٌ بلا اسم.");
        }

        $titles = (array) setting('store.admin.bundles.section_titles', []);

        foreach (array_keys(BundleLanding::SECTIONS) as $section) {
            $this->assertArrayHasKey($section, $titles, "السكشن «{$section}» بلا مبدّلٍ مسمًّى في الفورم.");
        }
    }

    // ============================================================ الوراثة الحيّة

    /**
     * ⭐ **الوراثة حيّة لا مجمَّدة**: تغييرُ النصّ **العامّ** يصل البندل الموروث
     * فورًا، ولا يمسّ البندل المخصَّص. وهذا هو الدليل على أنّ الافتراضيّ لم يُنسَخ
     * إلى صفّ البندل — ولو نُسِخ لصار الإعداد العامّ بلا أثر (نقضُ 2.13).
     */
    public function test_inheritance_is_live_not_a_frozen_snapshot(): void
    {
        $inherited = $this->bundle([$this->course(), $this->product()], ['slug' => 'inherited-pack']);
        $custom = $this->bundle([$this->course(['slug' => 'c2', 'name_ar' => 'تدريب تاني'])], [
            'slug' => 'custom-pack',
            'name_ar' => 'باقة مخصّصة',
            'landing_texts' => ['hero.badge' => 'شارة خاصّة بالباقة دي'],
        ]);

        $landing = app(BundleLanding::class);

        $this->assertSame('باقة متكاملة', $landing->text($inherited, 'hero.badge'));
        $this->assertSame('شارة خاصّة بالباقة دي', $landing->text($custom, 'hero.badge'));

        // المالك يغيّر النصّ **العامّ**
        $this->setting('store.bundle.hero_badge', 'عرض الموسم');

        $this->assertSame('عرض الموسم', $landing->text($inherited->fresh(), 'hero.badge'),
            'البندل الموروث ماتحرّكش مع النصّ العامّ — الافتراضيّ اتنسخ في صفّه فتجمّد.');
        $this->assertSame('شارة خاصّة بالباقة دي', $landing->text($custom->fresh(), 'hero.badge'),
            'البندل المخصَّص اتغيّر مع العامّ — الـoverride مش شغّال.');
    }

    /** والاختلاف يظهر على الصفحة نفسها لا في الخدمة وحدها */
    public function test_two_bundles_render_different_texts_side_by_side(): void
    {
        $inherited = $this->bundle([$this->product()], ['slug' => 'a-pack', 'name_ar' => 'باقة موروثة']);
        $custom = $this->bundle([$this->product(['slug' => 'p2', 'name_ar' => 'منتج تاني'])], [
            'slug' => 'b-pack',
            'name_ar' => 'باقة مخصّصة',
            'landing_texts' => [
                'hero.headline' => 'اتوظّف في 30 يوم',
                'hero.badge' => 'خطّة التوظيف',
            ],
        ]);

        $this->get(route('store.product', ['type' => 'bundle', 'slug' => $inherited->slug]))
            ->assertOk()
            ->assertSee('باقة موروثة')
            ->assertSee('باقة متكاملة')
            ->assertDontSee('اتوظّف في 30 يوم');

        $this->get(route('store.product', ['type' => 'bundle', 'slug' => $custom->slug]))
            ->assertOk()
            ->assertSee('اتوظّف في 30 يوم')
            ->assertSee('خطّة التوظيف')
            ->assertDontSee('باقة متكاملة');
    }

    /** والعنوان الفارغ يرتدّ لاسم الباقة — فلا صفحة بعنوانٍ فارغ */
    public function test_an_empty_headline_falls_back_to_the_bundle_name(): void
    {
        $bundle = $this->bundle([$this->product()], ['name_ar' => 'باقة بلا عنوان مخصّص']);

        $this->assertSame('باقة بلا عنوان مخصّص', app(BundleLanding::class)->text($bundle, 'hero.headline'));
    }

    // ============================================================ ↺ رجّع للموروث

    /**
     * ⭐ **↺ يمسح ولا يكتب**: بعد الإرسال بحقلٍ فارغ يعود المفتاح **غائبًا من
     * القاعدة** (لا مملوءًا بالافتراضيّ)، والصفحة تعرض العامّ.
     */
    public function test_reverting_a_field_empties_the_row(): void
    {
        $bundle = $this->bundle([$this->product()], ['landing_texts' => ['hero.badge' => 'شارة قديمة']]);

        $this->actingAs($this->owner())
            ->put(route('admin.store.bundles.update', $bundle), $this->formPayload($bundle, [
                'landing_texts' => ['hero.badge' => ''],
            ]))
            ->assertRedirect();

        $stored = $bundle->fresh()->landing_texts ?? [];

        $this->assertArrayNotHasKey('hero.badge', $stored,
            'الحقل الفارغ اتخزن — يبقى البندل اتجمّد على قيمة اليوم بدل ما يورث.');
        $this->assertSame('باقة متكاملة', app(BundleLanding::class)->text($bundle->fresh(), 'hero.badge'));
    }

    // ============================================================ السكشنات الثلاثيّة

    /**
     * ⭐ **ثلاث حالات لا اثنتان**: «مخفيّ» ≠ «موروث». والتوجّل الثنائيّ يخلطهما
     * فيتجمّد البندل على قيمة اليوم ويُبطِل الإعداد العامّ صامتًا.
     */
    public function test_a_section_has_three_states_not_two(): void
    {
        $landing = app(BundleLanding::class);

        $inherit = $this->bundle([$this->product()], ['slug' => 's-inherit']);
        $hidden = $this->bundle([$this->product(['slug' => 'p-h'])], ['slug' => 's-hide', 'landing_sections' => ['faq' => 'hide']]);
        $shown = $this->bundle([$this->product(['slug' => 'p-s'])], ['slug' => 's-show', 'landing_sections' => ['faq' => 'show']]);

        $this->assertTrue($landing->sectionVisible($inherit, 'faq'));
        $this->assertFalse($landing->sectionVisible($hidden, 'faq'));
        $this->assertTrue($landing->sectionVisible($shown, 'faq'));

        // المالك يطفئ البلوك **عامًّا**: الموروث يتبعه، والمُظهَر صراحةً يبقى
        $this->setting('store.bundle.blocks.faq_enabled', '0', 'bool');

        $this->assertFalse($landing->sectionVisible($inherit->fresh(), 'faq'),
            'الموروث ماتبعش الإعداد العامّ — يبقى الحالة اتجمّدت.');
        $this->assertTrue($landing->sectionVisible($shown->fresh(), 'faq'),
            '«ظاهر» صراحةً اتبع العامّ — يبقى الحالات اتحوّلت لتوجّلٍ ثنائيّ.');
        $this->assertFalse($landing->sectionVisible($hidden->fresh(), 'faq'));
    }

    /** وإخفاء سكشنٍ لبندلٍ واحد لا يمسّ غيره — على الصفحة نفسها */
    public function test_hiding_a_section_for_one_bundle_leaves_the_others_alone(): void
    {
        $hidden = $this->bundle([$this->product()], [
            'slug' => 'hidden-faq',
            'landing_sections' => ['faq' => 'hide'],
        ]);
        $other = $this->bundle([$this->product(['slug' => 'p-o'])], ['slug' => 'visible-faq']);

        $faqTitle = (string) setting('store.bundle.faq_title');

        $this->get(route('store.product', ['type' => 'bundle', 'slug' => $hidden->slug]))
            ->assertOk()
            ->assertDontSee($faqTitle);

        $this->get(route('store.product', ['type' => 'bundle', 'slug' => $other->slug]))
            ->assertOk()
            ->assertSee($faqTitle);
    }

    // ============================================================ مساعدات

    /** حمولة الفورم كاملةً — فالحفظ الجزئيّ يمسح ما لم يُرسَل */
    protected function formPayload(Bundle $bundle, array $overrides = []): array
    {
        return array_merge([
            'name_ar' => $bundle->name_ar,
            'slug' => $bundle->slug,
            'status' => $bundle->status,
            'price_coins' => (float) $bundle->price_coins,
        ], $overrides);
    }

    protected function owner(): \App\Models\User
    {
        $owner = \App\Models\User::create([
            'name' => 'مالك المنصّة',
            'email' => 'owner'.uniqid().'@test.local',
            'password' => 'secret-password',
            'code' => 'O'.strtoupper(substr(uniqid(), -7)),
            'status' => 'active',
        ]);

        $owner->assignRole('platform_owner');

        return $owner->fresh();
    }
}
