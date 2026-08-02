<?php

namespace App\Services\Library;

/**
 * تشكيل الحروف العربيّة للطباعة في الـPDF (خدمة القسم 9).
 *
 * **لماذا نحتاجها أصلًا؟** لأنّ الـPDF يرسم **الشكل** لا الحرف: لو كتبنا
 * «محمد» كما هي بلا تشكيل خرجت حروفًا منفصلة مقلوبة. المتصفّحات تفعل هذا
 * تلقائيًّا بمحرّك OpenType، **ونحن نولّد الملفّ على الخادم بلا أيّ مكتبة**،
 * فنقوم بالمهمّة بأنفسنا: تحويل كلّ حرف إلى صورته (مبدئيّة/وسطى/نهائيّة/منفردة)
 * من كتلة الأشكال العرضيّة U+FE70–FEFF، ثمّ ترتيب السطر بصريًّا من اليمين.
 *
 * ⭐ والنصّ المنطقيّ يبقى محفوظًا في خريطة `ToUnicode` داخل الـPDF،
 *   فبرامج الـATS تقرأ «محمد» صحيحةً لا أشكالًا — وهذا شرط «متوافق مع ATS» (9).
 */
class ArabicShaper
{
    /**
     * الحرف ⟵ [منفرد, نهائيّ, مبدئيّ, وسطيّ] — و`null` يعني أنّ الشكل غير موجود
     * (الحروف أحاديّة الاتّصال كالألف والدال لا مبدئيّ لها ولا وسطيّ).
     *
     * @var array<int, array<int, int|null>>
     */
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
        0x0640 => [0x0640, 0x0640, 0x0640, 0x0640], // التطويل يتّصل من الجهتين
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
        0x0671 => [0xFB50, 0xFB51, null, null],
    ];

    /** لام + ألف ⟵ حرف واحد (منفرد, نهائيّ) — تركيبة إلزاميّة في العربيّة */
    private const LAM_ALEF = [
        0x0622 => [0xFEF5, 0xFEF6],
        0x0623 => [0xFEF7, 0xFEF8],
        0x0625 => [0xFEF9, 0xFEFA],
        0x0627 => [0xFEFB, 0xFEFC],
    ];

    /**
     * تشكيل سطر وترتيبه بصريًّا.
     *
     * @return array<int, array{form:int, logical:array<int,int>, order:int}>
     *                                                                        الشكل المرسوم + الحروف المنطقيّة التي يمثّلها (للـToUnicode)
     *                                                                        + **رتبته المنطقيّة** — بها يكتب الـPDF النصّ بترتيب القراءة
     *                                                                        بينما يرسمه بترتيب العين، فيقرأ الـATS «محمد» لا «دمحم».
     */
    public function shape(string $line): array
    {
        $codes = $this->codepoints($line);
        $units = $this->joinLamAlef($codes);
        $shaped = [];

        foreach ($units as $i => $unit) {
            if ($unit['ligature'] !== null) {
                // اللام-ألف: نهائيّة لو سبقها حرف يتّصل، وإلّا منفردة
                $final = $this->connectsBefore($units, $i);
                $shaped[] = [
                    'form' => self::LAM_ALEF[$unit['ligature']][$final ? 1 : 0],
                    'logical' => $unit['logical'],
                    'order' => $i,
                ];

                continue;
            }

            $code = $unit['logical'][0];

            if (! isset(self::FORMS[$code])) {
                $shaped[] = ['form' => $code, 'logical' => $unit['logical'], 'order' => $i];

                continue;
            }

            $forms = self::FORMS[$code];
            $before = $this->connectsBefore($units, $i);
            $after = $this->connectsAfter($units, $i);

            $index = match (true) {
                $before && $after && $forms[3] !== null => 3,
                $before && $forms[1] !== null => 1,
                $after && $forms[2] !== null => 2,
                default => 0,
            };

            $shaped[] = ['form' => $forms[$index] ?? $code, 'logical' => $unit['logical'], 'order' => $i];
        }

        return $this->visualOrder($shaped);
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return array<int,int> */
    private function codepoints(string $text): array
    {
        $codes = [];
        $length = mb_strlen($text, 'UTF-8');

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $codes[] = (int) hexdec(bin2hex(mb_convert_encoding($char, 'UCS-4BE', 'UTF-8')));
        }

        return $codes;
    }

    /** @return array<int, array{logical:array<int,int>, ligature:int|null}> */
    private function joinLamAlef(array $codes): array
    {
        $units = [];
        $count = count($codes);

        for ($i = 0; $i < $count; $i++) {
            $next = $codes[$i + 1] ?? null;

            if ($codes[$i] === 0x0644 && $next !== null && isset(self::LAM_ALEF[$next])) {
                $units[] = ['logical' => [0x0644, $next], 'ligature' => $next];
                $i++;

                continue;
            }

            $units[] = ['logical' => [$codes[$i]], 'ligature' => null];
        }

        return $units;
    }

    /** هل يتّصل بما قبله؟ — أي هل الحرف السابق ثنائيّ الاتّصال */
    private function connectsBefore(array $units, int $index): bool
    {
        $previous = $units[$index - 1] ?? null;

        if ($previous === null || $previous['ligature'] !== null) {
            return false; // اللام-ألف لا تتّصل بما بعدها
        }

        $code = $previous['logical'][0];

        return isset(self::FORMS[$code]) && self::FORMS[$code][2] !== null;
    }

    /** هل يتّصل بما بعده؟ — الحرف الحاليّ ثنائيّ الاتّصال والتالي عربيّ */
    private function connectsAfter(array $units, int $index): bool
    {
        $code = $units[$index]['logical'][0];

        if (! isset(self::FORMS[$code]) || self::FORMS[$code][2] === null) {
            return false;
        }

        $next = $units[$index + 1] ?? null;

        if ($next === null) {
            return false;
        }

        return $next['ligature'] !== null || isset(self::FORMS[$next['logical'][0]]);
    }

    /**
     * الترتيب البصريّ المبسَّط: مقاطع العربيّة تُعكَس، ومقاطع اللاتينيّة والأرقام
     * تبقى كما هي، وترتيب المقاطع نفسه يُعكَس لأنّ اتّجاه الفقرة من اليمين.
     */
    private function visualOrder(array $shaped): array
    {
        $runs = [];
        $current = null;

        foreach ($shaped as $glyph) {
            $isArabic = $glyph['form'] >= 0x0600 && $glyph['form'] <= 0xFEFF;
            $type = $isArabic ? 'rtl' : 'ltr';

            if ($current === null || $current['type'] !== $type) {
                if ($current !== null) {
                    $runs[] = $current;
                }

                $current = ['type' => $type, 'glyphs' => []];
            }

            $current['glyphs'][] = $glyph;
        }

        if ($current !== null) {
            $runs[] = $current;
        }

        // لا عربيّة في السطر ⟵ سطر لاتينيّ خالص يُترَك كما هو
        if (! collect($runs)->contains(fn ($run) => $run['type'] === 'rtl')) {
            return $shaped;
        }

        $out = [];

        foreach (array_reverse($runs) as $run) {
            $glyphs = $run['type'] === 'rtl' ? array_reverse($run['glyphs']) : $run['glyphs'];

            foreach ($glyphs as $glyph) {
                $out[] = $glyph;
            }
        }

        return $out;
    }
}
