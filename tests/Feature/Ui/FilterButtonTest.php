<?php

namespace Tests\Feature\Ui;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * حارس **زرّ الفلترة المحايد**: المرجع يعطي الصفحة فعلًا أساسيًّا واحدًا بلون
 * العلامة، وزرّ «فلترة/طبّق/تصفية» ليس هو. كان تسعةٌ وعشرون زرًّا يملؤه
 * بالأحمر فينافس رأس الصفحة، وثلاثة عشر يتركه أبيض بلا حدٍّ فيبدو نصًّا
 * عاريًا. القاعدة: زرّ الفلترة `btn btn-g` (محايدٌ بحدّ) لا أحمر ولا عارٍ.
 */
class FilterButtonTest extends TestCase
{
    private const LABELS = ['فلترة', 'طبّق', 'طبق', 'تصفية', 'تطبيق', 'فلتر'];

    #[Test]
    public function every_filter_button_is_the_neutral_bordered_button(): void
    {
        $offenders = [];
        $labels = implode('|', array_map('preg_quote', self::LABELS));
        $pattern = '/<button\b(?![^>]*type="button")([^>]*)>\s*\{\{\s*setting\([^)]*,\s*\'(?:'.$labels.')\'\)\s*\}\}/su';

        foreach ($this->bladeFiles() as $file) {
            $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($file));

            if (! preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[1] as [$attributes, $offset]) {
                $red = str_contains($attributes, '--color-brand-500') || str_contains($attributes, 'btn-p');
                $bare = ! preg_match('/class="[^"]*\bbtn-g\b/', $attributes);

                if ($red || $bare) {
                    $line = substr_count($source, "\n", 0, $offset) + 1;
                    $offenders[] = $this->relative($file).':'.$line.($red ? '  (أحمر)' : '  (بلا حدّ)');
                }
            }
        }

        $this->assertSame([], $offenders,
            "زرّ فلترة ليس الزرّ المحايد بحدّ. اجعله `class=\"btn btn-g …\"` بلا لون العلامة:\n".implode("\n", $offenders));
    }

    /** @return list<string> */
    private function bladeFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($iterator as $entry) {
            if ($entry->isFile() && str_ends_with($entry->getFilename(), '.blade.php')) {
                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function relative(string $file): string
    {
        return str_replace(base_path().'/', '', $file);
    }
}
