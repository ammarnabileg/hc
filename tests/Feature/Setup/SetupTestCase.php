<?php

namespace Tests\Feature\Setup;

use App\Services\Setup\SetupPaths;
use App\Services\Setup\SetupState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * أساس اختبارات معالج التنصيب (2.2).
 * قاعدة حاسمة هنا: المعالج يكتب ملفّات حقيقيّة (.env · القفل · التوكن)،
 * فنوجّه SetupPaths لجذر مؤقّت — فلا يلمس أيّ اختبار ملفّات المشروع.
 */
abstract class SetupTestCase extends TestCase
{
    use RefreshDatabase;

    protected string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/setup-'.Str::random(12));

        @mkdir($this->root.'/storage', 0777, true);
        @copy(base_path('.env.example'), $this->root.'/.env.example');

        $this->app->instance(SetupPaths::class, new SetupPaths($this->root));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);

        parent::tearDown();
    }

    /** حالة معالج جاهزة: التوكن متحقَّق وخطوات سابقة تامّة */
    protected function state(array $completed = [], array $draft = []): static
    {
        $this->withSession([
            'setup' => [
                'token_ok' => true,
                'completed' => $completed,
                'draft' => $draft,
            ],
        ]);

        return $this;
    }

    protected function fingerprintFor(array $credentials): string
    {
        return (new SetupState)->fingerprint($credentials);
    }

    protected function tokenFromFile(): string
    {
        return trim((string) file_get_contents($this->root.'/storage/setup-token.txt'));
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;

            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }

        @rmdir($directory);
    }
}
