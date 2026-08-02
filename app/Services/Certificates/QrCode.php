<?php

namespace App\Services\Certificates;

/**
 * مولّد QR مكتوب هنا بالكامل (8.1 · 12.5-هـ) — **بلا أيّ مكتبة خارجيّة** لأنّ الشبكة محجوبة.
 *
 * نمط البايت · مستوى تصحيح الخطأ M · الإصدارات 1…10 — وهو أكثر من كافٍ لرابط التحقّق.
 * الـQR يفتح صفحة التحقّق العامّة والكود متعبّى ومتحقَّق تلقائيًّا (12.5-هـ).
 */
final class QrCode
{
    /** لكلّ إصدار: [بايتات تصحيح الخطأ لكلّ بلوك، [عدد بلوكات، بايتات بيانات], …] عند المستوى M */
    private const EC_M = [
        1 => [10, [[1, 16]]],
        2 => [16, [[1, 28]]],
        3 => [26, [[1, 44]]],
        4 => [18, [[2, 32]]],
        5 => [24, [[2, 43]]],
        6 => [16, [[4, 27]]],
        7 => [18, [[4, 31]]],
        8 => [22, [[2, 38], [2, 39]]],
        9 => [22, [[3, 36], [2, 37]]],
        10 => [26, [[4, 43], [1, 44]]],
    ];

    /** مراكز أنماط المحاذاة لكلّ إصدار */
    private const ALIGNMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    /** معلومات النسق (15 بت) للمستوى M مع كلّ قناع */
    private const FORMAT_M = [0x5412, 0x5125, 0x5E7C, 0x5B4B, 0x45F9, 0x40CE, 0x4F97, 0x4AA0];

    /** معلومات الإصدار (18 بت) — تُكتَب من الإصدار 7 فصاعدًا */
    private const VERSION_INFO = [7 => 0x07C94, 8 => 0x085BC, 9 => 0x09A99, 10 => 0x0A4D3];

    /** @var list<int> */
    private static array $expTable = [];

    /** @var list<int> */
    private static array $logTable = [];

    /**
     * مصفوفة الوحدات: `true` = وحدة داكنة.
     *
     * @return list<list<bool>>
     */
    public static function matrix(string $text): array
    {
        $version = self::pickVersion(strlen($text));
        [$ecCount, $groups] = self::EC_M[$version];

        $bits = self::dataBits($text, $version, self::dataCapacity($version));
        $codewords = self::interleave($bits, $ecCount, $groups);

        return self::build($version, $codewords);
    }

    /** صورة PNG للـQR — الحجم بالبكسل والهامش بالوحدات */
    public static function png(string $text, int $sizePx, int $marginModules = 4): string
    {
        $matrix = self::matrix($text);
        $modules = count($matrix) + ($marginModules * 2);
        $scale = max(1, (int) floor($sizePx / $modules));
        $side = $modules * $scale;

        $image = imagecreatetruecolor($side, $side);
        $light = imagecolorallocate($image, 255, 255, 255);
        $dark = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $side, $side, $light);

