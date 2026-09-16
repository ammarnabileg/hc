<?php

namespace App\Services\Library;

use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * استخراج نصّ عربيّ صحيح الترتيب من PDF عبر `qalam` (الدستور 9).
 *
 * ⭐ `CvImporter::fromPdf()` القديمة تقرأ فقط سلاسل `(...)` الحرفيّة في تدفّق
 * المحتوى — وخطوط الـSubset المُضمَّنة (Identity-H، وهي الغالبيّة في أيّ PDF
 * حديث فيه عربيّ، بما فيها تصدير الـCV من هذه المنصّة نفسها) تكتب نصّها
 * بسلاسل Hex `<...>` لا حرفيّة، فترجع الدالّة القديمة **نصًّا فاضيًا** لا
 * نصًّا مقلوبًا فقط. و`qalam` حلّ الاثنين معًا: يفكّ Identity-H عبر
 * `/ToUnicode`، ويعيد ترتيب الحروف قبل التطبيع (لا بعده) فلا ينقلب اللام-ألف.
 *
 * Binary مُلحَق بالمشروع (`vendor-bin/qalam/qalam`) لا مكتبة تُجلَب وقت
 * التشغيل — يعمل بلا إنترنت وبلا Toolchain Rust على السيرفر (VERSION.md
 * يشرح كيف يُعاد بناؤه). يُستدعى كعمليّة فرعيّة مستقلّة فحسب، فترخيصه
 * الـGPLv3 لا يُلزِم هذا المشروع (Mere aggregation، لا ربط).
 */
class QalamExtractor
{
    public function binaryPath(): string
    {
        return (string) config('services.qalam.binary_path', base_path('vendor-bin/qalam/qalam'));
    }

    public function isAvailable(): bool
    {
        $path = $this->binaryPath();

        return $path !== '' && is_file($path) && is_executable($path);
    }

    /** النصّ الصحيح الترتيب لكلّ صفحات الملفّ، أو null لو تعذّر (يرجع الاستدعاء لطريقة الاحتياط) */
    public function extract(string $path): ?string
    {
        if (! $this->isAvailable() || ! is_file($path)) {
            return null;
        }

        try {
            $process = new Process([$this->binaryPath(), 'extract', $path, 'all'], timeout: 20);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }
        } catch (ExceptionInterface) {
            return null;
        }

        $text = $this->keepReadablePages($process->getOutput());

        return trim($text) === '' ? null : $text;
    }

    /**
     * يفصل مُخرَج qalam على فواصل الصفحات «── page N [verdict]──» ويُبقي فقط
     * صفحات `ok`/`degraded` — صفحات `needs_ocr` رسالتها تشخيصيّة («no text
     * layer»…) لا نصّ حقيقيّ، فضمّها كان سيُدخِل جملًا إنجليزيّة غريبة في
     * سيرة المستخدم (2.13 لا ينطبق: مُخرَج أداة لا نصّ شاشة).
     */
    private function keepReadablePages(string $output): string
    {
        $blocks = preg_split('/^── page \d+ \[([a-z_]+)\]──$/mu', $output, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $pages = [];

        // الفهرس الأوّل ما قبل أوّل فاصل (فارغ عادةً)؛ بعده أزواج (verdict, body)
        for ($i = 1; $i < count($blocks); $i += 2) {
            $verdict = $blocks[$i];
            $body = trim($blocks[$i + 1] ?? '');

            if (in_array($verdict, ['ok', 'degraded'], true) && $body !== '') {
                $pages[] = $body;
            }
        }

        return implode("\n\n", $pages);
    }
}
