<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Interview;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\PlacementRequest;
use App\Models\RecruitmentCandidate;
use App\Models\Task;
use App\Models\TaskContribution;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Volunteer\Tasks\ActivityWindow;
use App\Services\Volunteer\Tasks\TaskBoard;
use App\Services\Volunteer\Tasks\TaskStatus;
use App\Services\Volunteer\Tasks\TaskWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * نظرة عامّة — ثلاث شاشات (الدستور 24.4-1):
 * رحلتي في التطوّع · تقريري الأسبوعيّ · تقويم نشاطي.
 *
 * كلّها **بيانات صاحب الحساب وحده**، وكلّ الأرقام تحترم نافذة النشاط (2.13).
 */
class OverviewController extends Controller
{
    public function __construct(
        private readonly ActivityWindow $window,
        private readonly TaskBoard $board,
        private readonly TaskWorkflow $workflow,
    ) {}

    /** رحلتي في التطوّع: Roadmap رأسيّ بالمحطّات + 4 كروت KPI */
    public function index(Request $request)
    {
        $user = $request->user();
        $membership = $user->activeMembership();

        $memberships = $user->memberships()->with('entity', 'position')->orderBy('started_at')->get();
        $first = $memberships->first();

        $certificates = Certificate::query()
            ->where('user_id', $user->id)
            ->where('status', 'valid')
            ->latest('issued_at')
            ->get();

        $kpis = [
            'service' => $first?->started_at
                ? $this->humanDuration(Carbon::parse($first->started_at), now())
                : (string) setting('volunteer.overview.index_msg', 'لسّه في الأوّل'),
            'position' => $membership?->position?->name_ar ?? '—',
            'certificates' => $certificates->count(),
            'vxp' => round($user->balance('vxp'), 2),
        ];

        return view('volunteer.overview.index', [
            'user' => $user,
            'membership' => $membership,
            'memberships' => $memberships,
            'kpis' => $kpis,
            'stations' => $this->journeyStations($user, $memberships, $certificates),
            'nextPositionProgress' => $this->nextPositionProgress($user, $membership),
        ]);
    }

    /**
     * التقرير الأسبوعيّ — صافي Rep · VXP · مهامّ · حضور + منحنى + جدول أحداث.
     * ⛔ **بلا مؤشّر مخاطر الفقدان إطلاقًا** — لا يُعرَض للمتطوّع عن نفسه أبدًا (24.4).
     */
    public function report(Request $request)
    {
        $user = $request->user();
        $offset = max(0, (int) $request->integer('w'));

        $start = now()->startOfWeek(Carbon::SATURDAY)->subWeeks($offset);
        $end = (clone $start)->addWeek();

        $transactions = $this->layerTransactions($user, $start, $end);

        $repRows = $transactions->where('code', 'rep');
        $vxpRows = $transactions->where('code', 'vxp');

        $previous = $this->layerTransactions($user, (clone $start)->subWeek(), $start)
            ->where('code', 'rep')->sum('amount');

        $delivered = Task::query()
            ->where('owner_id', $user->id)
            ->whereBetween('delivered_at', [$start, $end])
            ->get();

        $lateCount = $delivered->filter(fn (Task $task) => $this->workflow->isLate($task))->count();

        return view('volunteer.overview.report', [
            'user' => $user,
            'offset' => $offset,
            'start' => $start,
            'end' => $end->copy()->subSecond(),
            'netRep' => round($repRows->sum('amount'), 2),
            'previousRep' => round($previous, 2),
            'vxp' => round($vxpRows->sum('amount'), 2),
            'tasksDone' => $delivered->count(),
            'tasksLate' => $lateCount,
            'attendance' => $this->attendanceRate($user, $start, $end),
            'curve' => $this->repCurve($repRows, $start),
            'events' => $transactions->sortByDesc('created_at')->values(),
            'attention' => $this->attentionBlock($user),
        ]);
    }

