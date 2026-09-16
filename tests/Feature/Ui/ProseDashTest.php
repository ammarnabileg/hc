<?php

namespace Tests\Feature\Ui;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * حارس **الشرطة الطويلة في النصّ المعروض**: المالك رفضها بالاسم، والصوت
 * المطلوب مصريّ دارج يقرأه الناس بلا علامات كتابيّة مستوردة.
 *
 * ⚠️ **ما لا يحرسه هذا الحارس** (وكلُّه مقصود):
 *  1. الشرطة في **التعليقات** حقٌّ للمطوّر ولا تُقاس، والشرح مكتوبٌ بها.
 *  2. **الشرطة اليتيمة** `'—'` تعني «لا قيمة» في خليّةٍ فارغة، ويحرسها
 *     اختبارٌ آخر (`AdminContentTrainingTest`): التدريب بلا غلافٍ يظهر له
 *     شرطة بدل الصورة. ومثلها `coalesce(name, "—")` في الـSQL.
 *  3. **صنف المحارف في تعبيرٍ نمطيّ** مثل `[—–\-|،,]`: هذا يُمسِك الشرطة
 *     في سيرةٍ ذاتيّة مرفوعة **ليقصّها**، فحذفُه يكسر الاستيراد نفسه.
 *
 * فالقياس على الشرطة **الفاصلة داخل جملة** وحدها: ما يبقى بعد نزع ما سبق،
 * وشرطُه أن يكون على **جانبَيها** حرفٌ أو رقم — أي أنّها تفصل كلامًا.
 */
class ProseDashTest extends TestCase
{
    /** ما لا يصل المستخدم: مخرجات أوامر الـArtisan ووثائق المجلّدات المولّدة. */
    private const DEVELOPER_FACING = ['app/Console/'];

    #[Test]
    public function no_rendered_blade_text_carries_an_em_dash(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $source = (string) file_get_contents($file);

            if (! str_contains($source, '—')) {
                continue;
            }

            $rendered = $this->stripNonRendered($source);

            foreach (explode("\n", $rendered) as $number => $line) {
                if ($this->carriesProseDash($line)) {
                    $offenders[] = $this->relative($file).':'.($number + 1).'  '.trim($line);
                }
            }
        }

        $this->assertSame([], $offenders,
            'شرطة طويلة في نصٍّ معروض على المستخدم. استعمل «·» بين حقلين أو أعِد صياغة الجملة: '
            ."\n".implode("\n", $offenders));
    }

    /**
     * نفس القاعدة على النصوص الخارجة من الكود: رسائل الفلاش وبذور الإعدادات
     * وأجسام الإشعارات. والقياس على **حرفيّات النصّ** وحدها عبر `token_get_all`
     * حتى لا يُحسَب شرحٌ مكتوبٌ فوق السطر نفسه.
     */
    #[Test]
    public function no_php_string_shown_to_users_carries_an_em_dash(): void
    {
        $offenders = [];

        foreach ($this->phpFiles() as $file) {
            $source = (string) file_get_contents($file);

            if (! str_contains($source, '—')) {
                continue;
            }

            foreach (token_get_all($source) as $token) {
                if (! is_array($token)) {
                    continue;
                }

                [$id, $text, $line] = $token;

                $isString = $id === T_CONSTANT_ENCAPSED_STRING
                    || $id === T_ENCAPSED_AND_WHITESPACE
                    || $id === T_INLINE_HTML;

                if (! $isString || ! str_contains($text, '—')) {
                    continue;
                }

                if (! $this->carriesProseDash($text)) {
                    continue;
                }

                $offenders[] = $this->relative($file).':'.$line.'  '.trim($text);
            }
        }

        $offenders = array_values(array_unique($offenders));

        $this->assertSame([], $offenders,
            'شرطة طويلة في نصٍّ يخرج للمستخدم من الكود. أعِد صياغته بالعاميّة أو استعمل «·»: '
            ."\n".implode("\n", $offenders));
    }

    /**
     * ينزع ما لا يصل الشاشة: تعليقات Blade وكتل `@php` ووسوم PHP والتعليقات
     * الـHTML والسكربت والستايل — ويحفظ أرقام الأسطر بإبقاء الأسطر الفارغة.
     */
    private function stripNonRendered(string $source): string
    {
        $patterns = [
            '/\{\{--.*?--\}\}/s',
            '/@php\b.*?@endphp/s',
            '/<\?php.*?\?>/s',
            '/<!--.*?-->/s',
            '/<script\b[^>]*>.*?<\/script>/s',
            '/<style\b[^>]*>.*?<\/style>/s',
        ];

        foreach ($patterns as $pattern) {
            $source = (string) preg_replace_callback($pattern,
                fn (array $m) => str_repeat("\n", substr_count($m[0], "\n")), $source);
        }

        return $source;
    }

    /**
     * شرطةٌ **فاصلة داخل كلام**: تُستثنى أسطر الشرح، ثمّ الشرطة اليتيمة
     * المقتبَسة، ثمّ أصناف المحارف في التعابير النمطيّة — ويبقى الشرط الأخير:
     * أن يكون على **جانبَي** الشرطة حرفٌ أو رقم. فما لا شيء بعده (`'Rep —'`)
     * أو لا شيء قبله هو خانةٌ فارغة لا جملة.
     */
    private function carriesProseDash(string $line): bool
    {
        if (! str_contains($line, '—')) {
            return false;
        }

        $trimmed = ltrim($line);

        foreach (['//', '*', '/*', '|', '{{--'] as $commentStart) {
            if (str_starts_with($trimmed, $commentStart)) {
                return false;
            }
        }

        // الشرطة اليتيمة المقتبَسة: خانةٌ فارغة في قالبٍ أو في `coalesce`
        $line = str_replace(["'—'", '"—"', '>—<'], '', $line);

        // صنف المحارف في تعبيرٍ نمطيّ: الشرطة **مُدخَلٌ يُقصّ** لا نصٌّ يُقرأ
        $line = (string) preg_replace('/\[[^\]]*—[^\]]*\]/u', '', $line);

        if (! str_contains($line, '—')) {
            return false;
        }

        // يفصل كلامًا فعلًا: حرفٌ أو رقم قبله وحرفٌ أو رقم بعده
        return (bool) preg_match('/[\p{L}\p{N}][^—]*—[^—]*[\p{L}\p{N}]/u', $line);
    }

    /** @return list<string> */
    private function bladeFiles(): array
    {
        return $this->filesUnder(resource_path('views'), '.blade.php');
    }

    /** @return list<string> */
    private function phpFiles(): array
    {
        $files = array_merge(
            $this->filesUnder(app_path(), '.php'),
            $this->filesUnder(database_path('seeders'), '.php'),
        );

        return array_values(array_filter($files, function (string $file): bool {
            foreach (self::DEVELOPER_FACING as $prefix) {
                if (str_starts_with($this->relative($file), $prefix)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /** @return list<string> */
    private function filesUnder(string $root, string $suffix): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $entry) {
            if ($entry->isFile() && str_ends_with($entry->getFilename(), $suffix)) {
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
