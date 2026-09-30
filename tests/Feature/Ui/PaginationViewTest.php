<?php

namespace Tests\Feature\Ui;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * ⛔ ما قبله: 38 شاشة ترسم ترقيم الصفحات بقالب Laravel الافتراضيّ: «Showing 1 to 10 of
 * 50 results» بالإنجليزيّة، ومفاتيح `pagination.previous` حرفيّةً، ورماديّات Tailwind
 * غير مُجمَّعة في حزمة المنصّة. الآن قالبٌ واحد بنظام التصميم ونصوصٍ من setting().
 */
class PaginationViewTest extends UiTestCase
{
    public function test_length_aware_pagination_is_arabic_and_on_brand(): void
    {
        $html = (new LengthAwarePaginator(range(1, 10), 57, 10, 3, ['path' => '/list']))->links()->toHtml();

        $this->assertStringContainsString('aria-label="'.setting('ux.pagination.aria').'"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('rel="prev"', $html);
        $this->assertStringContainsString('rel="next"', $html);
        $this->assertStringContainsString(strtr(setting('ux.pagination.summary'), [':from' => 21, ':to' => 30, ':total' => 57]), $html);
        $this->assertStringContainsString('class="pagination-btn is-current"', $html);

        $this->assertStringNotContainsString('Showing', $html);
        $this->assertStringNotContainsString('pagination.previous', $html);
        $this->assertStringNotContainsString('text-gray-', $html);
    }

    public function test_the_first_and_last_pages_disable_their_edge_buttons(): void
    {
        $first = (new LengthAwarePaginator(range(1, 10), 25, 10, 1, ['path' => '/list']))->links()->toHtml();
        $last = (new LengthAwarePaginator(range(1, 5), 25, 10, 3, ['path' => '/list']))->links()->toHtml();

        $this->assertStringNotContainsString('rel="prev"', $first);
        $this->assertStringContainsString('rel="next"', $first);
        $this->assertStringContainsString('rel="prev"', $last);
        $this->assertStringNotContainsString('rel="next"', $last);
    }

    public function test_a_single_page_renders_nothing(): void
    {
        $html = (new LengthAwarePaginator(range(1, 5), 5, 10, 1, ['path' => '/list']))->links()->toHtml();

        $this->assertSame('', trim($html));
    }

    public function test_the_simple_paginator_uses_the_same_view(): void
    {
        $html = (new Paginator(range(1, 11), 10, 2, ['path' => '/list']))->links()->toHtml();

        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('rel="prev"', $html);
        $this->assertStringNotContainsString('pagination.next', $html);
    }
}