    /**
     * تقويم نشاطي — مرسوم بيدنا بلا أيّ مكتبة خارجيّة:
     * حبوب ملوّنة بالنوع، ولون الحدّ يتبع العدّاد، وتظليل ما هو خارج نافذة النشاط.
     */
    public function calendar(Request $request)
    {
        $user = $request->user();
        $month = $request->filled('m')
            ? Carbon::createFromFormat('Y-m', (string) $request->string('m'))->startOfMonth()
            : now()->startOfMonth();

        $gridStart = (clone $month)->startOfWeek(Carbon::SATURDAY);
        $gridEnd = (clone $month)->endOfMonth()->endOfWeek(Carbon::FRIDAY);

        $events = $this->calendarEvents($user, $gridStart, $gridEnd);

        $days = [];

        for ($day = clone $gridStart; $day->lessThanOrEqualTo($gridEnd); $day->addDay()) {
            $key = $day->toDateString();
            $days[] = [
                'date' => $day->copy(),
                'in_month' => $day->month === $month->month,
                'is_today' => $day->isToday(),
                'events' => $events->get($key, collect())->values(),
            ];
        }

        return view('volunteer.overview.calendar', [
            'user' => $user,
            'month' => $month,
            'days' => collect($days)->chunk(7),
            'today' => $events->get(now()->toDateString(), collect())->sortBy('at')->values(),
            'window' => $this->window,
            'hours' => range(0, 23),
        ]);
    }

    // ------------------------------------------------------------------ داخليّ

    /** محطّات الرحلة: التأهيليّ ⟵ القوائم ⟵ المقابلة ⟵ التسكين ⟵ البوزشنات */
    private function journeyStations(User $user, $memberships, $certificates): array
    {
        $stations = [];

        if (Schema::hasTable('recruitment_candidates')) {
            $candidate = RecruitmentCandidate::query()->where('user_id', $user->id)->latest('id')->first();

            if ($candidate) {
                $stations[] = [
                    'title' => (string) setting('volunteer.overview.journey_stations_msg', 'بدأتَ المسار التأهيليّ'),
                    'at' => $candidate->applied_at ?? $candidate->created_at,
                    'meta' => $candidate->qualifying_score ? strtr((string) setting('volunteer.overview.journey_stations_msg_2', 'درجة التأهيليّ: :a1'), [':a1' => (string) ($candidate->qualifying_score)]) : null,
                    'type' => 'qualifying',
                ];

                $reached = ['screening', 'interview', 'final_list', 'placed'];

                if (in_array($candidate->stage, $reached, true)) {
                    $stations[] = [
                        'title' => (string) setting('volunteer.overview.journey_stations_msg_3', 'دخلتَ القائمة المبدئيّة'),
                        'at' => $candidate->stage_changed_at ?? $candidate->updated_at,
                        'meta' => null,
                        'type' => 'shortlist',
                    ];
                }

                if (Schema::hasTable('interviews')) {
                    $interview = Interview::query()
                        ->where('recruitment_candidate_id', $candidate->id)
                        ->latest('id')->first();

                    if ($interview) {
                        $stations[] = [
                            'title' => (string) setting('volunteer.overview.journey_stations_msg_4', 'المقابلة'),
                            'at' => $interview->scheduled_at ?? $interview->created_at,
                            'meta' => null,
                            'type' => 'interview',
                        ];
                    }
                }

                if (Schema::hasTable('placement_requests')) {
                    $placements = PlacementRequest::query()
                        ->where('recruitment_candidate_id', $candidate->id)
                        ->where('status', 'accepted')
                        ->with('entity', 'position')
                        ->get();

                    foreach ($placements as $placement) {
                        $stations[] = [
                            'title' => strtr((string) setting('volunteer.overview.journey_stations_msg_5', 'التسكين في :a1'), [
                                ':a1' => (string) ($placement->entity?->name_ar ?? setting('volunteer.overview.entity_fallback', 'كيان')),
                            ]),
                            'at' => $placement->responded_at ?? $placement->created_at,
                            'meta' => $placement->position?->name_ar,
                            'type' => 'placement',
                        ];
                    }
                }
            }
        }

        foreach ($memberships as $membership) {
            $ended = $membership->ended_at ? Carbon::parse($membership->ended_at) : now();

            $stations[] = [
                'title' => $membership->position?->name_ar ?? (string) setting('volunteer.overview.journey_stations_msg_6', 'بوزشن'),
                'at' => $membership->started_at,
                'meta' => ($membership->entity?->name_ar ?? '—').' · '
                    .$this->humanDuration(Carbon::parse($membership->started_at), $ended),
                'type' => 'position',
                'certificate' => $certificates->first(
                    fn ($certificate) => $certificate->subject_type === 'App\Models\Position'
                        && (int) $certificate->subject_id === (int) $membership->position_id
                ),
            ];
        }

        foreach ($certificates as $certificate) {
            if ($certificate->subject_type === 'App\Models\Position') {
                continue; // ظهرت مع محطّة البوزشن
            }

            $stations[] = [
                'title' => strtr((string) setting('volunteer.overview.journey_stations_msg_7', 'شهادة: :a1'), [':a1' => (string) (($certificate->certificate_type?->name_ar ?? $certificate->code))]),
                'at' => $certificate->issued_at,
                'meta' => strtr((string) setting('volunteer.overview.journey_stations_msg_8', 'كود: :a1'), [':a1' => (string) ($certificate->code)]),
                'type' => 'certificate',
                'certificate' => $certificate,
            ];
        }

        usort($stations, fn ($a, $b) => Carbon::parse($a['at'] ?? now())->timestamp <=> Carbon::parse($b['at'] ?? now())->timestamp);

        return $stations;
    }

