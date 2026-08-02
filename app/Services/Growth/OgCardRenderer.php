<?php

namespace App\Services\Growth;

/**
 * ⭐ صورة OG تلقائيّة **لكلّ نوع رابط** (21.1-أ · 12.14): تدريب · مسار · بروفايل ·
 * ترتيب · مقال · شهادة — «فأيّ رابط يُنشَر على واتساب يظهر كبطاقة مصمَّمة لا رابطًا أصلع».
 *
 * ⭐ **مرسومة SVG بأيدينا** بهويّة المنصّة، بلا أيّ مكتبة صور أو أيقونات (2.16-ج)،
 *    وبقالبٍ مستقلّ لكلّ نوع كما ينصّ 12.14 — والألوان والتسميات كلّها إعدادات.
 */
class OgCardRenderer
{
    /** أنواع الروابط المعتمَدة — قائمة مقفولة لا نوع حرّ */
    public const TYPES = ['course', 'path', 'profile', 'leaderboard', 'article', 'certificate'];

    /**
     * قالب النوع: التسمية الفوقيّة والأيقونة المرسومة والّلون المميّز.
     *
     * @return array{label:string, accent:string, glyph:string}
     */
    public function template(string $type): array
    {
        $defaults = [
            'course' => ['label' => 'تدريب', 'accent' => '#00d4b8', 'glyph' => 'book'],
            'path' => ['label' => 'مسار تعلّم', 'accent' => '#7c9cff', 'glyph' => 'path'],
            'profile' => ['label' => 'بروفايل', 'accent' => '#f0b429', 'glyph' => 'person'],
            'leaderboard' => ['label' => 'لوحة الترتيب', 'accent' => '#ff8a5b', 'glyph' => 'trophy'],
            'article' => ['label' => 'مقال', 'accent' => '#9ad5a0', 'glyph' => 'quote'],
            'certificate' => ['label' => 'شهادة معتمدة', 'accent' => '#00d4b8', 'glyph' => 'seal'],
        ];

        $configured = setting('growth.og.templates');
        $template = is_array($configured) && isset($configured[$type]) && is_array($configured[$type])
            ? $configured[$type] + ($defaults[$type] ?? $defaults['course'])
            : ($defaults[$type] ?? $defaults['course']);

        return [
            'label' => (string) $template['label'],
            'accent' => (string) $template['accent'],
            'glyph' => (string) $template['glyph'],
        ];
    }

    /**
     * البطاقة: عنوان + سطر تعريفيّ + شارة النوع + أيقونة مرسومة.
     *
     * @param  array<int,string>  $meta  أسطر صغيرة أسفل العنوان
     */
    public function card(string $type, string $title, array $meta = [], ?string $footer = null): string
    {
        $template = $this->template($type);
        $accent = $template['accent'];
        $bg = (string) setting('growth.og.background', '#0b1512');
        $text = (string) setting('growth.og.text', '#e8f5f2');

        $titleLines = $this->wrap($title, (int) setting('growth.og.title_chars', 26), 3);
        $lines = '';
        $y = 300;

        foreach ($titleLines as $line) {
            $lines .= '<text x="1080" y="'.$y.'" text-anchor="end" direction="rtl" font-size="64" font-weight="800" fill="'.$this->e($text).'">'.$this->e($line).'</text>';
            $y += 82;
        }

        $metaText = '';
        $metaY = $y + 10;

        foreach (array_slice($meta, 0, 2) as $line) {
            $metaText .= '<text x="1080" y="'.$metaY.'" text-anchor="end" direction="rtl" font-size="30" fill="'.$this->e($text).'" opacity="0.72">'.$this->e($line).'</text>';
            $metaY += 44;
        }

        $glyph = $this->glyph($template['glyph'], $accent);
        $footerText = $footer ?? (string) setting('growth.og.footer', 'ابدأ رحلتك معنا');

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630" font-family="Cairo, sans-serif" role="img" aria-label="{$this->e($title)}">
              <rect width="1200" height="630" fill="{$this->e($bg)}"/>
              <rect x="0" y="0" width="1200" height="10" fill="{$this->e($accent)}"/>
              <circle cx="110" cy="520" r="190" fill="{$this->e($accent)}" opacity="0.07"/>
              <text x="1080" y="140" text-anchor="end" direction="rtl" font-size="34" font-weight="700" fill="{$this->e($accent)}">{$this->e((string) config('app.name'))} · {$this->e($template['label'])}</text>
              {$lines}
              {$metaText}
              <g transform="translate(96,104)">{$glyph}</g>
              <text x="1080" y="566" text-anchor="end" direction="rtl" font-size="28" fill="{$this->e($text)}" opacity="0.6">{$this->e($footerText)}</text>
            </svg>
            SVG;
    }

