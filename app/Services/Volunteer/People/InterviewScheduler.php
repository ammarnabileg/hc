<?php

namespace App\Services\Volunteer\People;

use App\Models\Interview;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * جدولة المقابلات (13.4-د · 24.4-12): تقويم **يتفادى التعارض** وحالات أربع.
 *
 * التعارض هنا ليس تحذيرًا تجميليًّا: مقابلتان لنفس المُقابِل في نفس النافذة
 * تعنيان أنّ أحد المرشّحين سينتظر على الرابط وحده — فنمنعها ونشرح البديل.
 */
class InterviewScheduler
{
    public const STATUSES = ['scheduled', 'done', 'no_show', 'cancelled'];

    public function __construct(private readonly AuditTrail $audit, private readonly PeopleBridge $bridge) {}

    /** تسميات الحالات — والمصدر الإعدادات لا الكود (2.13) */
    public function statusLabels(): array
    {
        return [
            'scheduled' => (string) setting('interviews.status.scheduled.label', 'مجدولة'),
            'done' => (string) setting('interviews.status.done.label', 'تمّت'),
            'no_show' => (string) setting('interviews.status.no_show.label', 'لم يحضر'),
            'cancelled' => (string) setting('interviews.status.cancelled.label', 'مُلغاة'),
        ];
    }

    /** لون الحالة من قاموس 2.16 — ومعه رمزه دائمًا عبر <x-state-badge> */
    public function statusState(string $status): string
    {
        return match ($status) {
            'done' => 'ok',
            'no_show' => 'danger',
            'cancelled' => 'idle',
            default => 'warn',
        };
    }

    public function slotMinutes(): int
    {
        return max(5, (int) setting('interviews.slot_minutes', 45));
    }

    /**
     * هل يتعارض هذا الموعد مع موعد قائم للمُقابِل نفسه؟
     * النافذة = مدّة السلوت من الإعدادات، والمُلغاة لا تحجز وقتًا.
     */
    public function conflictFor(int $interviewerId, Carbon $at, ?int $ignoreId = null): ?Interview
    {
        $window = $this->slotMinutes();

        return Interview::query()
            ->where('interviewer_id', $interviewerId)
            ->whereIn('status', ['scheduled', 'done'])
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->whereBetween('scheduled_at', [
                $at->copy()->subMinutes($window - 1),
                $at->copy()->addMinutes($window - 1),
            ])
            ->first();
    }

    /**
     * جدولة مقابلة — ترجع المقابلة، وترمي عند التعارض برسالة «ماذا حدث + ماذا تفعل» (2.17-ب).
     *
     * @throws \RuntimeException
     */
    public function schedule(RecruitmentCandidate $candidate, User $interviewer, Carbon $at, ?string $link, User $actor): Interview
    {
        $conflict = $this->conflictFor($interviewer->id, $at);

        if ($conflict) {
            throw new \RuntimeException(
                strtr(setting('recruitment.interview_scheduler.schedule_1', 'المُقابِل عنده موعد تاني الساعة :p1 — اختر وقتًا تانيًا أو مُقابِلًا تانيًا.'), [':p1' => (string) ($conflict->scheduled_at->translatedFormat('g:i A'))])
            );
        }

        $interview = Interview::create([
            'recruitment_candidate_id' => $candidate->id,
            'interviewer_id' => $interviewer->id,
            'scheduled_at' => $at,
            'external_link' => $link,
            'status' => 'scheduled',
        ]);

        $this->audit->record($actor, 'interview.scheduled', $interview, [], [
            'candidate' => $candidate->id,
            'interviewer' => $interviewer->id,
            'at' => $at->toDateTimeString(),
        ]);

        // تذكير الطرفين — والعدّاد الملوّن يأتي من `deadline_at` في مركز الإشعارات (2.8)
        $this->bridge->notify($interviewer, 'recruitment', setting('recruitment.interview_scheduler.schedule_2', 'مقابلة مجدولة'),
            strtr(setting('recruitment.interview_scheduler.schedule_3', 'موعدك مع المرشّح :p1'), [':p1' => (string) (($candidate->user?->name ?? '—'))]), route('volunteer.interviews'), $at);

        if ($candidate->user) {
            $this->bridge->notify($candidate->user, 'recruitment', setting('recruitment.interview_scheduler.schedule_4', 'اتحدّد موعد مقابلتك'),
                strtr(setting('recruitment.interview_scheduler.schedule_5', 'المقابلة يوم :p1'), [':p1' => (string) ($at->translatedFormat('l j F — g:i A'))]), null, $at);
        }

        return $interview;
    }

    /** تغيير الحالة (تمّت · لم يحضر · مُلغاة) بسبب حين يلزم */
    public function setStatus(Interview $interview, string $status, ?string $reason, User $actor): Interview
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException(setting('recruitment.interview_scheduler.set_status_1', 'حالة غير معروفة للمقابلة.'));
        }

        if ($status === 'cancelled' && trim((string) $reason) === '') {
            throw new \InvalidArgumentException(setting('recruitment.interview_scheduler.set_status_2', 'الإلغاء لازم يكون بسبب مكتوب — عشان المرشّح يفهم الخطوة الجاية.'));
        }

        $old = (string) $interview->status;

        $interview->forceFill([
            'status' => $status,
            'cancel_reason' => $reason ?: $interview->cancel_reason,
        ])->save();

        $this->audit->record($actor, 'interview.status_changed', $interview,
            ['status' => $old], ['status' => $status, 'reason' => $reason]);

        return $interview;
    }

    /** إعادة الجدولة بعد «لم يحضر» — دعوة جديدة بموعد جديد */
    public function reschedule(Interview $interview, Carbon $at, User $actor): Interview
    {
        $conflict = $this->conflictFor((int) $interview->interviewer_id, $at, (int) $interview->id);

        if ($conflict) {
            throw new \RuntimeException(setting('recruitment.interview_scheduler.reschedule_1', 'الموعد الجديد متعارض مع مقابلة تانية للمُقابِل — جرّب وقتًا غيره.'));
        }

        $old = $interview->scheduled_at?->toDateTimeString();

        $interview->forceFill(['scheduled_at' => $at, 'status' => 'scheduled'])->save();

        $this->audit->record($actor, 'interview.rescheduled', $interview,
            ['at' => $old], ['at' => $at->toDateTimeString()]);

        return $interview;
    }

    /**
     * أيّام التقويم للشهر المعروض — كلّ يوم بمقابلاته مرتّبة.
     *
     * @return Collection<string, Collection<int, Interview>>
     */
    public function calendar(Carbon $month, ?int $interviewerId = null): Collection
    {
        return Interview::query()
            ->with(['recruitment_candidate.user', 'interviewer'])
            ->when($interviewerId, fn ($q) => $q->where('interviewer_id', $interviewerId))
            ->whereBetween('scheduled_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('scheduled_at')
            ->get()
            ->groupBy(fn (Interview $i) => $i->scheduled_at->toDateString());
    }
}