        foreach ($matrix as $y => $row) {
            foreach ($row as $x => $on) {
                if (! $on) {
                    continue;
                }

                $px = ($x + $marginModules) * $scale;
                $py = ($y + $marginModules) * $scale;
                imagefilledrectangle($image, $px, $py, $px + $scale - 1, $py + $scale - 1, $dark);
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    // ------------------------------------------------------------ الترميز

    private static function pickVersion(int $length): int
    {
        foreach (array_keys(self::EC_M) as $version) {
            $cci = $version < 10 ? 8 : 16;

            if (4 + $cci + ($length * 8) <= self::dataCapacity($version) * 8) {
                return $version;
            }
        }

        throw new \InvalidArgumentException('النصّ أطول ممّا يتّسع له الـQR.');
    }

    private static function dataCapacity(int $version): int
    {
        $total = 0;

        foreach (self::EC_M[$version][1] as [$blocks, $dataCount]) {
            $total += $blocks * $dataCount;
        }

        return $total;
    }

    private static function dataBits(string $text, int $version, int $capacity): string
    {
        $cci = $version < 10 ? 8 : 16;
        $bits = '0100'.str_pad(decbin(strlen($text)), $cci, '0', STR_PAD_LEFT);

        for ($i = 0, $n = strlen($text); $i < $n; $i++) {
            $bits .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);
        }

        $limit = $capacity * 8;
        $bits .= str_repeat('0', min(4, $limit - strlen($bits)));
        $bits .= str_repeat('0', (8 - (strlen($bits) % 8)) % 8);

        $pad = ['11101100', '00010001'];
        $i = 0;

        while (strlen($bits) < $limit) {
            $bits .= $pad[$i++ % 2];
        }

        return $bits;
    }

    /** @return list<int> */
    private static function interleave(string $bits, int $ecCount, array $groups): array
    {
        $bytes = [];

        foreach (str_split($bits, 8) as $byte) {
            $bytes[] = bindec($byte);
        }

        $dataBlocks = [];
        $ecBlocks = [];
        $offset = 0;

        foreach ($groups as [$blockCount, $dataCount]) {
            for ($b = 0; $b < $blockCount; $b++) {
                $block = array_slice($bytes, $offset, $dataCount);
                $offset += $dataCount;
                $dataBlocks[] = $block;
                $ecBlocks[] = self::reedSolomon($block, $ecCount);
            }
        }

        $out = [];
        $maxData = max(array_map('count', $dataBlocks));

        for ($i = 0; $i < $maxData; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) {
                    $out[] = $block[$i];
                }
            }
        }

        for ($i = 0; $i < $ecCount; $i++) {
            foreach ($ecBlocks as $block) {
                $out[] = $block[$i];
            }
        }

