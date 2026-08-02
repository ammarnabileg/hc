<?php

namespace Tests\Feature\Library;

use App\Models\Product;
use App\Models\ReadingProgress;
use App\Models\User;
use App\Services\Library\ReadingAnalytics;

/**
 * فهرس القارئ (20.3) · تحليلات المكتبة المجمّعة وشاشة الحماية (20.5).
 */
class ReaderTocTest extends LibraryTestCase
{
    // ------------------------------------------------------------ الفهرس (TOC)

    public function test_table_of_contents_renders_with_its_entries(): void
    {
        $owner = $this->trainee('UTOC0001');
        $product = $this->protectedProduct();
        $product->update(['toc' => json_encode([
            ['page' => 1, 'title' => 'المقدّمة'],
            ['page' => 3, 'title' => 'الفصل الأوّل'],
        ], JSON_UNESCAPED_UNICODE)]);

        $this->entitle($owner, $product);

        $this->actingAs($owner)->get(route('library.read', $product))
            ->assertOk()
            ->assertSee(setting('reader.toc.title'))
            ->assertSee('data-toc-entry="1"', false)
            ->assertSee('data-toc-entry="3"', false)
            ->assertSee('المقدّمة')
            ->assertSee('الفصل الأوّل');
    }

    public function test_entries_beyond_the_page_count_are_dropped_and_order_is_kept(): void
    {
        $owner = $this->trainee('UTOC0002');
        $product = $this->protectedProduct();
        $product->update(['toc' => json_encode([
            ['page' => 3, 'title' => 'المتأخّر'],
            ['page' => 1, 'title' => 'المتقدّم'],
            ['page' => 900, 'title' => 'خارج الملفّ'],
            ['page' => 2, 'title' => ''],
        ], JSON_UNESCAPED_UNICODE)]);

        $this->entitle($owner, $product);

        $response = $this->actingAs($owner)->get(route('library.read', $product))->assertOk();

        $response->assertDontSee('خارج الملفّ');

        $body = $response->getContent();
        $this->assertLessThan(mb_strpos($body, 'المتأخّر'), mb_strpos($body, 'المتقدّم'));
    }

    public function test_reader_without_a_toc_shows_an_encouraging_empty_line(): void
    {
        $owner = $this->trainee('UTOC0003');
        $product = $this->protectedProduct();
        $this->entitle($owner, $product);

        $this->actingAs($owner)->get(route('library.read', $product))
            ->assertOk()
            ->assertSee(setting('reader.toc.empty_text'));
    }

    // ------------------------------------------------------------ العلامة المائيّة لكلّ منتج (20.5)

    public function test_watermark_can_be_switched_off_per_product(): void
    {
        $owner = $this->trainee('UWM00001', 'هدى عادل');
        $product = $this->protectedProduct();
        $this->entitle($owner, $product);

        $this->actingAs($owner)->get(route('library.read', $product))
            ->assertOk()
            ->assertSee('data-watermark', false);

        $product->update(['watermark_enabled' => false]);

        $this->actingAs($owner)->get(route('library.read', $product))
            ->assertOk()
            ->assertDontSee('data-watermark', false);
    }

    // ------------------------------------------------------------ التحليلات المجمّعة (20.5)

    public function test_aggregated_analytics_count_readers_and_average_completion(): void
    {
        $first = $this->protectedProduct();   // أربع صفحات
        $second = $this->protectedProduct();

        $readerOne = $this->trainee('UAN00001');
        $readerTwo = $this->trainee('UAN00002');
        $readerThree = $this->trainee('UAN00003');

        // الملفّ الأوّل: قارئان عند الصفحتين 4 و 2 ⟵ متوسّط الإكمال (100 + 50) / 2 = 75%
        $this->progress($readerOne, $first, 4);
        $this->progress($readerTwo, $first, 2);
        // الملفّ الثاني: قارئ واحد عند الصفحة 1 ⟵ 25%
        $this->progress($readerThree, $second, 1);

        $summary = app(ReadingAnalytics::class)->summary();

        $this->assertSame(3, $summary['readers']);
        $this->assertSame(2, $summary['products']);
        $this->assertSame(50, $summary['average_completion']);

        // الأكثر قراءةً أوّلًا
        $this->assertSame($first->name_ar, $summary['top']->first()['title']);
        $this->assertSame(2, $summary['top']->first()['readers']);
        $this->assertSame(75, $summary['top']->first()['completion']);
    }

    public function test_analytics_are_empty_and_safe_without_any_reading(): void
    {
        $summary = app(ReadingAnalytics::class)->summary();

        $this->assertSame(0, $summary['readers']);
        $this->assertSame(0, $summary['average_completion']);
        $this->assertTrue($summary['top']->isEmpty());
    }

    private function progress(User $user, Product $product, int $page): void
    {
        ReadingProgress::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'last_page' => $page,
        ]);
    }
}
