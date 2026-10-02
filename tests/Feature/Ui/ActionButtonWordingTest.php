<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * كلمة واحدة لفعلٍ واحد على الأزرار المفردة: زرّ الفلتر «طبّق» في كلّ الشاشات،
 * والأزرار المفردة الأخرى بالمصدر كما في مرجع الهويّة (حفظ · حذف · نسخ · إرسال ·
 * تعديل · عرض المزيد) لا بصيغة الأمر. كانت على صياغاتٍ تتبدّل من شاشةٍ لأخرى
 * (فلترة/فلتر/تصفية/تطبيق، احفظ/حفظ، احذف/حذف، انسخ/نسخ، ابعت/إرسال، تحرير/تعديل).
 * الجمل الكاملة («احفظ المهمّة» · «ابعتلي رمز التأكيد») خارج القاعدة: نداءٌ لا تسمية.
 */
class ActionButtonWordingTest extends TestCase
{
    private const OLD = ['فلترة', 'فلتر', 'تصفية', 'تطبيق', 'احفظ', 'احذف', 'انسخ', 'ابعت', 'تحرير', 'حمّل المزيد'];

    private function pattern(): string
    {
        return '('.implode('|', array_map(fn ($w) => preg_quote($w, '/'), self::OLD)).')';
    }

    public function test_no_view_defaults_a_setting_to_a_retired_button_spelling(): void
    {
        $pattern = "/setting\\('[a-z0-9_.]+', '".$this->pattern()."'\\)/u";
        $hits = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (preg_match_all($pattern, $file->getContents(), $m)) {
                $hits[] = $file->getRelativePathname().': '.implode(', ', $m[0]);
            }
        }

        $this->assertSame([], $hits, "صياغات متقاعدة لأزرار:\n".implode("\n", $hits));
    }

    public function test_the_seeded_defaults_of_the_action_button_keys_use_the_unified_words(): void
    {
        $keys = array_filter(array_map('trim', explode("\n", (string) file_get_contents(base_path('tests/fixtures/action-button-keys.txt')))));
        $this->assertGreaterThan(50, count($keys));

        $bad = [];
        foreach (File::allFiles(database_path('seeders')) as $file) {
            foreach (explode("\n", $file->getContents()) as $line) {
                if (preg_match("/^\\s*\\['([a-z0-9_.]+)'/", $line, $m) && in_array($m[1], $keys, true)
                    && preg_match("/'".$this->pattern()."'/u", $line)) {
                    $bad[] = $file->getFilename().': '.$m[1];
                }
            }
        }

        $this->assertSame([], $bad, implode("\n", $bad));
    }
}
