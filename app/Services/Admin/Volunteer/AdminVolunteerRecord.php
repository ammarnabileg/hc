<?php

namespace App\Services\Admin\Volunteer;

use App\Models\Certificate;
use App\Models\MeetingAttendance;
use App\Models\Membership;
use App\Models\RepScore;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Volunteer\Goals\RepService;
use App\Services\Volunteer\Org\RepBadge;
use Illuminate\Support\Collection;

/**
 * تاب «التطوّع» في صفحة المستخدم بالأدمن — سجلّ المشرف بعد تاب «متقدّم» (12.1 · 1103 · 2886).
 *
 * البنود السبعة المنصوصة بحرفها: **البوزشنز · التسكينات · المهامّ · VXP ·
 * تاريخ الالتزام · شهادات التطوّع · الاجتماعات** — كلّها من مصادرها الواحدة
 * القائمة بالفعل في مجال التطوّع (`OrganizationPanel`/`OverviewPanel`/`RepService`
 * تبني نفس الشاشة لصفحة البروفايل)، بلا حساب موازٍ (2.13-هـ).
 *
 * ولا يُبنى شيء إن لم يتطوّع هذا المستخدم قطّ — راية واحدة (`has_volunteered`)
 * توقف كلّ استعلام إضافيّ (2.15-ب).
 */
final class AdminVolunteerRecord
{
    public function __construct(private readonly RepService $rep) {}

    /** @return array<string, mixed> */
    public function build(User $subject): array
    {
        $memberships = Membership::query()
            ->with(['position:id,name_ar,rank', 'entity:id,name_ar', 'upline.user:id,name,code'])
            ->where('user_id', $subject->id)
            ->orderBy('started_at')
            ->get();

        if ($memberships->isEmpty()) {
            return ['has_volunteered' => false];
        }

        $rows = (int) setting('admin.users.partials.tab_volunteer.rows', 8);

        $score = (float) (RepScore::where('user_id', $subject->id)->value('score') ?? $this->rep->score($subject));

        return [
            'has_volunteered' => true,
            'positions' => $this->positions($memberships),
            'placements' => $this->placements($memberships, $rows),
            'tasks' => $this->tasks($subject, $rows),
            'vxp' => $this->vxp($subject, $rows),
            'commitment' => [
                'score' => round($score, 2),
                'state' => RepBadge::state($score),
                'recent' => $this->rep->query($subject)->latest('created_at')->limit($rows)->get(),
            ],
            'certificates' => $this->certificates($subject, $rows),
            'meetings' => $this->meetings($subject, $rows),
        ];
    }

    /**
     * البوزشنز: كلّ بوزشن شغله ولو مرّة — بأوّل وآخر تاريخ وهل ما زال شاغله.
     *
     * @param  Collection<int, Membership>  $memberships
     * @return Collection<int, array<string, mixed>>
     */
    private function positions(Collection $memberships): Collection
    {
        return $memberships
            ->groupBy('position_id')
            ->map(function (Collection $rows) {
                $first = $rows->first();
                $current = $rows->first(fn (Membership $m) => $m->status === 'active' && $m->ended_at === null);

                return [
                    'position' => $first->position?->name_ar ?? '',
                    'rank' => (int) ($first->position?->rank ?? 0),
                    'first_at' => $rows->min('started_at'),
                    'last_at' => $current ? null : $rows->max('ended_at'),
                    'is_current' => $current !== null,
                ];
            })
            ->sortBy('rank')
            ->values();
    }

    /**
     * التسكينات: كلّ عضويّة بعينها (كيان × بوزشن × أبلاين) — الأحدث أوّلًا.
     *
     * @param  Collection<int, Membership>  $memberships
     * @return Collection<int, array<string, mixed>>
     */
    private function placements(Collection $memberships, int $rows): Collection
    {
        return $memberships
            ->sortByDesc('started_at')
            ->take($rows)
            ->map(fn (Membership $m) => [
                'entity' => $m->entity?->name_ar ?? '',
                'position' => $m->position?->name_ar ?? '',
                'upline' => $m->upline?->user?->shortName(),
                'from' => $m->started_at,
                'to' => $m->ended_at,
                'is_current' => $m->status === 'active' && $m->ended_at === null,
                'is_acting' => (bool) $m->is_acting,
            ])
            ->values();
    }

    /** @return array<string, mixed> */
    private function tasks(User $subject, int $rows): array
    {
        $counts = Task::query()
            ->where('owner_id', $subject->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'done' => (int) ($counts['approved'] ?? 0),
            'open' => (int) (($counts['in_progress'] ?? 0) + ($counts['blocked'] ?? 0) + ($counts['returned'] ?? 0)),
            'no_delivery' => (int) ($counts['no_delivery'] ?? 0),
            'total' => (int) $counts->sum(),
            'recent' => Task::query()
                ->where('owner_id', $subject->id)
                ->latest('id')
                ->limit($rows)
                ->get(['id', 'title', 'status', 'deadline_at', 'vxp_value']),
        ];
    }

    /** @return array<string, mixed> */
    private function vxp(User $subject, int $rows): array
    {
        return [
            'balance' => round($subject->balance('vxp'), 2),
            'recent' => Transaction::query()
                ->where('user_id', $subject->id)
                ->whereHas('currency', fn ($q) => $q->where('code', 'vxp'))
                ->latest('created_at')
                ->limit($rows)
                ->get(['amount', 'source', 'reason', 'created_at']),
        ];
    }

    /** شهادات التطوّع فقط — الأربعة أنواعها (13.4-ع)، لا شهادات التدريب */
    private function certificates(User $subject, int $rows): Collection
    {
        return Certificate::query()
            ->with('certificate_type:id,name_ar,key')
            ->where('user_id', $subject->id)
            ->whereHas('certificate_type', fn ($q) => $q->whereIn('key', CertificateEligibility::TYPE_KEYS))
            ->orderByDesc('issued_at')
            ->limit($rows)
            ->get();
    }

    /** الاجتماعات: حضوره المسجَّل بحالته — الأحدث أوّلًا */
    private function meetings(User $subject, int $rows): Collection
    {
        return MeetingAttendance::query()
            ->with('meeting:id,title,scheduled_at,status')
            ->where('user_id', $subject->id)
            ->latest('id')
            ->limit($rows)
            ->get()
            ->map(fn (MeetingAttendance $a) => [
                'meeting' => $a->meeting,
                'status' => $a->status,
                'status_label' => $this->meetingStatusLabel($a->status),
                'registered_at' => $a->registered_at,
            ]);
    }

    private function meetingStatusLabel(string $status): string
    {
        return match ($status) {
            'registered' => (string) setting('admin.users.partials.tab_volunteer.meeting_status_registered', 'حضر'),
            'excused_absence' => (string) setting('admin.users.partials.tab_volunteer.meeting_status_excused', 'غياب باعتذار'),
            'unexcused_absence' => (string) setting('admin.users.partials.tab_volunteer.meeting_status_unexcused', 'غياب بلا اعتذار'),
            default => $status,
        };
    }
}
