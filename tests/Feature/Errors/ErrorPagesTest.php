<?php

namespace Tests\Feature\Errors;

use Database\Seeders\CoreSeeder;
use Database\Seeders\HttpTextDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⛔ ما قبله: 403 وحدها كانت بنظام التصميم، أمّا 404 (وأخواتها 419 · 429 · 500 · 503)
 * فكانت صفحة Laravel الخام: إنجليزيّة، LTR، بلا خطّ المنصّة ولا زرّ عودة.
 * الآن كلّها تمتدّ من قالبٍ مشترك بنصوصٍ من `setting()` وزرٍّ واحد.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(HttpTextDemoSeeder::class);
    }

    public function test_the_not_found_page_is_branded_and_arabic_for_guests(): void
    {
        $response = $this->get('/this-page-does-not-exist');

        $response->assertNotFound()
            ->assertSee('class="card p-8 text-center"', false)
            ->assertSee(setting('errors.404.message'), false)
            ->assertSee(setting('errors.404.action'), false)
            ->assertSee('lang="ar" dir="rtl"', false)
            ->assertDontSee('Not Found', false);
    }

    /**
     * ⛔ GET لرابطٍ لا يقبل إلّا PUT/DELETE (admin/courses/{course}) كان يعرض صفحة
     * مصحّح Laravel الخام، لأنّ Laravel لا يشحن قالبًا لـ405 فيسقط إلى عارض الاستثناءات.
     */
    public function test_a_method_not_allowed_url_renders_the_branded_page(): void
    {
        $response = $this->get('/admin/courses/media');

        $response->assertStatus(405)
            ->assertSee('class="card p-8 text-center"', false)
            ->assertSee(setting('errors.405.message'), false)
            ->assertDontSee('Method Not Allowed', false);
    }

    /**
     * 404 تُرسَم قبل وسطاء الجلسة فلا مستخدمَ مصادَقًا؛ كوكي المظهر غير المشفّر
     * (مرآة تفضيله) يبقي الصفحة داكنةً لمن اختار الداكن بدل وميضٍ فاتح.
     */
    public function test_the_not_found_page_follows_the_theme_cookie(): void
    {
        // بلا كوكي: فاتحة — أوّلًا، لأنّ عميل الاختبار يُبقي الكوكي غير المشفّر لبقيّة الطلبات
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertDontSee('data-theme="dark"', false);

        $this->withUnencryptedCookie('theme', 'dark')
            ->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('data-theme="dark"', false);
    }

    public function test_every_error_view_extends_the_shared_shell(): void
    {
        foreach (['401', '403', '404', '405', '419', '429', '500', '503'] as $code) {
            $view = file_get_contents(resource_path("views/errors/{$code}.blade.php"));

            $this->assertStringContainsString("@extends('errors.minimal'", $view, "errors/{$code} خارج القالب المشترك");
            $this->assertStringContainsString("@section('action_url')", $view, "errors/{$code} بلا زرّ عودة");
        }
    }
}
