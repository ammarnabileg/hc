<?php

namespace App\Services\Events;

use App\Models\Certificate;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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

        return $this->grant($event, $user, $registration);
    }

    /**
     * ⭐ **تشيك-إن بالـQR الديناميكيّ** (12.11: «QR ديناميكيّ للتشيك-إن يمنع
     * استخدام كود شخص لآخر»). الرمز نفسه **هو** إثبات الشخص، فلا كودَ إضافيّ
     * يُطلَب — لكنّ **الصرف يمرّ من نفس البوّابة** (`grant`) لا من نسخةٍ ثانية:
     * نفس الحارس الذرّيّ · نفس دفتر الأستاذ · نفس الشهادة · ونفس منع التكرار.
     * ولو كتبنا صرفًا موازيًا هنا لَافترقت النسختان يومًا وصُرِفت المكافأة مرّتين.
     *
     * @return array{ok: bool, message: string, registration: ?EventRegistration, certificate: ?Certificate, reward: array{xp: int, tickets: int}}
     */
    public function checkInByToken(EventRegistration $registration): array
    {
        $event = $registration->event;
        $user = $registration->user;

        if (! $event || ! $user) {
            return $this->fail((string) setting(
                'events.checkin.qr_msg_malformed',
                'الرمز ده مش رمز تشيك-إن سليم. اطلب من صاحبه يعرض الرمز من صفحة الفعاليّة تاني.',
            ));
        }

        if (! $this->windowOpen($event)) {
            return $this->fail(
                (string) setting(
                    'events.checkin.window_closed_message',
                    'كود الحضور بيشتغل مع بداية الفعاليّة. استنّى شويّة وجرّب تاني.',
                ),
                $registration,
            );
        }

        return $this->grant($event, $user, $registration);
    }

    /**
     * الصرف: حارسٌ ذرّيّ ⟵ دفتر الأستاذ ⟵ الشهادة. **بوّابةٌ واحدة** لكلّ طرق
     * إثبات الحضور (كود OTP · QR · تشيك-إن الأدمن).
     *
     * @return array{ok: bool, message: string, registration: ?EventRegistration, certificate: ?Certificate, reward: array{xp: int, tickets: int}}
     */
    public function grant(Event $event, User $user, EventRegistration $registration): array
    {
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

        /*
         | ⚠️ **وسائط مسمّاة لا بالترتيب:** توقيع دفتر الأستاذ
         | `(…, $source, ?Model $reference, $layer, ?string $reason)`، وتمريرُ
         | السبب العربيّ بالترتيب كان يُنزِله في خانة `source` فيتفتّت الدفتر
         | ويسقط الحدّ اليوميّ. والاسم لا ينزلق مع تغيّر التوقيع.
         */
        if ($reward['xp'] > 0) {
            $this->ledger->credit(
                user: $user,
                currencyCode: 'xp',
                amount: $reward['xp'],
                source: 'event',
                reason: (string) setting('events.reward.ledger_reason', 'حضور فعاليّة'),
                reference: $event,
            );
        }

        if ($reward['tickets'] > 0) {
            $this->ledger->credit(
                user: $user,
                currencyCode: 'tickets',
                amount: $reward['tickets'],
                source: 'event',
                reason: (string) setting('events.reward.ledger_reason', 'حضور فعاليّة'),
                reference: $event,
            );
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
     * ⭐ جدول المكافأة المتدرّجة كما ضبطه الأدمن — **مصدر واحد** لشاشة الأدمن
     * وللصرف وللعدّاد النازل. مرتَّب تصاعديًّا بالساعات، فأوّل درجة تشمل اللحظة
     * هي المستحقّة، والصفوف الناقصة تُهمَل بدل أن تكسر الترتيب.
     *
     * @return list<array{hours:int,xp:int,tickets:int}>
     */
    public function tiers(Event $event): array
    {
        $raw = $event->getAttribute('reward_tiers');
        $tiers = is_array($raw) ? $raw : json_decode((string) $raw, true);
        $tiers = is_array($tiers) && $tiers !== [] ? $tiers : (array) setting('events.reward_tiers_default', []);

        $rows = [];

        foreach ($tiers as $tier) {
            if (! is_array($tier) || ($tier['hours'] ?? null) === null || $tier['hours'] === '') {
                continue;
            }

            $rows[] = [
                'hours' => (int) $tier['hours'],
                'xp' => max(0, (int) ($tier['xp'] ?? 0)),
                'tickets' => max(0, (int) ($tier['tickets'] ?? 0)),
            ];
        }

        usort($rows, fn (array $a, array $b) => $a['hours'] <=> $b['hours']);

        return $rows;
    }

    /**
     * الدرجة المستحقّة الآن: أوّل درجة تشمل الساعات المنقضية **منذ انتهاء
     * الفعاليّة**؛ و`null` يعني أنّ آخر درجة انتهت فلا مكافأة.
     *
     * @return ?array{hours:int,xp:int,tickets:int}
     */
    public function currentTier(Event $event, ?CarbonInterface $moment = null): ?array
    {
        $moment = CarbonImmutable::parse($moment ?? now());
        $end = CarbonImmutable::parse($this->presenter->endsAt($event));
        $hours = $moment->isBefore($end) ? 0 : (int) floor($end->diffInHours($moment));

        foreach ($this->tiers($event) as $tier) {
            if ($hours <= $tier['hours']) {
                return $tier;
            }
        }

        return null;
    }

    /**
     * المكافأة متدرّجة زمنيًّا **على درجات** (13.3) — والعدّاد ظاهر للمستخدم.
     *
     * كان هذا يحسب `xp_reward × events.reward.late_percent` ويتجاهل الجدول الذي
     * يملؤه الأدمن ويُحفَظ في `events.reward_tiers` تمامًا: يضبط `[1س:10XP]`
     * فيُصرَف 500. الأدمن يضبط جدولًا بلا أثر والمستخدم يرى عدّادًا يعِد بما لا
     * يُصرَف — فالجدول من اليوم هو الحاكم، والنسبة المئويّة تبقى للفعاليّات
     * التي لا جدول لها أصلًا.
     *
     * @return array{xp: int, tickets: int}
     */
    public function reward(Event $event): array
    {
        if ($this->tiers($event) === []) {
            $factor = $this->rewardFactor($event);

            return [
                'xp' => (int) round(((int) $event->xp_reward) * $factor),
                'tickets' => (int) round(((int) $event->ticket_reward) * $factor),
            ];
        }

        $tier = $this->currentTier($event);

        return [
            'xp' => (int) ($tier['xp'] ?? 0),
            'tickets' => (int) ($tier['tickets'] ?? 0),
        ];
    }

    /** نسبة المكافأة حين لا جدولَ للفعاليّة — الحلّ الاحتياطيّ وحده */
    public function rewardFactor(Event $event): float
    {
        return $this->fullRewardUntil($event)->isFuture()
            ? 1.0
            : max(0.0, (float) setting('events.reward.late_percent', 50) / 100);
    }

    /** نهاية الدرجة الأعلى — وهي ما يعِد به العدّاد النازل، فلا يعِد بغير المصروف */
    public function fullRewardUntil(Event $event): CarbonImmutable
    {
        $tiers = $this->tiers($event);
        $hours = $tiers !== []
            ? $tiers[0]['hours']
            : (int) setting('events.reward.window_hours', 24);

        return CarbonImmutable::parse($this->presenter->endsAt($event))->addHours($hours);
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
