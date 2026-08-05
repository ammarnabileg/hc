<?php

namespace App\Services\Admin\System;

use FilesystemIterator;
use Illuminate\Support\Collection;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * ماسحُ المفاتيح: **ما يقرؤه الكود فعلًا** من `setting()` — لا ما زُرِع في القاعدة.
 *
 * لماذا يمسح الكود لا القاعدة؟ لأنّ سؤال «هل لكلّ مجموعة موجودة تابٌ؟» يمرّ
 * دائمًا: المجموعة لا توجد إلّا لو زُرِعت، والمزروع له تابه. حارسٌ يمرّ دائمًا
 * أسوأ من غياب حارس — يمنح ثقةً كاذبة ويوقف البحث. السؤال الصحيح معكوس:
 * **كلّ مفتاح يقرؤه الكود، هل له صفٌّ بعد `DatabaseSeeder`؟** فالمفتاح بلا صفّ
 * يأخذ الافتراضيّ المكتوب في الكود — رقمٌ محروق بخطوة إضافيّة (2.13-ب).
 */
class SettingKeyScanner
{
    /** المجلّدات التي يعيش فيها كود القراءة — نسبةً لجذر المشروع */
    private const ROOTS = ['app', 'resources', 'routes'];

    /** القراءة المباشرة — والمجموعة الثالثة تكشف الوصل (`'ads.events.'.$name`) */
    private const DIRECT = '/\bsetting\(\s*([\'"])((?:(?!\1).)*)\1(\s*\.)?/';

    /**
     * معالج التنصيب يقرأ الإعدادات نفسها بغلافٍ لا يكسر الشاشة قبل وجود
     * القاعدة، فمفاتيحه مفاتيحُ إعدادات كاملة.
     */
    private const WRAPPED = '/\bSetupSettings::(?:get|text|number|flag|list)\(\s*([\'"])((?:(?!\1).)*)\1(\s*\.)?/';

    /** مفتاح من متغيّر — يُعَدّ ولا يُحسَب ناقصًا */
    private const VARIABLE = '/\bsetting\(\s*\$/';

    /**
     * ⭐ مفتاحان حرفيّان في تعبير ثلاثيّ: `setting($cond ? 'a' : 'b', …)` — شكلٌ
     * شائع لرسالتين حسب حالة («جدولتُ» / «أُرسل الآن») لا يطابقه `DIRECT` لأنّ
     * أوّل ما بعد القوس ليس علامة اقتباس. كلا المفتاحين حرفيّان فيُحسَبان معًا.
     */
    private const TERNARY = '/\bsetting\(\s*[^,()]*?\?\s*([\'"])((?:(?!\1).)*)\1\s*:\s*([\'"])((?:(?!\3).)*)\3/';

    /**
     * ⚠️ **حدود هذا الماسح — موثَّقة لا مسكوتٌ عنها:** مفتاحٌ يصل إلى `setting()`
     * عبر **معامل دالّةٍ وسيطة** (مثل `positive(string $key)` تنادي
     * `setting($key)` داخلها، والمتّصل يمرّر الحرفَ لـ`positive('finance.…')`
     * لا لـ`setting()` نفسها) **لا يُكتَشف**: تتبّع تدفّق قيمةٍ عبر استدعاءين
     * يحتاج تحليلًا نحويًّا كاملًا (AST) لا مطابقة نمطٍ نصّيّ، وهو تعقيدٌ لا
     * يستحقّه ماسحٌ غرضُه سرعة الفحص لا دقّة مُصرِّف. **العلاج المعتمَد بدل
     * توسيع الماسح:** صاحب الميثود الوسيطة يُسجِّل مفاتيحه في ثابتٍ عامّ
     * (`MESSAGE_KEYS` ونحوه) يضمّه `SettingsCoverage::deadKeys()` كتالوجًا —
     * انظر `PurchaseException::MESSAGE_KEYS` و`CartService::FAIL_MESSAGE_KEYS`
     * و`ExchangeRates::keys()` أمثلةً قائمة.
     */

    /**
     * شكل المفتاح المعتمَد (2.13-و): `المجال.الميزة.المفتاح` — حروفٌ صغيرة
     * ونقاط. ما لا يطابقه ليس مفتاحًا (نصّ توثيق أو وسيط آخر) فلا يُحاسَب عليه.
     */
    private const KEY_SHAPE = '/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/';

