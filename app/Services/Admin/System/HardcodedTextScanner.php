<?php

namespace App\Services\Admin\System;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * ماسحُ **النصّ المحروق** — الوجه الثاني للقاعدة الذهبيّة 2.13.
 *
 * `SettingKeyScanner` يسأل: «كلّ مفتاح يقرؤه الكود، هل له صفّ؟» — وهو سؤالٌ
 * **لا يرى النصّ المحروق أصلًا**، لأنّ `<h1>أهلًا بعودتك</h1>` لا يمرّ بـ
 * `setting()` فلا يدخل المقياس. فمئةٌ بالمئة هناك لا تعني الامتثال لـ2.13.
 *
 * ونصُّ الدستور صريح:
 *
 * > «أيّ **ميزة أو عنصر أو رقم أو نصّ** — مُضاف سابقًا أو سيُضاف مستقبلًا —
 * > لازم يكون له **إعدادات في لوحة الإدارة**» (2.13)
 * > و**الحدّ الأدنى الإلزاميّ** يذكر صراحةً «**النصوص الظاهرة للمستخدم**» (2.13-أ)
 * > و«**ممنوع الحرق (Hard-coded):** كلّ رقم أو نصّ مذكور في الدستور يُعتبَر قيمة
 * > افتراضيّة قابلة للتعديل من اللوحة — **إلا ما نُصَّ صراحةً أنّه ثابت نظاميّ
 * > (منطق أمنيّ أو حدود منع تلاعب)**» (2.13-ب)
 *
 * فالاستثناء الوحيد في النصّ هو **الثابت النظاميّ المنصوص عليه**، والقيد الوحيد
 * على النطاق هو **«الظاهرة للمستخدم»**. وما عدا ذلك فاخترعناه نحن لا الدستور —
 * ولذلك كلّ استثناءٍ هنا **معلَنٌ باسمه وسببه** في `exclusionNotes()` ويُطبَع مع
 * كلّ تشغيل. الحارس الذي يستثني في صمتٍ حارسٌ يمرّ دائمًا، وهو أسوأ من غيابه.
 *
 * **ما يقيسه:** النصّ العربيّ المكتوب حرفيًّا في موضعٍ يُعرَض للمستخدم —
 * في القوالب (نصّ · سمة · تعبير Blade · سكربت) وفي أصناف `app/`.
 * **ما لا يقيسه:** معلنٌ حرفيًّا في `exclusionNotes()` — اقرأه قبل أن تثق بالرقم.
 */
class HardcodedTextScanner
{
    /** المجلّدات الممسوحة — القوالب والأصناف (نسبةً لجذر المشروع) */
    public const ROOTS = ['resources/views', 'app'];

    /**
     * الاستثناءات — **مسارٌ لكلّ استثناء وسببه**. لا استثناء بلا سطرٍ هنا،
     * والسطر يُطبَع في مخرجات الأمر حتى لا يصير الاستثناء سرًّا بين الكود ونفسه.
     *
     * @var array<string, string>
     */
    public const EXCLUDED_PATHS = [
        'app/Console' => 'مخرجاتها للطرفيّة (مشغّل النظام) لا لشاشة المستخدم — خارج «النصوص الظاهرة للمستخدم» (2.13-أ). وهذا **الاستثناء الوحيد المخترَع هنا**، وما عداه حدودُ مسحٍ معلَنة لا استثناءات.',
    ];

    /**
     * الاستدعاءات التي **تجعل النصّ ممتثلًا**: النصّ داخلها قيمةٌ افتراضيّة
     * يعدّلها المالك من لوحته — وهو عين ما تطلبه 2.13-ب («قيمة افتراضيّة قابلة
     * للتعديل»)، لا حرقٌ.
     *
     * @var list<string>
     */
    public const COMPLIANT_CALLS = [
        'setting',
        '__',
        'trans',
        'trans_choice',
        'lang',
        'SetupSettings::get',
        'SetupSettings::text',
        'SetupSettings::number',
        'SetupSettings::flag',
        'SetupSettings::list',
    ];

