<?php

namespace App\Services\Volunteer\Meetings;

use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * تسجيل الحضور وقيمته على درجة الالتزام (13.4-ن-ب · 24.4).
 *
 * المقياس ليس حضور الاجتماع نفسه، بل **تسجيل الحضور بالكود بعد ضغط
 * «إنهاء الاجتماع»** داخل نافذةٍ يحدّدها المسؤول:
 *   خلال 3 ساعات ⟵ `meeting.within_3h` · حتى 12 ساعة ⟵ `meeting.within_12h`
 *   غياب باعتذار مسبق ⟵ `meeting.excused_absence` · بلا اعتذار ⟵ `meeting.unexcused_absence`
 *   وإنهاؤه بمحضر موثَّق ⟵ `meeting.managed` لصاحبه.
 *
 * ⭐ ولا رقم واحد منها مكتوب هنا — كلّها من `rep_rule()`.
 */
class AttendanceService
{
    public function __construct(
        private readonly MeetingScope $scope,
        private readonly MeetingLedger $ledger,
    ) {}

    /** حدّ الشريحة الأولى (ساعات) — إعداد لا رقم محروق (2.13) */
    public function tier1Hours(): float
    {
        return (float) setting('meetings.attendance.tier1_hours', 3);
    }

    /** حدّ الشريحة الثانية (ساعات) */
    public function tier2Hours(): float
    {
        return (float) setting('meetings.attendance.tier2_hours', 12);
    }

    public function defaultWindowHours(): int
    {
        return (int) setting('meetings.attendance.default_window_hours', 12);
    }

    public function maxWindowHours(): int
    {
        return (int) setting('meetings.attendance.max_window_hours', 48);
    }

    /** نافذة التسجيل مفتوحة؟ */
    public function windowOpen(Meeting $meeting): bool
    {
        return $meeting->status === 'ended'
            && $meeting->attendance_closes_at !== null
            && now()->lessThan($meeting->attendance_closes_at);
    }

    /** الوقت المتبقّي في النافذة — يغذّي البانر الأحمر المتحرّك والعدّاد الملوّن */
    public function windowRemaining(Meeting $meeting): ?CarbonInterface
    {
        return $this->windowOpen($meeting) ? $meeting->attendance_closes_at : null;
    }

    /**
     * قيمة التسجيل حسب زمنه بعد الانتهاء — المصدر الوحيد لهذه القيم.
     *
     * @return array{key:string,value:float}
     */
    public function valueForHours(float $hoursAfterEnd): array
    {
        // ما بعد الشريحتين المنصوصتين: نافذة أطول يفتحها المسؤول، وقيمتها إعداد مستقلّ
        $key = match (true) {
            $hoursAfterEnd <= $this->tier1Hours() => 'meeting.within_3h',
            $hoursAfterEnd <= $this->tier2Hours() => 'meeting.within_12h',
            default => 'meeting.late_registration',
        };

        return ['key' => $key, 'value' => rep_rule($key)];
    }

    /** لون العدّاد يتبع الشريحة التي سيقع فيها التسجيل الآن (2.16) */
    public function tierState(Meeting $meeting): string
    {
        $hours = $this->hoursAfterEnd($meeting);

        if ($hours === null) {
            return 'idle';
        }

        return match (true) {
            $hours <= $this->tier1Hours() => 'ok',
            $hours <= $this->tier2Hours() => 'warn',
            default => 'danger',
        };
    }

    public function hoursAfterEnd(Meeting $meeting): ?float
    {
        if (! $meeting->ended_at) {
            return null;
        }

        return max(0, $meeting->ended_at->diffInMinutes(now(), false) / 60);
    }

    /**
     * اعتذار مسبق — يُقدَّم قبل إنهاء الاجتماع، ويمنع خصم الغياب (13.4-ن-ب).
     */
    public function excuse(Meeting $meeting, User $user, string $reason): array
    {
        if ($meeting->status === 'ended') {
            return $this->fail(setting('meetings.attendance_service.excuse_1', 'الاجتماع انتهى — الاعتذار المسبق بيتقدّم قبل الإنهاء. سجّل حضورك لو حضرت.'));
        }

        if (! $this->scope->isAudience($user, $meeting)) {
            return $this->fail(setting('meetings.attendance_service.excuse_2', 'الاجتماع ده مش في نطاقك.'));
        }

        $attendance = MeetingAttendance::query()->firstOrNew([
            'meeting_id' => $meeting->id,
            'user_id' => $user->id,
        ]);

        if ($attendance->exists && $attendance->status === 'registered') {
            return $this->fail(setting('meetings.attendance_service.excuse_3', 'إنت مسجّل حضورك بالفعل.'));
        }

        $attendance->fill([
            'status' => 'excused',
            'excuse_reason' => $reason,
            'rep_value' => rep_rule('meeting.excused_absence'),
        ])->save();

        return [
            'ok' => true,
            'message' => setting('meetings.attendance_service.excuse_4', 'وصلنا اعتذارك — مفيش أيّ خصم على درجة التزامك.'),
            'value' => (float) $attendance->rep_value,
        ];
    }

