<?php

namespace App\Services\AdminScreens;

/**
 * توليد **ملفّ Excel حقيقيّ (XLSX)** بلا أيّ مكتبة خارجيّة (قاعدة البناء §4 · الدستور 9).
 *
 * **لماذا كتبناه بأنفسنا؟** لأنّ الصيغة كانت **خيارًا في الشاشة والمرفق يخرج CSV
 * دائمًا** — وعدٌ في الواجهة بلا تنفيذ، وهو بالضبط ما يرفضه الدستور. والبدائل
 * كلّها مكتبات خارجيّة، فالمخرج الوحيد أن نكتب الصيغة نفسها.
 *
 * **وما XLSX أصلًا؟** حزمة ZIP فيها ملفّات XML بمواصفة OOXML:
 *   `[Content_Types].xml` · `_rels/.rels` · `xl/workbook.xml` ·
 *   `xl/_rels/workbook.xml.rels` · `xl/styles.xml` · `xl/sharedStrings.xml` ·
 *   `xl/worksheets/sheet1.xml`
 * فنبنيها نصًّا ونضغطها بحاوية ZIP مكتوبة هنا بـ`pack()` — بلا `ZipArchive`
 * حتّى لا نعلّق الميزة على امتداد قد لا يكون مثبَّتًا على خادم العميل.
 *
 * **والعربيّة؟** XML كلّه UTF-8 مصرَّحٌ به في ترويسة كلّ ملفّ، والنصوص تُخزَّن
 * في `sharedStrings` بحروفها المنطقيّة بلا تشكيلٍ ولا قلب — فتُقرأ سليمة في
 * إكسل وLibreOffice وأيّ قارئ، ويُضبَط اتّجاه الورقة يمينًا-يسارًا (`rightToLeft`).
 */
class XlsxWriter
{
    /** النصوص المشتركة: النصّ ⟵ فهرسه، فلا يتكرّر النصّ الواحد في الملفّ */
    private array $shared = [];

    private int $sharedCount = 0;

