<?php

namespace Tests\Feature\Library;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Referral;
use App\Services\Images\BoardSnapshot;
use App\Services\Referral\ReferralService;

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

    /**
     * ⭐ 20.1: «تابات مقسّمة بعدّادات … كلٌّ برقمه» — دائمًا، حتى لو كان الرقم صفرًا.
     * كان الشرط `empty($tab['count'])` يُخفي عدّاد أيّ تابٍ رصيده صفر (`empty(0) === true`)
     * فتاب «تدريبات» بلا أيّ تدريب مملوك كان يظهر بلا رقمٍ إطلاقًا.
     */
    public function test_tabs_show_their_counter_even_when_it_is_zero(): void
    {
        $user = $this->trainee('UZERO001');
        $product = $this->protectedProduct();
        $this->entitle($user, $product); // يملك منتجًا واحدًا فقط، فبقيّة التابات رصيدها صفر

        $html = $this->actingAs($user)->get(route('library.index'))->assertOk()->getContent();

        // تاب «تدريبات» رصيده صفر — لازم يظهر «(0)» بجانب اسمه داخل تاب المكتبة نفسه
        // (لا رابط السايد بار الذي يحمل نفس التسمية) — نميّزه برابطه `tab=courses`.
        $coursesTabPos = strpos($html, 'tab=courses');
        $this->assertNotFalse($coursesTabPos, 'تاب التدريبات (برابطه tab=courses) لازم يكون موجودًا.');

        $tabAnchor = substr($html, $coursesTabPos, 300);
        $this->assertStringContainsString(setting('library.tab.courses_label', 'تدريبات'), $tabAnchor);
        $this->assertStringContainsString('<span class="opacity-70">(0)</span>', $tabAnchor);
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

    /**
     * ⭐ 20.4: «مشاركة اقتباس/صفحة كصورة (بعلامة مائيّة + رابط ريفيرال)» —
     * كانت غائبة كلّيًّا؛ «أوصِ بهذا» وحده كان منفَّذًا.
     */
    public function test_an_owned_item_can_be_shared_as_an_image_carrying_the_referral_link(): void
    {
        $user = $this->trainee('USHARE01');
        $product = $this->protectedProduct();
        $this->entitle($user, $product);

        // زرّ الاستخراج مقفولٌ بصلاحيّة `image_export.use` (12.2.1) — لا يُمنَح افتراضيًّا لأيّ دور
        $this->grant($user, 'image_export.use');

        $html = $this->actingAs($user)->get(route('library.index'))->assertOk()->getContent();

        // زرّ [استخراج كصورة] موجود لكلّ عنصر
        $this->assertStringContainsString(setting('images.export_panel.text_1', 'استخراج كصورة'), $html);

        // ورابط الدعوة الحقيقيّ محقونٌ في حمولة الفورم الموقَّعة (d=) لا نصًّا زائفًا
        preg_match('/name="d"\s+value="([^"]+)"/', $html, $matches);
        $this->assertNotEmpty($matches, 'الحمولة الموقَّعة (d=) لازم تكون موجودة في الفورم.');

        $snapshot = BoardSnapshot::decode(html_entity_decode($matches[1]));
        $referralLink = app(ReferralService::class)->link($user->fresh());

        $this->assertSame($product->name_ar, $snapshot->title);
        $this->assertSame($referralLink, $snapshot->rows[0][1] ?? null);
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
