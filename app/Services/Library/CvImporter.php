<?php

namespace App\Services\Library;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

/**
 * رفع CV جاهز واستخراج بياناته بالكامل (الدستور 9).
 *
 * > «يقدر يرفع ملفّ CV، والنظام **يستخرج بياناته بالكامل** ويملأها بدلًا عنه
 * > — **بعد سؤاله: تبديل ولا إضافة؟** ثمّ **معاينة + تعديل قبل الحفظ**.»
 *
 * **بلا أيّ مكتبة خارجيّة:** النصّ يُستخرَج بأدوات PHP وحدها —
 *   · `.docx` بـZipArchive (وهو ملفّ ZIP فيه `word/document.xml`)
 *   · `.pdf` بفكّ ضغط تدفّقات `FlateDecode` بـ`gzuncompress` وقراءة نصوصها
 *   · `.txt` و`.md` و`.html` مباشرةً.
 * والتحليل بعدها قواعديّ (عناوين أقسام عربيّة/إنجليزيّة + أسطر) لا ذكاء
 * اصطناعيّ — لأنّ الشرط «0 تكلفة» ولا خدمة خارجيّة.
 */
class CvImporter
{
    /** الامتدادات المقبولة — إعداد لا قائمة محروقة (2.13) */
    public function allowedExtensions(): array
    {
        $configured = setting('cv.import.extensions');

        return is_array($configured) && $configured !== []
            ? array_map('strval', $configured)
            : ['txt', 'md', 'html', 'htm', 'docx', 'pdf'];
    }

    public function maxKb(): int
    {
        return max(64, (int) setting('cv.import.max_kb', 4096));
    }

    /** استخراج نصّ الملفّ — ويرمي رسالةً تقول ماذا حدث وماذا يفعل (2.17-ب) */
    public function extractText(UploadedFile $file): string
    {
        $ext = mb_strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($ext, $this->allowedExtensions(), true)) {
            throw new RuntimeException('نوع الملفّ ده مش مدعوم. ارفع '.implode(' أو ', $this->allowedExtensions()).'.');
        }

        $path = $file->getRealPath();
        $raw = $path ? (string) file_get_contents($path) : '';

        $text = match ($ext) {
            'docx' => $this->fromDocx($path ?: ''),
            'pdf' => $this->fromPdf($raw),
            'html', 'htm' => $this->fromHtml($raw),
            default => $raw,
        };

        $text = trim(preg_replace('/\n{3,}/u', "\n\n", str_replace("\r\n", "\n", $text)) ?? '');

        if ($text === '') {
            throw new RuntimeException('مقدرناش نقرا الملفّ ده. جرّب ترفعه بصيغة Word أو نصّ عاديّ.');
        }

