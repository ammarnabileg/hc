<?php

namespace App\Services\Events;

use InvalidArgumentException;

/**
 * مُرمِّز QR مكتوب بأيدينا سطرًا سطرًا — **بلا أيّ مكتبة خارجيّة وبلا أصلٍ من
 * شبكة** (قاعدة المشروع: الأيقونات SVG مرسومة، ولا اعتماد خارجيّ).
 *
 * لماذا مُرمِّزٌ كامل ولا شبكةٌ تشبه الـQR؟ لأنّ الدستور ينصّ في 13.3 على
 * «**تشيك-إن بـ QR**» للأوفلاين، وفي 12.11 على «**QR ديناميكيّ للتشيك-إن**
 * يمنع استخدام كود شخص لآخر». ومربّعاتٌ تشبه الـQR ولا تُقرأ بكاميرا = ميزةٌ
 * وهميّة تمرّ في اللقطة وتسقط في القاعة. فهذا الصنف يخرج **مصفوفةً مطابقة
 * للمواصفة** (ISO/IEC 18004) تقرؤها كاميرا أيّ هاتف.
 *
 * النطاق المبنيّ عمدًا: **نمط البايت · تصحيح مستوى L · الإصدارات 1–10 ·
 * القناع 0**. ولماذا قناعٌ واحد؟ لأنّ المواصفة تُلزِم بأن تكون **بتات الصيغة
 * مطابقة للقناع المستعمَل** — وهي كذلك هنا — ولا تُلزِم القارئ بقناعٍ بعينه؛
 * واختيار «الأفضل بالعقوبات» تحسينٌ بصريّ لا شرطُ قراءة. وما زاد عن سعة
 * الإصدار 10 (271 بايتًا) يُرمى صراحةً بدل أن يخرج رمزًا مبتورًا.
 */
class QrMatrix
{
    /** توزيع الكتل ومُصحِّحاتها لمستوى L (المواصفة — الجدول 13 والجدول 9) */
    private const VERSIONS = [
        1 => ['ec' => 7, 'blocks' => [[1, 19]]],
        2 => ['ec' => 10, 'blocks' => [[1, 34]]],
        3 => ['ec' => 15, 'blocks' => [[1, 55]]],
        4 => ['ec' => 20, 'blocks' => [[1, 80]]],
        5 => ['ec' => 26, 'blocks' => [[1, 108]]],
        6 => ['ec' => 18, 'blocks' => [[2, 68]]],
        7 => ['ec' => 20, 'blocks' => [[2, 78]]],
        8 => ['ec' => 24, 'blocks' => [[2, 97]]],
        9 => ['ec' => 30, 'blocks' => [[2, 116]]],
        10 => ['ec' => 18, 'blocks' => [[2, 68], [2, 69]]],
    ];

    /** مراكز أنماط المحاذاة لكلّ إصدار (المواصفة — الملحق E) */
    private const ALIGNMENT = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
        7 => [6, 22, 38],
        8 => [6, 24, 42],
        9 => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    /** مستوى التصحيح L = 01 في بتات الصيغة */
    private const EC_LEVEL_BITS = 1;

    /** القناع المستعمَل — ومطابقتُه لبتات الصيغة هي شرط القراءة */
    private const MASK = 0;

    /** @var array<int, array<int, bool>> */
    private array $modules = [];

    /** @var array<int, array<int, bool>> */
    private array $reserved = [];

    private int $size;

    private function __construct(private readonly int $version)
    {
        $this->size = 17 + 4 * $version;

        for ($row = 0; $row < $this->size; $row++) {
            $this->modules[$row] = array_fill(0, $this->size, false);
            $this->reserved[$row] = array_fill(0, $this->size, false);
        }
    }

    /**
     * المصفوفة النهائيّة: `true` = وحدةٌ داكنة.
     *
     * @return array<int, array<int, bool>>
     */
    public static function forText(string $text): array
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        $version = self::versionFor(count($bytes));

        $code = new self($version);
        $code->drawFunctionPatterns();
        $code->drawCodewords($code->interleave($bytes));
        $code->applyMask();
        $code->drawFormatBits();

