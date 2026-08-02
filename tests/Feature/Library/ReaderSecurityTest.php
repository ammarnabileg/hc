<?php

namespace Tests\Feature\Library;

use App\Models\ReadingProgress;

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

    public function test_item_outside_its_availability_window_is_not_readable(): void
    {
        $owner = $this->trainee('ULATER01');
        $product = $this->protectedProduct();
        $this->entitle($owner, $product)->update(['available_from' => now()->addWeek()]);

        $this->actingAs($owner)->get(route('library.read', $product))->assertForbidden();
    }
}
