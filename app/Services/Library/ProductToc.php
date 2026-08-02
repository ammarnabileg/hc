<?php

namespace App\Services\Library;

use App\Models\Product;

/**
 * فهرس الملفّ (TOC) في القارئ المحميّ (20.3).
 *
 * لماذا فهرسٌ مُدار لا مستخرَجٌ من الـPDF؟ لأنّ استخراج العناوين يحتاج محرّكًا
 * خارجيًّا وقد يفشل صامتًا، والدستور يمنع الأرقام والنصوص المحروقة (2.13):
 * فالفهرس **يُحرَّر من شاشة الحماية** ويُخزَّن مع المنتج — ومصدره واحد.
 *
 * التخزين JSON: [{"page":1,"title":"المقدّمة"}] — والتحرير بصيغة سطريّة مريحة
 * «رقم الصفحة | العنوان» حتى لا يُطالَب الأدمن بكتابة JSON بيده.
 */
class ProductToc
{
    /**
     * مدخلات الفهرس مرتّبةً ومقيّدةً بعدد صفحات الملفّ.
     *
     * @return array<int, array{page:int,title:string}>
     */
    public function entries(Product $product, int $pageCount): array
    {
        $rows = json_decode((string) ($product->toc ?? ''), true);

        if (! is_array($rows)) {
            return [];
        }

        $entries = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $page = (int) ($row['page'] ?? 0);

            if ($title === '' || $page < 1 || $page > max($pageCount, 1)) {
                continue;
            }

            $entries[] = ['page' => $page, 'title' => $title];
        }

        usort($entries, fn (array $a, array $b) => $a['page'] <=> $b['page']);

        return array_slice($entries, 0, (int) setting('reader.toc.max_entries', 200));
    }

    /** الصيغة السطريّة المعروضة في شاشة الحماية */
    public function toText(Product $product): string
    {
        $rows = json_decode((string) ($product->toc ?? ''), true);
        $lines = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && ($row['title'] ?? '') !== '') {
                $lines[] = (int) ($row['page'] ?? 1).' | '.trim((string) $row['title']);
            }
        }

        return implode("\n", $lines);
    }

    /** تحويل ما كتبه الأدمن إلى JSON — وسطرٌ بلا رقمٍ صالح يُهمَل بلا كسر */
    public function fromText(?string $text): ?string
    {
        $entries = [];

        foreach (preg_split('/\r\n|\r|\n/', (string) $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 2));
            $page = (int) ($parts[0] ?? 0);
            $title = (string) ($parts[1] ?? '');

            if ($page < 1 || $title === '') {
                continue;
            }

            $entries[] = ['page' => $page, 'title' => mb_substr($title, 0, 190)];
        }

        return $entries === [] ? null : json_encode($entries, JSON_UNESCAPED_UNICODE);
    }
}
