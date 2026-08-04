<?php

namespace App\Services\Library;

use RuntimeException;

/**
 * قارئ خطّ TrueType بأدوات PHP وحدها — لتضمين الخطّ داخل الـPDF (القسم 9).
 *
 * نقرأ ما يلزم فقط: `head` للوحدات، `hhea`+`hmtx` للعروض، `cmap` لربط
 * الحرف بالـGlyph، `maxp` لعدد الأشكال. ولا نجزّئ الخطّ (Subsetting) لأنّ
 * التجزئة تحتاج إعادة بناء `glyf`/`loca` وهي مخاطرة بلا عائد هنا: الملفّ
 * يُولَّد مرّةً ويُحمَّل مرّة.
 */
class TrueTypeFont
{
    private string $data;

    /** @var array<string, array{offset:int, length:int}> */
    private array $tables = [];

    private int $unitsPerEm = 1000;

    private int $numGlyphs = 0;

    /** @var array<int,int> */
    private array $widths = [];

    /** @var array<int,int> */
    private array $cmap = [];

    private array $box = [0, 0, 1000, 1000];

    private int $ascent = 800;

    private int $descent = -200;

    public function __construct(string $path)
    {
        $data = @file_get_contents($path);

        if ($data === false || strlen($data) < 12) {
            throw new RuntimeException(setting('library.true_type_font.construct_1', 'ملفّ الخطّ مش سليم — ارفع نسخة TTF صحيحة من الإعدادات.'));
        }

        $this->data = $data;
        $this->readTables();
        $this->readHead();
        $this->readMaxp();
        $this->readMetrics();
        $this->readCmap();
    }

    public function program(): string
    {
        return $this->data;
    }

    /** @return array{name:string, bbox:string, ascent:int, descent:int} */
    public function descriptor(): array
    {
        return [
            'name' => 'PlatformCV',
            'bbox' => implode(' ', array_map(fn ($v) => (string) $this->scale($v), $this->box)),
            'ascent' => $this->scale($this->ascent),
            'descent' => $this->scale($this->descent),
        ];
    }

    /** الحرف ⟵ رقم الشكل (Glyph ID)، و0 يعني «غير موجود» فيُرسَم فراغًا */
    public function glyphFor(int $codepoint): int
    {
        return $this->cmap[$codepoint] ?? 0;
    }

    /** عرض الشكل بوحدة 1000 — وهي وحدة الـPDF */
    public function widthOf(int $gid): float
    {
        if ($gid === 0) {
            return 300.0; // فراغ آمن بدل صفر يلصق الحروف
        }

        $units = $this->widths[$gid] ?? ($this->widths[array_key_last($this->widths)] ?? 500);

        return $units * 1000 / max(1, $this->unitsPerEm);
    }

    // ------------------------------------------------------------------ داخليّ

    private function readTables(): void
    {
        $count = $this->uint16(4);

        for ($i = 0; $i < $count; $i++) {
            $at = 12 + $i * 16;
            $tag = substr($this->data, $at, 4);
            $this->tables[$tag] = [
                'offset' => $this->uint32($at + 8),
                'length' => $this->uint32($at + 12),
            ];
        }

        foreach (['head', 'hhea', 'hmtx', 'cmap'] as $required) {
            if (! isset($this->tables[$required])) {
                throw new RuntimeException(strtr(setting('library.true_type_font.read_tables_1', 'الخطّ ناقص جدول «:p1» — ارفع نسخة TTF كاملة.'), [':p1' => (string) ($required)]));
            }
        }
    }

    private function readHead(): void
    {
        $at = $this->tables['head']['offset'];
        $this->unitsPerEm = max(16, $this->uint16($at + 18));
        $this->box = [
            $this->int16($at + 36), $this->int16($at + 38),
            $this->int16($at + 40), $this->int16($at + 42),
        ];

        $hhea = $this->tables['hhea']['offset'];
        $this->ascent = $this->int16($hhea + 4);
        $this->descent = $this->int16($hhea + 6);
    }

    private function readMaxp(): void
    {
        $this->numGlyphs = isset($this->tables['maxp'])
            ? $this->uint16($this->tables['maxp']['offset'] + 4)
            : 0;
    }

