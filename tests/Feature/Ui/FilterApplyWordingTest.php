<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * كلمة واحدة لفعلٍ واحد: زرّ تطبيق الفلاتر اسمه «طبّق» في كلّ الشاشات. كان على
 * ستّ صياغات (طبّق · فلترة · فلتر · تصفية · تطبيق · بحث) تبعًا للشاشة، فيتعلّم
 * المستخدم الزرّ من جديد في كلّ صفحة.
 */
class FilterApplyWordingTest extends TestCase
{
    private const OLD = ['فلترة', 'فلتر', 'تصفية', 'تطبيق'];

    public function test_no_view_defaults_a_setting_to_an_old_filter_apply_spelling(): void
    {
        $pattern = "/setting\\('[a-z0-9_.]+', '(".implode('|', self::OLD).")'\\)/u";
        $hits = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (preg_match_all($pattern, $file->getContents(), $m)) {
                $hits[] = $file->getRelativePathname().': '.implode(', ', $m[0]);
            }
        }

        $this->assertSame([], $hits, "صياغات قديمة لزرّ الفلتر:\n".implode("\n", $hits));
    }

    public function test_the_seeded_defaults_of_the_filter_apply_keys_say_apply(): void
    {
        $keys = array_filter(array_map('trim', explode("\n", (string) file_get_contents(base_path('tests/fixtures/filter-apply-keys.txt')))));
        $this->assertNotEmpty($keys);

        $bad = [];
        foreach (File::allFiles(database_path('seeders')) as $file) {
            foreach (explode("\n", $file->getContents()) as $line) {
                if (preg_match("/^\\s*\\['([a-z0-9_.]+)'/", $line, $m) && in_array($m[1], $keys, true)
                    && preg_match("/'(".implode('|', self::OLD).")'/u", $line)) {
                    $bad[] = $file->getFilename().': '.$m[1];
                }
            }
        }

        $this->assertSame([], $bad, implode("\n", $bad));
    }
}
