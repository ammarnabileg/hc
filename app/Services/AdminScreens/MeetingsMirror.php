<?php

namespace App\Services\AdminScreens;

use App\Models\Entity;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\User;
use App\Services\Volunteer\Meetings\AttendanceService;
use App\Services\Volunteer\Meetings\MeetingScope;
use App\Support\Scope\ScopeFilter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * مرآة اجتماعات التطوّع في لوحة الإدارة (24.2-أوّلًا).
 *
 * الاجتماعات تُدار من لوحة التطوّع، لكنّ الدستور يطلب لها **مرآةً إداريّة**:
 * لأنّ مسؤول اللوحة يحتاج نظرةً عرضيّة على الأقسام كلّها — أيّ نافذة حضور
 * لسّه مفتوحة، وأيّ اجتماع انتهى بلا محضر — وهي أسئلة لا تجيب عنها شاشة
 * المتطوّع التي تعرض نطاقه هو.
 *
 * ولا نُعيد بناء منطق الحضور: نستدعي `AttendanceService` نفسها، فمرآةٌ لها
 * منطقٌ خاصّ بها ليست مرآة.
 */
class MeetingsMirror
{
    public function __construct(
        private readonly MeetingScope $scope,
        private readonly AttendanceService $attendance,
    ) {}

    public const STATUSES = [
        'scheduled' => 'قادم',
        'running' => 'جارٍ',
        'ended' => 'منتهٍ',
    ];

    public function defaultRangeDays(): int
    {
        return max(1, (int) setting('admin_meetings.default_range_days', 30));
    }

