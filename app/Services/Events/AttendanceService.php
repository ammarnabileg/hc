<?php

namespace App\Services\Events;

use App\Models\Certificate;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * إثبات الحضور (13.3): **كود OTP رقميّ** للأونلاين و**تشيك-إن** للأوفلاين،
 * والتحقّق **خادميّ بالكامل**. والمكافأة **تُصرَف بالكود الصحيح فقط ومرّة واحدة**،
 * وهي **متدرّجة زمنيًّا** (أعلى داخل النافذة ثمّ أقلّ) — والشهادة تُفتَح بعدها.
 */
class AttendanceService
{
    public function __construct(
        private readonly EventPresenter $presenter,
        private readonly LedgerBridge $ledger,
        private readonly CertificateBridge $certificates,
        private readonly Tracker $tracker,
    ) {}

    /**
     * @return array{ok: bool, message: string, registration: ?EventRegistration, certificate: ?Certificate, reward: array{xp: int, tickets: int}}
     */
    public function checkIn(Event $event, User $user, string $code): array
    {
        $registration = EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $registration) {
            return $this->fail('لازم تسجّل في الفعاليّة الأوّل، وبعدها تقدر تثبت حضورك.');
        }

        if (! $this->windowOpen($event)) {
            return $this->fail('كود الحضور بيشتغل مع بداية الفعاليّة. استنّى شويّة وجرّب تاني.', $registration);
        }

        if (! $this->codeMatches($event, $code)) {
            // المكافأة بالكود الصحيح فقط — والكود الغلط لا يفتح شيئًا
            return $this->fail('الكود مش مظبوط. راجع الكود المعروض في الفعاليّة وأدخله تاني.', $registration);
        }

        if ($registration->attended) {
            return [
                'ok' => true,
                'message' => 'حضورك متسجّل قبل كده ✓ — شهادتك ومكافأتك مصروفة بالفعل.',
                'registration' => $registration->refresh(),
                'certificate' => $registration->certificate,
                'reward' => ['xp' => 0, 'tickets' => 0],
            ];
        }

        // الحارس الذرّيّ: أوّل تحديث فقط ينجح، فلا تُصرَف المكافأة مرّتين مهما تكرّر الطلب
        $claimed = DB::table('event_registrations')
            ->where('id', $registration->id)
            ->where('attended', false)
            ->update(['attended' => true, 'attended_at' => now(), 'updated_at' => now()]);

        if ($claimed !== 1) {
            return [
                'ok' => true,
                'message' => 'حضورك متسجّل بالفعل ✓',
                'registration' => $registration->refresh(),
                'certificate' => $registration->refresh()->certificate,
                'reward' => ['xp' => 0, 'tickets' => 0],
            ];
        }

        $registration->refresh();
        $reward = $this->reward($event);

        if ($reward['xp'] > 0) {
            $this->ledger->credit($user, 'xp', $reward['xp'], 'event', 'حضور فعاليّة', $event);
        }

        if ($reward['tickets'] > 0) {
            $this->ledger->credit($user, 'tickets', $reward['tickets'], 'event', 'حضور فعاليّة', $event);
        }

        $certificate = $this->certificates->issueForRegistration($registration);
        $this->tracker->record('event_attended', $event, $user->id);

        return [
            'ok' => true,
            'message' => 'اتأكّد حضورك ✓ — شهادتك ومكافأتك اتفتحت.',
            'registration' => $registration->refresh(),
            'certificate' => $certificate,
            'reward' => $reward,
        ];
    }

    /** المقارنة خادميّة وبزمن ثابت — والمسافات وحالة الأحرف لا تُفشل مستخدمًا صادقًا */
    public function codeMatches(Event $event, string $code): bool
    {
        $expected = trim((string) $event->attendance_code);

        if ($expected === '') {
            return false;
        }

        return hash_equals(mb_strtoupper($expected), mb_strtoupper(trim($code)));
    }

    /** الكود يفتح مع بداية الفعاليّة ويبقى مستمرًّا بعدها (13.3) */
    public function windowOpen(Event $event): bool
    {
        return CarbonImmutable::parse($event->starts_at)
            ->subMinutes((int) setting('events.checkin.minutes_before', 15))
            ->isPast();
    }

    /**
     * المكافأة متدرّجة زمنيًّا: كاملة داخل النافذة ثمّ نسبة أقلّ — والعدّاد ظاهر للمستخدم.
     *
     * @return array{xp: int, tickets: int}
     */
    public function reward(Event $event): array
    {
        $factor = $this->rewardFactor($event);

        return [
            'xp' => (int) round(((int) $event->xp_reward) * $factor),
            'tickets' => (int) round(((int) $event->ticket_reward) * $factor),
        ];
    }

    public function rewardFactor(Event $event): float
    {
        return $this->fullRewardUntil($event)->isFuture()
            ? 1.0
            : max(0.0, (float) setting('events.reward.late_percent', 50) / 100);
    }

    public function fullRewardUntil(Event $event): CarbonImmutable
    {
        return CarbonImmutable::parse($this->presenter->endsAt($event))
            ->addHours((int) setting('events.reward.window_hours', 24));
    }

    /**
     * @return array{ok: bool, message: string, registration: ?EventRegistration, certificate: ?Certificate, reward: array{xp: int, tickets: int}}
     */
    private function fail(string $message, ?EventRegistration $registration = null): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'registration' => $registration,
            'certificate' => null,
            'reward' => ['xp' => 0, 'tickets' => 0],
        ];
    }
}
