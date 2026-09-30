<?php

namespace Tests\Feature\Ui;

/**
 * تذييل الصفحة كالمرجع (`.page-footer`): سطر الهويّة ورابط المساعدة أسفل كلّ صفحة
 * في الليَاوتات الثلاثة، ونصوصه من setting() (2.13).
 */
class PageFooterTest extends UiTestCase
{
    public function test_every_authenticated_layout_includes_the_page_footer(): void
    {
        foreach (['app', 'admin', 'volunteer'] as $layout) {
            $source = file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));

            $this->assertStringContainsString("@include('partials.page-footer')", $source, "layouts/{$layout} بلا تذييل");
        }
    }

    public function test_the_dashboard_renders_the_footer_with_the_help_link(): void
    {
        $this->actingAs($this->trainee())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<footer class="page-footer">', false)
            ->assertSee(setting('ux.footer.tagline'), false)
            ->assertSee(setting('ux.footer.help'), false)
            ->assertSee(route('help.index'), false);
    }
}
