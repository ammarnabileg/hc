<?php

namespace App\Services\Export;

use App\Services\AdminScreens\XlsxWriter;
use App\Services\Library\AtsPdfWriter;
use Throwable;

/**
 * ملفّ تصدير جدوليّ بالصيغة التي طلبها الزرّ — **CSV · Excel · PDF** (12.8 · 24.3-خامسًا).
 *
 * ================== النصّ الحاكم حرفيًّا ==================
 * • **24.3-خامسًا (الإحصائيّات):** «**الهيدر:** «الإحصائيّات» + **فلتر فترة عامّ**
 *   … + `**تصدير CSV/Excel/PDF**` + `جدولة تقرير` + تحديث.»
 * • **12.8:** «… و**تصدير مرن** (**CSV/Excel/PDF**) + **جدولة تقارير دوريّة بالبريد**».
 * • **جدول الموارد:** «`reports_users.export` … **تصدير تقرير المستخدمين
 *   (CSV/Excel/PDF)**» · «`report_exports.export` … **تنزيل ملفّ التصدير
 *   (CSV/Excel/PDF)**».
 *
 * ================== لماذا وُجد هذا الصنف؟ ==================
 * لأنّ الشاشة كانت تعرض **زرًّا واحدًا «تصدير CSV»** والنصّ يوجب ثلاث صيغ.
 * والصيغتان الأخريان **مكتوبتان في المشروع أصلًا** بلا أيّ مكتبة خارجيّة:
 *  · **XLSX** — `App\Services\AdminScreens\XlsxWriter` (حزمة ZIP بملفّات XML
 *    بمواصفة OOXML، والضغط من zlib المدمج بلا `ZipArchive`).
 *  · **PDF** — `App\Services\Library\AtsPdfWriter` (مواصفة PDF مباشرةً مع خطّ
 *    عربيّ مضمَّن وطبقة `ToUnicode`، فالنصّ يُستخرَج ولا يخرج صورةً).
 * فلا نكتب مولّدًا ثالثًا ولا نضيف حزمة — **نركّب الموجود ولا نكرّره**.
 *
 * ولماذا لا نستعمل `AdminScreens\ReportRenderer` كما هو؟ لأنّه مقيَّدٌ بنموذج
 * `ReportSchedule` (اسم الجدولة وسقوفها ومرفق البريد)، وتصديرُ الشاشة ليس له
 * صفُّ جدولةٍ أصلًا. فالمشترك بينهما هو **مولّدا الصيغتين** — وهما مُعادَا
 * الاستعمال هنا حرفيًّا، لا منسوخَين.
 *
 * ================== وقاعدة الوعد ==================
 * زرٌّ يقول «Excel» ويخرج CSV **عطبٌ في ذاته**. فإن تعذّرت صيغةٌ تقنيًّا (كغياب
 * الخطّ العربيّ للـPDF) **لا نكذب**: يخرج CSV **ومعه سببٌ مكتوب** في ترويسة
 * `X-Export-Note` وفي اسم الملفّ — لا امتدادٌ يَعِد بما ليس فيه (2.17-ب).
 */
class TabularExport
{
    /** الصيغ التي يعرفها هذا المصدِّر — والزرّ لا يُبنى إلّا منها */
    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    public function __construct(
        private readonly XlsxWriter $xlsx,
        private readonly AtsPdfWriter $pdf,
    ) {}

    /** الصيغة المطلوبة بعد تنقيتها — وما ليس منها يعود CSV */
    public function normalizeFormat(?string $format): string
    {
        $format = strtolower(trim((string) $format));

        return in_array($format, self::FORMATS, true) ? $format : 'csv';
    }