    /** موضع المتغيّر داخل نصّ مركَّب: `{$x}` أو `$x` أو `$x->y` أو `$x['y']` */
    private const PLACEHOLDER = '/\{?\$[A-Za-z_][A-Za-z0-9_]*(?:(?:->|\[)[^\]}\s]*\]?)*\}?/';

    /** علامة الوصل: `setting('ads.events.'.$name)` — بادئةٌ لا مفتاح */
    private const CONCAT_MARK = '$…';

    /**
     * الافتراضيّ الحرفيّ في موضع القراءة: `setting('key', <هنا>)`.
     * نقبل النصّ والرقم والمنطقيّ والمصفوفة الفارغة وحدها — وأيّ تعبيرٍ أعقد
     * (استدعاء أو متغيّر) لا يُخمَّن، لأنّ افتراضيًّا مخمَّنًا يغيّر السلوك.
     */
    private const LITERAL = '(?:\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\$]|\\\\.)*"|-?\d+(?:\.\d+)?|true|false|\[\s*\])';

    /**
     * الكاش ساكنٌ لا لكلّ نسخة: المسح يقرأ ألوف الملفّات، والملفّات لا تتغيّر
     * أثناء تشغيلٍ واحد — فنسخةٌ ثانية من الماسح لا تدفع الثمن مرّتين.
     *
     * @var array{keys:array<string,list<string>>, dynamic:array<string,list<string>>, variable:int}|null
     */
    private static ?array $scan = null;

    /** @var array<string,string>|null */
    private static ?array $defaults = null;

    /**
     * المفاتيح الحرفيّة ⟵ مواضع قراءتها.
     *
     * @return array<string, list<string>>
     */
    public function keys(): array
    {
        return $this->scan()['keys'];
    }

    /**
     * الأنماط المركَّبة ديناميكيًّا (`"prefix.{$x}.suffix"`) ⟵ مواضعها.
     * لا تُحسَب ناقصةً: مفتاحها لا يُعرَف إلّا وقت التشغيل.
     *
     * @return array<string, list<string>>
     */
    public function dynamicPatterns(): array
    {
        return $this->scan()['dynamic'];
    }

    /** عدد مواضع `setting($variable)` — مفاتيحها تأتي من كتالوج لا من نصّ */
    public function variableCallSites(): int
    {
        return $this->scan()['variable'];
    }

    /**
     * نمطٌ مركَّب بلا أيّ صفّ يطابقه — الوحيد الذي يُعتبَر فجوةً حقيقيّة فيها.
     *
     * @param  list<string>  $seededKeys
     * @return Collection<string, list<string>>
     */
    public function unmatchedDynamicPatterns(array $seededKeys): Collection
    {
        return collect($this->dynamicPatterns())
            ->reject(function (array $places, string $pattern) use ($seededKeys) {
                $regex = $this->patternToRegex($pattern);

                foreach ($seededKeys as $key) {
                    if (preg_match($regex, $key) === 1) {
                        return true;
                    }
                }

                return false;
            });
    }

