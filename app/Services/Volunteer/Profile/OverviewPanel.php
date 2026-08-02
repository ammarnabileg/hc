<?php

namespace App\Services\Volunteer\Profile;

use App\Models\Certificate;
use App\Models\Currency;
use App\Models\Interview;
use App\Models\Kudos;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\Membership;
use App\Models\Position;
use App\Models\RecruitmentCandidate;
use App\Models\RepScore;
use App\Models\Task;
use App\Models\User;
use App\Models\WalletBalance;
use App\Services\Volunteer\Goals\LeadershipService;
use App\Services\Volunteer\Goals\RepService;
use App\Services\Volunteer\Org\MemberDirectory;
use App\Services\Volunteer\Org\RepBadge;
use Illuminate\Support\Collection;

/**
 * تاب «أوفر فيو» في طبقة التطوّع (13.4-م-1).
 *
 * المبدأ: **الاتّجاه أهمّ من الرقم المجرّد** — ولذلك منحنيان قصيران بجانب الأرقام،
 * و**القصّة أقوى من العدّاد** — ولذلك آخر ثلاث شكرات بأسبابها لا العدّاد وحده.
 * وكلّ الأرقام من مصادرها الواحدة بلا حساب موازٍ (12.5 / 20 / 13.4-ن).
 */
final class OverviewPanel
{
    public function __construct(
        private readonly RepService $rep,
        private readonly LeadershipService $leadership,
        private readonly MemberDirectory $directory,
        private readonly ViewerLevel $levels,
    ) {}

    public function build(User $owner, ?User $viewer, string $level, ?Membership $membership): array
    {
        $days = (int) setting('ux.lists.default_range_days', 30);
        $score = $this->score($owner);
        $summary = $this->leadership->receivedSummary($owner);
        $privileged = $this->levels->isPrivileged($level);

        return [
            'rep' => [
                'score' => round($score, 2),
                'state' => RepBadge::state($score),
                'min' => (float) setting('rep.display.min', -10),
                'max' => (float) setting('rep.display.max', 10),
                // علامة الإنذار عند −5 — من جدول Rep لا رقمًا محروقًا (13.4-ن)
                'warning' => rep_rule('limit.warning_threshold', -5.0),
                'percent' => $this->repPercent($score),
                'warning_percent' => $this->repPercent(rep_rule('limit.warning_threshold', -5.0)),
            ],
            'vxp' => $this->vxp($owner),
            'service_duration' => $this->directory->serviceDuration($membership?->started_at),
            'position' => $membership?->position?->name_ar,
            'entity' => $membership?->entity?->name_ar,
            'tasks' => $this->tasks($owner),
            // التقييمات **متوسّطات مجهولة** دائمًا — تحمي علاقات الفريق (13.4-ي)
            'evaluation_average' => $summary['visible'] ? round((float) $summary['average'], 1) : null,
            'evaluation_scale' => $this->leadership->maxScore(),
            'attendance' => $this->attendance($owner, $days),
            'journey' => $this->journey($owner),
            'rep_series' => $this->rep->dailySeries($owner, $days),
            'vxp_series' => $this->rep->vxpSeries($owner, $days),
            'series_days' => $days,
            'kudos_count' => Kudos::where('receiver_id', $owner->id)->count(),
            'kudos_latest' => $this->latestKudos($owner),
            'certificates' => $this->certificates($owner),
            // نقطة نشاط نابضة فقط — **بلا «آخر ظهور»** (13.4-م-1)
            'is_active_now' => $owner->last_seen_at !== null
                && $owner->last_seen_at->gt(now()->subMinutes((int) setting('account.profile.online_window_minutes', 10))),
            // تنبيه هادئ للأبلاين فقط — بلا فضح أمام الزملاء (13.4-م-1)
            'upline_alert' => $privileged && $score <= rep_rule('limit.warning_threshold', -5.0)
                ? str_replace(':name', $owner->shortName(), (string) setting(
                    'volunteer.profile.overview.upline_alert',
                    'درجة الالتزام عند :name وصلت لحدّ الإنذار — كلمة منك دلوقتي بتفرق.',
                ))
                : null,
            'next_position' => $this->nextPosition($membership, $score),
            'can_export_report' => $viewer?->allows('reports_volunteer.view', $owner) === true,
            // مهامّه الجارية وأقرب اجتماع — اختصار لوحة التطوّع لصاحب البروفايل وحده
            'my_open_tasks' => $level === ViewerLevel::OWNER ? $this->openTasks($owner) : collect(),
            'next_meeting' => $level === ViewerLevel::OWNER ? $this->nextMeeting($owner) : null,
        ];
    }

    /**
     * الدرجة من **مصدرها الواحد**: جدول `rep_scores` هو الرقم الظاهر في كلّ الشاشات،
     * ونرجع لمحفظة Rep لو لم يُبنَ له صفٌّ بعد — فلا يختلف الرقم بين شاشتين.
     */
    public function score(User $owner): float
    {
        $stored = RepScore::where('user_id', $owner->id)->value('score');

        return $stored !== null ? (float) $stored : $this->rep->score($owner);
    }

    /** موضع الدرجة على بار من −10 إلى +10 (نسبة مئويّة للعرض) */
    public function repPercent(float $score): int
    {
        $min = (float) setting('rep.display.min', -10);
        $max = (float) setting('rep.display.max', 10);

        if ($max <= $min) {
            return 0;
        }

        return (int) max(0, min(100, round(($score - $min) / ($max - $min) * 100)));
    }