    /** بار «طريقك للبوزشن الجاي» — مدّة الخدمة في البوزشن الحاليّ مقابل أدنى مدّة (إعداد) */
    private function nextPositionProgress(User $user, $membership): array
    {
        $minDays = (int) setting('volunteer_cert.min_days_in_position', 30);
        $days = $membership?->started_at
            ? (int) Carbon::parse($membership->started_at)->diffInDays(now())
            : 0;

        return [
            'days' => $days,
            'target' => $minDays,
            'percent' => $minDays > 0 ? min(100, (int) round($days / $minDays * 100)) : 0,
        ];
    }

    /** معاملات طبقة التطوّع في مدى — مع رمز العملة جاهزًا للعرض */
    private function layerTransactions(User $user, Carbon $start, Carbon $end)
    {
        return Transaction::query()
            ->with('currency')
            ->where('user_id', $user->id)
            ->where('layer', 'volunteer')
            ->whereBetween('created_at', [$start, $end])
            ->get()
            ->map(function (Transaction $row) {
                $row->setAttribute('code', $row->currency?->code);

                return $row;
            });
    }

    /** نسبة حضور الاجتماعات في الأسبوع */
    private function attendanceRate(User $user, Carbon $start, Carbon $end): int
    {
        if (! Schema::hasTable('meeting_attendances')) {
            return 0;
        }

        $rows = MeetingAttendance::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        return (int) round($rows->where('status', 'registered')->count() / $rows->count() * 100);
    }

    /** منحنى Rep اليوميّ — نقاط جاهزة لرسم SVG بيدنا بلا مكتبة */
    private function repCurve($repRows, Carbon $start): array
    {
        $series = [];
        $running = 0.0;

        for ($i = 0; $i < 7; $i++) {
            $day = (clone $start)->addDays($i);
            $sum = $repRows
                ->filter(fn ($row) => Carbon::parse($row->created_at)->isSameDay($day))
                ->sum('amount');

            $running += (float) $sum;

            $series[] = [
                'label' => $day->translatedFormat('D'),
                'date' => $day->toDateString(),
                'value' => round((float) $sum, 2),
                'running' => round($running, 2),
            ];
        }

        return $series;
    }

    /** «ما يستحقّ انتباهك»: ديدلاينات تقترب · نوافذ دمج مفتوحة · اعتراضات ما زالت ممكنة */
    private function attentionBlock(User $user): array
    {
        $objectionDays = (int) setting('rep.objection.window_days', 5);

        return [
            'due_soon' => $this->board->upcoming($user, (int) setting('workflow.deadline.soon_days', 3)),
            'merge_windows' => Task::query()
                ->where('owner_id', $user->id)
                ->whereNotNull('merge_window_at')
                ->whereIn('status', TaskStatus::OPEN)
                ->orderBy('merge_window_at')
                ->get(),
            'objectionable' => Transaction::query()
                ->where('user_id', $user->id)
                ->where('layer', 'volunteer')
                ->where('created_at', '>=', now()->subDays($objectionDays))
                ->latest()
                ->limit((int) setting('volunteer.overview.objectionable_rows', 5))
                ->get(),
            'objection_days' => $objectionDays,
        ];
    }