    private function readMetrics(): void
    {
        $numberOfHMetrics = $this->uint16($this->tables['hhea']['offset'] + 34);
        $at = $this->tables['hmtx']['offset'];

        $last = 500;

        for ($i = 0; $i < $numberOfHMetrics; $i++) {
            $last = $this->uint16($at + $i * 4);
            $this->widths[$i] = $last;
        }

        // بقيّة الأشكال ترث آخر عرض — وهي قاعدة المواصفة نفسها
        for ($i = $numberOfHMetrics; $i < $this->numGlyphs; $i++) {
            $this->widths[$i] = $last;
        }
    }

    private function readCmap(): void
    {
        $base = $this->tables['cmap']['offset'];
        $count = $this->uint16($base + 2);
        $best = null;
        $bestScore = -1;

        for ($i = 0; $i < $count; $i++) {
            $at = $base + 4 + $i * 8;
            $platform = $this->uint16($at);
            $encoding = $this->uint16($at + 2);
            $offset = $base + $this->uint32($at + 4);

            // الأفضليّة: يونيكود كامل (3,10) ثمّ (3,1) ثمّ أيّ يونيكود
            $score = match (true) {
                $platform === 3 && $encoding === 10 => 3,
                $platform === 3 && $encoding === 1 => 2,
                $platform === 0 => 1,
                default => 0,
            };

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $offset;
            }
        }

        if ($best === null) {
            throw new RuntimeException(setting('library.true_type_font.read_cmap_1', 'الخطّ مفيهوش جدول ربط يونيكود — ارفع نسخة TTF أحدث.'));
        }

        match ($this->uint16($best)) {
            4 => $this->readCmap4($best),
            12 => $this->readCmap12($best),
            default => throw new RuntimeException(setting('library.true_type_font.read_cmap_2', 'صيغة ربط الحروف في الخطّ مش مدعومة — ارفع نسخة TTF قياسيّة.')),
        };
    }

    private function readCmap4(int $at): void
    {
        $segCount = intdiv($this->uint16($at + 6), 2);
        $endAt = $at + 14;
        $startAt = $endAt + $segCount * 2 + 2;
        $deltaAt = $startAt + $segCount * 2;
        $rangeAt = $deltaAt + $segCount * 2;

        for ($seg = 0; $seg < $segCount; $seg++) {
            $end = $this->uint16($endAt + $seg * 2);
            $start = $this->uint16($startAt + $seg * 2);
            $delta = $this->int16($deltaAt + $seg * 2);
            $rangeOffset = $this->uint16($rangeAt + $seg * 2);

            if ($start > $end) {
                continue;
            }

            for ($code = $start; $code <= $end && $code !== 0xFFFF; $code++) {
                if ($rangeOffset === 0) {
                    $gid = ($code + $delta) & 0xFFFF;
                } else {
                    $glyphAt = $rangeAt + $seg * 2 + $rangeOffset + ($code - $start) * 2;
                    $gid = $this->uint16($glyphAt);

                    if ($gid !== 0) {
                        $gid = ($gid + $delta) & 0xFFFF;
                    }
                }

                if ($gid !== 0) {
                    $this->cmap[$code] = $gid;
                }
            }
        }
    }

    private function readCmap12(int $at): void
    {
        $groups = $this->uint32($at + 12);

        for ($i = 0; $i < $groups; $i++) {
            $groupAt = $at + 16 + $i * 12;
            $start = $this->uint32($groupAt);
            $end = $this->uint32($groupAt + 4);
            $startGid = $this->uint32($groupAt + 8);

            // حدّ أمان: لا نفرد نطاقًا ضخمًا في الذاكرة بلا داعٍ
            $end = min($end, $start + 0xFFFF);

            for ($code = $start; $code <= $end; $code++) {
                $this->cmap[$code] = $startGid + ($code - $start);
            }
        }
    }

    private function scale(int $value): int
    {
        return (int) round($value * 1000 / max(1, $this->unitsPerEm));
    }

    private function uint16(int $at): int
    {
        return (int) (unpack('n', substr($this->data, $at, 2))[1] ?? 0);
    }

    private function int16(int $at): int
    {
        $value = $this->uint16($at);

        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    private function uint32(int $at): int
    {
        return (int) (unpack('N', substr($this->data, $at, 4))[1] ?? 0);
    }
}
