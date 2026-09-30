<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * المحتوى اللاتينيّ (بريد · رابط · موبايل · أكواد) داخل واجهةٍ RTL يأخذ اتّجاهه من
 * أوّل حرفٍ فيه (unicode-bidi: plaintext) فلا تتقلّب الأرقام والشرطات ولا يتعثّر المؤشّر.
 */
class BidiContentTest extends TestCase
{
    public function test_latin_inputs_and_code_use_plaintext_bidi(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            "/input:is\\(\\[type='email'\\], \\[type='url'\\], \\[type='tel'\\]\\):not\\(\\[dir\\]\\), code:not\\(\\[dir\\]\\), kbd:not\\(\\[dir\\]\\) \\{ unicode-bidi: plaintext; \\}/",
            $css
        );
    }
}
