<?php

namespace App\Services\Volunteer\Profile;

use App\Models\MeetingAttendance;
use App\Models\Membership;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Volunteer\Goals\LeadershipService;
use App\Services\Volunteer\Goals\RepService;
use App\Services\Volunteer\People\AuditTrail;

/**
 * «تقرير PDF» للمخوَّل (13.4-م-1) — **مادّة قرار الترقية**، ولذلك:
 * محتواه محدَّد بالنصّ (معدّل Rep آخر 30 يومًا · نسبة الحضور · المهامّ ·
 * متوسّط التقييم · الأبلاين الموصي)، ومعه **رقم مرجع وتاريخ إصدار**،
 * و**يُسجَّل في الأوديت** — فالقرار موثّق بمصدره.
 *
 * ولماذا صفحة طباعة لا ملفّ PDF مولَّد؟ لأنّ **الاعتماد على مكتبة خارجيّة ممنوع**
 * في هذا المشروع، ومولّد PDF يدويّ لا يدعم العربيّة بشكل مقبول — فالمخرَج
 * صفحة بتنسيق طباعة (A4) يحفظها المتصفّح PDF بضغطة، ومحتواها هو المحتوى نفسه.
 */
final class ProfileReport
{
    public function __construct(
        private readonly RepService $rep,
        private readonly LeadershipService $leadership,
        private readonly AuditTrail $audit,
    ) {}

    public function build(User $owner, User $issuer): array
    {
        $days = (int) setting('volunteer.profile.report.days', 30);
        $membership = Membership::query()
            ->with(['entity:id,name_ar', 'position:id,name_ar', 'upline.user:id,name,code', 'upline.position:id,name_ar'])
            ->where('user_id', $owner->id)
            ->where('status', 'active')
            ->orderByDesc('is_primary')
            ->first();

        $summary = $this->leadership->receivedSummary($owner);

        // الأوديت أوّلًا: رقم المرجع مشتقّ من سطر التدقيق نفسه فلا يوجد تقرير بلا أثر
        $log = $this->audit->record($issuer, 'volunteer_profile.report.issued', $owner, [], [
            'days' => $days,
            'issued_to' => $owner->code,
        ]);

        $reference = str_replace(
            [':prefix', ':date', ':code', ':id'],
            [
                (string) setting('volunteer.profile.report.prefix', 'VPR'),
                now()->format('Ymd'),
                (string) $owner->code,
                str_pad((string) $log->id, 5, '0', STR_PAD_LEFT),
            ],
            (string) setting('volunteer.profile.report.reference_format', ':prefix-:date-:code-:id'),
        );

        return [
            'reference' => $reference,
            'issued_at' => now(),
            'issued_by' => $issuer,
            'owner' => $owner,
            'membership' => $membership,
            'days' => $days,
            'rep_average' => $this->repAverage($owner, $days),
            'rep_now' => round($this->rep->score($owner), 2),
            'attendance' => $this->attendance($owner, $days),
            'tasks' => $this->tasks($owner, $days),
            'evaluation' => $summary['visible'] ? round((float) $summary['average'], 2) : null,
            'evaluation_scale' => $this->leadership->maxScore(),
            // الأبلاين الموصي — صاحب التوصية في قرار الترقية
            'recommender' => $membership?->upline?->user,
            'recommender_position' => $membership?->upline?->position?->name_ar,
            'audit_id' => $log->id,
        ];
    }

    /** معدّل حركة Rep اليوميّ خلال المدى — الاتّجاه لا الرقم المجرّد */
    private function repAverage(User $owner, int $days): float
    {
        $series = $this->rep->dailySeries($owner, $days);
        $values = array_column($series, 'value');

        return $values === [] ? 0.0 : round(array_sum($values) / count($values), 2);
    }

    private function attendance(User $owner, int $days): array
    {
        $rows = MeetingAttendance::query()
            ->where('user_id', $owner->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->get(['status']);

        $total = $rows->count();
        $present = $rows->where('status', 'registered')->count();

        return [
            'total' => $total,
            'present' => $present,
            'percent' => $total > 0 ? (int) round($present / $total * 100) : null,
        ];
    }

    private function tasks(User $owner, int $days): array
    {
        $rows = Task::query()
            ->where('owner_id', $owner->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->get(['status', 'deadline_at', 'delivered_at']);

        return [
            'total' => $rows->count(),
            'approved' => $rows->where('status', 'approved')->count(),
            'late' => $rows->filter(fn (Task $t) => $t->status === 'no_delivery'
                || ($t->delivered_at && $t->deadline_at && $t->delivered_at > $t->deadline_at))->count(),
            'vxp' => round((float) Transaction::query()
                ->where('user_id', $owner->id)
                ->where('source', 'task')
                ->where('created_at', '>=', now()->subDays($days))
                ->sum('amount'), 2),
        ];
    }
}