        return $code->modules;
    }

    /**
     * الرمز كـSVG مرسوم — بلا صورةٍ ولا خطٍّ خارجيّ، فيُطبَع ويُصوَّر كما هو.
     *
     * @param  int  $box  ضلع الوحدة بالبكسل
     * @param  int  $quiet  المنطقة الهادئة بالوحدات (المواصفة تطلب 4)
     */
    public static function svg(string $text, int $box, int $quiet, string $dark, string $light, string $title): string
    {
        $matrix = self::forText($text);
        $size = count($matrix);
        $side = ($size + 2 * $quiet) * $box;

        $path = '';

        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                if ($matrix[$row][$col]) {
                    $x = ($col + $quiet) * $box;
                    $y = ($row + $quiet) * $box;
                    $path .= 'M'.$x.' '.$y.'h'.$box.'v'.$box.'h-'.$box.'z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$side.' '.$side.'" '.
            'width="'.$side.'" height="'.$side.'" role="img" aria-label="'.e($title).'" shape-rendering="crispEdges">'.
            '<title>'.e($title).'</title>'.
            '<rect width="'.$side.'" height="'.$side.'" fill="'.e($light).'"/>'.
            '<path d="'.$path.'" fill="'.e($dark).'"/>'.
            '</svg>';
    }

    /** أصغر إصدارٍ يسع الحمولة — وما زاد يُرمى بدل أن يخرج رمزًا مبتورًا */
    public static function versionFor(int $bytes): int
    {
        foreach (array_keys(self::VERSIONS) as $version) {
            if ($bytes <= self::capacity($version)) {
                return $version;
            }
        }

        throw new InvalidArgumentException('QR payload too long: '.$bytes.' bytes');
    }

    /** سعة الإصدار بالبايت في نمط البايت (رأسٌ 4 بتات + عدّاد 8 أو 16) */
    public static function capacity(int $version): int
    {
        $data = 0;

        foreach (self::VERSIONS[$version]['blocks'] as [$count, $length]) {
            $data += $count * $length;
        }

        $header = 4 + self::countBits($version);

        return (int) floor(($data * 8 - $header) / 8);
    }

    private static function countBits(int $version): int
    {
        return $version < 10 ? 8 : 16;
    }

    // ================================================== الترميز وتصحيح الأخطاء

    /**
     * تدفّق البتات ⟵ كلمات بيانات ⟵ كتلٌ بمصحّحاتها ⟵ تشابكٌ نهائيّ.
     *
     * @param  list<int>  $bytes
     * @return list<int>
     */
    private function interleave(array $bytes): array
    {
        $spec = self::VERSIONS[$this->version];
        $data = $this->dataCodewords($bytes);
        $blocks = [];
        $offset = 0;

        foreach ($spec['blocks'] as [$count, $length]) {
            for ($i = 0; $i < $count; $i++) {
                $chunk = array_slice($data, $offset, $length);
                $offset += $length;
                $blocks[] = ['data' => $chunk, 'ec' => $this->remainder($chunk, $spec['ec'])];
            }
        }

        $longest = 0;

        foreach ($blocks as $block) {
            $longest = max($longest, count($block['data']));
        }

        $result = [];

        for ($i = 0; $i < $longest; $i++) {
            foreach ($blocks as $block) {
                if ($i < count($block['data'])) {
                    $result[] = $block['data'][$i];
                }
            }
        }

        for ($i = 0; $i < $spec['ec']; $i++) {
            foreach ($blocks as $block) {
                $result[] = $block['ec'][$i];
            }
        }

        return $result;
    }

    /**
     * @param  list<int>  $bytes
     * @return list<int>
     */
    private function dataCodewords(array $bytes): array
    {
        $total = 0;

        foreach (self::VERSIONS[$this->version]['blocks'] as [$count, $length]) {
            $total += $count * $length;
        }

        $bits = '0100';                                             // نمط البايت
        $bits .= str_pad(decbin(count($bytes)), self::countBits($this->version), '0', STR_PAD_LEFT);

        foreach ($bytes as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $capacity = $total * 8;
        $bits .= str_repeat('0', min(4, $capacity - strlen($bits)));  // مُنهٍ
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);        // إلى حدّ البايت

        $codewords = [];

        foreach (str_split($bits, 8) as $chunk) {
            $codewords[] = bindec($chunk);
        }

        // حشوٌ متناوب حتى تمتلئ كلمات البيانات (المواصفة: 0xEC ثمّ 0x11)
        $pad = [0xEC, 0x11];
        $filled = count($codewords);

        for ($i = $filled; $i < $total; $i++) {
            $codewords[$i] = $pad[($i - $filled) % 2];
        }

        return array_values($codewords);
    }

    /**
     * باقي القسمة على مولّد ريد-سولومون في GF(256) — وهو كلمات التصحيح.
     *
     * @param  list<int>  $data
     * @return list<int>
     */
    private function remainder(array $data, int $degree): array
    {
        $divisor = $this->divisor($degree);
        $result = array_fill(0, $degree, 0);

        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($result);
            $result[] = 0;

            foreach ($divisor as $i => $coefficient) {
                $result[$i] ^= $this->multiply($coefficient, $factor);
            }
        }

        return array_values($result);
    }

    /** @return list<int> */
    private function divisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;

        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = $this->multiply($result[$j], $root);

                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }

            $root = $this->multiply($root, 2);
        }

        return $result;
    }

    /** ضربٌ في GF(256) بالكثيرة الأوّليّة 0x11D */
    private function multiply(int $x, int $y): int
    {
        $z = 0;

        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }

        return $z & 0xFF;
    }

    // ========================================================= رسم المصفوفة

    private function drawFunctionPatterns(): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunction(6, $i, $i % 2 === 0);   // التوقيت الأفقيّ
            $this->setFunction($i, 6, $i % 2 === 0);   // التوقيت الرأسيّ
        }

        $this->finder(3, 3);
        $this->finder($this->size - 4, 3);
        $this->finder(3, $this->size - 4);

        $positions = self::ALIGNMENT[$this->version];
        $last = count($positions) - 1;

        foreach ($positions as $i => $row) {
            foreach ($positions as $j => $col) {
                // الزوايا الثلاث محجوزةٌ لأنماط الكشف — ولا محاذاة فوقها
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0)) {
                    continue;
                }

                $this->alignment($row, $col);
            }
        }

        $this->reserveFormatAreas();
        $this->drawVersionBits();
    }

    private function finder(int $centerRow, int $centerCol): void
    {
        for ($dr = -4; $dr <= 4; $dr++) {
            for ($dc = -4; $dc <= 4; $dc++) {
                $distance = max(abs($dr), abs($dc));
                $row = $centerRow + $dr;
                $col = $centerCol + $dc;

                if ($this->inside($row, $col)) {
                    $this->setFunction($row, $col, $distance !== 2 && $distance !== 4);
                }
            }
        }
    }

    private function alignment(int $centerRow, int $centerCol): void
    {
        for ($dr = -2; $dr <= 2; $dr++) {
            for ($dc = -2; $dc <= 2; $dc++) {
                $this->setFunction($centerRow + $dr, $centerCol + $dc, max(abs($dr), abs($dc)) !== 1);
            }
        }
    }

    /** حجز موضعَي بتات الصيغة قبل وضع البيانات — تُكتَب قيمتها بعد التقنيع */
    private function reserveFormatAreas(): void
    {
        for ($i = 0; $i <= 5; $i++) {
            $this->setFunction($i, 8, false);
        }

        $this->setFunction(7, 8, false);
        $this->setFunction(8, 8, false);
        $this->setFunction(8, 7, false);

        for ($i = 9; $i < 15; $i++) {
            $this->setFunction(8, 14 - $i, false);
        }

        for ($i = 0; $i < 8; $i++) {
            $this->setFunction(8, $this->size - 1 - $i, false);
        }

        for ($i = 8; $i < 15; $i++) {
            $this->setFunction($this->size - 15 + $i, 8, false);
        }

        $this->setFunction($this->size - 8, 8, true);   // الوحدة الداكنة الثابتة
    }

    private function drawFormatBits(): void
    {
        $data = (self::EC_LEVEL_BITS << 3) | self::MASK;
        $rem = $data;

        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }

        $bits = (($data << 10) | ($rem & 0x3FF)) ^ 0x5412;

        for ($i = 0; $i <= 5; $i++) {
            $this->modules[$i][8] = $this->bit($bits, $i);
        }

        $this->modules[7][8] = $this->bit($bits, 6);
        $this->modules[8][8] = $this->bit($bits, 7);
        $this->modules[8][7] = $this->bit($bits, 8);

        for ($i = 9; $i < 15; $i++) {
            $this->modules[8][14 - $i] = $this->bit($bits, $i);
        }

        for ($i = 0; $i < 8; $i++) {
            $this->modules[8][$this->size - 1 - $i] = $this->bit($bits, $i);
        }

        for ($i = 8; $i < 15; $i++) {
            $this->modules[$this->size - 15 + $i][8] = $this->bit($bits, $i);
        }

        $this->modules[$this->size - 8][8] = true;
    }

    private function drawVersionBits(): void
    {
        if ($this->version < 7) {
            return;
        }

        $rem = $this->version;

        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }

        $bits = ($this->version << 12) | ($rem & 0xFFF);

        for ($i = 0; $i < 18; $i++) {
            $value = $this->bit($bits, $i);
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);

            $this->setFunction($b, $a, $value);
            $this->setFunction($a, $b, $value);
        }
    }

    /** المسح المتعرّج: عمودان عمودان من اليمين، وعمود التوقيت يُتخطّى */
    private function drawCodewords(array $codewords): void
    {
        $index = 0;
        $total = count($codewords) * 8;

        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }

            for ($vertical = 0; $vertical < $this->size; $vertical++) {
                for ($j = 0; $j < 2; $j++) {
                    $col = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $row = $upward ? $this->size - 1 - $vertical : $vertical;

                    if (! $this->reserved[$row][$col] && $index < $total) {
                        $this->modules[$row][$col] = $this->bit($codewords[$index >> 3], 7 - ($index & 7));
                        $index++;
                    }
                }
            }
        }
    }

    private function applyMask(): void
    {
        for ($row = 0; $row < $this->size; $row++) {
            for ($col = 0; $col < $this->size; $col++) {
                if (! $this->reserved[$row][$col] && ($row + $col) % 2 === 0) {
                    $this->modules[$row][$col] = ! $this->modules[$row][$col];
                }
            }
        }
    }

    // =============================================================== أدوات

    private function setFunction(int $row, int $col, bool $value): void
    {
        if (! $this->inside($row, $col)) {
            return;
        }

        $this->modules[$row][$col] = $value;
        $this->reserved[$row][$col] = true;
    }

    private function inside(int $row, int $col): bool
    {
        return $row >= 0 && $row < $this->size && $col >= 0 && $col < $this->size;
    }

    private function bit(int $value, int $index): bool
    {
        return (($value >> $index) & 1) === 1;
    }
}