    /** هل يطابق هذا المفتاحُ نمطًا مركَّبًا وقت التشغيل؟ (فيكون مقروءًا لا ميّتًا) */
    public function matchesAnyPattern(string $key): bool
    {
        foreach (array_keys($this->dynamicPatterns()) as $pattern) {
            if (preg_match($this->patternToRegex($pattern), $key) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * مفاتيح يقرؤها الكود ولا صفّ لها — قلب الفحص.
     *
     * @param  list<string>  $seededKeys
     * @return Collection<string, list<string>>
     */
    public function missing(array $seededKeys): Collection
    {
        $seeded = array_flip($seededKeys);

        return collect($this->keys())
            ->reject(fn (array $places, string $key) => isset($seeded[$key]))
            ->sortKeys();
    }

    /**
     * المفتاح ⟵ **افتراضيّه المكتوب في الكود**، مخزَّنًا بصيغة عمود `value`.
     *
     * هذا ما يجعل الزرعَ بلا أثرٍ على السلوك: القيمة المزروعة هي بعينها التي
     * كان `setting()` سيرجّعها حين لا يجد صفًّا. والمفتاح الذي لا نستطيع قراءة
     * افتراضيّه حرفيًّا لا يُخمَّن — يُترَك ليعلنه مجاله بيده.
     *
     * @return array<string, string>
     */
    public function codeDefaults(): array
    {
        if (self::$defaults !== null) {
            return self::$defaults;
        }

        $defaults = [];

        foreach ($this->keys() as $key => $places) {
            foreach ($places as $place) {
                $source = @file_get_contents(base_path($place));

                if ($source === false) {
                    continue;
                }

                $regex = '/\bsetting\(\s*([\'"])'.preg_quote($key, '/').'\1\s*,\s*('.self::LITERAL.')\s*[,)]/';

                if (preg_match($regex, $source, $match) === 1) {
                    $defaults[$key] = $this->normalize($match[2]);

                    break;
                }
            }
        }

        return self::$defaults = $defaults;
    }

    /** الحرفيّ كما كُتِب ⟵ نصُّ عمود `value` الذي يعيده `setting()` كما هو */
    private function normalize(string $literal): string
    {
        $literal = trim($literal);

        if ($literal === 'true') {
            return '1';
        }

        if ($literal === 'false') {
            return '0';
        }

        if (str_starts_with($literal, '[')) {
            return '[]';
        }

        if (str_starts_with($literal, "'") || str_starts_with($literal, '"')) {
            $quote = $literal[0];
            $inner = substr($literal, 1, -1);

            // فكّ التهريب: `\'` و`\\` في النصّ المفرد، ومعهما `\n` و`\t` في المزدوج
            return $quote === "'"
                ? str_replace(["\\'", '\\\\'], ["'", '\\'], $inner)
                : str_replace(['\\"', '\\n', '\\t', '\\\\'], ['"', "\n", "\t", '\\'], $inner);
        }

        return $literal;
    }

    /** `dashboard.achievements.{$key}.base` ⟵ `/^dashboard\.achievements\.[^.]+\.base$/` */
    private function patternToRegex(string $pattern): string
    {
        // الذيل الموصول قد يحمل نقاطًا («ads.events.» + «course.viewed»)،
        // أمّا المتغيّر داخل النصّ فجزءٌ واحد فلا يبتلع النقاط.
        $tail = str_ends_with($pattern, self::CONCAT_MARK);
        $body = $tail ? substr($pattern, 0, -strlen(self::CONCAT_MARK)) : $pattern;

        $masked = preg_replace(self::PLACEHOLDER, "\0", $body) ?? $body;

        // نقتطع أوّلًا ثمّ نهرّب كلّ قطعة: `preg_quote` يحوّل البايت الصفريّ نفسه
        $parts = array_map(
            fn (string $part) => preg_quote($part, '/'),
            explode("\0", $masked),
        );

        return '/^'.implode('[^.]+', $parts).($tail ? '.+' : '').'$/u';
    }

    /** @return array{keys:array<string,list<string>>, dynamic:array<string,list<string>>, variable:int} */
    private function scan(): array
    {
        if (self::$scan !== null) {
            return self::$scan;
        }

        $keys = [];
        $dynamic = [];
        $variable = 0;

        foreach (self::ROOTS as $root) {
            $path = base_path($root);

            if (! is_dir($path)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($files as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }

                $source = file_get_contents($file->getPathname());

                if ($source === false) {
                    continue;
                }

                $place = str_replace(base_path().'/', '', $file->getPathname());

                foreach ([self::DIRECT, self::WRAPPED] as $regex) {
                    preg_match_all($regex, $source, $matches, PREG_SET_ORDER);

                    foreach ($matches as $match) {
                        $key = $match[2];

                        // نصٌّ موصولٌ بمتغيّر: `'ads.events.'.$name` — بادئةٌ لا مفتاح
                        if (($match[3] ?? '') !== '') {
                            $dynamic[$key.self::CONCAT_MARK][] = $place;

                            continue;
                        }

                        if (str_contains($key, '$')) {
                            $dynamic[$key][] = $place;

                            continue;
                        }

                        // ما لا يطابق شكل المفتاح ليس مفتاحًا — لا يُحاسَب ولا يُهمَل زورًا
                        if (preg_match(self::KEY_SHAPE, $key) === 1) {
                            $keys[$key][] = $place;
                        }
                    }
                }

                preg_match_all(self::TERNARY, $source, $ternaryMatches, PREG_SET_ORDER);

                foreach ($ternaryMatches as $match) {
                    foreach ([$match[2], $match[4]] as $key) {
                        if (preg_match(self::KEY_SHAPE, $key) === 1) {
                            $keys[$key][] = $place;
                        }
                    }
                }

                $variable += preg_match_all(self::VARIABLE, $source);
            }
        }

        ksort($keys);
        ksort($dynamic);

        return self::$scan = ['keys' => $keys, 'dynamic' => $dynamic, 'variable' => $variable];
    }
}
