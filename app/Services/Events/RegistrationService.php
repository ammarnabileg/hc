<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * التسجيل في الفعاليّة (13.3): مجّانًا أو بالكوينز/التذاكر،
 * ومرّة واحدة لكلّ مستخدم — ومعه **تذكرة قابلة للنشر** بكود فريد.
 */
class RegistrationService
{
    public function __construct(
        private readonly EventPresenter $presenter,
        private readonly LedgerBridge $ledger,
        private readonly Tracker $tracker,
    ) {}

    /**
     * @return array{ok: bool, message: string, registration: ?EventRegistration}
     */
    public function register(Event $event, User $user, ?string $attendMode = null): array
    {
        if ($existing = $this->registrationFor($event, $user)) {
            return $this->fail(setting('events.registration_service.register_1', 'إنت مسجّل في الفعاليّة دي بالفعل — تذكرتك تحت.'), $existing);
        }

        if ($this->presenter->hasEnded($event)) {
            return $this->fail(setting('events.registration_service.register_2', 'الفعاليّة دي خلصت. شوف تسجيلها أو اختار فعاليّة قادمة.'));
        }

        if ($this->presenter->isFull($event)) {
            return $this->fail(setting('events.registration_service.register_3', 'اكتمل العدد في الفعاليّة دي. تابع الصفحة — بننزل مواعيد جديدة باستمرار.'));
        }

        // الهجين: المستخدم يختار نمط الحضور عند التسجيل (13.3)
        if ($event->mode === 'hybrid' && ! in_array($attendMode, ['online', 'offline'], true)) {
            return $this->fail(setting('events.registration_service.register_4', 'اختار نمط الحضور الأوّل: أونلاين ولّا حضور بالمكان.'));
        }

        $mode = $event->mode === 'hybrid' ? $attendMode : $event->mode;
        $coins = (float) $event->price_coins;
        $tickets = (float) $event->price_tickets;

        if ($coins > 0 && ! $this->ledger->debit($user, 'coins', $coins, 'event', setting('events.registration_service.register_5', 'تسجيل في فعاليّة'), $event)) {
            return $this->fail(setting('events.registration_service.register_6', 'رصيد الكوينز مش كفاية للتسجيل. اشحن محفظتك وجرّب تاني.'));
        }

        if ($tickets > 0 && ! $this->ledger->debit($user, 'tickets', $tickets, 'event', setting('events.registration_service.register_7', 'تسجيل في فعاليّة'), $event)) {
            // إرجاع الكوينز فورًا لأنّ التسجيل لم يكتمل
            if ($coins > 0) {
                $this->ledger->credit($user, 'coins', $coins, 'event', setting('events.registration_service.register_8', 'إلغاء خصم تسجيل لم يكتمل'), $event);
            }

            return $this->fail(setting('events.registration_service.register_9', 'رصيد التذاكر مش كفاية للتسجيل. اشحن محفظتك وجرّب تاني.'));
        }

        try {
            $registration = DB::transaction(fn () => EventRegistration::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'attend_mode' => $mode,
                'ticket_code' => $this->ticketCode(),
            ]));
        } catch (QueryException) {
            // القيد الفريد (event_id,user_id) هو الحارس الأخير ضدّ التسجيل المكرَّر
            return $this->fail(setting('events.registration_service.register_10', 'إنت مسجّل في الفعاليّة دي بالفعل — تذكرتك تحت.'), $this->registrationFor($event, $user));
        }

        $this->tracker->record('event_register', $event, $user->id);

        return [
            'ok' => true,
            'message' => setting('events.registration_service.register_11', 'تمّ تسجيلك ✓ — تذكرتك جاهزة وتقدر تشاركها.'),
            'registration' => $registration,
        ];
    }

    public function registrationFor(Event $event, ?User $user): ?EventRegistration
    {
        if (! $user) {
            return null;
        }

        return EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();
    }

    /** كود تذكرة فريد — طوله إعداد لا رقم محروق (2.13) */
    private function ticketCode(): string
    {
        $prefix = (string) setting('events.ticket.code_prefix', 'TK');
        $length = max(6, (int) setting('events.ticket.code_length', 8));

        do {
            $code = $prefix.'-'.Str::upper(Str::random($length));
        } while (EventRegistration::where('ticket_code', $code)->exists());

        return $code;
    }

    /**
     * @return array{ok: bool, message: string, registration: ?EventRegistration}
     */
    private function fail(string $message, ?EventRegistration $registration = null): array
    {
        return ['ok' => false, 'message' => $message, 'registration' => $registration];
    }
}
