<?php

namespace Tests\Feature\Admin\System;

use App\Models\LibraryEntitlement;
use App\Models\Product;
use App\Services\Library\PdfPageRenderer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * منتج المكتبة الرقميّة (24.3 حرفيًّا): فورم الإنشاء يرفع الملفّ + الغلاف +
 * الوصف، وجدولها يعرض الغلاف/الحجم/المالكين، وصفّها يستبدل الملفّ ويعاين
 * كقارئ — بعد أن كان الفورم يتجاهل الأعمدة الثلاثة الموجودة أصلًا في الجدول
 * (`cover_path` · `file_path` · `description`) ولا يكتبها أبدًا.
 */
class AdminStoreLibraryUploadTest extends SystemTestCase
{
    private const STORE_ADMIN = [
        'store_products.list', 'store_products.create', 'store_products.edit', 'store_products.archive',
        'product_protection.view', 'product_protection.manage', 'product_categories.create',
    ];

    public function test_admin_can_create_a_library_product_with_file_cover_and_description(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = $this->admin(self::STORE_ADMIN);
        $cover = UploadedFile::fake()->image('cover.jpg');
        $file = UploadedFile::fake()->create('guide.pdf', 40, 'application/pdf');

        $this->actingAs($admin)->post(route('admin.store.products.store'), [
            'name_ar' => 'كتاب اختبار المكتبة',
            'type' => 'protected_pdf',
            'price_currency' => 'coins',
            'price' => 75,
            'status' => 'published',
            'description' => 'وصفٌ تجريبيّ للمنتج.',
            'cover_path' => 'media/cover-demo.jpg',
            'file' => $file,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::query()->where('name_ar', 'كتاب اختبار المكتبة')->firstOrFail();

        // الوصف والغلاف وصلا كما أُرسِلا — والعمودان كانا موجودَين وبلا كاتبٍ (الفجوة المزعومة)
        $this->assertSame('وصفٌ تجريبيّ للمنتج.', $product->description);
        $this->assertSame('media/cover-demo.jpg', $product->cover_path);

        // الملفّ اتخزّن فعلًا على القرص المحلّيّ (بلا رابط علنيّ — 20.5) وامتلأ العمود بمساره
        $this->assertNotNull($product->file_path);
        Storage::disk('local')->assertExists($product->file_path);
        $this->assertNotNull($product->fileSizeBytes());
    }

    public function test_library_table_shows_cover_size_and_owner_count_columns(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $admin = $this->admin(self::STORE_ADMIN);
        $path = Storage::disk('local')->putFileAs('library', UploadedFile::fake()->create('book.pdf', 12), 'book.pdf');

        $product = Product::create([
            'slug' => 'lib-owner-count-test',
            'name_ar' => 'ملفّ عدّاد المالكين',
            'type' => 'digital',
            'is_downloadable' => true,
            'cover_path' => 'media/lib-owner-count.jpg',
            'file_path' => $path,
            'price_coins' => 40,
            'status' => 'published',
        ]);

        $owner = $this->makeUser('مالك الملفّ');

        LibraryEntitlement::create([
            'user_id' => $owner->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'source' => 'purchase',
            'available_from' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.store.index', ['tab' => 'library']));

        $response->assertOk()->assertSee('ملفّ عدّاد المالكين', false);

        // «المالكون» = عدد صفوف library_entitlements الحقيقيّة — نفس ما يفتح في «مكتبتي» (20)
        $this->assertSame(1, $product->fresh()->entitlements()->count());
        $this->assertStringContainsString(
            '<td class="p-3 tabular-nums">1</td>',
            (string) $response->getContent(),
        );
    }

    public function test_replace_file_action_actually_swaps_the_stored_file_and_busts_reader_cache(): void
    {
        Storage::fake('local');

        $admin = $this->admin(self::STORE_ADMIN);
        $oldPath = Storage::disk('local')->putFileAs('library', UploadedFile::fake()->create('old.pdf', 10), 'old.pdf');

        $product = Product::create([
            'slug' => 'lib-replace-file-test',
            'name_ar' => 'ملفّ الاستبدال',
            'type' => 'protected_pdf',
            'is_downloadable' => false,
            'file_path' => $oldPath,
            'teaser_pages' => 2,
            'price_coins' => 50,
            'status' => 'published',
        ]);

        // كاش صفحة قديمة لمحاكاة ما يُنتجه القارئ فعلًا — لازم يُمحى بعد الاستبدال (20.3)
        Storage::disk('local')->put('library/reader-cache/'.$product->id.'/stale.png', 'قديم');

        $newFile = UploadedFile::fake()->create('new.pdf', 20, 'application/pdf');

        $this->actingAs($admin)->post(route('admin.store.products.file.update', $product), [
            'file' => $newFile,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product->refresh();

        // القديم اختفى فعلًا لا مجرّد سطرٍ جديد فوقه
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($product->file_path);
        $this->assertNotSame($oldPath, $product->file_path);

        // كاش القارئ أُبطِل — فالنسخة الجديدة تصل المالكين تلقائيًّا كما ينصّ الهيدر حرفيًّا
        Storage::disk('local')->assertDirectoryEmpty('library/reader-cache/'.$product->id);

        app(PdfPageRenderer::class); // يضمن أنّ الخدمة قابلة للحلّ بعد التعديل
    }

    /** «معاينة القارئ» صفّ الجدول يربط فعلًا بمسار القارئ العامّ (20.3) لا رابطٍ ميّت */
    public function test_reader_preview_row_action_links_to_a_working_teaser_route(): void
    {
        Storage::fake('local');

        $admin = $this->admin(self::STORE_ADMIN);
        $path = Storage::disk('local')->putFileAs('library', UploadedFile::fake()->create('teaser.pdf', 8), 'teaser.pdf');

        $product = Product::create([
            'slug' => 'lib-teaser-route-test',
            'name_ar' => 'ملفّ معاينة القارئ',
            'type' => 'protected_pdf',
            'is_downloadable' => false,
            'file_path' => $path,
            'teaser_pages' => 3,
            'price_coins' => 30,
            'status' => 'published',
        ]);

        $this->actingAs($admin)->get(route('admin.store.index', ['tab' => 'library']))
            ->assertOk()
            ->assertSee(route('library.teaser', $product), false);

        // الرابط نفسه يفتح فعلًا بلا حاجة لملكيّة — عيّنة عامّة (20.3)
        $this->get(route('library.teaser', $product))->assertOk();
    }
}