    /**
     * تسجيل الحضور: **التحقّق كلّه على الخادم**.
     * ⭐ والكود الخاطئ لا يُنشئ سطر حضور ولا يستهلك محاولة التسجيل الصحيحة —
     * يقدر يعيد المحاولة ما دامت النافذة مفتوحة (24.4).
     *
     * @param  array<int,string>  $answers  إجابات أسئلة الاختيارات: question_id => answer
     */
    public function register(Meeting $meeting, User $user, ?string $code, array $answers = []): array
    {
        if (! $this->scope->isAudience($user, $meeting)) {
            return $this->fail(setting('meetings.attendance_service.register_1', 'الاجتماع ده مش في نطاقك.'));
        }

        if ($meeting->status !== 'ended') {
            return $this->fail(setting('meetings.attendance_service.register_2', 'نافذة التسجيل بتفتح بعد إنهاء الاجتماع — استنّى إشعار الإنهاء.'));
        }

        if (! $this->windowOpen($meeting)) {
            return $this->fail(setting('meetings.attendance_service.register_3', 'نافذة تسجيل الحضور اتقفلت. لو عندك عذر كلّم مسؤولك.'));
        }

        $existing = MeetingAttendance::query()
            ->where('meeting_id', $meeting->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && $existing->status === 'registered') {
            return $this->fail(setting('meetings.attendance_service.register_4', 'حضورك متسجّل خلاص — القيمة اتضافت قبل كده.'));
        }

        if (! $this->verify($meeting, $code, $answers)) {
            // بلا سطر حضور وبلا معاملة: المحاولة الصحيحة ما زالت في يده
            return $this->fail(setting('meetings.attendance_service.register_5', 'الكود أو الإجابة مش مظبوطة. راجعها وجرّب تاني — محاولتك لسّه موجودة.'));
        }

        $hours = $this->hoursAfterEnd($meeting) ?? 0.0;
        $tier = $this->valueForHours($hours);

        return DB::transaction(function () use ($meeting, $user, $existing, $hours, $tier) {
            $attendance = $existing ?: new MeetingAttendance([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ]);

            $attendance->fill([
                'status' => 'registered',
                'registered_at' => now(),
                'hours_after_end' => (int) floor($hours),
                'rep_value' => $tier['value'],
            ])->save();

            $transaction = $this->ledger->rep(
                user: $user,
                value: $tier['value'],
                source: 'meeting',
                reason: strtr(setting('meetings.attendance_service.register_6', 'تسجيل حضور اجتماع: :p1'), [':p1' => (string) ($meeting->title)]),
                reference: $meeting,
            );

            if ($transaction) {
                $attendance->forceFill(['transaction_id' => $transaction->id])->save();
            }

            return [
                'ok' => true,
                'message' => strtr(setting('meetings.attendance_service.register_7', 'اتسجّل حضورك ✓ :p1 على درجة التزامك.'), [':p1' => (string) ($this->valueLabel($tier['value']))]),
                'value' => $tier['value'],
            ];
        });
    }

    /**
     * إنهاء الاجتماع: تحديد عدد ساعات نافذة التسجيل ورفع المحضر.
     * وإنهاؤه **بمحضر موثَّق** يمنح صاحبه `meeting.managed`.
     */
    public function end(Meeting $meeting, User $user, int $windowHours, ?string $minutes): array
    {
        if ($meeting->status === 'ended') {
            return $this->fail(setting('meetings.attendance_service.end_1', 'الاجتماع منتهي بالفعل.'));
        }

        /*
         | ⭐ [2026-09-11] الملغى لا يُنهى (24.2-أوّلًا: «إلغاء بسبب»). وليس هذا
         | تجميلًا: «الإنهاء» يفتح نافذة تسجيلٍ، وقفلُها يستدعي `settleAbsences`
         | فيُخصَم **غيابٌ بلا اعتذار** على كلّ جمهور اجتماعٍ لم ينعقد أصلًا.
         | فالحارس هنا في الخدمة لا في الواجهة وحدها.
         */
        if ($meeting->status === 'cancelled') {
            return $this->fail(setting('meetings.attendance_service.end_6', 'الاجتماع ملغيّ — مافيش نافذة حضور لاجتماع ما انعقدش.'));
        }

        $windowHours = max(1, min($windowHours, $this->maxWindowHours()));

        DB::transaction(function () use ($meeting, $user, $windowHours, $minutes) {
            $meeting->forceFill([
                'status' => 'ended',
                'ended_at' => now(),
                'attendance_window_hours' => $windowHours,
                'attendance_closes_at' => now()->addHours($windowHours),
                'minutes' => $minutes ?: $meeting->minutes,
            ])->save();

            // +1 لمن أنهاه بمحضر موثَّق — لا لمجرّد ضغط الزرّ (13.4-ن-ب)
            if (filled($meeting->minutes)) {
                $this->ledger->rep(
                    user: $meeting->owner ?? $user,
                    value: rep_rule('meeting.managed'),
                    source: 'meeting',
                    reason: strtr(setting('meetings.attendance_service.end_2', 'إدارة اجتماع وتوثيق محضره: :p1'), [':p1' => (string) ($meeting->title)]),
                    reference: $meeting,
                    createdBy: $user->id,
                );
            }

            foreach ($this->scope->audienceUserIds($meeting) as $userId) {
                $this->ledger->notify(
                    User::find($userId),
                    'meeting.attendance_registered',
                    strtr(setting('meetings.attendance_service.end_3', 'اتفتحت نافذة تسجيل حضور: :p1'), [':p1' => (string) ($meeting->title)]),
                    strtr(setting('meetings.attendance_service.end_4', 'سجّل حضورك خلال :p1 ساعة.'), [':p1' => (string) ($windowHours)]),
                    route('volunteer.meetings.show', $meeting),
                );
            }
        });

        return ['ok' => true, 'message' => strtr(setting('meetings.attendance_service.end_5', 'اتقفل الاجتماع، ونافذة التسجيل مفتوحة :p1 ساعة.'), [':p1' => (string) ($windowHours)]), 'value' => null];
    }

