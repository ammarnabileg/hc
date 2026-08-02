<?php

namespace App\Services\Library;

use RuntimeException;

/**
 * توليد **PDF متوافق مع ATS** على الخادم بلا أيّ مكتبة خارجيّة (الدستور 9).
 *
 * **ما الذي يجعله متوافقًا مع ATS؟**
 *  · عمود واحد بلا جداول ولا أعمدة ولا صناديق نصّ — النصّ يُقرأ بالترتيب.
 *  · عناوين أقسام قياسيّة بأسماء يعرفها كلّ محلّل سِيَر.
 *  · **طبقة نصّ حقيقيّة** لا صورة: كلّ حرف مربوط بحرفه المنطقيّ عبر `ToUnicode`،
 *    فالنسخ من الملفّ يعطي «محمد» لا أشكالًا مقطّعة.
 *  · بلا صور ولا زخارف ولا رؤوس/تذييلات تشوّش التحليل.
 *
 * **ولماذا كتبناه بأنفسنا؟** لأنّ كلّ مولّدات الـPDF مكتبات خارجيّة، والشرط
 * «التوليد بالخادم بلا مكتبات خارجيّة». فنكتب الملفّ بمواصفة PDF مباشرةً،
 * ونضمّن خطّ المنصّة (Cairo) كـCIDFontType2 بترميز Identity-H ليدعم العربيّة.
 */
class AtsPdfWriter
{
    /** A4 بالنقاط — مقاس السيرة القياسيّ */
    private const PAGE_W = 595.28;

    private const PAGE_H = 841.89;

    private array $objects = [];

    private array $pages = [];

    private array $lines = [];

    private array $usedGlyphs = [];

    private ?TrueTypeFont $font = null;

    public function __construct(private readonly ArabicShaper $shaper) {}

    public function available(): bool
    {
        return $this->fontFile() !== null;
    }

    /** رسالة تقول ماذا حدث وماذا تفعل حين يغيب الخطّ (2.17-ب) */
    public function unavailableReason(): string
    {
        return 'الخطّ العربيّ المضمَّن مش مرفوع على الخادم لسّه، فمقدرناش نطلع PDF عربيّ سليم. '
            .'افتح النسخة القابلة للطباعة واحفظها PDF من المتصفّح، أو اطلب من الإدارة رفع الخطّ من الإعدادات.';
    }

    /**
     * بناء الملفّ من أقسام السيرة.
     *
     * @param  array<int, array{heading:string, lines:array<int,string>}>  $sections
     */
    public function build(string $fullName, string $headline, array $sections): string
    {
        $path = $this->fontFile();

        if ($path === null) {
            throw new RuntimeException($this->unavailableReason());
        }

        $this->font = new TrueTypeFont($path);
        $this->lines = [];
        $this->pages = [];
        $this->usedGlyphs = [];

        $margin = (float) setting('cv.ats.margin_pt', 56);
        $bodySize = (float) setting('cv.ats.body_size_pt', 11);
        $headingSize = (float) setting('cv.ats.heading_size_pt', 13);
        $titleSize = (float) setting('cv.ats.title_size_pt', 20);
        $leading = (float) setting('cv.ats.leading', 1.55);

        $cursor = self::PAGE_H - $margin;
        $bottom = $margin;
        $width = self::PAGE_W - ($margin * 2);
        $page = [];

        $push = function (string $text, float $size, bool $rule = false) use (&$page, &$cursor, $margin, $bottom, $width, $leading) {
            foreach ($this->wrap($text, $size, $width) as $line) {
                if ($cursor - ($size * $leading) < $bottom) {
                    $this->pages[] = $page;
                    $page = [];
                    $cursor = self::PAGE_H - $margin;
                }

                $cursor -= $size * $leading;
                $page[] = ['text' => $line, 'size' => $size, 'y' => $cursor, 'x' => $margin, 'width' => $width, 'rule' => false];
            }

            if ($rule) {
                $cursor -= 4;
                $page[] = ['rule' => true, 'y' => $cursor, 'x' => $margin, 'width' => $width, 'text' => '', 'size' => $size];
                $cursor -= 6;
            }
        };

        $push($fullName, $titleSize);

        if (trim($headline) !== '') {
            $push($headline, $bodySize);
        }

        foreach ($sections as $section) {
            $heading = trim((string) ($section['heading'] ?? ''));
            $body = array_values(array_filter(array_map('trim', (array) ($section['lines'] ?? [])), fn ($l) => $l !== ''));

            if ($heading === '' || $body === []) {
                continue; // القسم الفارغ لا يُطبَع — ATS يكره العناوين الجوفاء
            }

            $cursor -= $bodySize * 0.8;
            $push($heading, $headingSize, true);

            foreach ($body as $line) {
                $push($line, $bodySize);
            }
        }

        $this->pages[] = $page;

        return $this->assemble($fullName);
    }