    /** VXP الإجماليّ وترتيبه بين كلّ المتطوّعين — من المحفظة لا من عدّاد موازٍ */
    private function vxp(User $owner): array
    {
        $balance = $owner->balance('vxp');
        $currencyId = Currency::where('code', 'vxp')->value('id');

        $rank = WalletBalance::query()
            ->where('currency_id', $currencyId)
            ->where('balance', '>', $balance)
            ->whereIn('user_id', Membership::query()->where('status', 'active')->select('user_id'))
            ->count() + 1;

        return [
            'balance' => round($balance, 2),
            'rank' => $rank,
        ];
    }

    private function tasks(User $owner): array
    {
        $rows = Task::query()
            ->where('owner_id', $owner->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'done' => (int) ($rows['approved'] ?? 0),
            'open' => (int) (($rows['in_progress'] ?? 0) + ($rows['blocked'] ?? 0) + ($rows['returned'] ?? 0)),
        ];
    }

    /** نسبة حضور الاجتماعات — الغياب بعذر لا يُحسَب تخلّفًا */
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

    /** «رحلتي في التطوّع»: التأهيليّ → المقابلة → التسكين → كلّ بوزشن بتاريخه */
    private function journey(User $owner): Collection
    {
        $steps = collect();
        $candidate = RecruitmentCandidate::where('user_id', $owner->id)->latest('id')->first();

        if ($candidate?->applied_at) {
            $steps->push(['key' => 'qualifying', 'label' => 'بدأ المسار التأهيليّ', 'at' => $candidate->applied_at]);
        }

        if ($candidate) {
            $interview = Interview::where('recruitment_candidate_id', $candidate->id)
                ->orderBy('scheduled_at')->first();

            if ($interview) {
                $steps->push(['key' => 'interview', 'label' => 'المقابلة', 'at' => $interview->scheduled_at]);
            }
        }

        Membership::query()
            ->with(['position:id,name_ar,rank', 'entity:id,name_ar'])
            ->where('user_id', $owner->id)
            ->orderBy('started_at')
            ->get()
            ->each(function (Membership $m, int $index) use ($steps) {
                $steps->push([
                    'key' => $index === 0 ? 'placement' : 'position',
                    'label' => $index === 0
                        ? 'التسكين — '.($m->position?->name_ar ?? '').($m->entity ? ' · '.$m->entity->name_ar : '')
                        : ($m->position?->name_ar ?? '').($m->entity ? ' · '.$m->entity->name_ar : ''),
                    'at' => $m->started_at,
                ]);
            });

        return $steps->filter(fn (array $step) => $step['at'] !== null)->sortBy('at')->values();
    }

    /** آخر ثلاث شكرات **بأسبابها** — القصّة أقوى من العدّاد (13.4-م-1) */
    private function latestKudos(User $owner): Collection
    {
        return Kudos::query()
            ->with('sender:id,name,code,avatar_path')
            ->where('receiver_id', $owner->id)
            ->latest('id')
            ->limit((int) setting('volunteer.profile.overview.kudos_preview', 3))
            ->get();
    }

    /** الشهادات من **المصدر الواحد** (12.5 / مكتبتي 20) — لا حساب موازٍ */
    private function certificates(User $owner): Collection
    {
        return Certificate::query()
            ->with('certificate_type:id,name_ar')
            ->where('user_id', $owner->id)
            ->where('status', 'valid')
            ->orderByDesc('issued_at')
            ->limit((int) setting('volunteer.profile.overview.certificates_preview', 4))
            ->get();
    }

    /**
     * بار «طريقك للبوزشن الجاي» — يُفعَّل حين تُحدَّد شروط الترقّي،
     * وقبلها يبقى سطرًا هادئًا بلا وعد كاذب.
     */
    private function nextPosition(?Membership $membership, float $score): ?array
    {
        if (! $membership?->position) {
            return null;
        }

        $next = Position::query()
            ->where('is_honorary', false)
            ->where('rank', '>', (int) $membership->position->rank)
            ->orderBy('rank')
            ->first();

        if (! $next) {
            return null;
        }

        $months = (int) setting('volunteer.profile.promotion.min_months', 6);
        $repTarget = (float) setting('volunteer.profile.promotion.min_rep', 5);
        $served = $membership->started_at ? (int) $membership->started_at->diffInMonths(now()) : 0;

        $monthsPercent = $months > 0 ? min(100, (int) round($served / $months * 100)) : 100;
        $repPercent = $repTarget > 0 ? min(100, (int) round(max(0, $score) / $repTarget * 100)) : 100;

        return [
            'label' => $next->name_ar,
            'percent' => (int) round(($monthsPercent + $repPercent) / 2),
            'served_months' => $served,
            'required_months' => $months,
            'rep_target' => $repTarget,
        ];
    }

    private function openTasks(User $owner): Collection
    {
        return Task::query()
            ->where('owner_id', $owner->id)
            ->whereIn('status', ['in_progress', 'blocked', 'returned'])
            ->orderBy('deadline_at')
            ->limit((int) setting('volunteer.profile.overview.tasks_preview', 3))
            ->get(['id', 'title', 'status', 'deadline_at']);
    }

    private function nextMeeting(User $owner): ?Meeting
    {
        $meetingIds = MeetingAttendance::where('user_id', $owner->id)->pluck('meeting_id');

        return Meeting::query()
            ->whereIn('id', $meetingIds)
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->first();
    }
}
