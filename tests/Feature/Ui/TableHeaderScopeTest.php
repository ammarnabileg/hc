<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * رؤوس الجداول تعلن نطاقها (`scope="col"`) فيربط قارئ الشاشة كلّ خليّة بعنوان عمودها
 * (12.6-أ · 2.16). كانت 389 رأسًا بلا نطاق.
 */
class TableHeaderScopeTest extends TestCase
{
    public function test_every_table_header_declares_its_scope(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $source = file_get_contents($file);

            if (preg_match_all('/<th\b(?![^>]*\bscope=)[^>]*>/', $source, $m)) {
                $offenders[] = str_replace(base_path().'/', '', $file).' ×'.count($m[0]);
            }
        }

        $this->assertSame([], $offenders, 'رؤوس جداول بلا scope: '.implode(', ', $offenders));
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
}