        return $text;
    }

    /**
     * تحليل النصّ إلى بنية السيرة (نفس شكل `CvBuilder::blank()`).
     *
     * @return array<string,mixed>
     */
    public function parse(string $text): array
    {
        $sections = $this->splitSections($text);

        return [
            'profile' => $this->parseProfile($text, $sections),
            'experience' => $this->parseEntries($sections['experience'] ?? [], 'experience'),
            'education' => $this->parseEntries($sections['education'] ?? [], 'education'),
            'skills' => $this->parseSkills($sections['skills'] ?? []),
            'languages' => $this->parseLanguages($sections['languages'] ?? []),
        ];
    }

    /**
     * ⭐ «تبديل ولا إضافة؟» — سؤال المستخدم قبل أيّ كتابة (9).
     *
     * @param  'replace'|'append'  $mode
     */
    public function apply(array $current, array $parsed, string $mode): array
    {
        if ($mode === 'replace') {
            return array_replace($current, array_filter($parsed, fn ($v) => $v !== [] && $v !== ''));
        }

        $merged = $current;

        // الإضافة: الحقول الفارغة تُملأ، والصفوف تُلحَق بلا تكرار
        $merged['profile'] = array_replace(
            (array) ($parsed['profile'] ?? []),
            array_filter((array) ($current['profile'] ?? []), fn ($v) => trim((string) $v) !== ''),
        );

        foreach (['experience', 'education', 'languages'] as $key) {
            $merged[$key] = $this->appendUnique((array) ($current[$key] ?? []), (array) ($parsed[$key] ?? []));
        }

        $skills = array_filter(array_map('trim', array_merge(
            explode(',', (string) ($current['skills'] ?? '')),
            explode(',', (string) ($parsed['skills'] ?? '')),
        )));

        $merged['skills'] = implode('، ', array_unique($skills));

        return $merged;
    }

    // ------------------------------------------------------------------ الاستخراج

    private function fromDocx(string $path): string
    {
        if ($path === '' || ! class_exists(ZipArchive::class)) {
            throw new RuntimeException('مقدرناش نفتح ملفّ Word. جرّب تحفظه PDF أو نصّ وارفعه تاني.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('الملفّ مش سليم. جرّب تحفظه من جديد وارفعه.');
        }

        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        // الفقرات والأسطر تصير أسطرًا حقيقيّة قبل نزع الوسوم
        $xml = preg_replace('/<w:(p|br)[^>]*\/?>/u', "\n", $xml) ?? $xml;

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** نصّ الـPDF: تدفّقات FlateDecode ثمّ عوامل النصّ Tj/TJ */
    private function fromPdf(string $raw): string
    {
        $out = [];

        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = @gzuncompress($stream);

                if ($decoded === false) {
                    $decoded = @gzinflate($stream);
                }

                $body = $decoded === false ? $stream : $decoded;

                if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)/s', $body, $texts)) {
                    foreach ($texts[0] as $chunk) {
                        $out[] = stripcslashes(substr($chunk, 1, -1));
                    }
                }

                if (str_contains($body, 'TD') || str_contains($body, 'Td') || str_contains($body, 'TJ')) {
                    $out[] = "\n";
                }
            }
        }

        return trim(implode(' ', $out));
    }

    private function fromHtml(string $raw): string
    {
        $raw = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $raw) ?? $raw;
        $raw = preg_replace('#<(br|/p|/div|/li|/tr|/h[1-6])[^>]*>#i', "\n", $raw) ?? $raw;

        return html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // ------------------------------------------------------------------ التحليل

    /** عناوين الأقسام بالعربيّة والإنجليزيّة — إعداد قابل للتوسيع (2.13) */
    private function headings(): array
    {
        $configured = setting('cv.import.headings');

        if (is_array($configured) && $configured !== []) {
            return $configured;
        }

        return [
            'experience' => ['الخبرة', 'الخبرات', 'الخبرة العملية', 'الخبرة العمليّة', 'خبرات العمل', 'experience', 'work experience', 'employment'],
            'education' => ['التعليم', 'المؤهلات', 'المؤهّلات', 'رحلة التعلم', 'التعليم والمؤهلات', 'education', 'academic'],
            'skills' => ['المهارات', 'مهارات', 'skills', 'technical skills'],
            'languages' => ['اللغات', 'لغات', 'languages'],
            'summary' => ['نبذة', 'الملخص', 'الملخّص', 'الملخص المهني', 'summary', 'profile', 'objective', 'about'],
        ];
    }

    /** @return array<string, array<int,string>> */
    private function splitSections(string $text): array
    {
        $lines = preg_split('/\n/u', $text) ?: [];
        $headings = $this->headings();
        $sections = [];
        $current = null;

        foreach ($lines as $line) {
            $clean = trim(preg_replace('/^[\s\p{P}]+|[\s\p{P}]+$/u', '', $line) ?? '');

            if ($clean === '') {
                continue;
            }

            $matched = $this->matchHeading($clean, $headings);

            if ($matched !== null) {
                $current = $matched;
                $sections[$current] ??= [];

                continue;
            }

            if ($current !== null) {
                $sections[$current][] = $clean;
            }
        }

        return $sections;
    }

    private function matchHeading(string $line, array $headings): ?string
    {
        // العنوان سطر قصير — فلا تُلتَقط جملة داخل فقرة بالخطأ
        if (mb_strlen($line) > 40) {
            return null;
        }

        $normalized = mb_strtolower(str_replace(['أ', 'إ', 'آ', 'ة'], ['ا', 'ا', 'ا', 'ه'], $line));

        foreach ($headings as $key => $words) {
            foreach ((array) $words as $word) {
                $needle = mb_strtolower(str_replace(['أ', 'إ', 'آ', 'ة'], ['ا', 'ا', 'ا', 'ه'], (string) $word));

                if ($normalized === $needle) {
                    return (string) $key;
                }
            }
        }

        return null;
    }

    private function parseProfile(string $text, array $sections): array
    {
        $profile = [];

        if (preg_match('/[\w.+-]+@[\w-]+\.[\w.]+/u', $text, $m)) {
            $profile['email'] = $m[0];
        }

        if (preg_match('/(?:\+?\d[\d\s-]{7,16}\d)/u', $text, $m)) {
            $profile['phone'] = preg_replace('/\s+/', '', $m[0]);
        }

        $summary = implode(' ', $sections['summary'] ?? []);

        if ($summary !== '') {
            $profile['summary'] = mb_substr($summary, 0, (int) setting('cv.summary.max_chars', 600));
        }

        // أوّل سطر خبرة غالبًا «المسمّى — الشركة»
        $firstExperience = ($sections['experience'] ?? [])[0] ?? '';

        if ($firstExperience !== '') {
            $parts = preg_split('/\s*[—–\-|،,]\s*/u', $firstExperience) ?: [];
            $profile['job_title'] = trim((string) ($parts[0] ?? ''));
            $profile['company'] = trim((string) ($parts[1] ?? ''));
        }

        return array_filter($profile, fn ($v) => trim((string) $v) !== '');
    }

    /** @param  'experience'|'education'  $kind */
    private function parseEntries(array $lines, string $kind): array
    {
        $entries = [];

        foreach ($lines as $line) {
            $years = [];

            if (preg_match_all('/(19|20)\d{2}/u', $line, $m)) {
                $years = $m[0];
            }

            $current = (bool) preg_match('/حتى الآن|حتى الان|حتّى الآن|present|now/iu', $line);
            $parts = array_values(array_filter(array_map('trim', preg_split('/\s*[—–|،,]\s*/u', $line) ?: [])));

            if ($parts === []) {
                continue;
            }

            $entry = $kind === 'experience'
                ? ['title' => $parts[0], 'company' => $parts[1] ?? '', 'city' => $parts[2] ?? '', 'description' => '']
                : ['degree' => $parts[0], 'institution' => $parts[1] ?? '', 'major' => $parts[2] ?? '', 'gpa' => ''];

            $entry['from'] = (string) ($years[0] ?? '');
            $entry['to'] = $current ? '' : (string) ($years[1] ?? '');
            $entry['current'] = $current;

            $entries[] = $entry;
        }

        return $entries;
    }

    private function parseSkills(array $lines): string
    {
        $skills = [];

        foreach ($lines as $line) {
            foreach (preg_split('/\s*[،,•\-·]\s*/u', $line) ?: [] as $skill) {
                $skill = trim((string) $skill);

                if ($skill !== '' && mb_strlen($skill) <= 40) {
                    $skills[] = $skill;
                }
            }
        }

        return mb_substr(implode('، ', array_unique($skills)), 0, (int) setting('cv.skills.max_chars', 600));
    }

    private function parseLanguages(array $lines): array
    {
        $languages = [];

        foreach ($lines as $line) {
            $parts = array_values(array_filter(array_map('trim', preg_split('/\s*[:—–|،,\-]\s*/u', $line) ?: [])));

            if ($parts === []) {
                continue;
            }

            $languages[] = ['language' => $parts[0], 'level' => $parts[1] ?? ''];
        }

        return $languages;
    }

    private function appendUnique(array $current, array $incoming): array
    {
        $seen = collect($current)->map(fn ($row) => json_encode($row, JSON_UNESCAPED_UNICODE))->all();

        foreach ($incoming as $row) {
            $key = json_encode($row, JSON_UNESCAPED_UNICODE);

            if (! in_array($key, $seen, true)) {
                $current[] = $row;
                $seen[] = $key;
            }
        }

        return array_values($current);
    }
}
