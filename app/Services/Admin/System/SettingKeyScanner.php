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

    /** `setting('key')` — القراءة المباشرة */
    private const DIRECT = '/\bsetting\(\s*([\'"])((?:(?!\1).)*)\1/';

    /**
     * `SetupSettings::text('key', …)` — معالج التنصيب يقرأ الإعدادات نفسها
     * بغلافٍ لا يكسر الشاشة قبل وجود القاعدة، فمفاتيحه مفاتيحُ إعدادات كاملة.
     */
    private const WRAPPED = '/\bSetupSettings::(?:get|text|number|flag|list)\(\s*([\'"])((?:(?!\1).)*)\1/';

    /** `setting($key)` — مفتاح من متغيّر: يُعَدّ ولا يُحسَب ناقصًا */
    private const VARIABLE = '/\bsetting\(\s*\$/';

    /** @var array{keys:array<string,list<string>>, dynamic:array<string,list<string>>, variable:int}|null */
    private ?array $scan = null;

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

    /** `dashboard.achievements.{$key}.base` ⟵ `/^dashboard\.achievements\.[^.]+\.base$/` */
    private function patternToRegex(string $pattern): string
    {
        // الجزء المتغيّر جزءٌ واحد من المفتاح، فلا يبتلع النقاط
        $literal = preg_replace('/\{?\$[A-Za-z_][A-Za-z0-9_\->\[\]\'"]*\}?/', "\0", $pattern) ?? $pattern;

        return '/^'.str_replace("\0", '[^.]+', preg_quote($literal, '/')).'$/u';
    }

    /** @return array{keys:array<string,list<string>>, dynamic:array<string,list<string>>, variable:int} */
    private function scan(): array
    {
        if ($this->scan !== null) {
            return $this->scan;
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
                        $bucket = str_contains($key, '$') ? 'dynamic' : 'keys';

                        if ($bucket === 'dynamic') {
                            $dynamic[$key][] = $place;
                        } else {
                            $keys[$key][] = $place;
                        }
                    }
                }

                $variable += preg_match_all(self::VARIABLE, $source);
            }
        }

        ksort($keys);
        ksort($dynamic);

        return $this->scan = ['keys' => $keys, 'dynamic' => $dynamic, 'variable' => $variable];
    }
}
