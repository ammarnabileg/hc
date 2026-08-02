<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * «أضِف لتقويمي» (13.3) — ملفّ ICS مكتوب بأيدينا سطرًا سطرًا، **بلا أيّ مكتبة تقويم**.
 *
 * التوقيت: نكتب الأوقات بـUTC (لاحقة Z) ونرفق منطقة المستخدم في X-WR-TIMEZONE،
 * فيعرضها تقويمه **بتوقيته المحلّيّ** بلا خطأ في التوقيت الصيفيّ.
 */
class IcsGenerator
{
    public function __construct(private readonly EventPresenter $presenter) {}

    public function forEvent(Event $event, ?User $user = null): string
    {
        $tz = $this->presenter->timezone($user);
        $start = CarbonImmutable::parse($event->starts_at)->utc();
        $end = CarbonImmutable::parse($this->presenter->endsAt($event))->utc();
        $localStart = $start->setTimezone($tz);

        $where = $event->mode === 'offline'
            ? (string) $event->location
            : ($event->join_link ? 'أونلاين' : (string) $event->location);

        $description = trim(
            ($event->description ? strip_tags((string) $event->description)."\n\n" : '')
            .'التوقيت المحلّيّ: '.$localStart->format('Y-m-d H:i').' ('.$tz.')'
            ."\n".route('events.show', $event->slug)
        );

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.$this->escape((string) config('app.name')).'//Events//AR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-TIMEZONE:'.$this->escape($tz),
            'BEGIN:VEVENT',
            'UID:event-'.$event->id.'@'.parse_url((string) config('app.url'), PHP_URL_HOST).'',
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$start->format('Ymd\THis\Z'),
            'DTEND:'.$end->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape((string) $event->title_ar),
            'DESCRIPTION:'.$this->escape($description),
            'LOCATION:'.$this->escape($where),
            'URL:'.$this->escape(route('events.show', $event->slug)),
            'STATUS:CONFIRMED',
            'BEGIN:VALARM',
            'TRIGGER:-PT'.max(5, (int) setting('events.reminder.minutes_before', 60)).'M',
            'ACTION:DISPLAY',
            'DESCRIPTION:'.$this->escape('فاضل شويّة على: '.$event->title_ar),
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    public function filename(Event $event): string
    {
        return 'event-'.$event->slug.'.ics';
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n'],
            trim($value),
        );
    }

    /** طيّ السطور عند 75 بايت كما يفرض RFC 5545 */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $chunks = [];
        $current = '';

        foreach (mb_str_split($line) as $char) {
            if (strlen($current.$char) > 73) {
                $chunks[] = $current;
                $current = '';
            }

            $current .= $char;
        }

        $chunks[] = $current;

        return implode("\r\n ", $chunks);
    }
}