    /** أحداث التقويم مجمّعة باليوم: مهامّ · صب-تاسكات · مساهمات · نوافذ دمج · اجتماعات */
    private function calendarEvents(User $user, Carbon $from, Carbon $to)
    {
        $events = collect();

        $tasks = Task::query()
            ->with('entity')
            ->where('owner_id', $user->id)
            ->whereNotNull('deadline_at')
            ->whereBetween('deadline_at', [$from, $to])
            ->get();

        foreach ($tasks as $task) {
            $events->push([
                'type' => $task->parent_task_id ? 'subtask' : 'task',
                'type_label' => $task->parent_task_id ? (string) setting('volunteer.overview.calendar_events_msg', 'صب-تاسك') : (string) setting('volunteer.overview.calendar_events_msg_2', 'مهمّة'),
                'title' => $task->title,
                'at' => Carbon::parse($task->deadline_at),
                'state' => $this->workflow->counterState($task),
                'entity' => $task->entity?->name_ar,
                'url' => route('volunteer.tasks.show', $task),
            ]);

            if ($task->merge_window_at) {
                $mergeAt = Carbon::parse($task->merge_window_at);

                if ($mergeAt->betweenIncluded($from, $to)) {
                    $events->push([
                        'type' => 'merge',
                        'type_label' => (string) setting('volunteer.overview.calendar_events_msg_3', 'نافذة دمج'),
                        'title' => strtr((string) setting('volunteer.overview.calendar_events_msg_4', 'دمج وتسليم: :a1'), [':a1' => (string) ($task->title)]),
                        'at' => $mergeAt,
                        'state' => $this->workflow->counterState($task),
                        'entity' => $task->entity?->name_ar,
                        'url' => route('volunteer.tasks.show', $task),
                    ]);
                }
            }
        }

        $contributions = TaskContribution::query()
            ->with('task')
            ->where('contributor_id', $user->id)
            ->whereBetween('internal_deadline_at', [$from, $to])
            ->get();

        foreach ($contributions as $contribution) {
            $at = Carbon::parse($contribution->internal_deadline_at);

            $events->push([
                'type' => 'contribution',
                'type_label' => (string) setting('volunteer.overview.calendar_events_msg_5', 'مساهمة'),
                'title' => $contribution->item_title,
                'at' => $at,
                'state' => $at->isPast() ? 'danger' : 'warn',
                'entity' => $contribution->task?->title,
                'url' => $contribution->task ? route('volunteer.tasks.show', $contribution->task) : null,
            ]);
        }

        if (Schema::hasTable('meetings')) {
            $entityIds = $user->memberships()->where('status', 'active')->pluck('entity_id')->filter()->all();

            $meetings = Meeting::query()
                ->whereBetween('scheduled_at', [$from, $to])
                ->where(function ($query) use ($entityIds, $user) {
                    $query->whereIn('entity_id', $entityIds)->orWhere('owner_id', $user->id);
                })
                ->get();

            foreach ($meetings as $meeting) {
                $events->push([
                    'type' => 'meeting',
                    'type_label' => (string) setting('volunteer.overview.calendar_events_msg_6', 'اجتماع'),
                    'title' => $meeting->title,
                    'at' => Carbon::parse($meeting->scheduled_at),
                    'state' => Carbon::parse($meeting->scheduled_at)->isPast() ? 'idle' : 'ok',
                    'entity' => $meeting->entity?->name_ar,
                    'url' => null,
                ]);
            }
        }

        return $events
            ->map(function (array $event) {
                // ما يقع خارج نافذة النشاط يُظلَّل ولا يُحتسَب تأخيرًا (24.4)
                $event['outside_window'] = ! $this->window->contains($event['at']);

                return $event;
            })
            ->groupBy(fn (array $event) => $event['at']->toDateString());
    }

    /** مدّة بالعربيّة: «سنة وشهران» — بلا مكتبات ولا أرقام محروقة في الواجهة */
    private function humanDuration(Carbon $from, Carbon $to): string
    {
        $months = (int) $from->diffInMonths($to);
        $years = intdiv($months, 12);
        $rest = $months % 12;

        $parts = [];

        if ($years > 0) {
            $parts[] = $years === 1 ? (string) setting('volunteer.overview.human_duration_msg', 'سنة') : ($years === 2 ? (string) setting('volunteer.overview.human_duration_msg_2', 'سنتان') : strtr((string) setting('volunteer.overview.human_duration_msg_3', ':a1 سنوات'), [':a1' => (string) ($years)]));
        }

        if ($rest > 0) {
            $parts[] = $rest === 1 ? (string) setting('volunteer.overview.human_duration_msg_4', 'شهر') : ($rest === 2 ? (string) setting('volunteer.overview.human_duration_msg_5', 'شهران') : strtr((string) setting('volunteer.overview.human_duration_msg_6', ':a1 شهور'), [':a1' => (string) ($rest)]));
        }

        if ($parts === []) {
            $days = (int) $from->diffInDays($to);

            return $days <= 1 ? (string) setting('volunteer.overview.human_duration_msg_7', 'أوّل يوم') : strtr((string) setting('volunteer.overview.human_duration_msg_8', ':a1 يومًا'), [':a1' => (string) ($days)]);
        }

        return implode(' و', $parts);
    }
}