    /**
     * تسوية الغياب بعد قفل النافذة: مَن لم يسجّل ولم يعتذر يأخذ خصم الغياب،
     * ومَن اعتذر مسبقًا يأخذ قيمة الاعتذار (صفرًا) بلا عقاب.
     * الدالّة **آمنة التكرار** — سطر الحضور الواحد لكلّ عضو يمنع التكرار.
     */
    public function settleAbsences(Meeting $meeting): int
    {
        if ($meeting->status !== 'ended' || ! $meeting->attendance_closes_at) {
            return 0;
        }

        if (now()->lessThan($meeting->attendance_closes_at)) {
            return 0;
        }

        $audience = $this->scope->audienceUserIds($meeting);

        $already = MeetingAttendance::query()
            ->where('meeting_id', $meeting->id)
            ->pluck('status', 'user_id');

        $settled = 0;

        foreach ($audience as $userId) {
            $status = $already[$userId] ?? null;

            if ($status === 'registered' || $status === 'absent' || $status === 'excused_settled') {
                continue;
            }

            $user = User::find($userId);

            if (! $user) {
                continue;
            }

            $excused = $status === 'excused';
            $value = rep_rule($excused ? 'meeting.excused_absence' : 'meeting.unexcused_absence');

            $attendance = MeetingAttendance::query()->firstOrNew([
                'meeting_id' => $meeting->id,
                'user_id' => $userId,
            ]);

            $attendance->fill([
                'status' => $excused ? 'excused_settled' : 'absent',
                'rep_value' => $value,
            ])->save();

            $transaction = $this->ledger->rep(
                user: $user,
                value: $value,
                source: 'meeting',
                reason: ($excused ? setting('meetings.attendance_service.settle_absences_1', 'غياب باعتذار مسبق: ') : setting('meetings.attendance_service.settle_absences_2', 'غياب بلا اعتذار: ')).$meeting->title,
                reference: $meeting,
            );

            if ($transaction) {
                $attendance->forceFill(['transaction_id' => $transaction->id])->save();
            }

            $settled++;
        }

        return $settled;
    }

    /** صياغة القيمة بإشارتها ورمزها — اللون وحده لا يحمل المعنى (2.16) */
    public function valueLabel(float $value): string
    {
        return match (true) {
            $value > 0 => '+'.rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.'),
            $value < 0 => '−'.rtrim(rtrim(number_format(abs($value), 2, '.', ''), '0'), '.'),
            default => '0',
        };
    }

    // ------------------------------------------------------------------ داخليّ

    /** التحقّق Server-side حصرًا: الكود أوّلًا ثمّ أسئلة الاختيارات */
    private function verify(Meeting $meeting, ?string $code, array $answers): bool
    {
        if (filled($meeting->attendance_code)) {
            $given = trim((string) $code);

            if ($given === '' || ! hash_equals(
                mb_strtolower(trim((string) $meeting->attendance_code)),
                mb_strtolower($given)
            )) {
                return false;
            }
        }

        $questions = $meeting->questions()->get();

        foreach ($questions as $question) {
            if (blank($question->correct_answer)) {
                continue;
            }

            $given = trim((string) ($answers[$question->id] ?? ''));

            if ($given === '' || mb_strtolower($given) !== mb_strtolower(trim((string) $question->correct_answer))) {
                return false;
            }
        }

        return true;
    }

    /** رسالة الخطأ = ماذا حدث + ماذا تفعل (2.17-ب) */
    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'value' => null];
    }
}
