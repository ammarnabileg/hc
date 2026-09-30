<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * الفلاتر التي تُرسل نفسها عند التغيير تمرّ بحدث submit (انشغال الزرّ · حارس الإرسال
 * المزدوج) عبر `data-autosubmit` المشترك، لا بـ`onchange="this.form.submit()"` خام.
 */
class AutosubmitTest extends TestCase
{
    public function test_no_view_auto_submits_through_an_inline_onchange(): void
    {
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($iterator as $entry) {
            if ($entry->isFile() && str_ends_with($entry->getFilename(), '.blade.php')
                && preg_match('/onchange="this\.form\.(submit|requestSubmit)\(\)"/', file_get_contents($entry->getPathname()))) {
                $offenders[] = str_replace(base_path().'/', '', $entry->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'onchange خام في: '.implode(', ', $offenders));
    }

    public function test_the_shared_script_handles_the_hook(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("closest('[data-autosubmit]')", $js);
        $this->assertStringContainsString('el.form.requestSubmit()', $js);
    }
}
