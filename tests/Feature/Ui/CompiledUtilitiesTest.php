<?php

namespace Tests\Feature\Ui;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * **2.15-ج — لا تمرير أفقيّ:** وحارسٌ يقيس **الحزمة المشحونة** لا القالب وحده.
 *
 * القصّة التي وُلد منها هذا الحارس: `/admin/settings?tab=features` كانت تخرج
 * عن 375px بـ6px بالضبط. والقالب كان يحمل العلاج الصحيح فعلًا:
 *
 *     grid-cols-[minmax(0,1fr)] md:grid-cols-[240px_minmax(0,1fr)]
 *
 * لكنّ الصنفين **لم يكونا في ملفّ الأنماط المشحون** (`public/build/assets/*.css`)
 * لأنّ `npm run build` لم يُشغَّل بعد التعديل. وTailwind لا يُنشئ الأصناف ذات
 * القيمة الحرّة (`foo-[…]`) إلّا بمسح المصادر وقت البناء — فما لم يُبنَ **يسقط
 * صامتًا**: لا خطأ في الطرفيّة، ولا تحذير في المتصفّح، ولا اختبارٌ أحمر.
 *
 * والأثر كان مضاعفًا:
 *  - على الموبايل: `grid-template-columns` رجع إلى مسارٍ واحد `auto`، فاتّسع
 *    لمقاس محتواه الأدنى (365.33px) بدل 343px المتاحة ⟵ `scrollWidth` 381.
 *  - وعلى الديسكتوب: العمودان انهارا إلى عمودٍ واحد مكدَّس — والشاشة تبدو
 *    «شغّالة» فلا ينتبه أحد.
 *
 * فالحارس هنا لا يقرأ القالب ويصدّقه، بل **يفتح الحزمة ويتحقّق** أنّ كلّ صنفٍ
 * ذي قيمة حرّة مكتوبٍ في القوالب له قاعدةٌ فعليّة فيها.
 */
class CompiledUtilitiesTest extends TestCase
{
    #[Test]
    public function every_arbitrary_utility_written_in_a_blade_exists_in_the_shipped_stylesheet(): void
    {
        $css = $this->shippedCss();

        $missing = [];

        foreach ($this->bladeFiles() as $file) {
            foreach ($this->arbitraryUtilities($file) as $class) {
                if (! $this->stylesheetDefines($css, $class)) {
                    $missing[] = $class.' ⟵ '.str_replace(base_path().'/', '', $file);
                }
            }
        }

        $missing = array_values(array_unique($missing));

        $this->assertSame([], $missing,
            'أصنافٌ ذات قيمة حرّة في القوالب بلا قاعدة في الحزمة المشحونة — '
            .'التخطيط ساقطٌ صامتًا، وغالبًا `npm run build` لم يُشغَّل بعد التعديل: '
            .implode(' · ', $missing));
    }

    /**
     * والوجه الثاني للعطب نفسه: عمودا شبكة الإعدادات **يقدران على الانكماش**.
     *
     * عنصر الشبكة افتراضيّه `min-width: auto` — أي لا يصغر تحت مقاس محتواه
     * الأدنى. وتابّ المزايا وحده يرفع ذلك المقاس فوق المتاح (كارت الموبايل فيه
     * مفتاحُ ميزةٍ بـ`truncate`، و`truncate` تعني `white-space: nowrap` فمقاسه
     * الأدنى سطرُه كاملًا). فبلا `min-w-0` على العمودين تعود الـ6px حتّى لو
     * كانت `grid-cols` مبنيّةً — وهذا حزامٌ ثانٍ لا يعتمد على البناء أصلًا.
     */
    #[Test]
    public function both_columns_of_the_settings_grid_can_shrink(): void
    {
        $index = (string) file_get_contents(resource_path('views/admin/settings/index.blade.php'));

        // شريط التابات (nav) وعمود المحتوى — كلاهما عنصر شبكة مباشر
        $this->assertMatchesRegularExpression('/<nav[^>]*\bclass="[^"]*\bmin-w-0\b/', $index,
            'شريط تابات الإعدادات بلا `min-w-0` — الرقائق هتمدّ العمود وتمدّ الصفحة (2.15-ج).');

        $this->assertMatchesRegularExpression('/<div class="min-w-0 space-y-4">/', $index,
            'عمود محتوى الإعدادات بلا `min-w-0` — تابّ المزايا هيمدّ العمود لمقاس محتواه الأدنى '
            .'ويخرج الصفحة عن 375px (2.15-ج).');
    }

    /** ملفّ الأنماط المشحون فعلًا — من مانيفست Vite لا بتخمين الاسم المُجزَّأ. */
    private function shippedCss(): string
    {
        $manifestPath = public_path('build/manifest.json');

        $this->assertFileExists($manifestPath, 'مافيش حزمة مبنيّة — شغّل `npm run build`.');

        $manifest = (array) json_decode((string) file_get_contents($manifestPath), true);

        $this->assertArrayHasKey('resources/css/app.css', $manifest,
            'مانيفست Vite مافيهوش مدخل `resources/css/app.css`.');

        $file = public_path('build/'.$manifest['resources/css/app.css']['file']);

        $this->assertFileExists($file, 'ملفّ الأنماط المذكور في المانيفست مش موجود: '.$file);

        return (string) file_get_contents($file);
    }

    /**
     * الأصناف ذات القيمة الحرّة المكتوبة **حرفيًّا** في سمة `class`.
     * وما فيه `{{` أو `$` يُترَك: قيمته تُحسَب وقت العرض فلا يُقاس هنا.
     *
     * @return list<string>
     */
    private function arbitraryUtilities(string $file): array
    {
        $content = (string) file_get_contents($file);

        if (! preg_match_all('/\bclass="([^"]*)"/', $content, $matches)) {
            return [];
        }

        $classes = [];

        foreach ($matches[1] as $attribute) {
            if (str_contains($attribute, '{{') || str_contains($attribute, '$')) {
                continue;
            }

            foreach (preg_split('/\s+/', trim($attribute)) ?: [] as $class) {
                if ($class !== '' && str_contains($class, '[') && str_ends_with($class, ']')) {
                    $classes[] = $class;
                }
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * هل في الحزمة قاعدةٌ لهذا الصنف؟
     *
     * Tailwind يهرّب الرموز في المُحدِّد (`grid-cols-[minmax(0,1fr)]` تُكتب
     * `.grid-cols-\[minmax\(0\,1fr\)\]`)، وتفاصيل التهريب تتغيّر بين الإصدارات —
     * فالمطابقة تسمح بشرطة مائلة اختياريّة قبل كلّ رمزٍ غير أبجديّ.
     */
    private function stylesheetDefines(string $css, string $class): bool
    {
        $pattern = '';

        foreach (preg_split('//u', $class, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $pattern .= preg_match('/[A-Za-z0-9_-]/', $character) === 1
                ? preg_quote($character, '/')
                : '\\\\?'.preg_quote($character, '/');
        }

        return preg_match('/\.'.$pattern.'(?![A-Za-z0-9_-])/u', $css) === 1;
    }

    /** @return list<string> */
    private function bladeFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $entry) {
            if ($entry->isFile() && str_ends_with($entry->getFilename(), '.blade.php')) {
                $files[] = $entry->getPathname();
            }
        }

        return $files;
    }
}