    /**
     * ما يراه هذا الأدمن: صاحب النطاق `ALL` يرى الكلّ، وغيره **يرى نطاقه فقط**
     * — لا شاشةً معطّلة ولا رسالة منع، بل قائمةً أضيق (2.15-أ-7).
     *
     * @param  array{q?:string,entity?:string,status?:string,window?:string,no_minutes?:string,from?:string,to?:string}  $filters
     */
    public function query(User $viewer, array $filters): Builder
    {
        /*
         | ⭐ النطاق يُطبَّق على البيانات لا على الباب وحده (12.2.1-ب).
         | `ALL` يرى الكلّ، وما دونه يُقاطَع قيدان معًا: جمهور الاجتماع (مَن يخصّه)
         | **و**النطاق الممنوح (TEAM ⟵ داونلاينه · ENTITY ⟵ كيانه وفروعه) —
         | فلا يتّسع أحدهما على حساب الآخر.
         */
        $base = $viewer->widestScope('meetings.list') === 'ALL'
            ? Meeting::query()
            : app(ScopeFilter::class)->apply(
                $this->scope->visibleQuery($viewer),
                $viewer,
                'meetings.list',
                'owner_id',
                'entity_id',
            );

        $from = ($filters['from'] ?? '') !== ''
            ? CarbonImmutable::parse($filters['from'])->startOfDay()
            : CarbonImmutable::now()->subDays($this->defaultRangeDays() - 1)->startOfDay();

        $to = ($filters['to'] ?? '') !== ''
            ? CarbonImmutable::parse($filters['to'])->endOfDay()
            : CarbonImmutable::now()->addDays($this->defaultRangeDays())->endOfDay();

        return $base
            ->with(['entity:id,name_ar', 'owner:id,name,code'])
            ->whereBetween('scheduled_at', [$from, $to])
            ->when(($filters['q'] ?? '') !== '', fn ($q) => $q->where('title', 'like', '%'.$filters['q'].'%'))
            ->when(($filters['entity'] ?? '') !== '', fn ($q) => $q->where('entity_id', (int) $filters['entity']))
            ->when(($filters['status'] ?? '') !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when(($filters['window'] ?? '') === '1', fn ($q) => $q->where('attendance_closes_at', '>', now()))
            ->when(($filters['no_minutes'] ?? '') === '1', fn ($q) => $q->where('status', 'ended')
                ->where(fn ($w) => $w->whereNull('minutes')->orWhere('minutes', '')))
            ->orderByDesc('scheduled_at');
    }

    public function paginate(User $viewer, array $filters): LengthAwarePaginator
    {
        return $this->query($viewer, $filters)
            ->paginate(max(5, (int) setting('admin_meetings.per_page', 20)))
            ->withQueryString();
    }

    /**
     * أربعة أرقام (2.15-أ-3): اجتماعات المدى · نوافذ مفتوحة · منتهٍ بلا محضر · متوسّط الحضور.
     *
     * @return array{total:int,open_windows:int,without_minutes:int,attendance_rate:int}
     */
    public function stats(User $viewer, array $filters): array
    {
        $base = $this->query($viewer, $filters);

        // سقفٌ صريح: حساب «المدعوّين» يمرّ على شجرة كلّ اجتماع، فبلا سقفٍ تصير
        // الكروت أبطأ من الجدول نفسه — والمدى الافتراضيّ 30 يومًا يبقى تحته دائمًا.
        $meetings = (clone $base)
            ->limit(max(1, (int) setting('admin_meetings.stats_scan_limit', 200)))
            ->get(['id', 'status', 'minutes', 'attendance_closes_at', 'entity_id', 'audience', 'owner_id']);

        $registered = MeetingAttendance::query()
            ->whereIn('meeting_id', $meetings->pluck('id'))
            ->where('status', 'registered')
            ->count();

        $invited = $meetings->sum(fn (Meeting $meeting) => count($this->scope->audienceUserIds($meeting)));

        return [
            // العدّ الحقيقيّ من قاعدة البيانات لا من العيّنة — الرقم الظاهر صادق دائمًا (2.9-7)
            'total' => (int) (clone $base)->count(),
            'open_windows' => $meetings->filter(fn (Meeting $m) => $m->attendance_closes_at?->isFuture() ?? false)->count(),
            'without_minutes' => $meetings->filter(fn (Meeting $m) => $m->status === 'ended' && blank($m->minutes))->count(),
            'attendance_rate' => $invited > 0 ? (int) round($registered / $invited * 100) : 0,
        ];
    }

    /**
     * الحاضرون/المدعوّون لكلّ اجتماع في الصفحة — استعلامان لا استعلامان لكلّ صفّ.
     *
     * @return array<int,array{registered:int,invited:int}>
     */
    public function attendanceCounts(iterable $meetings): array
    {
        $collection = collect($meetings);

        $registered = MeetingAttendance::query()
            ->whereIn('meeting_id', $collection->pluck('id'))
            ->where('status', 'registered')
            ->groupBy('meeting_id')
            ->selectRaw('meeting_id, count(*) as total')
            ->pluck('total', 'meeting_id');

        $counts = [];

        foreach ($collection as $meeting) {
            $counts[$meeting->id] = [
                'registered' => (int) ($registered[$meeting->id] ?? 0),
                'invited' => count($this->scope->audienceUserIds($meeting)),
            ];
        }

        return $counts;
    }

    /** الكيانات لقائمة فلتر النطاق */
    public function entities(): Collection
    {
        return Entity::query()->where('status', 'active')->orderBy('name_ar')->get(['id', 'name_ar']);
    }

    /** تفاصيل اللوحة الجانبيّة: الحضور بأسمائه وقيمه — لا صفحة جديدة (2.15-أ-8) */
    public function attendees(Meeting $meeting): Collection
    {
        return MeetingAttendance::query()
            ->with('user:id,name,code')
            ->where('meeting_id', $meeting->id)
            ->orderByDesc('registered_at')
            ->get();
    }

    /**
     * منح حضور استثنائيّ (24.2-أوّلًا) — **بسببٍ إلزاميّ** لأنّ منحةً بلا سبب
     * أثرٌ لا يُراجَع. وقيمة الـRep تُؤخَذ من نفس سلّم الحضور لا من رقم جديد.
     *
     * @return array{ok:bool,message:string}
     */
    public function grantExceptional(Meeting $meeting, User $member, string $reason, User $actor): array
    {
        if (setting('admin_meetings.exceptional_reason_required', true) && trim($reason) === '') {
            return ['ok' => false, 'message' => setting('admin_users.meetings_mirror.grant_exceptional_1', 'السبب مطلوب — اكتب سطرًا يوضّح لماذا مُنح الحضور استثناءً.')];
        }

        $existing = MeetingAttendance::query()
            ->where('meeting_id', $meeting->id)
            ->where('user_id', $member->id)
            ->first();

        if ($existing && $existing->status === 'registered') {
            return ['ok' => false, 'message' => setting('admin_users.meetings_mirror.grant_exceptional_2', 'العضو ده مسجَّل حضوره أصلًا — مافيش حاجة تتمنح.')];
        }

        $tier = $this->attendance->valueForHours(0);

        MeetingAttendance::updateOrCreate(
            ['meeting_id' => $meeting->id, 'user_id' => $member->id],
            [
                'status' => 'registered',
                'registered_at' => now(),
                'hours_after_end' => 0,
                'rep_value' => $tier['value'],
                'excuse_reason' => strtr(setting('admin_users.meetings_mirror.grant_exceptional_3', 'حضور استثنائيّ: :p1 — منحه :p2'), [':p1' => (string) (trim($reason)), ':p2' => (string) ($actor->name)]),
            ],
        );

        return ['ok' => true, 'message' => setting('admin_users.meetings_mirror.grant_exceptional_4', 'اتمنح الحضور الاستثنائيّ ✓')];
    }

    /**
     * تصدير الحضور — صفٌّ لكلّ عضو بحالته وقيمته.
     *
     * @return array<int,array<string,mixed>>
     */
    public function exportRows(User $viewer, array $filters): array
    {
        $meetings = $this->query($viewer, $filters)->limit((int) setting('admin_meetings.export.max_meetings', 2000))->get();

        $rows = [];

        foreach (MeetingAttendance::query()->with('user:id,name,code')->whereIn('meeting_id', $meetings->pluck('id'))->get() as $attendance) {
            $meeting = $meetings->firstWhere('id', $attendance->meeting_id);

            $rows[] = [
                'meeting' => $meeting?->title,
                'scheduled_at' => $meeting?->scheduled_at?->format('Y-m-d H:i'),
                'member' => $attendance->user?->name,
                'code' => $attendance->user?->code,
                'status' => $attendance->status,
                'registered_at' => $attendance->registered_at?->format('Y-m-d H:i'),
                'rep_value' => (float) $attendance->rep_value,
            ];
        }

        return $rows;
    }
}
