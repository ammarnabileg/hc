<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventRegistration;

/**
 * صورة OG لكلّ رابط فعاليّة (21.1-أ) وبطاقة التذكرة القابلة للنشر (13.3) —
 * **مرسومة SVG بأيدينا** بهويّة المنصّة، بلا أيّ مكتبة صور أو أيقونات (2.16-ج).
 */
class ShareCardRenderer
{
    public function __construct(private readonly EventPresenter $presenter) {}

    public function eventCard(Event $event): string
    {
        $accent = (string) setting('events.og.accent', '#00d4b8');
        $bg = (string) setting('events.og.background', '#0b1512');
        $text = (string) setting('events.og.text', '#e8f5f2');

        $when = $this->presenter->localStart($event, null)->format('Y-m-d · H:i');
        $mode = $this->presenter->modeLabel((string) $event->mode);
        $where = $event->mode === 'offline' ? (string) $event->location : $mode;

        $title = $this->wrap((string) $event->title_ar, 26, 3);
        $lines = '';
        $y = 300;

        foreach ($title as $line) {
            $lines .= '<text x="1120" y="'.$y.'" text-anchor="end" direction="rtl" font-size="64" font-weight="800" fill="'.$this->e($text).'">'.$this->e($line).'</text>';
            $y += 82;
        }

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630" font-family="Cairo, sans-serif">
              <rect width="1200" height="630" fill="{$this->e($bg)}"/>
              <rect x="0" y="0" width="1200" height="10" fill="{$this->e($accent)}"/>
              <circle cx="90" cy="540" r="180" fill="{$this->e($accent)}" opacity="0.08"/>
              <text x="1120" y="140" text-anchor="end" direction="rtl" font-size="34" font-weight="700" fill="{$this->e($accent)}">{$this->e((string) config('app.name'))} · فعاليّة</text>
              <text x="1120" y="200" text-anchor="end" direction="rtl" font-size="30" fill="{$this->e($text)}" opacity="0.75">{$this->e($mode)} · {$this->e($when)}</text>
              {$lines}
              <text x="1120" y="560" text-anchor="end" direction="rtl" font-size="30" fill="{$this->e($text)}" opacity="0.7">{$this->e($where)}</text>
            </svg>
            SVG;
    }

    /** بطاقة التذكرة: الكود بخطّ كبير مقروء — لا مكتبة QR ولا اعتماد خارجيّ */
    public function ticketCard(EventRegistration $registration): string
    {
        $event = $registration->event;
        $accent = (string) setting('events.og.accent', '#00d4b8');
        $bg = (string) setting('events.og.background', '#0b1512');
        $text = (string) setting('events.og.text', '#e8f5f2');

        $title = $this->wrap((string) $event?->title_ar, 30, 2);
        $lines = '';
        $y = 250;

        foreach ($title as $line) {
            $lines .= '<text x="1120" y="'.$y.'" text-anchor="end" direction="rtl" font-size="52" font-weight="800" fill="'.$this->e($text).'">'.$this->e($line).'</text>';
            $y += 68;
        }

        $when = $event ? $this->presenter->localStart($event, $registration->user)->format('Y-m-d · H:i') : '';

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630" font-family="Cairo, sans-serif">
              <rect width="1200" height="630" fill="{$this->e($bg)}"/>
              <rect x="0" y="0" width="1200" height="10" fill="{$this->e($accent)}"/>
              <text x="1120" y="130" text-anchor="end" direction="rtl" font-size="32" font-weight="700" fill="{$this->e($accent)}">تذكرة حضور</text>
              {$lines}
              <line x1="80" y1="400" x2="1120" y2="400" stroke="{$this->e($accent)}" stroke-opacity="0.35" stroke-dasharray="14 12"/>
              <text x="1120" y="360" text-anchor="end" direction="rtl" font-size="28" fill="{$this->e($text)}" opacity="0.7">{$this->e($when)}</text>
              <text x="600" y="500" text-anchor="middle" font-size="72" font-weight="800" letter-spacing="6" fill="{$this->e($accent)}">{$this->e((string) $registration->ticket_code)}</text>
              <text x="600" y="560" text-anchor="middle" direction="rtl" font-size="26" fill="{$this->e($text)}" opacity="0.6">{$this->e((string) $registration->user?->name)}</text>
            </svg>
            SVG;
    }

    /**
     * لفّ النصّ يدويًّا على عدد أحرف — فالـSVG لا يلفّ النصّ من نفسه.
     *
     * @return array<int,string>
     */
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
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return array_slice($lines, 0, $maxLines) ?: [''];
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
