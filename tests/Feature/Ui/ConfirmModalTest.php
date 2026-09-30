<?php

namespace Tests\Feature\Ui;

use Database\Seeders\ScreenTextDemoSeeder;

/**
 * ⛔ ما قبله: ~55 فعلًا حسّاسًا (حذف · إرجاع للافتراضيّ · تدوير مفتاح) كانت تمرّ من
 * `confirm()` الخام: نافذة المتصفّح بخطّه ومظهره لا مظهر المنصّة، وبلا Bottom Sheet
 * على الموبايل. الآن كلّها `data-confirm` على النموذج/الزرّ، وبوب-أب واحد مشترك
 * (`components/confirm-modal`) يعرض الرسالة بنصوصٍ من `setting()`.
 */
class ConfirmModalTest extends UiTestCase
{
    public function test_no_view_uses_the_native_browser_confirm(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $source = file_get_contents($file);

            if (preg_match('/on(?:submit|click)="return confirm\(/', $source)
                || preg_match('/(?<![\w.`])(?:window\.)?confirm\(/', $source)) {
                $offenders[] = $this->relative($file);
            }
        }

        $this->assertSame([], $offenders, 'confirm() الخام لا يزال في: '.implode(', ', $offenders));
    }

    public function test_every_authenticated_layout_mounts_the_confirm_modal(): void
    {
        foreach (['app', 'admin', 'volunteer'] as $layout) {
            $source = file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));

            $this->assertStringContainsString('<x-confirm-modal />', $source, "layouts/{$layout} بلا بوب-أب التأكيد");
        }
    }

    public function test_the_confirm_modal_renders_its_texts_from_settings(): void
    {
        $this->seed(ScreenTextDemoSeeder::class);

        $html = view('components.confirm-modal')->render();

        $this->assertStringContainsString('id="confirm-modal"', $html);
        $this->assertStringContainsString('role="alertdialog"', $html);
        $this->assertStringContainsString('data-confirm-message', $html);
        $this->assertStringContainsString(setting('ux.confirm.ok'), $html);
        $this->assertStringContainsString(setting('ux.confirm.cancel'), $html);
        $this->assertStringContainsString(setting('ux.confirm.title'), $html);
    }

    public function test_the_shared_script_intercepts_data_confirm_forms_and_buttons(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("form.hasAttribute('data-confirm')", $js);
        $this->assertStringContainsString("closest('button[data-confirm], a[data-confirm]')", $js);
        $this->assertStringContainsString('window.platformConfirm = platformConfirm', $js);
    }

    /** الحذف في كلّ الشاشات حسّاس: كلّ نموذج DELETE موجّه لمسار destroy يحمل تأكيدًا أو تراجعًا */
    public function test_data_confirm_messages_are_never_empty_literals(): void
    {
        foreach ($this->bladeFiles() as $file) {
            $source = file_get_contents($file);

            $this->assertStringNotContainsString('data-confirm=""', $source, $this->relative($file).' فيه data-confirm فارغ');
        }
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
