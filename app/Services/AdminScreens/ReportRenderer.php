<?php

namespace App\Services\AdminScreens;

use App\Models\ReportSchedule;
use App\Services\Library\AtsPdfWriter;
use Throwable;

/**
 * ملفّ التقرير المجدول بالصيغة التي اختارها الأدمن (24.3-خامسًا).
 *
 * **لماذا وُجد هذا الملفّ؟** لأنّ الشاشة كانت تعرض ثلاث صيغ (CSV · Excel · PDF)
 * والمرفق يخرج CSV دائمًا — **وعدٌ في الواجهة بلا تنفيذ**، وهو ما يرفضه الدستور
 * صراحةً. فإمّا تُخفى الخيارات وإمّا تُنفَّذ، واخترنا التنفيذ.
 *
 * والصيغتان مكتوبتان **داخل المشروع بلا مكتبة خارجيّة**:
 *  · **XLSX** عبر `XlsxWriter` — حزمة ZIP بملفّات XML بمواصفة OOXML.
 *  · **PDF** عبر `AtsPdfWriter` الموجود أصلًا في المشروع — **نستعمله ولا نكتب
 *    مولّدًا ثانيًا**، فهو يضمّن خطّ المنصّة ويكتب طبقة `ToUnicode` فتخرج
 *    العربيّة سليمةً عند الاستخراج والنسخ لا صورةً.
 */
class ReportRenderer
{
    public function __construct(
        private readonly XlsxWriter $xlsx,
        private readonly AtsPdfWriter $pdf,
    ) {}

    /**
     * بناء الملفّ.
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @param  array{days:int,label:string}  $meta
     * @return array{name:string,mime:string,content:string,format:string,note:string}
     */
    public function render(ReportSchedule $schedule, array $rows, array $meta): array
    {
        $format = (string) $schedule->format;

        // ⚠️ الـPDF العربيّ يحتاج الخطّ المضمَّن؛ ولو غاب لا نرمي خطأً في وجه
        //    الأدمن ولا نبعت ملفًّا بمربّعات — نرجع لـCSV ونقول ماذا حدث (2.17-ب).
        if ($format === 'pdf' && ! $this->pdf->available()) {
            return $this->file($schedule, 'csv', $this->csv($rows), $this->pdf->unavailableReason());
        }

        try {
            $content = match ($format) {
                'xlsx' => $this->xlsx->build($this->limit($rows, 'xlsx'), $schedule->name, $meta['label'] ?? $schedule->name),
                'pdf' => $this->pdfContent($schedule, $rows, $meta),
                default => $this->csv($rows),
            };
        } catch (Throwable $exception) {
            // فشل مولّد صيغة لا يمنع وصول التقرير — يخرج CSV ويقول لماذا
            return $this->file($schedule, 'csv', $this->csv($rows), strtr(setting('stats.report_renderer.render_1', 'تعذّر توليد ملفّ :p1 فبعتناه CSV.'), [':p1' => (string) (mb_strtoupper($format))]));
        }

        return $this->file($schedule, in_array($format, ['xlsx', 'pdf'], true) ? $format : 'csv', $content);
    }

    /** الامتدادات والأنواع — مرجع واحد يستعمله المرفق ومسار التنزيل معًا */
    public function mime(string $format): string
    {
        return match ($format) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
            default => 'text/csv; charset=UTF-8',
        };
    }

    // ------------------------------------------------------------------ الصيغ

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

    /**
     * PDF: عمودٌ واحد بسطرٍ لكلّ صفّ («العمود: القيمة» مفصولةً بنقطة وسطى).
     * لماذا لا جدول؟ لأنّ `AtsPdfWriter` مصمَّم على عمودٍ واحد تُقرأ سطوره
     * بالترتيب — وهو ما يجعل النصّ **قابلًا للاستخراج** لا صورةً مرسومة.
     */
    private function pdfContent(ReportSchedule $schedule, array $rows, array $meta): string
    {
        $shown = $this->limit($rows, 'pdf');
        $lines = [];

        foreach ($shown as $row) {
            $parts = [];

            foreach ((array) $row as $column => $value) {
                $parts[] = trim((string) $column).': '.trim((string) $value);
            }

            $lines[] = implode(' · ', $parts);
        }

        if ($lines === []) {
            $lines[] = (string) setting('report_schedules.pdf_empty_line', 'مافيش بيانات في المدى ده.');
        }

        if (count($shown) < count($rows)) {
            $lines[] = strtr((string) setting('report_schedules.pdf_truncated_line', 'معروض أوّل :shown صفًّا من :total — الملفّ الكامل بصيغة CSV.'), [
                ':shown' => (string) count($shown),
                ':total' => (string) count($rows),
            ]);
        }

        $headline = strtr((string) setting('report_schedules.pdf_headline', 'عن آخر :days يوم · :rows صفًّا · :date'), [
            ':days' => (string) ($meta['days'] ?? 0),
            ':rows' => (string) count($rows),
            ':date' => now()->format('Y-m-d'),
        ]);

        return $this->pdf->build($schedule->name, $headline, [
            ['heading' => (string) ($meta['label'] ?? $schedule->name), 'lines' => $lines],
        ]);
    }

    /** سقف صفوف لكلّ صيغة — الملفّ الذي لا يُفتَح لا ينفع أحدًا (2.13) */
    private function limit(array $rows, string $format): array
    {
        $limit = max(1, (int) setting('report_schedules.'.$format.'_row_limit', $format === 'pdf' ? 500 : 20000));

        return array_slice($rows, 0, $limit);
    }

    /** @return array{name:string,mime:string,content:string,format:string,note:string} */
    private function file(ReportSchedule $schedule, string $format, string $content, string $note = ''): array
    {
        // الاسم العربيّ يخرج من `slug` فارغًا — فنرجع لاسمٍ لاتينيّ بدل ملفٍّ بلا اسم
        $slug = str()->slug($schedule->name ?: '') ?: 'report-'.$schedule->getKey();

        return [
            'name' => $slug.'-'.now()->format('Ymd').'.'.$format,
            'mime' => $this->mime($format),
            'content' => $content,
            'format' => $format,
            'note' => $note,
        ];
    }
}