    /** حرفٌ عربيّ (بلا الأرقام الهنديّة ولا التشكيل) — به وحده يُعرَف النصّ */
    private const ARABIC_LETTER = '[\x{0620}-\x{064A}\x{0660}-\x{0669}\x{066E}-\x{06D3}\x{06FA}-\x{06FF}]';

    /**
     * أقلّ عدد حروفٍ عربيّة في المقطع حتى يُعَدّ نصًّا.
     * الحرف الواحد قد يكون رمزًا (واو عطف في تعبير · وحدة قياس) — والضجيج
     * يُعطِّل الحارس بعد يومين، فنُغلِّب الدقّة على الطمع.
     */
    private const MIN_LETTERS = 2;

    /** @var list<array{file:string,line:int,kind:string,text:string}>|null */
    private ?array $findings = null;

    /**
     * @param  list<string>|null  $roots  مجلّدات المسح — تُمرَّر في الاختبار على شجرة تجريبيّة
     */
    public function __construct(
        private readonly ?array $roots = null,
        private readonly ?string $basePath = null,
    ) {}

    /**
     * كلّ المخالفات — ملفٌّ وسطرٌ ونوعُ الموضع ونصُّه.
     *
     * @return list<array{file:string,line:int,kind:string,text:string}>
     */
    public function findings(): array
    {
        if ($this->findings !== null) {
            return $this->findings;
        }

        $findings = [];

        foreach ($this->files() as $path) {
            $relative = $this->relative($path);
            $source = @file_get_contents($path);

            if ($source === false || $source === '') {
                continue;
            }

            $found = str_ends_with($path, '.blade.php')
                ? $this->scanBlade($source)
                : $this->scanPhpFile($source);

            foreach ($found as $item) {
                $findings[] = ['file' => $relative] + $item;
            }
        }

        usort($findings, fn (array $a, array $b) => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);

        return $this->findings = $findings;
    }

