<?php

namespace Tests\Feature\Library;

use App\Models\ReadingProgress;
use Illuminate\Support\Facades\Storage;

/** القارئ المحميّ (20.3): الأمان أوّلًا — الملكيّة والعلامة المائيّة وبلا رابط ملفّ مباشر. */
class ReaderSecurityTest extends LibraryTestCase
{
    public function test_non_owner_cannot_open_the_reader(): void
    {
        $owner = $this->trainee('UOWNER01', 'مالك الكتاب');
        $stranger = $this->trainee('USTRNG01', 'زائر فضوليّ');
        $product = $this->protectedProduct();

        $this->entitle($owner, $product);

        // الرابط لا يفتح لغير المالك — لا الصفحة ولا صور صفحاتها
        $this->actingAs($stranger)->get(route('library.read', $product))->assertForbidden();
        $this->actingAs($stranger)->get(route('library.page', ['product' => $product->id, 'page' => 1]))->assertForbidden();
        $this->actingAs($stranger)->get(route('library.thumb', ['product' => $product->id, 'page' => 1]))->assertForbidden();
        $this->actingAs($stranger)->post(route('library.progress', $product), ['page' => 2])->assertForbidden();
    }

    public function test_guest_cannot_open_the_reader(): void
    {
        $product = $this->protectedProduct();

        $this->get(route('library.read', $product))->assertRedirect();
        $this->get(route('library.page', ['product' => $product->id, 'page' => 1]))->assertRedirect();
    }

    public function test_watermark_layer_carries_the_user_code(): void
    {
        $owner = $this->trainee('UMARK123', 'سلمى محمود');
        $product = $this->protectedProduct();
        $this->entitle($owner, $product);

        $response = $this->actingAs($owner)->get(route('library.read', $product));

        $response->assertOk();
        // العلامة المائيّة الديناميكيّة: الاسم + الكود، كطبقة تُرسَم لحظة العرض
        $response->assertSee('data-watermark', false);
        $response->assertSee('#UMARK123', false);
        $response->assertSee('سلمى محمود', false);
    }

    public function test_page_is_served_as_a_session_bound_image_without_a_file_link(): void
    {
        $owner = $this->trainee('UPAGE001');
        $product = $this->protectedProduct();
        $this->entitle($owner, $product);

        $response = $this->actingAs($owner)->get(route('library.page', ['product' => $product->id, 'page' => 2]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('Content-Disposition', 'inline');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        // لا يظهر مسار الملفّ الأصل في أيّ مكان من صفحة القارئ
        $this->actingAs($owner)->get(route('library.read', $product))
            ->assertDontSee($product->file_path);
    }

    public function test_page_out_of_range_is_rejected(): void
    {
        $owner = $this->trainee('URANGE01');
        $product = $this->protectedProduct();
        $this->entitle($owner, $product);

        $this->actingAs($owner)->get(route('library.page', ['product' => $product->id, 'page' => 99]))->assertNotFound();
    }

    public function test_reading_progress_is_saved_for_continue_reading(): void
    {
        $owner = $this->trainee('UPROG001');
        $product = $this->protectedProduct();
        $this->entitle($owner, $product);

        $this->actingAs($owner)
            ->post(route('library.progress', $product), ['page' => 3])
            ->assertOk()
            ->assertJson(['saved' => true, 'page' => 3]);

        $this->assertSame(3, (int) ReadingProgress::where('user_id', $owner->id)->value('last_page'));

        $this->actingAs($owner)->get(route('library.read', $product))
            ->assertSee(setting('reader.continue_label', 'تابع القراءة'), false);
    }

    public function test_teaser_stops_at_the_configured_page_count(): void
    {
        $product = $this->protectedProduct(teaserPages: 2);

        $this->get(route('library.teaser.page', ['product' => $product->id, 'page' => 2]))->assertOk();
        $this->get(route('library.teaser.page', ['product' => $product->id, 'page' => 3]))->assertForbidden();

        $this->get(route('library.teaser', $product))
            ->assertOk()
            ->assertSee(setting('reader.teaser.buy_label', 'شراء'), false);
    }

    /**
     * (20.3) «تحديث الملفّ يصل للمالك تلقائيًّا»: مفتاح الكاش في PdfPageRenderer
     * يتضمّن بصمة الملفّ (مسار+mtime+حجم) — فاستبدال الأدمن للـPDF يجب أن يُنتج
     * صورةً جديدةً فعلًا لا نسخةً مخزَّنةً قديمة، عبر ReaderController::page.
     */
    public function test_replacing_the_pdf_file_invalidates_the_cache_and_reaches_the_owner_automatically(): void
    {
        Storage::fake('local');

        $owner = $this->trainee('USWAP001');
        $product = $this->protectedProduct();
        $this->entitle($owner, $product);

        $disk = Storage::disk('local');
        $cacheDir = trim((string) setting('reader.cache.directory', 'library/reader-cache'), '/').'/'.$product->id;

        // الطلب الأوّل يملأ كاش الصفحة الأولى بالملفّ الحاليّ
        $first = $this->actingAs($owner)->get(route('library.page', ['product' => $product->id, 'page' => 1]));
        $first->assertOk();

        $filesAfterFirst = $disk->files($cacheDir);
        $this->assertCount(1, $filesAfterFirst, 'ملفّ كاشٍ واحد يُتوقَّع بعد أوّل طلب');
        $originalCacheFile = $filesAfterFirst[0];

        // الأدمن يستبدل ملفّ نفس المنتج بمحتوًى مختلفٍ فعليًّا، مع تحديث mtime صراحةً
        $newContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            ."2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            ."3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>\nendobj\n"
            ."trailer\n<< /Size 4 /Root 1 0 R >>\n%%EOF\n".str_repeat('Z', 512);
        $disk->put($product->file_path, $newContent);
        $absolutePath = $disk->path($product->file_path);
        touch($absolutePath, time() + 5);
        clearstatcache(true, $absolutePath);

        // نفس صفحة القارئ تُطلَب مرّةً أخرى بعد الاستبدال
        $second = $this->actingAs($owner)->get(route('library.page', ['product' => $product->id, 'page' => 1]));
        $second->assertOk();

        // (أ) بايتات الصورة المُرجَعة تختلف عن الاستجابة الأولى
        $this->assertNotSame($first->getContent(), $second->getContent());

        // (ب) ملفّ كاشٍ جديد وُجِد تحت مجلّد كاش القارئ — لا إعادة استعمال المفتاح القديم
        $filesAfterSecond = $disk->files($cacheDir);
        $this->assertCount(2, $filesAfterSecond, 'الكاش القديم يبقى بجانب ملفٍّ جديد بعد الاستبدال');
        $this->assertContains($originalCacheFile, $filesAfterSecond);

        $newCacheFiles = array_values(array_diff($filesAfterSecond, [$originalCacheFile]));
        $this->assertCount(1, $newCacheFiles);
        $this->assertNotSame($originalCacheFile, $newCacheFiles[0]);
    }

    public function test_item_outside_its_availability_window_is_not_readable(): void
    {
        $owner = $this->trainee('ULATER01');
        $product = $this->protectedProduct();
        $this->entitle($owner, $product)->update(['available_from' => now()->addWeek()]);

        $this->actingAs($owner)->get(route('library.read', $product))->assertForbidden();
    }
}
