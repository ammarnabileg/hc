<?php

namespace Tests\Feature\Lang;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * ⛔ ما قبله: 61 قاعدة تحقّق بلا رسالة عربيّة (المنصّة عربيّة بلا لغة احتياطيّة)، فكان
 * `uuid` أو `current_password` أو `Password::min()->letters()` يطبع المفتاح حرفيًّا
 * («validation.uuid») في الحقل. الآن كلّ قاعدة في Laravel لها رسالة بنبرة المنصّة.
 */
class ValidationMessagesTest extends TestCase
{
    public function test_every_framework_validation_rule_has_an_arabic_message(): void
    {
        $en = include base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $ar = include lang_path('ar/validation.php');

        $missing = [];

        foreach ($en as $rule => $message) {
            if (in_array($rule, ['custom', 'attributes'], true)) {
                continue;
            }

            if (! array_key_exists($rule, $ar)) {
                $missing[] = $rule;

                continue;
            }

            if (is_array($message)) {
                foreach (array_keys($message) as $sub) {
                    if (! isset($ar[$rule][$sub])) {
                        $missing[] = "{$rule}.{$sub}";
                    }
                }
            }
        }

        $this->assertSame([], $missing, 'قواعد بلا رسالة عربيّة: '.implode(', ', $missing));
    }

    public function test_a_rule_that_was_missing_no_longer_leaks_its_key(): void
    {
        $validator = Validator::make(['code' => 'nope'], ['code' => ['uuid']]);

        $message = $validator->errors()->first('code');

        $this->assertStringNotContainsString('validation.', $message);
        $this->assertStringContainsString('UUID', $message);
    }
    /** «title_ar مطلوب.» بحروفٍ لاتينيّة كان يظهر في كلّ شاشةٍ لا تمرّر أسماء حقولها */
    public function test_common_field_names_render_with_arabic_labels(): void
    {
        $validator = Validator::make([], ['title_ar' => ['required'], 'sort_order' => ['required'], 'file' => ['required']]);

        $this->assertSame('العنوان بالعربيّة مطلوب.', $validator->errors()->first('title_ar'));
        $this->assertStringNotContainsString('sort_order', $validator->errors()->first('sort_order'));
        $this->assertStringNotContainsString('file ', $validator->errors()->first('file'));
    }
}
