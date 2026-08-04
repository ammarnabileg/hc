<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * حالة الفعاليّة كما يراها مستخدمٌ بعينه (13.3 · 24.5):
 * الوقت المحلّيّ · العدّاد · السعة · وهل حان وقت رابط الانضمام.
 */
class EventPresenter
{
    /** المنطقة الزمنيّة للمستخدم — من دولته، وإلّا منطقة المنصّة (2.13) */
    public function timezone(?User $user): string
    {
        return $user?->country?->timezone
            ?: (string) setting('system.timezone', 'Africa/Cairo');
    }

    public function localStart(Event $event, ?User $user): CarbonInterface
    {
        return CarbonImmutable::parse($event->starts_at)->setTimezone($this->timezone($user));
    }

    public function localEnd(Event $event, ?User $user): CarbonInterface
    {
        return CarbonImmutable::parse($this->endsAt($event))->setTimezone($this->timezone($user));
    }

    public function endsAt(Event $event): CarbonInterface
    {
        return $event->ends_at
            ? CarbonImmutable::parse($event->ends_at)
            : CarbonImmutable::parse($event->starts_at)
                ->addMinutes((int) setting('events.default_duration_minutes', 90));
    }

    public function hasEnded(Event $event): bool
    {
        return $this->endsAt($event)->isPast();
    }

    public function isLive(Event $event): bool
    {
        return CarbonImmutable::parse($event->starts_at)->isPast() && ! $this->hasEnded($event);
    }

    public function seatsTaken(Event $event): int
    {
        return (int) ($event->registrations_count ?? EventRegistration::where('event_id', $event->id)->count());
    }

    public function isFull(Event $event): bool
    {
        return $event->capacity !== null && $this->seatsTaken($event) >= (int) $event->capacity;
    }

    public function seatsLeft(Event $event): ?int
    {
        return $event->capacity === null ? null : max(0, (int) $event->capacity - $this->seatsTaken($event));
    }

    /**
     * رابط الانضمام **يظهر قبل الموعد بفترة فقط** (13.3) — لا قبلها ولا بعد الانتهاء.
     * والقرار خادميّ: الرابط لا يُرسَل للواجهة أصلًا قبل وقته.
     */
    public function joinLinkVisible(Event $event, ?EventRegistration $registration = null): bool
    {
        if (! $event->join_link || $event->mode === 'offline') {
            return false;
        }

        if (! $registration) {
            return false;
        }

        if ($this->hasEnded($event)) {
            return false;
        }

        $opensAt = CarbonImmutable::parse($event->starts_at)
            ->subMinutes((int) setting('events.join_link.minutes_before', 30));

        return $opensAt->isPast();
    }

    public function joinLinkOpensAt(Event $event): CarbonInterface
    {
        return CarbonImmutable::parse($event->starts_at)
            ->subMinutes((int) setting('events.join_link.minutes_before', 30));
    }

    /** حالة الفعاليّة بقاموس 2.16: سليم/انتبه/غير نشط — مع رمزها دائمًا */
    public function state(Event $event): string
    {
        return match (true) {
            $this->hasEnded($event) => 'idle',
            $this->isLive($event) => 'ok',
            $this->isFull($event) => 'warn',
            default => 'ok',
        };
    }

    public function stateLabel(Event $event): string
    {
        return match (true) {
            $this->hasEnded($event) => setting('events.event_presenter.state_label_1', 'انتهت'),
            $this->isLive($event) => setting('events.event_presenter.state_label_2', 'شغّالة دلوقتي'),
            $this->isFull($event) => setting('events.event_presenter.state_label_3', 'اكتمل العدد'),
            default => setting('events.event_presenter.state_label_4', 'قادمة'),
        };
    }

    public function modeLabel(string $mode): string
    {
        return match ($mode) {
            'offline' => setting('events.event_presenter.mode_label_1', 'أوفلاين'),
            'hybrid' => setting('events.event_presenter.mode_label_2', 'هجين'),
            default => setting('events.event_presenter.mode_label_3', 'أونلاين'),
        };
    }

    /** نصّ العدّاد التنازليّ من الخادم — فالرقم يظهر حتّى لو تعطّل الـJS (2.17-أ) */
    public function countdownText(Event $event): string
    {
        if ($this->hasEnded($event)) {
            return setting('events.event_presenter.countdown_text_1', 'انتهت');
        }

        $start = CarbonImmutable::parse($event->starts_at);

        if ($start->isPast()) {
            return setting('events.event_presenter.countdown_text_2', 'شغّالة دلوقتي');
        }

        return strtr(setting('events.event_presenter.countdown_text_3', 'باقي :p1'), [':p1' => (string) ($start->diffForHumans(now(), CarbonInterface::DIFF_ABSOLUTE, true, 2))]);
    }
}