    // ------------------------------------------------------------------ التخطيط

    /** لفّ السطر على عرض العمود — بالكلمات لا بالحروف */
    private function wrap(string $text, float $size, float $width): array
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return [];
        }

        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if ($this->measure($candidate, $size) <= $width || $current === '') {
                $current = $candidate;

                continue;
            }

            $lines[] = $current;
            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function measure(string $text, float $size): float
    {
        $total = 0.0;

        foreach ($this->shaper->shape($text) as $glyph) {
            $total += $this->font->widthOf($this->glyphOf($glyph)) * $size / 1000;
        }

        return $total;
    }

    /**
     * رقم الشكل مع بديلٍ آمن: كثيرٌ من الخطوط — ومنها Cairo — **لا تُدرج الأشكال
     * المنفردة** في جدول الربط لأنّها تُشتقّ من الحرف الأصل عبر GSUB. فلو غاب
     * الشكل رجعنا إلى الحرف نفسه، وهو ما يرسمه الخطّ منفردًا أصلًا — فلا يظهر
     * مربّع فارغ ولا تتصادم خرائط `ToUnicode` على الشكل صفر.
     */
    private function glyphOf(array $glyph): int
    {
        $gid = $this->font->glyphFor($glyph['form']);

        if ($gid !== 0) {
            return $gid;
        }

        foreach ((array) $glyph['logical'] as $code) {
            $fallback = $this->font->glyphFor($code);

            if ($fallback !== 0) {
                return $fallback;
            }
        }

        return 0;
    }

    // ------------------------------------------------------------------ الملفّ

    private function assemble(string $title): string
    {
        $contents = [];

        foreach ($this->pages as $page) {
            $contents[] = $this->pageStream($page);
        }

        $this->objects = [];

        // 1 كتالوج · 2 شجرة الصفحات · 3 الخطّ Type0 · 4 CIDFont · 5 الواصف · 6 ملفّ الخطّ · 7 ToUnicode
        $pageCount = count($contents);
        $firstPageObj = 8;

        $kids = [];

        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = ($firstPageObj + $i * 2).' 0 R';
        }

        $this->objects[1] = '<< /Type /Catalog /Pages 2 0 R /Lang (ar) >>';
        $this->objects[2] = '<< /Type /Pages /Count '.$pageCount.' /Kids ['.implode(' ', $kids).'] >>';

        $fontProgram = $this->font->program();
        $descriptor = $this->font->descriptor();

        $this->objects[3] = '<< /Type /Font /Subtype /Type0 /BaseFont /'.$descriptor['name']
            .' /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 7 0 R >>';

        $this->objects[4] = '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /'.$descriptor['name']
            .' /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >>'
            .' /FontDescriptor 5 0 R /DW 1000 /W '.$this->widthArray().' /CIDToGIDMap /Identity >>';

        $this->objects[5] = '<< /Type /FontDescriptor /FontName /'.$descriptor['name']
            .' /Flags 4 /FontBBox ['.$descriptor['bbox'].'] /ItalicAngle 0 /Ascent '.$descriptor['ascent']
            .' /Descent '.$descriptor['descent'].' /CapHeight '.$descriptor['ascent'].' /StemV 80 /FontFile2 6 0 R >>';

        $this->objects[6] = ['stream' => $fontProgram, 'dict' => '/Length1 '.strlen($fontProgram)];
        $this->objects[7] = ['stream' => $this->toUnicodeCMap(), 'dict' => ''];

        foreach ($contents as $i => $stream) {
            $pageObj = $firstPageObj + $i * 2;
            $contentObj = $pageObj + 1;

            $this->objects[$pageObj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
                .round(self::PAGE_W, 2).' '.round(self::PAGE_H, 2).']'
                .' /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentObj.' 0 R >>';

            $this->objects[$contentObj] = ['stream' => $stream, 'dict' => ''];
        }

        return $this->serialize($title);
    }

    private function pageStream(array $page): string
    {
        $out = [];

        foreach ($page as $item) {
            if (! empty($item['rule'])) {
                // خطّ فاصل رفيع تحت العنوان — عنصر رسم واحد لا يؤثّر على استخراج النصّ
                $out[] = sprintf(
                    '0.75 w 0.6 0.7 0.75 RG %.2F %.2F m %.2F %.2F l S',
                    $item['x'], $item['y'], $item['x'] + $item['width'], $item['y'],
                );

                continue;
            }

            $glyphs = $this->shaper->shape($item['text']);
            $placed = [];
            $lineWidth = 0.0;

            foreach ($glyphs as $glyph) {
                $gid = $this->glyphOf($glyph);
                $this->usedGlyphs[$gid] = $glyph['logical'];
                $advance = $this->font->widthOf($gid) * $item['size'] / 1000;
                $placed[] = ['gid' => $gid, 'offset' => $lineWidth, 'advance' => $advance, 'order' => $glyph['order']];
                $lineWidth += $advance;
            }

            // المحاذاة لليمين لأنّ المستند عربيّ — والسطر اللاتينيّ يبدأ من اليمين كذلك
            $start = max($item['x'], $item['x'] + $item['width'] - $lineWidth);

            /*
             | ⭐ نرسم كلّ شكلٍ في **موضعه البصريّ**، لكنّنا نكتبه في التدفّق
             |   بـ**ترتيبه المنطقيّ**. لماذا؟ لأنّ برامج الـATS تستخرج النصّ
             |   بترتيب ورودِه في التدفّق لا بمواضعه؛ فلو كتبناه بترتيب العين
             |   خرج «دمحم» بدل «محمد». والوضع المطلق يجعل الشكل النهائيّ واحدًا
             |   في الحالتين — فيقرأ الإنسان صحيحًا ويقرأ الـATS صحيحًا.
             */
            usort($placed, fn ($a, $b) => $a['order'] <=> $b['order']);

            $body = sprintf('BT /F1 %.2F Tf 0.06 0.09 0.11 rg', $item['size']);

            foreach ($placed as $glyph) {
                $body .= sprintf(
                    ' 1 0 0 1 %.2F %.2F Tm <%04X> Tj',
                    $start + $glyph['offset'], $item['y'], $glyph['gid'],
                );
            }

            $out[] = $body.' ET';
        }

        return implode("\n", $out);
    }

    private function widthArray(): string
    {
        $parts = [];

        foreach (array_keys($this->usedGlyphs) as $gid) {
            $parts[] = $gid.' ['.round($this->font->widthOf($gid)).']';
        }

        return '['.implode(' ', $parts).']';
    }

    /** ⭐ الخريطة التي تجعل نصّ الملفّ مقروءًا لبرامج الـATS والنسخ واللصق */
    private function toUnicodeCMap(): string
    {
        $entries = [];

        foreach ($this->usedGlyphs as $gid => $logical) {
            $value = '';

            foreach ((array) $logical as $code) {
                $value .= sprintf('%04X', $code);
            }

            $entries[] = sprintf('<%04X> <%s>', $gid, $value);
        }

        $body = '';

        foreach (array_chunk($entries, 100) as $chunk) {
            $body .= count($chunk)." beginbfchar\n".implode("\n", $chunk)."\nendbfchar\n";
        }

        return "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n"
            ."/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
            ."/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n"
            ."1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n"
            .$body
            ."endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";
    }

    private function serialize(string $title): string
    {
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        $max = max(array_keys($this->objects));

        // معلومات المستند: العنوان يساعد بعض محلّلات الـATS على التسمية
        $infoObj = $max + 1;
        $this->objects[$infoObj] = '<< /Title ('.$this->escape($title).') /Producer ('.$this->escape((string) setting('platform.identity.name', config('app.name'))).') >>';
        $max = $infoObj;

        for ($i = 1; $i <= $max; $i++) {
            if (! isset($this->objects[$i])) {
                continue;
            }

            $offsets[$i] = strlen($out);
            $object = $this->objects[$i];

            if (is_array($object)) {
                $stream = $object['stream'];
                $out .= $i." 0 obj\n<< /Length ".strlen($stream).($object['dict'] !== '' ? ' '.$object['dict'] : '')." >>\nstream\n".$stream."\nendstream\nendobj\n";

                continue;
            }

            $out .= $i." 0 obj\n".$object."\nendobj\n";
        }

        $xrefAt = strlen($out);
        $out .= "xref\n0 ".($max + 1)."\n0000000000 65535 f \n";

        for ($i = 1; $i <= $max; $i++) {
            $out .= isset($offsets[$i])
                ? sprintf("%010d 00000 n \n", $offsets[$i])
                : "0000000000 65535 f \n";
        }

        $out .= "trailer\n<< /Size ".($max + 1)." /Root 1 0 R /Info ".$infoObj." 0 R >>\nstartxref\n".$xrefAt."\n%%EOF";

        return $out;
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    private function fontFile(): ?string
    {
        $path = (string) setting('cv.ats.font_path', (string) setting('images.font.path', 'fonts/Cairo-Regular.ttf'));
        $full = public_path($path);

        return is_file($full) ? $full : null;
    }
}