        return $out;
    }

    private static function initGaloisField(): void
    {
        if (self::$expTable !== []) {
            return;
        }

        $x = 1;

        for ($i = 0; $i < 256; $i++) {
            self::$expTable[$i] = $x;
            self::$logTable[$x] = $i;
            $x <<= 1;

            if ($x & 0x100) {
                $x ^= 0x11D; // كثير الحدود البدائيّ لحقل جالوا 256
            }
        }
    }

    /** @return list<int> */
    private static function reedSolomon(array $data, int $ecCount): array
    {
        self::initGaloisField();

        $generator = [1];

        for ($i = 0; $i < $ecCount; $i++) {
            $next = array_fill(0, count($generator) + 1, 0);

            foreach ($generator as $j => $coefficient) {
                $next[$j] ^= $coefficient;
                $next[$j + 1] ^= self::multiply($coefficient, self::$expTable[$i]);
            }

            $generator = $next;
        }

        $remainder = array_merge($data, array_fill(0, $ecCount, 0));

        for ($i = 0, $n = count($data); $i < $n; $i++) {
            $lead = $remainder[$i];

            if ($lead === 0) {
                continue;
            }

            foreach ($generator as $j => $coefficient) {
                $remainder[$i + $j] ^= self::multiply($coefficient, $lead);
            }
        }

        return array_values(array_slice($remainder, count($data)));
    }

    private static function multiply(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }

        return self::$expTable[(self::$logTable[$a] + self::$logTable[$b]) % 255];
    }

    // ------------------------------------------------------------ المصفوفة

    /** @return list<list<bool>> */
    private static function build(int $version, array $codewords): array
    {
        $size = 17 + (4 * $version);
        $matrix = array_fill(0, $size, array_fill(0, $size, false));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        self::placeFinders($matrix, $reserved, $size);
        self::placeAlignment($matrix, $reserved, $version, $size);
        self::placeTiming($matrix, $reserved, $size);
        self::reserveFormat($reserved, $size);

        // الوحدة الداكنة الثابتة
        $matrix[$size - 8][8] = true;
        $reserved[$size - 8][8] = true;

        if ($version >= 7) {
            self::placeVersionInfo($matrix, $reserved, $version, $size);
        }

        self::placeData($matrix, $reserved, $codewords, $size);

        $best = null;
        $bestPenalty = PHP_INT_MAX;

        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = self::applyMask($matrix, $reserved, $mask, $size);
            self::placeFormat($candidate, $mask, $size);
            $penalty = self::penalty($candidate, $size);

            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $best = $candidate;
            }
        }

        return $best;
    }

    private static function placeFinders(array &$matrix, array &$reserved, int $size): void
    {
        foreach ([[0, 0], [$size - 7, 0], [0, $size - 7]] as [$ox, $oy]) {
            for ($y = -1; $y <= 7; $y++) {
                for ($x = -1; $x <= 7; $x++) {
                    $px = $ox + $x;
                    $py = $oy + $y;

                    if ($px < 0 || $py < 0 || $px >= $size || $py >= $size) {
                        continue;
                    }

                    $inner = $x >= 0 && $x <= 6 && $y >= 0 && $y <= 6;
                    $dark = $inner && (
                        $x === 0 || $x === 6 || $y === 0 || $y === 6
                        || ($x >= 2 && $x <= 4 && $y >= 2 && $y <= 4)
                    );

                    $matrix[$py][$px] = $dark;
                    $reserved[$py][$px] = true;
                }
            }
        }
    }

    private static function placeAlignment(array &$matrix, array &$reserved, int $version, int $size): void
    {
        $centers = self::ALIGNMENT[$version];

        foreach ($centers as $cy) {
            foreach ($centers as $cx) {
                // المراكز المتداخلة مع أنماط البحث لا تُرسَم
                if (($cx <= 8 && $cy <= 8)
                    || ($cx <= 8 && $cy >= $size - 9)
                    || ($cx >= $size - 9 && $cy <= 8)) {
                    continue;
                }

                for ($y = -2; $y <= 2; $y++) {
                    for ($x = -2; $x <= 2; $x++) {
                        $matrix[$cy + $y][$cx + $x] = max(abs($x), abs($y)) !== 1;
                        $reserved[$cy + $y][$cx + $x] = true;
                    }
                }
            }
        }
    }

    private static function placeTiming(array &$matrix, array &$reserved, int $size): void
    {
        for ($i = 8; $i < $size - 8; $i++) {
            $dark = $i % 2 === 0;
            $matrix[6][$i] = $dark;
            $reserved[6][$i] = true;
            $matrix[$i][6] = $dark;
            $reserved[$i][6] = true;
        }
    }

    private static function reserveFormat(array &$reserved, int $size): void
    {
        for ($i = 0; $i <= 8; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }

        for ($i = 0; $i < 8; $i++) {
            $reserved[8][$size - 1 - $i] = true;
            $reserved[$size - 1 - $i][8] = true;
        }
    }

    private static function placeVersionInfo(array &$matrix, array &$reserved, int $version, int $size): void
    {
        $bits = self::VERSION_INFO[$version];

        for ($i = 0; $i < 18; $i++) {
            $bit = (bool) (($bits >> $i) & 1);
            $x = (int) floor($i / 3);
            $y = $size - 11 + ($i % 3);

            $matrix[$y][$x] = $bit;
            $reserved[$y][$x] = true;
            $matrix[$x][$y] = $bit;
            $reserved[$x][$y] = true;
        }
    }

    private static function placeData(array &$matrix, array $reserved, array $codewords, int $size): void
    {
        $bits = '';

        foreach ($codewords as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $index = 0;
        $upward = true;

        for ($right = $size - 1; $right > 0; $right -= 2) {
            if ($right === 6) {
                $right = 5; // عمود التوقيت يُتخطّى
            }

            for ($step = 0; $step < $size; $step++) {
                $y = $upward ? $size - 1 - $step : $step;

                foreach ([$right, $right - 1] as $x) {
                    if ($reserved[$y][$x]) {
                        continue;
                    }

                    $matrix[$y][$x] = ($bits[$index] ?? '0') === '1';
                    $index++;
                }
            }

            $upward = ! $upward;
        }
    }

    private static function applyMask(array $matrix, array $reserved, int $mask, int $size): array
    {
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($reserved[$y][$x]) {
                    continue;
                }

                $flip = match ($mask) {
                    0 => ($y + $x) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($y + $x) % 3 === 0,
                    4 => ((int) floor($y / 2) + (int) floor($x / 3)) % 2 === 0,
                    5 => (($y * $x) % 2) + (($y * $x) % 3) === 0,
                    6 => (((($y * $x) % 2) + (($y * $x) % 3)) % 2) === 0,
                    default => (((($y + $x) % 2) + (($y * $x) % 3)) % 2) === 0,
                };

                if ($flip) {
                    $matrix[$y][$x] = ! $matrix[$y][$x];
                }
            }
        }

        return $matrix;
    }

    private static function placeFormat(array &$matrix, int $mask, int $size): void
    {
        $bits = self::FORMAT_M[$mask];

        for ($i = 0; $i < 15; $i++) {
            $bit = (bool) (($bits >> $i) & 1);

            // النسخة الأولى حول نمط البحث العلويّ — وتتخطّى صفّ التوقيت وعموده
            if ($i < 6) {
                $matrix[$i][8] = $bit;
            } elseif ($i === 6) {
                $matrix[7][8] = $bit;
            } elseif ($i === 7) {
                $matrix[8][8] = $bit;
            } elseif ($i === 8) {
                $matrix[8][7] = $bit;
            } else {
                $matrix[8][14 - $i] = $bit;
            }

            // النسخة الثانية موزّعة على النمطين الآخرين
            if ($i < 8) {
                $matrix[8][$size - 1 - $i] = $bit;
            } else {
                $matrix[$size - 15 + $i][8] = $bit;
            }
        }
    }

    /** قواعد العقوبة الأربع المعياريّة — نختار القناع الأقلّ عقوبةً */
    private static function penalty(array $matrix, int $size): int
    {
        $score = 0;

        // 1) خمس وحدات متجاورة بنفس اللون فأكثر
        for ($i = 0; $i < $size; $i++) {
            foreach ([true, false] as $isRow) {
                $run = 1;

                for ($j = 1; $j < $size; $j++) {
                    $current = $isRow ? $matrix[$i][$j] : $matrix[$j][$i];
                    $previous = $isRow ? $matrix[$i][$j - 1] : $matrix[$j - 1][$i];

                    if ($current === $previous) {
                        $run++;

                        continue;
                    }

                    if ($run >= 5) {
                        $score += 3 + ($run - 5);
                    }

                    $run = 1;
                }

                if ($run >= 5) {
                    $score += 3 + ($run - 5);
                }
            }
        }

        // 2) مربّعات 2×2 بلون واحد
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                if ($matrix[$y][$x] === $matrix[$y][$x + 1]
                    && $matrix[$y][$x] === $matrix[$y + 1][$x]
                    && $matrix[$y][$x] === $matrix[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }

        // 3) النمط الشبيه بنمط البحث
        $patterns = ['10111010000', '00001011101'];

        for ($i = 0; $i < $size; $i++) {
            $row = '';
            $column = '';

            for ($j = 0; $j < $size; $j++) {
                $row .= $matrix[$i][$j] ? '1' : '0';
                $column .= $matrix[$j][$i] ? '1' : '0';
            }

            foreach ($patterns as $pattern) {
                $score += 40 * (substr_count($row, $pattern) + substr_count($column, $pattern));
            }
        }

        // 4) اختلال نسبة الداكن إلى الفاتح
        $dark = 0;

        foreach ($matrix as $row) {
            $dark += count(array_filter($row));
        }

        $percent = ($dark * 100) / ($size * $size);
        $score += (int) (abs($percent - 50) / 5) * 10;

        return $score;
    }
}