    /** أيقونات SVG **مرسومة داخل المشروع** — ولكلّ نوعٍ شكلُه فلا يحمل اللونُ المعنى وحده (2.16-ب) */
    private function glyph(string $key, string $accent): string
    {
        $c = $this->e($accent);

        return match ($key) {
            'path' => '<path d="M8 56 L28 20 L48 44 L68 8" fill="none" stroke="'.$c.'" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/><circle cx="68" cy="8" r="7" fill="'.$c.'"/>',
            'person' => '<circle cx="34" cy="20" r="14" fill="none" stroke="'.$c.'" stroke-width="6"/><path d="M8 60 C8 42 60 42 60 60" fill="none" stroke="'.$c.'" stroke-width="6" stroke-linecap="round"/>',
            'trophy' => '<path d="M18 8 H54 V26 A18 18 0 0 1 18 26 Z" fill="none" stroke="'.$c.'" stroke-width="6" stroke-linejoin="round"/><path d="M18 12 H6 V20 A12 12 0 0 0 18 32" fill="none" stroke="'.$c.'" stroke-width="5"/><path d="M54 12 H66 V20 A12 12 0 0 1 54 32" fill="none" stroke="'.$c.'" stroke-width="5"/><path d="M36 44 V58 M22 62 H50" fill="none" stroke="'.$c.'" stroke-width="6" stroke-linecap="round"/>',
            'quote' => '<path d="M12 44 V26 A10 10 0 0 1 22 16 H30" fill="none" stroke="'.$c.'" stroke-width="6" stroke-linecap="round"/><path d="M42 44 V26 A10 10 0 0 1 52 16 H60" fill="none" stroke="'.$c.'" stroke-width="6" stroke-linecap="round"/><path d="M12 44 H30 M42 44 H60" stroke="'.$c.'" stroke-width="6" stroke-linecap="round"/>',
            'seal' => '<circle cx="36" cy="28" r="22" fill="none" stroke="'.$c.'" stroke-width="6"/><path d="M24 30 L33 39 L50 20" fill="none" stroke="'.$c.'" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 48 L18 68 L36 60 L54 68 L50 48" fill="none" stroke="'.$c.'" stroke-width="5" stroke-linejoin="round"/>',
            default => '<path d="M10 12 H32 A6 6 0 0 1 38 18 V62 A6 6 0 0 0 32 56 H10 Z" fill="none" stroke="'.$c.'" stroke-width="6" stroke-linejoin="round"/><path d="M62 12 H40 A6 6 0 0 0 34 18 V62 A6 6 0 0 1 40 56 H62 Z" fill="none" stroke="'.$c.'" stroke-width="6" stroke-linejoin="round"/>',
        };
    }

    /** لفّ العنوان على أسطر بلا قطع كلمة — والزائد يُختصر بثلاث نقاط */
    private function wrap(string $value, int $perLine, int $maxLines): array
    {
        $words = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if (mb_strlen($candidate) > $perLine && $current !== '') {
                $lines[] = $current;
                $current = $word;

                if (count($lines) === $maxLines) {
                    break;
                }
            } else {
                $current = $candidate;
            }
        }

        if (count($lines) < $maxLines && $current !== '') {
            $lines[] = $current;
        }

        if (count($lines) === $maxLines && $current !== '' && ! in_array($current, $lines, true)) {
            $lines[$maxLines - 1] = mb_substr($lines[$maxLines - 1], 0, $perLine - 1).'…';
        }

        return $lines === [] ? [''] : $lines;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