    /**
     * بناء الملفّ.
     *
     * @param  array<int,array<string,mixed>>  $rows  الصفوف — ومفاتيح أوّل صفّ هي العناوين
     * @param  string  $slug  اسمٌ لاتينيّ للملفّ (الاسم العربيّ يخرج من `slug` فارغًا)
     * @return array{name:string,mime:string,content:string,format:string,note:string}
     */
    public function build(array $rows, string $format, string $title, string $subtitle = '', string $slug = 'export'): array
    {
        $format = $this->normalizeFormat($format);

        // ⚠️ الـPDF العربيّ يحتاج الخطّ المضمَّن؛ ولو غاب لا نُخرِج ملفًّا بمربّعات
        //    ولا نرمي خطأً في وجه الأدمن — نرجع لـCSV **ونقول لماذا** (2.17-ب).
        if ($format === 'pdf' && ! $this->pdf->available()) {
            return $this->file($slug, 'csv', $this->csv($rows), $this->pdf->unavailableReason());
        }

        try {
            $content = match ($format) {
                'xlsx' => $this->xlsx->build($this->limit($rows, 'xlsx'), $title, $title),
                'pdf' => $this->pdfContent($this->limit($rows, 'pdf'), count($rows), $title, $subtitle),
                default => $this->csv($rows),
            };
        } catch (Throwable) {
            return $this->file($slug, 'csv', $this->csv($rows), strtr(
                (string) setting('exports.fallback_note', 'تعذّر توليد ملفّ :format فبعتناه CSV.'),
                [':format' => mb_strtoupper($format)],
            ));
        }

        return $this->file($slug, $format, $content);
    }

    /** الامتداد ونوع المحتوى — مرجعٌ واحد يستعمله التنزيل والمرفق معًا */
    public function mime(string $format): string
    {
        return match ($format) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
            default => 'text/csv; charset=UTF-8',
        };
    }

    /**
     * CSV بـBOM حتّى تفتح العربيّة سليمةً في إكسل بلا خطوة إضافيّة.
     *
     * @param  array<int,array<string,mixed>>  $rows
     */
    public function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");

        if ($rows !== []) {
            fputcsv($handle, array_keys((array) $rows[0]));

            foreach ($rows as $row) {
                fputcsv($handle, array_values((array) $row));
            }
        }

        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    // ------------------------------------------------------------------ داخليّ

    /**
     * PDF: عمودٌ واحد بسطرٍ لكلّ صفّ («العمود: القيمة» مفصولةً بنقطة وسطى).
     * ولماذا لا جدول؟ لأنّ `AtsPdfWriter` مصمَّم على عمودٍ واحد تُقرأ سطوره
     * بالترتيب — وهو ما يجعل النصّ **قابلًا للاستخراج** لا صورةً مرسومة.
     *
     * @param  array<int,array<string,mixed>>  $shown
     */
    private function pdfContent(array $shown, int $total, string $title, string $subtitle): string
    {
        $lines = [];

        foreach ($shown as $row) {
            $parts = [];

            foreach ((array) $row as $column => $value) {
                $parts[] = trim((string) $column).': '.trim((string) $value);
            }

            $lines[] = implode(' · ', $parts);
        }

        if ($lines === []) {
            $lines[] = (string) setting('exports.pdf_empty_line', 'مافيش بيانات في المدى ده.');
        }

        if (count($shown) < $total) {
            $lines[] = strtr((string) setting('exports.pdf_truncated_line', 'معروض أوّل :shown صفًّا من :total — الملفّ الكامل بصيغة CSV أو Excel.'), [
                ':shown' => (string) count($shown),
                ':total' => (string) $total,
            ]);
        }

        return $this->pdf->build($title, $subtitle, [
            ['heading' => $title, 'lines' => $lines],
        ]);
    }

    /** سقف صفوف لكلّ صيغة — الملفّ الذي لا يُفتَح لا ينفع أحدًا (2.13) */
    private function limit(array $rows, string $format): array
    {
        $limit = max(1, (int) setting('exports.'.$format.'_row_limit', $format === 'pdf' ? 500 : 20000));

        return array_slice($rows, 0, $limit);
    }

    /** @return array{name:string,mime:string,content:string,format:string,note:string} */
    private function file(string $slug, string $format, string $content, string $note = ''): array
    {
        $slug = str()->slug($slug) ?: 'export';

        return [
            'name' => $slug.'-'.now()->format('Ymd-His').'.'.$format,
            'mime' => $this->mime($format),
            'content' => $content,
            'format' => $format,
            'note' => $note,
        ];
    }
}
