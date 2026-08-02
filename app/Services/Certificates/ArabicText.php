<?php

namespace App\Services\Certificates;

/**
 * تشكيل النصّ العربيّ للرسم بـGD (8 · 12.5-ب).
 *
 * لماذا: GD يرسم الحروف كما وردت من اليسار لليمين، ولا يعرف وصل العربيّة ولا اتّجاهها.
 * فنحوّل كلّ حرف إلى صورته السياقيّة (Arabic Presentation Forms-B) ثمّ نعكس السطر،
 * فتخرج الشهادة بخطّ عربيّ سليم **بلا أيّ مكتبة خارجيّة** (الشبكة محجوبة).
 */
final class ArabicText
{
    /** لكلّ حرف: [منفصل، نهائيّ، ابتدائيّ، وسطيّ] — و null يعني أنّ الحرف لا يقبل هذه الصورة */
    private const FORMS = [
        0x0621 => [0xFE80, null, null, null],
        0x0622 => [0xFE81, 0xFE82, null, null],
        0x0623 => [0xFE83, 0xFE84, null, null],
        0x0624 => [0xFE85, 0xFE86, null, null],
        0x0625 => [0xFE87, 0xFE88, null, null],
        0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C],
        0x0627 => [0xFE8D, 0xFE8E, null, null],
        0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92],
        0x0629 => [0xFE93, 0xFE94, null, null],
        0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98],
        0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C],
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0],
        0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4],
        0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8],
        0x062F => [0xFEA9, 0xFEAA, null, null],
        0x0630 => [0xFEAB, 0xFEAC, null, null],
        0x0631 => [0xFEAD, 0xFEAE, null, null],
        0x0632 => [0xFEAF, 0xFEB0, null, null],
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4],
        0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8],
        0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC],
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0],
        0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4],
        0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC],
        0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0],
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4],
        0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8],
        0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC],
        0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4],
        0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8],
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC],
        0x0648 => [0xFEED, 0xFEEE, null, null],
        0x0649 => [0xFEEF, 0xFEF0, null, null],
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],
        // حروف فارسيّة/أردية شائعة في الأسماء
        0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59],
        0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D],
        0x0698 => [0xFB8A, 0xFB8B, null, null],
        0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91],
        0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95],
        0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF],
    ];

    private const LAM = 0x0644;

    /** لام + ألف: رباطٌ واجبٌ في العربيّة — [منفصل، نهائيّ] */
    private const LAM_ALEF = [
        0x0622 => [0xFEF5, 0xFEF6],
        0x0623 => [0xFEF7, 0xFEF8],
        0x0625 => [0xFEF9, 0xFEFA],
        0x0627 => [0xFEFB, 0xFEFC],
    ];

    /** الأقواس تُعكَس مع عكس السطر وإلّا انقلب معناها البصريّ */
    private const MIRROR = [
        0x0028 => 0x0029, 0x0029 => 0x0028,
        0x005B => 0x005D, 0x005D => 0x005B,
        0x007B => 0x007D, 0x007D => 0x007B,
        0x003C => 0x003E, 0x003E => 0x003C,
    ];

    /** هل النصّ يحوي حرفًا عربيًّا أصلًا؟ */
    public static function hasArabic(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}]/u', $text);
    }

    /**
     * يحوّل النصّ العربيّ إلى سلسلة جاهزة لـ`imagettftext`.
     * والنصّ اللاتينيّ يعود كما هو بلا مساس.
     */
    public static function prepare(string $text, bool $stripTashkeel = true): string
    {
        if (! self::hasArabic($text)) {
            return $text;
        }

        $codes = self::toCodePoints($text);

        if ($stripTashkeel) {
            $codes = array_values(array_filter($codes, fn (int $c) => ! self::isMark($c)));
        }

        $codes = self::joinLamAlef($codes);
        $shaped = self::shape($codes);

        return self::toString(self::reorder($shaped));
    }

    /** يلفّ النصّ في أسطر بحدّ أقصى للعرض بالحروف (قبل التشكيل) */
    public static function wrap(string $text, int $charsPerLine): array
    {
        if ($charsPerLine < 1) {
            return [$text];
        }

        $lines = [];

        foreach (preg_split('/\R/u', $text) ?: [$text] as $paragraph) {
            $line = '';

            foreach (preg_split('/\s+/u', trim($paragraph)) ?: [] as $word) {
                $candidate = $line === '' ? $word : $line.' '.$word;

                if (mb_strlen($candidate) > $charsPerLine && $line !== '') {
                    $lines[] = $line;
                    $line = $word;
                } else {
                    $line = $candidate;
                }
            }

            $lines[] = $line;
        }

        return $lines;
    }

    // ------------------------------------------------------------ الداخل

    /** @return list<int> */
    private static function toCodePoints(string $text): array
    {
        $codes = [];

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $codes[] = mb_ord($char, 'UTF-8');
        }

        return $codes;
    }

    /** @param  list<int>  $codes */
    private static function toString(array $codes): string
    {
        $out = '';

        foreach ($codes as $code) {
            $out .= mb_chr($code, 'UTF-8');
        }

        return $out;
    }

    /** علامات التشكيل شفّافة في الوصل — والقاعدة أن نسقطها كي لا تتزحزح فوق الحروف */
    private static function isMark(int $code): bool
    {
        return ($code >= 0x0610 && $code <= 0x061A)
            || ($code >= 0x064B && $code <= 0x065F)
            || $code === 0x0670
            || ($code >= 0x06D6 && $code <= 0x06ED);
    }

    /** @param  list<int>  $codes  @return list<array{0:int,1:int|null}> */
    private static function joinLamAlef(array $codes): array
    {
        $out = [];
        $count = count($codes);

        for ($i = 0; $i < $count; $i++) {
            if ($codes[$i] === self::LAM && isset($codes[$i + 1], self::LAM_ALEF[$codes[$i + 1]])) {
                $out[] = ['lig', self::LAM_ALEF[$codes[$i + 1]]];
                $i++;

                continue;
            }

            $out[] = ['chr', $codes[$i]];
        }

        return $out;
    }

    /**
     * الصورة السياقيّة لكلّ حرف: وسطيّ إن اتّصل بالجانبين، ونهائيّ إن اتّصل بما قبله فقط،
     * وابتدائيّ إن اتّصل بما بعده فقط، وإلّا منفصل.
     *
     * @param  list<array>  $items
     * @return list<int>
     */
    private static function shape(array $items): array
    {
        $out = [];
        $count = count($items);

        for ($i = 0; $i < $count; $i++) {
            [$kind, $value] = $items[$i];

            $prevConnects = $i > 0 && self::connectsForward($items[$i - 1]);
            $nextConnects = $i + 1 < $count && self::isArabicItem($items[$i + 1]);

            if ($kind === 'lig') {
                $out[] = $prevConnects ? $value[1] : $value[0];

                continue;
            }

            $forms = self::FORMS[$value] ?? null;

            if ($forms === null) {
                $out[] = $value;

                continue;
            }

            $out[] = match (true) {
                $prevConnects && $nextConnects && $forms[3] !== null => $forms[3],
                $prevConnects && $forms[1] !== null => $forms[1],
                $nextConnects && $forms[2] !== null => $forms[2],
                default => $forms[0],
            };
        }

        return $out;
    }

    private static function isArabicItem(array $item): bool
    {
        return $item[0] === 'lig' || isset(self::FORMS[$item[1]]);
    }

    /** الحرف يصل بما بعده فقط إن كانت له صورة ابتدائيّة (والرباط لا يصل بما بعده) */
    private static function connectsForward(array $item): bool
    {
        if ($item[0] === 'lig') {
            return false;
        }

        return (self::FORMS[$item[1]] ?? [null, null, null, null])[2] !== null;
    }

    /**
     * ترتيب بصريّ مبسّط: نعكس السطر كلّه ثمّ نعيد المقاطع اللاتينيّة/الرقميّة إلى اتّجاهها.
     *
     * @param  list<int>  $codes
     * @return list<int>
     */
    private static function reorder(array $codes): array
    {
        $out = array_reverse($codes);
        $count = count($out);

        for ($i = 0; $i < $count; $i++) {
            $out[$i] = self::MIRROR[$out[$i]] ?? $out[$i];
        }

        $i = 0;

        while ($i < $count) {
            if (! self::isLtr($out[$i])) {
                $i++;

                continue;
            }

            $end = $i;

            for ($j = $i + 1; $j < $count; $j++) {
                if (self::isLtr($out[$j])) {
                    $end = $j;

                    continue;
                }

                if (self::isNeutral($out[$j]) && isset($out[$j + 1]) && self::isLtr($out[$j + 1])) {
                    continue;
                }

                break;
            }

            if ($end > $i) {
                array_splice($out, $i, $end - $i + 1, array_reverse(array_slice($out, $i, $end - $i + 1)));
            }

            $i = $end + 1;
        }

        return $out;
    }

    private static function isLtr(int $code): bool
    {
        return ($code >= 0x0030 && $code <= 0x0039)   // أرقام
            || ($code >= 0x0041 && $code <= 0x005A)   // لاتينيّ كبير
            || ($code >= 0x0061 && $code <= 0x007A);  // لاتينيّ صغير
    }

    private static function isNeutral(int $code): bool
    {
        return in_array($code, [0x0020, 0x002D, 0x002E, 0x002F, 0x003A, 0x005F, 0x0023], true);
    }
}
