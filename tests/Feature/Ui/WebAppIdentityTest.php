<?php

namespace Tests\Feature\Ui;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⛔ ما قبله: favicon.ico ملفٌّ فارغ (0 بايت) فكلّ تبويب بأيقونةٍ عامّة، ولا ملفّ تعريف
 * تطبيق فـ«أضِف إلى الشاشة الرئيسيّة» بلا اسمٍ ولا أيقونة. الآن: أيقونة الهويّة (النقطة
 * الحمراء على الكريميّ) SVG وPNG، وملفّ تعريف بألوان النظام واسم المنصّة.
 */
class WebAppIdentityTest extends TestCase
{
    // صفحة الدخول تقرأ جداول (الرسائل الإيجابيّة والإعدادات) فلا نعتمد على حالة اختبارٍ سابق
    use RefreshDatabase;

    public function test_the_icon_files_exist_and_are_not_empty(): void
    {
        foreach (['favicon.ico', 'icons/icon.svg', 'icons/icon-192.png', 'icons/icon-512.png', 'icons/apple-touch-icon.png'] as $file) {
            $this->assertGreaterThan(100, filesize(public_path($file)), "{$file} فارغ أو ناقص");
        }
    }

    public function test_the_manifest_describes_the_platform_in_arabic_with_system_colors(): void
    {
        $this->get('/site.webmanifest')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=86400, public')
            ->assertJsonPath('name', config('app.name'))
            ->assertJsonPath('lang', 'ar')
            ->assertJsonPath('dir', 'rtl')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('icons.1.sizes', '512x512')
            ->assertJsonPath('theme_color', '#fcfbf8');
    }

    public function test_every_layout_links_the_icons_and_the_manifest(): void
    {
        foreach (['layouts/app', 'layouts/admin', 'layouts/volunteer', 'layouts/guest', 'errors/minimal', 'exams/layouts/focus'] as $view) {
            $this->assertStringContainsString("@include('partials.head-icons')", file_get_contents(resource_path("views/{$view}.blade.php")), "{$view} بلا أيقونات");
        }

        $this->get('/login')
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('rel="apple-touch-icon"', false)
            ->assertSee('icons/icon.svg', false);
    }
}
