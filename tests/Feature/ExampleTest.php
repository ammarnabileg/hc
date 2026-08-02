<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /*
     | «/» صارت الصفحة الرئيسيّة العامّة وتقرأ الإعدادات والمحتوى المنشور (21.1)،
     | فتحتاج قاعدة بيانات مهيّأة — ولذلك أُضيف RefreshDatabase هنا.
     */
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
