<?php

namespace Tests\Feature\Store;

use App\Models\Bundle;
use App\Models\User;

/**
 * ⭐ **صورة OG للبندل** — «عمودٌ ومصادقةٌ بلا حقل» في صورته الثالثة.
 *
 * النصّ الحاكم (21.1-أ · مطالب الميديا باير): «**صورة OG تلقائيّة لكلّ نوع
 * رابط**». والعمود `bundles.og_image_path` كان **موجودًا ويُقرَأ** في
 * `StoreController::ogImage()` وينزل وسمًا في `<head>` — **بلا حقلٍ في الفورم
 * يكتبه**. فالسطر الوحيد الذي كان يمرّ هو الارتداد إلى `cover_path`، والأدمن
 * لا يملك صورة مشاركةٍ مختلفةً عن الغلاف مهما أراد.
 *
 * وقاعدة المصدر الواحد (12.4-هـ حرفيًّا): «**إعادة الاستخدام:** أيّ حقل رفع
 * (غلاف/مرفق/صورة سؤال) يفتح **«اختَر من المكتبة»** أو **«ارفع جديد»** — يترفع
 * مرّة ويُعاد استخدامه» — فلا منتقٍ ثانٍ يُبنى للبندل.
 *
 * والطفرات التي يمسكها هذا الملفّ:
 *  - نزعُ الحقل من القالب ⟵ يسقط `the_identity_tab_carries_the_og_field`.
 *  - بناءُ منتقٍ ثانٍ بدل بوب-أب المكتبة ⟵ يسقط الاختبار نفسه.
 *  - نزعُ `og_image_path` من التحقّق أو من الحمولة ⟵ يسقط `a_saved_og_path_reaches_the_head_tag`.
 *  - جعلُ الفارغ سلسلةً فارغة بدل `null` ⟵ يسقط `an_empty_og_path_falls_back_to_the_cover`.
 */
class BundleOgImageTest extends StoreTestCase
{
    /** ⭐ الحقل في `[الهويّة]` — وبوب-أب المكتبة نفسه لا نسخةٌ ثانية منه. */
    public function test_the_identity_tab_carries_the_og_field(): void
    {
        $bundle = $this->bundle([$this->course()]);

        $html = $this->actingAs($this->owner())
            ->get(route('admin.store.bundles.show', $bundle))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="og_image_path"', $html,
            'العمود `og_image_path` يُقرَأ في `<head>` بلا حقلٍ يكتبه — عمودٌ بلا حقل (21.1-أ).');

        $this->assertStringContainsString('data-media-pick="og_image_path"', $html,
            'الحقل لا يفتح بوب-أب المكتبة — و12.4-هـ يوجب أن يفتحه **أيّ** حقل رفع.');

        // المصدر الواحد: البوب-أب المشترك نفسه، لا منتقٍ ثانٍ خاصّ بالمتجر
        $this->assertStringContainsString('data-picker-modal', $html,
            'بوب-أب المكتبة غائبٌ عن الصفحة، فالزرّ يضغط على لا شيء.');
    }

    /** ⭐ المسار المحفوظ **ينزل وسمًا في `<head>`** — لا يُخزَّن ويُنسى. */
    public function test_a_saved_og_path_reaches_the_head_tag(): void
    {
        $bundle = $this->bundle([$this->course()], ['cover_path' => 'media/cover.png']);

        $this->actingAs($this->owner())
            ->put(route('admin.store.bundles.update', $bundle), $this->payload($bundle, [
                'og_image_path' => 'media/og-share.png',
            ]))
            ->assertRedirect();

        $this->assertSame('media/og-share.png', Bundle::find($bundle->id)->og_image_path,
            'الحقل مرّ من الفورم ولم يستقرّ على الصفّ.');

        $html = $this->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('property="og:image"', $html);
        $this->assertStringContainsString('media/og-share.png', $html,
            'الصورة محفوظةٌ على الصفّ ولا تظهر في وسم OG — فالحقل زينة.');
        $this->assertStringNotContainsString('media/cover.png', $this->ogTag($html),
            'وسم OG ما زال يعرض الغلاف رغم صورة المشاركة الصريحة.');
    }

    /** ⚠️ الفارغ = **ارتدادٌ للغلاف** لا سلسلةٌ فارغة تكسر الوسم. */
    public function test_an_empty_og_path_falls_back_to_the_cover(): void
    {
        $bundle = $this->bundle([$this->course()], [
            'cover_path' => 'media/cover.png',
            'og_image_path' => 'media/og-share.png',
        ]);

        $this->actingAs($this->owner())
            ->put(route('admin.store.bundles.update', $bundle), $this->payload($bundle, [
                'og_image_path' => '',
            ]))
            ->assertRedirect();

        $this->assertNull(Bundle::find($bundle->id)->og_image_path,
            'الفارغ خُزِّن سلسلةً فارغة — فالارتداد للغلاف لا يقع أبدًا.');

        $tag = $this->ogTag($this->get(route('store.product', ['type' => 'bundle', 'slug' => $bundle->slug]))
            ->assertOk()->getContent());

        $this->assertStringContainsString('media/cover.png', $tag,
            'الوسم لم يرتدّ للغلاف بعد تفريغ صورة المشاركة.');
    }

    // ------------------------------------------------------------------ أدوات

    /** وسم `og:image` وحده — حتّى لا يخلط التأكيد بينه وبين صورةٍ أخرى في الصفحة. */
    private function ogTag(string $html): string
    {
        preg_match('/<meta property="og:image"[^>]*>/', $html, $m);

        return $m[0] ?? '';
    }

    /**
     * حمولة الفورم كما يرسلها المتصفّح — الحقول المطلوبة وحدها فوقها ما نقيسه.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Bundle $bundle, array $overrides = []): array
    {
        return [
            'name_ar' => $bundle->name_ar,
            'slug' => $bundle->slug,
            'status' => $bundle->status,
            ...$overrides,
        ];
    }

    protected function owner(): User
    {
        $user = User::create([
            'name' => 'مالك المنصّة',
            'email' => 'o'.uniqid().'@test.local',
            'password' => 'secret-password',
            'code' => 'O'.strtoupper(substr(uniqid(), -7)),
            'status' => 'active',
        ]);

        $user->assignRole('platform_owner');

        return $user;
    }
}
