<?php

namespace Tests\Feature\Admin\System;

use App\Services\Admin\System\HardcodedTextScanner;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * حارس **النصّ المحروق** (2.13-أ/ب) — واختبارُه نفسه على المحكّ.
 *
 * القاعدة المسجَّلة في هذا المشروع: «كلّ فحصٍ آليّ يجب أن يُثبِت أنّه **يفشل**
 * حين يقع الخلل الذي وُضِع له، وإلّا فهو زينة». ولذلك نصفُ هذا الملفّ يزرع
 * العيب ويطالب الفحص بأن يمسكه، ونصفُه الآخر يزرع **الحالات الممتثلة** ويطالبه
 * بالصمت. والثاني ليس أقلّ أهمّيّةً من الأوّل: الفحص الذي يصرخ في وجه تعليقٍ
 * عربيّ يُعطَّل بعد يومين، فيصير غيابًا بثوب حضور.
 *
 * والمسح يجري على **شجرةٍ تجريبيّة** لا على المستودع: فحصٌ يُختبَر على الواقع
 * وحده يتغيّر حكمُه كلّما كتب أحدٌ سطرًا، فلا يبقى دليلًا على شيء.
 */
class HardcodedTextGuardTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/hardcoded-'.bin2hex(random_bytes(6));
        @mkdir($this->root.'/resources/views', 0777, true);
        @mkdir($this->root.'/app/Services', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->root);

        parent::tearDown();
    }

    // ============================================ 1) يفشل حين يقع الخلل فعلًا

    /** ⭐ الحارس: نصٌّ عربيّ في قالبٍ يُعرَض للمستخدم ⟵ يُمسَك بملفّه وسطره */
    public function test_it_catches_arabic_text_written_straight_into_a_template(): void
    {
        $this->putView('welcome.blade.php', <<<'BLADE'
            <div class="card">
                <h1>أهلًا بعودتك</h1>
            </div>
            BLADE);

        $findings = $this->scan();

        $this->assertCount(1, $findings, 'النصّ المحروق في القالب لم يُمسَك — الحارس زينة.');
        $this->assertSame('resources/views/welcome.blade.php', $findings[0]['file']);
        $this->assertSame(2, $findings[0]['line']);
        $this->assertSame('أهلًا بعودتك', $findings[0]['text']);
    }

    /** والسمة تُعرَض كما يُعرَض النصّ: `placeholder` · `title` · خاصّيّة مكوّن */
    public function test_it_catches_arabic_inside_displayed_attributes(): void
    {
        $this->putView('form.blade.php', <<<'BLADE'
            <input placeholder="اكتب اسمك" class="w-full">
            <x-kpi label="عدد المتدرّبين" :value="$count" />
            BLADE);

        $this->assertSame(
            ['placeholder="اكتب اسمك"', 'label="عدد المتدرّبين"'],
            array_column($this->scan(), 'text'),
        );
    }

    /** ووسيط التوجيه نصٌّ يُعرَض: `@section('title', '…')` عنوانُ الصفحة */
    public function test_it_catches_arabic_in_directive_arguments(): void
    {
        $this->putView('page.blade.php', "@extends('layouts.app')\n@section('title', 'لوحة القيادة')\n");

        $findings = $this->scan();

        $this->assertCount(1, $findings);
        $this->assertSame('لوحة القيادة', $findings[0]['text']);
        $this->assertSame(2, $findings[0]['line']);
    }

    /** والتعبير داخل `{{ }}` نصٌّ يُعرَض ولو كان طرفَ شرط */
    public function test_it_catches_arabic_inside_blade_expressions(): void
    {
        $this->putView('flag.blade.php', "<p>{{ \$ok ? 'مفعَّل' : 'متوقّف' }}</p>\n");

        $this->assertSame(['مفعَّل', 'متوقّف'], array_column($this->scan(), 'text'));
    }

    /** ورسالة المستخدم في صنف PHP محروقةٌ كما هي في القالب */
    public function test_it_catches_arabic_in_php_classes(): void
    {
        $this->putPhp('app/Services/Thing.php', <<<'PHP'
            <?php

            class Thing
            {
                public function save(): string
                {
                    return back()->with('status', 'اتحفظ ✓');
                }
            }
            PHP);

        $findings = $this->scan();

        $this->assertCount(1, $findings);
        $this->assertSame('app/Services/Thing.php', $findings[0]['file']);
        $this->assertSame(7, $findings[0]['line']);
        $this->assertSame('اتحفظ ✓', $findings[0]['text']);
    }

    /** وسطرٌ فيه وسمان يُعَدّ نصّين لا نصًّا — وإلّا اختفت الزيادة داخل الجمع */
    public function test_two_texts_on_one_line_are_two_findings(): void
    {
        $this->putView('row.blade.php', "<span>الفوز</span> · <span>الخسارة</span>\n");

        $this->assertSame(['الفوز', 'الخسارة'], array_column($this->scan(), 'text'));
    }

    // ======================================= 2) ولا يفشل حين لا يقع الخلل

    /** النصّ من `setting()` هو **عين الامتثال** لـ2.13-ب — لا يُبلَّغ عنه */
    public function test_text_that_comes_from_setting_is_not_reported(): void
    {
        $this->putView('ok.blade.php', <<<'BLADE'
            <p>{{ setting('auth.session.persistent_hint', 'هتفضل داخل على طول.') }}</p>
            <x-empty :message="setting('wallet.empty', 'مافيش حركات لسّه.')" />
            BLADE);

        $this->putPhp('app/Services/Ok.php', <<<'PHP'
            <?php

            class Ok
            {
                public function label(): string
                {
                    return setting('store.cart.empty', 'السلّة فاضية.');
                }

                public function nested(): string
                {
                    return e(setting('store.cart.hint', 'ابدأ التسوّق.'));
                }
            }
            PHP);

        $this->assertSame([], $this->scan(), 'نصٌّ من `setting()` بُلِّغ عنه — الحارس ضجيج.');
    }

    /** والتعليق العربيّ ليس معروضًا — بأشكاله الأربعة */
    public function test_arabic_comments_are_never_reported(): void
    {
        // والتعليق قد يحوي **مثالًا مقتبَسًا** — وهو أخطر ما فيه: لو لم يُقنَّع
        // أوّلًا لقرأه الماسح كتعبير Blade وأبلغ عن نصٍّ لا وجود له في الشاشة.
        $this->putView('commented.blade.php', <<<'BLADE'
            {{-- شرحٌ عربيّ داخل تعليق Blade --}}
            {{-- مثالٌ في تعليق: {{ 'نصٌّ في مثالٍ لا يُعرَض' }} --}}
            <!-- شرحٌ عربيّ داخل تعليق HTML -->
            <div class="card"></div>
            BLADE);

        $this->putPhp('app/Services/Commented.php', <<<'PHP'
            <?php

            /**
             * شرحٌ عربيّ في دوك-بلوك — لماذا نفعل هذا (2.13).
             */
            class Commented
            {
                // تعليقٌ عربيّ من سطر واحد
                public const N = 1; /* وتعليقٌ ثالث */
            }
            PHP);

        $this->assertSame([], $this->scan(), 'تعليقٌ عربيّ بُلِّغ عنه — الحارس ضجيج يُعطَّل بعد يومين.');
    }

    /** والاختبارات والسيدرات خارج المسح: بياناتها ليست شاشةً للمستخدم */
    public function test_tests_and_seeders_are_outside_the_scan(): void
    {
        @mkdir($this->root.'/tests/Feature', 0777, true);
        @mkdir($this->root.'/database/seeders', 0777, true);

        file_put_contents(
            $this->root.'/tests/Feature/SomeTest.php',
            "<?php\n\nclass SomeTest\n{\n    public const NAME = 'أحمد التجريبيّ';\n}\n",
        );

        file_put_contents(
            $this->root.'/database/seeders/SomeDemoSeeder.php',
            "<?php\n\nclass SomeDemoSeeder\n{\n    public const TITLE = 'دورة تجريبيّة';\n}\n",
        );

        $this->assertSame([], $this->scan());
    }

    /** ومخرجات الطرفيّة استثناءٌ **معلَن** — والإعلان جزءٌ من الفحص لا هامشُه */
    public function test_the_console_exclusion_is_declared_in_the_output(): void
    {
        @mkdir($this->root.'/app/Console/Commands', 0777, true);
        file_put_contents(
            $this->root.'/app/Console/Commands/Some.php',
            "<?php\n\nclass Some\n{\n    public const LINE = 'شغّل الأمر تاني';\n}\n",
        );

        $this->assertSame([], $this->scan());

        $notes = implode("\n", (new HardcodedTextScanner([], $this->root))->exclusionNotes());

        $this->assertStringContainsString('app/Console/', $notes);
        $this->assertStringContainsString('database/**', $notes);
        $this->assertStringContainsString('tests/**', $notes);
    }

    // ================================== 3) العتبة تمنع الزيادة لا تكتفي بالعدّ

    /**
     * ⭐ الحارس على المستودع الحقيقيّ: موضعٌ واحد جديد ⟵ كود 1 + الملفّ والسطر.
     *
     * والملفّ المزروع في مجلّدٍ نملكه لا في `resources/` — فالإثبات لا يجوز أن
     * يمرّ على ملفّ غيرنا ولو لثوانٍ.
     */
    public function test_the_command_fails_on_a_single_new_occurrence(): void
    {
        $probe = base_path('app/Services/Admin/System/HardcodedTextProbe.php');

        try {
            file_put_contents($probe, "<?php\n\nnamespace App\\Services\\Admin\\System;\n\n".
                "class HardcodedTextProbe\n{\n    public function message(): string\n    {\n".
                "        return 'نصٌّ محروقٌ مزروع لإثبات سقوط الحارس';\n    }\n}\n");

            $exit = Artisan::call('settings:hardcoded');
            $output = Artisan::output();

            $this->assertSame(1, $exit, 'الحارس مرّ رغم نصٍّ محروقٍ جديد — وحارسٌ يمرّ دائمًا أسوأ من غيابه.');
            $this->assertStringContainsString('app/Services/Admin/System/HardcodedTextProbe.php', $output);
            $this->assertStringContainsString('نصٌّ محروقٌ مزروع', $output);
            $this->assertMatchesRegularExpression(
                '/\b9\s+نصٌّ محروقٌ مزروع/u',
                $output,
                'ما سمّى السطر — والملفّ وحده لا يكفي للإصلاح.',
            );
        } finally {
            @unlink($probe);
        }
    }

    /** ويمرّ حين يعود الملفّ — وإلّا كان الأمر يفشل دائمًا وهو عطبٌ مقابل */
    public function test_the_command_passes_when_nothing_changed(): void
    {
        $exit = Artisan::call('settings:hardcoded');

        $this->assertSame(
            0,
            $exit,
            "الأمر يفشل بلا خلل. لو كان النقصان محمودًا فأحكِم العتبة:\n".
            "php artisan settings:hardcoded --update-baseline\n".
            Artisan::output(),
        );
    }

    /** والعتبة نفسها موجودة ومطابقة للقياس — عتبةٌ قديمة تعني فحصًا لا يقيس اليوم */
    public function test_the_baseline_file_matches_the_current_measurement(): void
    {
        $path = base_path('docs/hardcoded-text-baseline.json');

        $this->assertFileExists($path, 'مافيش عتبة — وفحصٌ بلا عتبة لا يمنع زيادةً.');

        $baseline = json_decode((string) file_get_contents($path), true);

        $this->assertSame(
            app(HardcodedTextScanner::class)->countsByFile(),
            $baseline['الملفّات'],
            'العتبة لا تطابق القياس — أحكِمها بـ`php artisan settings:hardcoded --update-baseline`.',
        );
    }

    // ================================================================== أدوات

    /** @return list<array{file:string,line:int,kind:string,text:string}> */
    private function scan(): array
    {
        return (new HardcodedTextScanner(['resources/views', 'app'], $this->root))->findings();
    }

    private function putView(string $name, string $content): void
    {
        file_put_contents($this->root.'/resources/views/'.$name, $content);
    }

    private function putPhp(string $path, string $content): void
    {
        @mkdir(dirname($this->root.'/'.$path), 0777, true);
        file_put_contents($this->root.'/'.$path, $content);
    }

    private function rmdir(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path.'/'.$entry;
            is_dir($full) ? $this->rmdir($full) : @unlink($full);
        }

        @rmdir($path);
    }
}
