<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * ⛔ ما قبله: ملفّات الحزم الفرعيّة (`arabic-400.css` + `latin-400.css`) بلا unicode-range
 * فيغلب الوجه اللاتينيّ ولا يُطلَب ملفّ العربيّة أصلًا، فكانت العربيّة كلّها بخطّ النظام
 * الاحتياطيّ لا بخطّ الهويّة Alexandria. ملفّ الوزن يعلن كلّ حزمةٍ بنطاقها.
 */
class FontSubsetsTest extends TestCase
{
    public function test_the_font_is_imported_per_weight_with_unicode_ranges_not_per_subset(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        foreach ([400, 500, 600, 700, 800] as $weight) {
            $this->assertStringContainsString("@import '@fontsource/alexandria/{$weight}.css';", $css, "وزن {$weight} غير مستورد بملفّ الوزن");
        }

        $this->assertDoesNotMatchRegularExpression('#@fontsource/alexandria/(arabic|latin)-\d+\.css#', $css,
            'استيراد حزمة فرعيّة بلا unicode-range يُسقط العربيّة إلى خطّ النظام.');

        $index = file_get_contents(base_path('node_modules/@fontsource/alexandria/400.css'));
        $this->assertStringContainsString('unicode-range', $index);
        $this->assertStringContainsString('alexandria-arabic-400-normal', $index);
    }
}