    /**
     * بناء المصنَّف من صفوف مفتاحيّة.
     *
     * @param  array<int,array<string,mixed>>  $rows  الصفوف — ومفاتيح أوّل صفّ هي العناوين
     * @param  string  $sheetName  اسم الورقة كما يظهر في تبويب إكسل
     */
    public function build(array $rows, string $sheetName = '', string $title = ''): string
    {
        // الافتراضيّ كان محروقًا في التوقيع، والتوقيع تعبيرٌ ثابت لا يقبل قراءة
        // إعداد — فنُقِل إلى أوّل الجسم بنفس النصّ حرفًا بحرف (2.13-ب).
        $sheetName = $sheetName !== '' ? $sheetName : (string) setting('stats.xlsx_writer.sheet_name_1', 'التقرير');

        $this->shared = [];
        $this->sharedCount = 0;

        $headers = $rows === [] ? [] : array_keys((array) $rows[0]);
        $sheet = $this->sheet($headers, $rows);

        $files = [
            '[Content_Types].xml' => $this->contentTypes(),
            '_rels/.rels' => $this->rootRels(),
            'docProps/core.xml' => $this->coreProps($title !== '' ? $title : $sheetName),
            'docProps/app.xml' => $this->appProps(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRels(),
            'xl/workbook.xml' => $this->workbook($sheetName),
            'xl/styles.xml' => $this->styles(),
            'xl/sharedStrings.xml' => $this->sharedStrings(),
            'xl/worksheets/sheet1.xml' => $sheet,
        ];

        return $this->zip($files);
    }

    // ------------------------------------------------------------------ الورقة

    private function sheet(array $headers, array $rows): string
    {
        $xml = $this->head()
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            // ⭐ الورقة عربيّة: الاتّجاه من اليمين، والصفّ الأوّل مثبَّت فوق التمرير
            .'<sheetViews><sheetView rightToLeft="1" tabSelected="1" workbookViewId="0">'
            .'<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            .'</sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/>'
            .$this->columns(count($headers))
            .'<sheetData>';

        if ($headers !== []) {
            $xml .= $this->row(1, array_map(fn ($h) => (string) $h, $headers), header: true);
        }

        foreach (array_values($rows) as $index => $rowData) {
            $values = [];

            // نقرأ بترتيب العناوين لا بترتيب الصفّ: صفٌّ ناقص عمودًا لا يزيح البقيّة
            foreach ($headers as $header) {
                $values[] = ((array) $rowData)[$header] ?? '';
            }

            $xml .= $this->row($index + 2, $values);
        }

        return $xml.'</sheetData><pageMargins left="0.5" right="0.5" top="0.75" bottom="0.75" header="0.3" footer="0.3"/></worksheet>';
    }

    private function columns(int $count): string
    {
        if ($count === 0) {
            return '';
        }

        return '<cols><col min="1" max="'.$count.'" width="22" customWidth="1"/></cols>';
    }

    private function row(int $number, array $values, bool $header = false): string
    {
        $cells = '';

        foreach (array_values($values) as $index => $value) {
            $cells .= $this->cell($this->reference($index + 1, $number), $value, $header);
        }

        return '<row r="'.$number.'"'.($header ? ' ht="22" customHeight="1"' : '').'>'.$cells.'</row>';
    }

    /**
     * الخليّة: الرقم يُكتَب رقمًا كي تعمل عليه دوالّ إكسل، وما عداه نصٌّ مشترك.
     * والنصّ الذي يبدأ بصفرٍ (كرقم هاتف) يبقى نصًّا فلا يبتلع إكسل صفره.
     */
    private function cell(string $reference, mixed $value, bool $header): string
    {
        $style = $header ? ' s="1"' : '';

        if ($value === null || $value === '') {
            return '<c r="'.$reference.'"'.$style.'/>';
        }

        if (is_bool($value)) {
            $value = $value ? setting('stats.xlsx_writer.cell_1', 'نعم') : setting('stats.xlsx_writer.cell_2', 'لا');
        }

        if (is_int($value) || is_float($value) || (is_string($value) && $this->isPlainNumber($value))) {
            return '<c r="'.$reference.'"'.$style.'><v>'.(0 + $value).'</v></c>';
        }

        return '<c r="'.$reference.'"'.$style.' t="s"><v>'.$this->sharedIndex((string) $value).'</v></c>';
    }

    private function isPlainNumber(string $value): bool
    {
        // «007» و«1e5» و«+5» ليست أرقامًا هنا — نحفظ شكلها كما كتبه المصدر
        return preg_match('/^-?(0|[1-9]\d*)(\.\d+)?$/', $value) === 1;
    }

    /** A1 · B2 … — والعمود بعد Z يصير AA */
    private function reference(int $column, int $row): string
    {
        $letters = '';

        while ($column > 0) {
            $column--;
            $letters = chr(65 + ($column % 26)).$letters;
            $column = intdiv($column, 26);
        }

        return $letters.$row;
    }

    private function sharedIndex(string $value): int
    {
        if (! array_key_exists($value, $this->shared)) {
            $this->shared[$value] = $this->sharedCount++;
        }

        return $this->shared[$value];
    }

    private function sharedStrings(): string
    {
        $items = '';

        foreach (array_keys($this->shared) as $text) {
            // `xml:space="preserve"` يحفظ الفراغات الطرفيّة كما هي
            $items .= '<si><t xml:space="preserve">'.$this->escape((string) $text).'</t></si>';
        }

        return $this->head()
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'
            .$this->sharedCount.'" uniqueCount="'.$this->sharedCount.'">'.$items.'</sst>';
    }

    // ------------------------------------------------------------------ الهيكل

    private function head(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";
    }

    private function contentTypes(): string
    {
        return $this->head()
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    private function rootRels(): string
    {
        return $this->head()
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function workbookRels(): string
    {
        return $this->head()
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            .'</Relationships>';
    }

    private function workbook(string $sheetName): string
    {
        return $this->head()
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->escape($this->sheetName($sheetName)).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    /** إكسل يرفض هذه الحروف في اسم الورقة ويقصّه عند 31 محرفًا */
    private function sheetName(string $name): string
    {
        $clean = trim(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $name));

        return mb_substr($clean === '' ? setting('stats.xlsx_writer.sheet_name_1', 'التقرير') : $clean, 0, 31);
    }

    /** خطّان: عاديّ للجسم، وعريض بخلفيّة خفيفة لصفّ العناوين */
    private function styles(): string
    {
        return $this->head()
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FF0F172A"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFEFF3F8"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1">'
            .'<alignment vertical="center"/></xf>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    private function coreProps(string $title): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');

        return $this->head()
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
            .' xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"'
            .' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.$this->escape($title).'</dc:title>'
            .'<dc:creator>'.$this->escape($this->producer()).'</dc:creator>'
            .'<cp:lastModifiedBy>'.$this->escape($this->producer()).'</cp:lastModifiedBy>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:created>'
            .'<dcterms:modified xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:modified>'
            .'</cp:coreProperties>';
    }

    private function appProps(): string
    {
        return $this->head()
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"'
            .' xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>'.$this->escape($this->producer()).'</Application>'
            .'</Properties>';
    }

    private function producer(): string
    {
        return (string) setting('platform.identity.name', config('app.name'));
    }

    private function escape(string $value): string
    {
        // نُسقط محارف التحكّم التي يرفضها XML 1.0 — وجودها يفسد الملفّ كلّه
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? $value;

        return htmlspecialchars($clean, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    // ------------------------------------------------------------------ حاوية ZIP

    /**
     * حاوية ZIP مكتوبة بالمواصفة مباشرةً (APPNOTE 4.3): ترويسة محليّة لكلّ ملفّ،
     * ثمّ الفهرس المركزيّ، ثمّ خاتمته. والضغط `deflate` من zlib المدمج في PHP،
     * ولو غاب رجعنا للتخزين بلا ضغط — الملفّ يبقى صالحًا في الحالتين.
     *
     * @param  array<string,string>  $files  المسار داخل الحزمة ⟵ محتواه
     */
    private function zip(array $files): string
    {
        $local = '';
        $central = '';
        $offset = 0;
        [$time, $date] = $this->dosTimestamp();

        foreach ($files as $name => $content) {
            $crc = crc32($content);
            $plain = strlen($content);
            $deflated = function_exists('gzdeflate') ? gzdeflate($content, 6) : false;
            $method = $deflated === false ? 0 : 8;
            $data = $deflated === false ? $content : $deflated;
            $packed = strlen($data);

            $entry = pack('VvvvvvVVVvv', 0x04034B50, 20, 0, $method, $time, $date, $crc, $packed, $plain, strlen($name), 0)
                .$name.$data;

            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014B50, 20, 20, 0, $method, $time, $date, $crc, $packed, $plain,
                strlen($name), 0, 0, 0, 0, 32, $offset,
            ).$name;

            $local .= $entry;
            $offset += strlen($entry);
        }

        return $local.$central.pack(
            'VvvvvVVv',
            0x06054B50, 0, 0, count($files), count($files), strlen($central), $offset, 0,
        );
    }

    /** وقت DOS المضغوط في ترويسة ZIP (ثانيتان لكلّ وحدة، والسنة من 1980) */
    private function dosTimestamp(): array
    {
        $now = getdate();

        return [
            ($now['hours'] << 11) | ($now['minutes'] << 5) | intdiv($now['seconds'], 2),
            ((max($now['year'], 1980) - 1980) << 9) | ($now['mon'] << 5) | $now['mday'],
        ];
    }
}
