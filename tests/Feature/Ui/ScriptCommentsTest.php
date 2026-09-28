<?php

namespace Tests\Feature\Ui;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * حارس **الشرح العربيّ داخل `<script>`**: تعليق JS بصيغة `/* ... *\/` يُشحَن
 * حرفيًّا إلى المتصفّح مع كلّ صفحة، فيثقلها ويُظهر كلماتٍ في مصدر الصفحة لم
 * تُكتب للمستخدم. وقد كسر ذلك فعلًا اختبارَ «زرّ التنزيل مخفيّ»: كلمة «تحميل»
 * في تعليقٍ بسكربت الدرج وُجدت في كلّ صفحة.
 *
 * الشرح حقٌّ للمطوّر ويبقى، لكن بصيغة تعليق Blade `{{-- --}}` يُحذَف قبل
 * الإرسال. والقياس على التعليقات الكتليّة وحدها: تعليق السطر `//` قد يكون
 * جزءًا من رابط.
 */
class ScriptCommentsTest extends TestCase
{
    #[Test]
    public function no_arabic_block_comment_ships_inside_a_script_tag(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $source = (string) file_get_contents($file);

            if (! str_contains($source, '<script')) {
                continue;
            }

            // تعليقات Blade تُنزَع أوّلًا: هي الصيغة الصحيحة وليست مخالفة
            $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);

            preg_match_all('/<script\b[^>]*>(.*?)<\/script>/s', $source, $scripts, PREG_OFFSET_CAPTURE);

            foreach ($scripts[1] as [$body, $offset]) {
                preg_match_all('#/\*.*?\*/#s', $body, $comments, PREG_OFFSET_CAPTURE);

                foreach ($comments[0] as [$comment, $inner]) {
                    if (preg_match('/\p{Arabic}/u', $comment)) {
                        $line = substr_count($source, "\n", 0, $offset + $inner) + 1;
                        $offenders[] = $this->relative($file).':'.$line;
                    }
                }
            }
        }

        $this->assertSame([], $offenders,
            'تعليق JS عربيّ داخل <script> يصل المتصفّح. حوّله إلى تعليق Blade {{-- --}}: '
            ."\n".implode("\n", $offenders));
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
