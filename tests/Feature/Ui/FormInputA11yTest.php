<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * ⭐ خطأ الحقل مرتبطٌ بالحقل لقارئ الشاشة: `aria-invalid` و`aria-describedby`
 * يشيران إلى سطر الخطأ نفسه، فلا يبقى سطرًا أحمر يراه المبصر وحده.
 */
class FormInputA11yTest extends UiTestCase
{
    public function test_a_field_with_an_error_is_linked_to_its_error_line(): void
    {
        // كما يفعل وسيط ShareErrorsFromSession: المكوّنات ترى البيانات المشتركة لا بيانات النداء
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['email' => ['البريد ده مش صحّ']])));

        $html = Blade::render('<x-form.input name="email" label="البريد" />');

        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="email-error"', $html);
        $this->assertStringContainsString('id="email-error" class="field-error', $html);
        $this->assertStringContainsString('البريد ده مش صحّ', $html);
    }

    public function test_a_clean_field_carries_no_error_attributes(): void
    {
        view()->share('errors', new ViewErrorBag);

        $html = Blade::render('<x-form.input name="email" label="البريد" />');

        $this->assertStringNotContainsString('aria-invalid', $html);
        $this->assertStringNotContainsString('aria-describedby', $html);
        $this->assertStringNotContainsString('field-error', $html);
    }
}