    /**
     * العدد لكلّ ملفّ — شكلُ العتبة نفسه.
     *
     * @return array<string, int>
     */
    public function countsByFile(): array
    {
        $counts = [];

        foreach ($this->findings() as $finding) {
            $counts[$finding['file']] = ($counts[$finding['file']] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * العدد لكلّ مجلّد (بعمقٍ محدَّد) — لتقرأ الخريطة لا القائمة.
     *
     * @return array<string, int>
     */
    public function countsByFolder(int $depth = 3): array
    {
        $counts = [];

        foreach ($this->findings() as $finding) {
            $parts = explode('/', $finding['file']);
            array_pop($parts);
            $folder = implode('/', array_slice($parts, 0, $depth)) ?: '.';
            $counts[$folder] = ($counts[$folder] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }

    /** @return array<string, int> */
    public function countsByKind(): array
    {
        $counts = [];

        foreach ($this->findings() as $finding) {
            $counts[$finding['kind']] = ($counts[$finding['kind']] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }

    public function total(): int
    {
        return count($this->findings());
    }

    /**
     * **ما لا يقيسه هذا الفحص** — يُطبَع مع كلّ تشغيل ويُقرَأ قبل الوثوق بالرقم.
     *
     * @return list<string>
     */
    public function exclusionNotes(): array
    {
        $notes = [
            'المقياس هو **النصّ العربيّ** وحده: نصٌّ إنجليزيّ ظاهر للمستخدم لا يراه هذا الفحص.',
            'التعليقات لا تُحسَب (`{{-- --}}` · `<!-- -->` · `//` · `/* */`) — التعليق ليس معروضًا.',
            '`resources/js` و`resources/css` و`public/**` خارج المسح — الأصول المبنيّة لا تُمسَح.',
            '`database/**` (السيدرات والمايجريشنز) خارج المسح: هي **مصدر القيم الافتراضيّة** نفسه لا حرقًا (BUILD.md §3).',
            '`tests/**` و`lang/**` خارج المسح — ليست شاشةً للمستخدم.',
            'النصّ داخل `setting()`/`__()`/`SetupSettings::*` يُعَدّ **ممتثلًا** ولو لم يكن للمفتاح صفٌّ في القاعدة — تلك مسؤوليّة `settings:coverage`، وهي فحصٌ آخر. **الفحصان معًا** لا أحدهما.',
            'لا يحكم على **جودة** المفتاح ولا على وجود شاشةٍ تعدّله — يحكم على وجود النصّ حرفيًّا فقط.',
            'لا يفرّق بين نصّ الواجهة و**محتوى العرض** (اسم مقرّر · مقال) لو كُتِب في قالب — كلاهما محروق، لكنّ علاجهما مختلف (إعداد مقابل قاعدة بيانات).',
            'مقطعٌ فيه أقلّ من '.self::MIN_LETTERS.' حرفًا عربيًّا لا يُحسَب — قد يفوته نصٌّ قصير جدًّا.',
            'وحدة العدّ **مقطعٌ متّصل** لا كلمة: `<h1>أهلًا بعودتك</h1>` موضعٌ واحد لا اثنان — فعدٌّ بوحدةٍ أدقّ يعطي رقمًا أكبر، والرقم هنا **حدٌّ أدنى** لا حصر.',
            'رسائل الاستثناءات والسجلّات في `app/` **تُحسَب** وإن كان جمهورها المطوّر — الفحص لا يقرأ النيّة.',
        ];

        foreach (self::EXCLUDED_PATHS as $path => $why) {
            $notes[] = "`{$path}/` مستثنًى — {$why}";
        }

        return $notes;
    }

    // ================================================================== القوالب

    /**
     * @return list<array{line:int,kind:string,text:string}>
     */
    public function scanBlade(string $source): array
    {
        $findings = [];
        $work = $source;

        // 1) التعليقات أوّلًا — قبل أيّ تفسير، فقد تحوي أقواسًا وأوسمة
        $work = $this->mask($work, '/\{\{--.*?--\}\}/s');
        $work = $this->mask($work, '/<!--.*?-->/s');

        // 2) كتل PHP الصريحة — تُفحَص بقواعد PHP لا بقواعد النصّ
        $work = $this->extract($work, '/@php\b(?!\s*\()(.*?)@endphp/s', 1, function (string $code, int $offset) use ($source, &$findings) {
            foreach ($this->scanPhpSnippet($code, $this->lineAt($source, $offset)) as $item) {
                $findings[] = $item;
            }
        });

        $work = $this->extract($work, '/<\?php(.*?)(?:\?>|$)/s', 1, function (string $code, int $offset) use ($source, &$findings) {
            foreach ($this->scanPhpSnippet($code, $this->lineAt($source, $offset)) as $item) {
                $findings[] = $item;
            }
        });

        // 3) الأنماط خارج النطاق المعلَن
        $work = $this->mask($work, '/<style\b[^>]*>.*?<\/style>/is');

        // 4) السكربت: نصُّه العربيّ رسالةٌ للمستخدم غالبًا — تعليقاته وحدها تُقنَّع
        $work = $this->extract($work, '/<script\b[^>]*>(.*?)<\/script>/is', 1, function (string $code, int $offset) use ($source, &$findings) {
            $code = $this->mask($code, '#//[^\n]*#');
            $code = $this->mask($code, '#/\*.*?\*/#s');

            foreach ($this->runs($code) as $run) {
                $findings[] = [
                    'line' => $this->lineAt($source, $offset + $run['offset']),
                    'kind' => 'script',
                    'text' => $run['text'],
                ];
            }
        });

        // 5) الوسوم والسمات — كلّ قيمة سمةٍ فيها عربيّة نصٌّ يُعرَض (عنوان · placeholder · خاصّيّة مكوّن)
        // الوسمُ يتخطّى القيم المقتبَسة قبل أن يبحث عن `>`، وإلّا قطعَه أوّلُ
        // `=>` داخل سمةٍ مربوطة (`:breadcrumbs="[['label' => …]]"`) فضاع نصفُها.
        $work = $this->extract($work, '/<\/?[a-zA-Z][a-zA-Z0-9:._\-]*(?:"[^"]*"|\'[^\']*\'|[^>"\'])*>/s', 0, function (string $tag, int $offset) use ($source, &$findings) {
            foreach ($this->scanTag($tag, $offset, $source) as $item) {
                $findings[] = $item;
            }
        });

        // 6) ما بقي نصٌّ ظاهر: نطرح منه تعابير Blade والتوجيهات ونفحصها بقواعد PHP
        $work = $this->extract($work, '/\{!!(.*?)!!\}|\{\{(.*?)\}\}/s', null, function (string $code, int $offset) use ($source, &$findings) {
            foreach ($this->scanPhpSnippet($code, $this->lineAt($source, $offset)) as $item) {
                $findings[] = ['line' => $item['line'], 'kind' => 'blade-expr', 'text' => $item['text']];
            }
        });

        $work = $this->extractDirectives($work, function (string $code, int $offset) use ($source, &$findings) {
            foreach ($this->scanPhpSnippet($code, $this->lineAt($source, $offset)) as $item) {
                $findings[] = ['line' => $item['line'], 'kind' => 'directive', 'text' => $item['text']];
            }
        });

        foreach ($this->runs($work) as $run) {
            $findings[] = [
                'line' => $this->lineAt($source, $run['offset']),
                'kind' => 'text',
                'text' => $run['text'],
            ];
        }

        usort($findings, fn (array $a, array $b) => $a['line'] <=> $b['line']);

        return $findings;
    }

    /**
     * سمات الوسم: `:prop="expr"` تعبيرٌ PHP، وغيرها نصٌّ يُعرَض.
     *
     * @return list<array{line:int,kind:string,text:string}>
     */
    private function scanTag(string $tag, int $tagOffset, string $source): array
    {
        $findings = [];

        preg_match_all(
            '/([:@]?[A-Za-z_][A-Za-z0-9_.:\-]*)\s*=\s*(["\'])(.*?)\2/s',
            $tag,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );

        foreach ($matches as $match) {
            [$name] = $match[1];
            [$value, $valueOffset] = $match[3];

            if (! $this->hasArabic($value)) {
                continue;
            }

            $absolute = $tagOffset + $valueOffset;

            // سمةٌ مربوطة (`:label="…"`) قيمتها تعبير PHP كاملة
            if (str_starts_with($name, ':')) {
                foreach ($this->scanPhpSnippet($value, $this->lineAt($source, $absolute)) as $item) {
                    $findings[] = ['line' => $item['line'], 'kind' => 'attribute-expr', 'text' => $item['text']];
                }

                continue;
            }

            $rest = $this->extract($value, '/\{!!(.*?)!!\}|\{\{(.*?)\}\}/s', null, function (string $code, int $offset) use ($absolute, $source, &$findings) {
                foreach ($this->scanPhpSnippet($code, $this->lineAt($source, $absolute + $offset)) as $item) {
                    $findings[] = ['line' => $item['line'], 'kind' => 'attribute-expr', 'text' => $item['text']];
                }
            });

            foreach ($this->runs($rest) as $run) {
                $findings[] = [
                    'line' => $this->lineAt($source, $absolute + $run['offset']),
                    'kind' => 'attribute',
                    'text' => $name.'="'.$run['text'].'"',
                ];
            }
        }

        return $findings;
    }

    /**
     * `@section('title', 'نصّ')` — التوجيه باسمه وأقواسه المتوازنة.
     * (والأقواس تُعَدّ يدويًّا لأنّ الوسيط قد يحوي أقواسًا بداخله.)
     */
    private function extractDirectives(string $work, callable $handler): string
    {
        $length = strlen($work);
        $result = $work;

        if (preg_match_all('/@([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $work, $matches, PREG_OFFSET_CAPTURE) === false) {
            return $result;
        }

        foreach ($matches[0] as $index => [$head, $start]) {
            $open = $start + strlen($head) - 1;

            if ($open >= $length || $work[$open] !== '(') {
                continue;
            }

            $depth = 0;
            $close = null;

            for ($i = $open; $i < $length; $i++) {
                $char = $work[$i];

                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;

                    if ($depth === 0) {
                        $close = $i;

                        break;
                    }
                }
            }

            if ($close === null) {
                continue;
            }

            $inner = substr($work, $open + 1, $close - $open - 1);
            $name = $matches[1][$index][0];

            // `@lang('نصّ')` ممتثل بنصّ الدستور نفسه — نمرّره كاستدعاء دالّة
            $code = in_array($name, self::COMPLIANT_CALLS, true) ? $name.'('.$inner.')' : $inner;

            $handler($code, in_array($name, self::COMPLIANT_CALLS, true) ? $start + 1 : $open + 1);

            $result = substr_replace($result, $this->blank(substr($work, $start, $close - $start + 1)), $start, $close - $start + 1);
        }

        return $result;
    }

    // ==================================================================== PHP

    /**
     * @return list<array{line:int,kind:string,text:string}>
     */
    public function scanPhpFile(string $source): array
    {
        return $this->scanTokens(@token_get_all($source), 0);
    }

    /**
     * @return list<array{line:int,kind:string,text:string}>
     */
    private function scanPhpSnippet(string $code, int $startLine): array
    {
        return $this->scanTokens(@token_get_all('<?php '.$code), $startLine - 1);
    }

    /**
     * النصّ الحرفيّ في PHP: **مخالفٌ إلّا أن يكون داخل استدعاءٍ ممتثل**.
     *
     * والقرار يُتَّخذ بالاستدعاء **الأقرب** (الداخليّ)، فـ`e(setting('k','نصّ'))`
     * ممتثل: النصّ افتراضيُّ `setting` لا وسيطُ `e`.
     *
     * @param  array<int, array{0:int,1:string,2:int}|string>  $tokens
     * @return list<array{line:int,kind:string,text:string}>
     */
    private function scanTokens(array $tokens, int $lineOffset): array
    {
        $findings = [];
        /** @var list<string|null> $stack */
        $stack = [];
        /** @var list<array{0:int,1:string,2:int}|string> $meaningful */
        $meaningful = [];

        foreach ($tokens as $token) {
            if (is_array($token)) {
                [$id, $text, $line] = $token;

                if (in_array($id, [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE, T_OPEN_TAG, T_CLOSE_TAG, T_INLINE_HTML], true)) {
                    if ($id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
                        $meaningful[] = $token;
                    }

                    continue;
                }

                if (in_array($id, [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                    $value = $id === T_CONSTANT_ENCAPSED_STRING ? $this->unquote($text) : $text;

                    if ($this->hasArabic($value) && ! $this->compliant($stack)) {
                        $findings[] = [
                            'line' => $line + $lineOffset,
                            'kind' => 'php',
                            'text' => $this->trim($value),
                        ];
                    }
                }

                $meaningful[] = $token;

                continue;
            }

            if ($token === '(') {
                $stack[] = $this->callName($meaningful);
            } elseif ($token === ')') {
                array_pop($stack);
            }

            $meaningful[] = $token;
        }

        return $findings;
    }

    /**
     * اسمُ الاستدعاء الذي فُتِح قوسُه الآن — أو `null` لو لم يكن استدعاءً
     * (قوس تجميع · شرط · تعريف دالّة).
     *
     * @param  list<array{0:int,1:string,2:int}|string>  $meaningful
     */
    private function callName(array $meaningful): ?string
    {
        $parts = [];

        for ($i = count($meaningful) - 1; $i >= 0; $i--) {
            $token = $meaningful[$i];

            if ($token === '::') {
                array_unshift($parts, '::');

                continue;
            }

            if (! is_array($token)) {
                break;
            }

            if (in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                array_unshift($parts, $token[1]);

                continue;
            }

            if ($token[0] === T_DOUBLE_COLON) {
                array_unshift($parts, '::');

                continue;
            }

            break;
        }

        if ($parts === []) {
            return null;
        }

        $name = implode('', $parts);

        // اسمُ الصنف الكامل يُختصَر لآخر جزأين: `App\…\SetupSettings::text` ⟵ `SetupSettings::text`
        if (str_contains($name, '\\')) {
            $segments = explode('\\', $name);
            $name = end($segments);
        }

        return $name;
    }

    /** @param  list<string|null>  $stack */
    private function compliant(array $stack): bool
    {
        for ($i = count($stack) - 1; $i >= 0; $i--) {
            if ($stack[$i] === null) {
                continue;
            }

            return in_array($stack[$i], self::COMPLIANT_CALLS, true);
        }

        return false;
    }

    // ================================================================== أدوات

    /**
     * مقاطع النصّ العربيّ في نصٍّ خام — مقطعٌ واحد لكلّ نصٍّ متّصل.
     *
     * @return list<array{offset:int,text:string}>
     */
    private function runs(string $text): array
    {
        $runs = [];

        // المقطع: عربيّةٌ وما يلتصق بها من مسافاتٍ وترقيمٍ ولاتينيّة — ويقطعه
        // الوسمُ والتعبيرُ و**القناع** (`\x00`)، فسطرٌ فيه وسمان يُعَدّ نصّين لا نصًّا.
        $pattern = '/(?:'.self::ARABIC_LETTER.'|[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}])'.
            '[^<>{}@\n\x00]*/u';

        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return $runs;
        }

        foreach ($matches[0] as [$run, $offset]) {
            if ($this->hasArabic($run)) {
                $runs[] = ['offset' => $offset, 'text' => $this->trim($run)];
            }
        }

        return $runs;
    }

    private function hasArabic(string $text): bool
    {
        return preg_match_all('/'.self::ARABIC_LETTER.'/u', $text) >= self::MIN_LETTERS;
    }

    private function trim(string $text): string
    {
        $text = trim(preg_replace('/[\s\0]+/u', ' ', $text) ?? $text);

        return mb_strlen($text) > 60 ? mb_substr($text, 0, 57).'…' : $text;
    }

    /** يستبدل المطابقات بفراغٍ **يحفظ الإزاحات والأسطر** فتبقى الأرقام صحيحة */
    private function mask(string $source, string $pattern): string
    {
        return preg_replace_callback($pattern, fn (array $m) => $this->blank($m[0]), $source) ?? $source;
    }

    /**
     * يقنّع المطابقة **بعد** تسليم محتواها للمعالج مع إزاحته الحقيقيّة.
     * `$group = null` تعني «أوّل مجموعة غير فارغة» (لتعابير Blade بشكلَيها).
     */
    private function extract(string $source, string $pattern, ?int $group, callable $handler): string
    {
        return preg_replace_callback($pattern, function (array $matches) use ($group, $handler) {
            if ($group === 0) {
                $handler($matches[0][0], $matches[0][1]);

                return $this->blank($matches[0][0]);
            }

            if ($group !== null) {
                if (($matches[$group][1] ?? -1) >= 0) {
                    $handler($matches[$group][0], $matches[$group][1]);
                }

                return $this->blank($matches[0][0]);
            }

            for ($i = 1; $i < count($matches); $i++) {
                if (($matches[$i][1] ?? -1) >= 0 && $matches[$i][0] !== '') {
                    $handler($matches[$i][0], $matches[$i][1]);

                    break;
                }
            }

            return $this->blank($matches[0][0]);
        }, $source, -1, $count, PREG_OFFSET_CAPTURE) ?? $source;
    }

    /**
     * قناعٌ **بالبايت لا بالحرف** (بلا `u`): الحرف العربيّ بايتان، ولو قنّعناه
     * بحرفٍ واحد لانزاحت كلّ الإزاحات بعده فصارت أرقام الأسطر كذبًا.
     */
    private function blank(string $text): string
    {
        return preg_replace('/[^\n]/', "\0", $text) ?? $text;
    }

    private function lineAt(string $source, int $offset): int
    {
        return substr_count($source, "\n", 0, min($offset, strlen($source))) + 1;
    }

    private function unquote(string $literal): string
    {
        $quote = $literal[0] ?? '';

        return in_array($quote, ["'", '"'], true) ? substr($literal, 1, -1) : $literal;
    }

    // ================================================================= الملفّات

    /** @return list<string> */
    private function files(): array
    {
        $files = [];

        foreach ($this->roots ?? self::ROOTS as $root) {
            $path = $this->path($root);

            if (! is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }

                if ($this->excluded($this->relative($file->getPathname()))) {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function excluded(string $relative): bool
    {
        foreach (array_keys(self::EXCLUDED_PATHS) as $prefix) {
            if (str_starts_with($relative, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    private function path(string $relative): string
    {
        return rtrim($this->base(), '/').'/'.$relative;
    }

    private function relative(string $absolute): string
    {
        return str_replace(rtrim($this->base(), '/').'/', '', $absolute);
    }

    private function base(): string
    {
        return $this->basePath ?? base_path();
    }
}
