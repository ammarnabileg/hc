<?php

namespace Tests\Feature\Ui;

use Database\Seeders\ScreenTextDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * النسخ إلى الحافظة بردٍّ فوريّ (2.17-ب): زرّا «نسخ» مفتاح الـAPI وسرّ الويب-هوك كانا
 * ينسخان صامتَين (onclick خام بلا أيّ أثر)؛ الآن الخطّاف المشترك ينسخ ويُظهر «اتنسخ ✓».
 */
class CopyHookTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_view_copies_to_the_clipboard_through_a_raw_onclick(): void
    {
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($iterator as $entry) {
            if ($entry->isFile() && str_ends_with($entry->getFilename(), '.blade.php')
                && str_contains(file_get_contents($entry->getPathname()), 'onclick="navigator.clipboard')) {
                $offenders[] = str_replace(base_path().'/', '', $entry->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'نسخٌ صامت بـonclick في: '.implode(', ', $offenders));
    }

    public function test_the_shared_script_texts_carry_the_copy_feedback(): void
    {
        $this->seed(ScreenTextDemoSeeder::class);

        $html = view('partials.script-texts')->render();

        $this->assertStringContainsString('"copied":"'.setting('ux.script.copied').'"', $html);
        $this->assertStringContainsString('"copy_prompt":', $html);
        $this->assertStringContainsString("closest('[data-copy-text], [data-copy-target]')", file_get_contents(resource_path('js/app.js')));
    }

    public function test_the_developer_key_buttons_use_the_hook(): void
    {
        $this->assertStringContainsString('data-copy-target="#plain-api-key"', file_get_contents(resource_path('views/admin/developers/tabs/api.blade.php')));
        $this->assertStringContainsString('data-copy-target="#plain-webhook-secret"', file_get_contents(resource_path('views/admin/developers/tabs/webhooks.blade.php')));
    }
}
