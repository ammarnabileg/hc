<?php

namespace Tests\Feature\Library;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Referral;

/** مكتبتي (20 · 24.5): الرفّ والعدّادات والفلاتر والحالة الفارغة والبوب-أب. */
class LibraryIndexTest extends LibraryTestCase
{
    public function test_empty_library_encourages_instead_of_blaming(): void
    {
        $user = $this->trainee('UEMPTY01');

        $this->actingAs($user)->get(route('library.index'))
            ->assertOk()
            ->assertSee(setting('library.empty.message', 'مكتبتك لسّه فاضية'), false)
            ->assertSee(setting('library.empty.action', 'اكتشف المتجر'), false);
    }

    public function test_shelf_lists_owned_items_with_type_action_and_availability(): void
    {
        $user = $this->trainee('USHELF01');
        $product = $this->protectedProduct();
        $this->entitle($user, $product);

        $response = $this->actingAs($user)->get(route('library.index'));

        $response->assertOk();
        $response->assertSee($product->name_ar, false);
        // زرّ رئيسيّ حسب النوع + حالة الإتاحة
        $response->assertSee(setting('library.action.read_label', 'قراءة'), false);
        $response->assertSee(setting('library.availability.now_label', 'متاح الآن'), false);
        // تابات بعدّاداتها
        $response->assertSee(setting('library.tab.products_label', 'منتجات'), false);
    }

    public function test_search_filters_the_shelf(): void
    {
        $user = $this->trainee('USEARCH1');
        $product = $this->protectedProduct();
        $this->entitle($user, $product);

        $this->actingAs($user)->get(route('library.index', ['q' => 'كتاب']))
            ->assertOk()->assertSee($product->name_ar, false);

        $this->actingAs($user)->get(route('library.index', ['q' => 'لا-يوجد-كده']))
            ->assertOk()->assertSee(setting('library.empty.filtered_message', 'مفيش نتائج للفلتر ده'), false);
    }

    public function test_item_popup_and_recommend_link_belong_to_the_owner_only(): void
    {
        $owner = $this->trainee('UOWNIT01');
        $stranger = $this->trainee('USTRIT01');
        $product = $this->protectedProduct();
        $entitlement = $this->entitle($owner, $product);

        $this->actingAs($owner)->getJson(route('library.item', $entitlement))
            ->assertOk()
            ->assertJsonPath('title', $product->name_ar);

        $this->actingAs($stranger)->getJson(route('library.item', $entitlement))->assertForbidden();
        $this->actingAs($stranger)->postJson(route('library.recommend', $entitlement))->assertForbidden();

        // «أوصِ بهذا» يولّد رابط ريفيرال بعمولة ومعايير UTM موحّدة
        $response = $this->actingAs($owner)->postJson(route('library.recommend', $entitlement))->assertOk();

        $this->assertStringContainsString('ref='.$owner->code, $response->json('url'));
        $this->assertStringContainsString('utm_source=', $response->json('url'));
        $this->assertSame(
            (float) setting('library.recommend.commission_percent', 7),
            (float) Referral::where('referrer_id', $owner->id)->value('commission_percent'),
        );
    }

    /**
     * ⭐ 20.1: «رفّ أنيق يجمّع العناصر حسب النوع، وعرض الشهادات كأوسمة على رفّ»
     * — كان الكلّ يظهر في شبكةٍ مسطّحة واحدة بلا تجميع وبلا فرق شكل للشهادات.
     */
    public function test_the_all_tab_groups_items_into_shelves_by_type_with_certificates_as_badges(): void
    {
        $user = $this->trainee('USHELF02');
        $product = $this->protectedProduct();
        $this->entitle($user, $product);

        $type = CertificateType::create([
            'key' => 'course-'.uniqid(),
            'name_ar' => 'شهادة اختبار الرفّ',
            'name_en' => 'Shelf test certificate',
        ]);
        Certificate::create([
            'code' => 'CERT-'.strtoupper(uniqid()),
            'hash' => str()->random(40),
            'user_id' => $user->id,
            'certificate_type_id' => $type->id,
            'issued_at' => now(),
            'status' => 'valid',
        ]);

        $html = $this->actingAs($user)->get(route('library.index'))->assertOk()->getContent();

        // عنوانا الرفّين ظاهران، والمنتج يسبق الشهادة (نفس ترتيب TABS: منتجات قبل شهادات)
        $this->assertStringContainsString(setting('library.tab.products_label', 'منتجات'), $html);
        $this->assertStringContainsString(setting('library.tab.certificates_label', 'شهادات'), $html);
        // تلافي التقاط تسمية التاب نفسه أعلى الصفحة: نبحث عن رفّ الشهادات بعد اسم المنتج
        $productPos = strpos($html, $product->name_ar);
        $certificateShelfPos = strpos($html, setting('library.tab.certificates_label', 'شهادات'), $productPos);
        $this->assertNotFalse($productPos);
        $this->assertNotFalse($certificateShelfPos);

        // الشهادة صارت وسامًا: جزء `certificate-badge.blade.php` لا كارت `card.blade.php` العاديّ
        $this->assertStringContainsString('شهادة اختبار الرفّ', $html);
        $this->assertSame(1, substr_count($html, 'data-library-item="certificate-badge"'));
    }

    /** والفلترة بتاب «شهادات» وحده تعرض الوسام أيضًا — لا الكارت العاديّ */
    public function test_the_certificates_tab_alone_also_renders_the_badge_style(): void
    {
        $user = $this->trainee('USHELF03');
        $type = CertificateType::create([
            'key' => 'course-'.uniqid(),
            'name_ar' => 'شهادة تاب مستقلّ',
            'name_en' => 'Standalone tab certificate',
        ]);
        Certificate::create([
            'code' => 'CERT-'.strtoupper(uniqid()),
            'hash' => str()->random(40),
            'user_id' => $user->id,
            'certificate_type_id' => $type->id,
            'issued_at' => now(),
            'status' => 'valid',
        ]);

        $html = $this->actingAs($user)->get(route('library.index', ['tab' => 'certificates']))
            ->assertOk()
            ->assertSee('شهادة تاب مستقلّ', false)
            ->getContent();

        $this->assertStringContainsString('data-library-item="certificate-badge"', $html);
    }
}
