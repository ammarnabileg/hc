<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\MeetingPost;
use App\Models\PostVote;
use App\Services\Volunteer\Meetings\AttendanceService;
use App\Services\Volunteer\Meetings\MeetingLedger;
use App\Services\Volunteer\Meetings\MeetingManager;
use App\Services\Volunteer\Meetings\MeetingScope;
use App\Services\Volunteer\Meetings\MinutesTaskService;
use App\Services\Volunteer\Objections\ObjectionService;
use App\Services\Volunteer\Tasks\TaskCreation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * الاجتماعات (الدستور 13.4-ح · 13.4-ن-ب · 24.4).
 *
 * سؤال شاشة القائمة واحد: «فيه إيه في نطاقي وهل عليّ تسجيل حضور؟»
 * وسؤال صفحة الاجتماع واحد: «إيه اللي حصل في الاجتماع ده؟» — والباقي تابات.
 */
class MeetingController extends Controller
{
    public function __construct(
        private readonly MeetingScope $scope,
        private readonly AttendanceService $attendance,
        private readonly MeetingLedger $ledger,
        private readonly MinutesTaskService $minutesTasks,
        private readonly TaskCreation $taskCreation,
        private readonly MeetingManager $manager,
    ) {}

    // ------------------------------------------------------------------ الاجتماعات

    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $request->string('tab')->toString() === 'ended' ? 'ended' : 'upcoming';

        $filters = [
            'entity' => (int) $request->integer('entity'),
            'days' => (int) $request->integer('days', (int) setting('ux.lists.default_range_days', 30)),
            'mine' => $request->string('mine')->toString(), // registered · pending · excused
            'q' => trim($request->string('q')->toString()),
        ];

        $query = $this->scope->visibleQuery($user)->with('owner', 'entity');

        $query->when($filters['entity'] > 0, fn ($q) => $q->where('entity_id', $filters['entity']))
            ->when($filters['q'] !== '', fn ($q) => $q->where('title', 'like', '%'.$filters['q'].'%'));

        // المدى الافتراضيّ آخر 30 يومًا للمنتهية، والقادمة كلّها (2.15-د)
        $query = $tab === 'ended'
            ? $query->where('status', 'ended')->where('scheduled_at', '>=', now()->subDays(max(1, $filters['days'])))->orderByDesc('scheduled_at')
            : $query->where('status', '!=', 'ended')->orderBy('scheduled_at');

        $meetings = $query->limit((int) setting('meetings.list_limit', 60))->get();

        // تسوية الغياب تتمّ لحظة قراءة القائمة — آمنة التكرار ولا تحتاج جدولة
        foreach ($meetings as $meeting) {
            $this->attendance->settleAbsences($meeting);
        }

        $mine = $this->myAttendances($user->id, $meetings->pluck('id')->all());

        if ($filters['mine'] !== '') {
            $meetings = $meetings->filter(function (Meeting $m) use ($mine, $filters) {
                $status = $mine[$m->id]->status ?? 'pending';

                return match ($filters['mine']) {
                    'registered' => $status === 'registered',
                    'excused' => in_array($status, ['excused', 'excused_settled'], true),
                    default => ! in_array($status, ['registered', 'excused', 'excused_settled'], true),
                };
            })->values();
        }

        // البانر الأحمر المتحرّك: نافذة تسجيل مفتوحة ولسّه ما سجّلتش
        $openWindow = $this->scope->visibleQuery($user)
            ->where('status', 'ended')
            ->where('attendance_closes_at', '>', now())
            ->orderBy('attendance_closes_at')
            ->get()
            ->first(fn (Meeting $m) => ($this->myAttendances($user->id, [$m->id])[$m->id]->status ?? null) !== 'registered');

        return view('volunteer.meetings.index', [
            'tab' => $tab,
            'meetings' => $meetings,
            'mine' => $mine,
            'filters' => $filters,
            'openWindow' => $openWindow,
            'entities' => $this->userEntities($user),
            'canCreate' => $user->allows('meetings.create'),
            'attendance' => $this->attendance,
            'scope' => $this->scope,
            'counts' => [
                'upcoming' => $this->scope->visibleQuery($user)->where('status', '!=', 'ended')->count(),
                'ended' => $this->scope->visibleQuery($user)->where('status', 'ended')->count(),
            ],
        ]);
    }

    public function show(Request $request, Meeting $meeting): View
    {
        $user = $request->user();
        $canManage = $this->scope->canManage($user, $meeting);

        abort_unless($this->scope->isAudience($user, $meeting) || $canManage, 404);

        $this->attendance->settleAbsences($meeting);

        // تحميل كسول: تاب واحد فقط يُبنى في كلّ طلب (2.15-د · 2.7)
        $tab = $request->string('tab')->toString();
        $tab = in_array($tab, ['attendance', 'minutes', 'discussion'], true) ? $tab : 'details';

        $full = $this->scope->canSeeFullAttendance($user, $meeting);

        $rows = collect();
        $posts = collect();

        if ($tab === 'attendance') {
            $rows = MeetingAttendance::query()
                ->with('user')
                ->where('meeting_id', $meeting->id)
                ->when(! $full, fn ($q) => $q->where('user_id', $user->id))
                ->orderByDesc('registered_at')
                ->get();
        }

        if ($tab === 'discussion') {
            $sort = $request->string('sort')->toString() === 'top' ? 'top' : 'new';

            $posts = MeetingPost::query()
                ->with('user', 'replies.user')
                ->where('meeting_id', $meeting->id)
                ->whereNull('parent_id')
                // المثبَّت أعلى دائمًا (13.4-ح)
                ->orderByDesc('is_pinned')
                ->when($sort === 'top', fn ($q) => $q->orderByDesc('votes'), fn ($q) => $q->orderByDesc('created_at'))
                ->get();
        }

        /*
         | تاب المحضر: بنوده معروضة بندًا بندًا، ولمن يملك إدارته + `tasks.create`
         | زرُّ **توليد مهمّة «تنفيذ»** لكلّ بند (23-0.3) — بنفس حقول فورم المهمّة
         | الجديدة حرفيًّا، فلا بابٌ ثانٍ بعقدٍ أضعف.
         */
        $minutesItems = collect();
        $generatedTasks = collect();
        $canGenerateTask = false;
        $workItems = collect();
        $teamMembers = collect();

        if ($tab === 'minutes') {
            $minutesItems = $this->minutesTasks->items($meeting);
            $generatedTasks = $meeting->generated_tasks()->with('owner', 'work_item')->orderBy('id')->get();
            $canGenerateTask = $this->minutesTasks->canGenerate($user, $meeting);

            if ($canGenerateTask) {
                $membership = $user->activeMembership();
                $workItems = $this->taskCreation->workItemsFor($membership);
                $teamMembers = $this->taskCreation->teamMembersFor($user, $membership);
            }
        }

        return view('volunteer.meetings.show', [
            'meeting' => $meeting->load('owner', 'entity', 'questions'),
            'tab' => $tab,
            'minutesItems' => $minutesItems,
            'generatedTasks' => $generatedTasks,
            'canGenerateTask' => $canGenerateTask,
            'minutesTasks' => $this->minutesTasks,
            'workItems' => $workItems,
            'teamMembers' => $teamMembers,
            'canManage' => $canManage,
            'canSeeFull' => $full,
            'rows' => $rows,
            'posts' => $posts,
            'myVotes' => $this->myVotes($user->id, $posts),
            'myAttendance' => $this->myAttendances($user->id, [$meeting->id])[$meeting->id] ?? null,
            'attendance' => $this->attendance,
            'attachments' => $this->manager->attachments($meeting),
            'sort' => $request->string('sort')->toString() === 'top' ? 'top' : 'new',
        ]);
    }

    /**
     * إنشاء اجتماع — والجمهور محدود بما تسمح به صلاحيّة المُنشئ.
     *
     * والمنطق نفسه في `MeetingManager` لا هنا: لوحة الإدارة تُنشئ بالفعل
     * نفسه (24.2-أوّلًا)، واجتماعٌ من هناك يجب أن يكون اجتماعًا من هنا حرفيًّا.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate(
            $this->manager->creationRules(),
            [],
            $this->manager->creationAttributes(),
        );

        $this->manager->create(
            $user,
            $data,
            (array) $request->input('questions', []),
            (array) $request->file('attachments', []),
            $request->boolean('restricted'),
        );

        return back()->with('status', (string) setting('meetings.screen.store_ok', 'اتعمل الاجتماع ✓ وابعتنا إشعارًا لجمهوره.'));
    }

    /** إنهاء الاجتماع: ساعات نافذة التسجيل + رفع المحضر */
    public function end(Request $request, Meeting $meeting): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->scope->canManage($user, $meeting), 403);

        $data = $request->validate([
            'window_hours' => ['required', 'integer', 'min:1', 'max:'.$this->attendance->maxWindowHours()],
            'minutes' => ['nullable', 'string', 'max:20000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:8192'],
        ], [], ['window_hours' => (string) setting('meetings.screen.end_msg', 'عدد ساعات نافذة التسجيل')]);

        $result = $this->attendance->end($meeting, $user, (int) $data['window_hours'], $data['minutes'] ?? null);

        $this->manager->saveAttachments($meeting, $user->id, (array) $request->file('attachments', []), $request->boolean('restricted'));

        return back()->with('status', $result['message']);
    }

    /**
     * ⭐ **توليد مهمّة «تنفيذ» من بند المحضر** (23-0.3): «~~اجتماع~~ ممنوع كنوع
     * مهمّة — والبديل الفعاليّات بكود الحضور، **وتقدر تولّد مهمّة «تنفيذ» لبنود
     * المحضر**». فالبند لا يبقى نصًّا حرًّا خارج شجرة التنفيذ.
     *
     * والفعل بابٌ إضافيّ لا عقدٌ ثانٍ: نفس قواعد `TaskCreation` ونفس حرّاسها
     * (الفريق · سقف الانشغال · الغياب المعذور · الربط الإلزاميّ ببند).
     */
    public function storeMinutesTask(Request $request, Meeting $meeting): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->scope->canManage($user, $meeting), 403);

        $membership = $user->activeMembership();

        $this->taskCreation->guard($user, $membership);

        $data = $request->validate(
            array_merge($this->taskCreation->rules(), [
                // العنوان يُملأ مسبقًا من نصّ البند، ويبقى قابلًا للتحرير قبل الحفظ
                'title' => ['nullable', 'string', 'max:180'],
                'minutes_item' => ['required', 'integer', 'min:0'],
            ]),
            [],
            array_merge($this->taskCreation->attributes(), [
                'minutes_item' => (string) setting('meetings.screen.store_minutes_task_msg', 'بند المحضر'),
            ]),
        );

        $this->taskCreation->guardAssignee($data, $membership);

        $task = $this->minutesTasks->generate($meeting, $user, (int) $data['minutes_item'], $data);

        return redirect()
            ->route('volunteer.tasks.show', $task)
            ->with('status', strtr((string) setting('meetings.screen.store_minutes_task_ok', 'اتولّدت مهمّة «:a1» من بند المحضر ✓ — بقت مهمّة عاديّة بعدّادها ومراجعها.'), [':a1' => (string) $task->title]));
    }

    /** ⭐ الأسئلة والـOTP: صاحب الاجتماع أو أيّ أبلاين فوقه حتى السقف (13.4-ن-ب) */
    public function questions(Request $request, Meeting $meeting): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->scope->canManage($user, $meeting), 403);

        $data = $request->validate([
            'attendance_code' => ['nullable', 'string', 'max:32'],
            'questions' => ['nullable', 'array'],
            'questions.*.prompt' => ['nullable', 'string', 'max:300'],
            'questions.*.options' => ['nullable', 'string', 'max:500'],
            'questions.*.correct_answer' => ['nullable', 'string', 'max:120'],
        ]);

        $result = $this->manager->saveCodeAndQuestions(
            $meeting,
            $user,
            $data['attendance_code'] ?? null,
            (array) $request->input('questions', []),
            $request->filled('attendance_code'),
        );

        return back()->with('status', $result['message']);
    }

    /** تسجيل الحضور — التحقّق Server-side ورسالة بالقيمة المضافة */
    public function register(Request $request, Meeting $meeting): RedirectResponse
    {
        $result = $this->attendance->register(
            $meeting,
            $request->user(),
            $request->string('code')->toString(),
            (array) $request->input('answers', []),
        );

        return back()->with('status', $result['message']);
    }

    /** اعتذار مسبق — يمنع خصم الغياب */
    public function excuse(Request $request, Meeting $meeting): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], [], ['reason' => (string) setting('meetings.screen.excuse_msg', 'سبب الاعتذار')]);

        $result = $this->attendance->excuse($meeting, $request->user(), $data['reason']);

        return back()->with('status', $result['message']);
    }

    // ------------------------------------------------------------------ النقاش

    public function storePost(Request $request, Meeting $meeting): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->scope->isAudience($user, $meeting) || $this->scope->canManage($user, $meeting), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
            'parent_id' => ['nullable', 'integer', 'exists:meeting_posts,id'],
            'attachment' => ['nullable', 'file', 'max:8192'],
        ], [], ['body' => (string) setting('meetings.screen.store_post_msg', 'النصّ')]);

        MeetingPost::create([
            'meeting_id' => $meeting->id,
            'user_id' => $user->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
            'attachment_path' => $request->hasFile('attachment')
                ? $request->file('attachment')->store('meetings/'.$meeting->id, 'public')
                : null,
        ]);

        return back()->with('status', (string) setting('meetings.screen.store_post_ok', 'اتنشر ✓'));
    }

    /** Vote up/down — صوت واحد لكلّ عضو، وإعادة نفس الصوت تسحبه */
    public function vote(Request $request, MeetingPost $post): RedirectResponse
    {
        $user = $request->user();
        $meeting = $post->meeting;

        abort_unless($meeting && ($this->scope->isAudience($user, $meeting) || $this->scope->canManage($user, $meeting)), 403);

        $value = (int) $request->integer('value') >= 0 ? 1 : -1;

        $existing = PostVote::query()
            ->where('votable_type', $post->getMorphClass())
            ->where('votable_id', $post->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && (int) $existing->value === $value) {
            $existing->delete();
        } else {
            PostVote::updateOrCreate(
                ['votable_type' => $post->getMorphClass(), 'votable_id' => $post->id, 'user_id' => $user->id],
                ['value' => $value],
            );
        }

        $post->forceFill(['votes' => (int) $post->votes()->sum('value')])->save();

        return back();
    }

    /** التثبيت لصاحب الاجتماع أو أيّ أبلاين فوقه */
    public function pin(Request $request, MeetingPost $post): RedirectResponse
    {
        $meeting = $post->meeting;
        abort_unless($meeting && $this->scope->canManage($request->user(), $meeting), 403);

        return back()->with('status', $this->manager->togglePin($post)['message']);
    }

    // ------------------------------------------------------------------ حضوري والمحاضر

    public function attendance(Request $request): View
    {
        $user = $request->user();
        $tab = $request->string('tab')->toString() === 'minutes' ? 'minutes' : 'attendance';

        $days = (int) $request->integer('days', (int) setting('ux.lists.default_range_days', 30));
        $entity = (int) $request->integer('entity');
        $q = trim($request->string('q')->toString());

        $visibleIds = $this->scope->visibleQuery($user)->pluck('id');

        $rows = MeetingAttendance::query()
            ->with('meeting.entity', 'meeting.owner', 'transaction')
            ->where('user_id', $user->id)
            ->whereIn('meeting_id', $visibleIds)
            ->whereHas('meeting', function ($m) use ($days, $entity, $q) {
                $m->where('scheduled_at', '>=', now()->subDays(max(1, $days)))
                    ->when($entity > 0, fn ($x) => $x->where('entity_id', $entity))
                    ->when($q !== '', fn ($x) => $x->where('title', 'like', '%'.$q.'%'));
            })
            ->orderByDesc('created_at')
            ->get();

        $registered = $rows->where('status', 'registered')->count();
        $rate = $rows->count() > 0 ? round($registered * 100 / $rows->count()) : 0;

        $minutes = collect();

        if ($tab === 'minutes') {
            $minutes = Meeting::query()
                ->with('owner')
                ->whereIn('id', $visibleIds)
                ->where('status', 'ended')
                ->where('scheduled_at', '>=', now()->subDays(max(1, $days)))
                ->when($entity > 0, fn ($x) => $x->where('entity_id', $entity))
                ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('title', 'like', '%'.$q.'%')->orWhere('minutes', 'like', '%'.$q.'%')))
                ->orderByDesc('ended_at')
                ->limit((int) setting('meetings.list_limit', 60))
                ->get();
        }

        return view('volunteer.meetings.attendance', [
            'tab' => $tab,
            'rows' => $rows,
            'minutes' => $minutes,
            'attachments' => $this->manager->attachmentsFor($minutes),
            'rate' => $rate,
            'registered' => $registered,
            'filters' => ['days' => $days, 'entity' => $entity, 'q' => $q],
            'entities' => $this->userEntities($user),
            'attendance' => $this->attendance,
            'scope' => $this->scope,
            // مهلة الاعتراض على معاملة الحضور تُقرَأ من خدمتها لا من رقم محروق
            'objections' => app(ObjectionService::class),
        ]);
    }

    /** المرفق المقيَّد لا يُخفى: يظهر بقفله وزرّ [اطلب وصولًا] (24.4) */
    public function requestAccess(Request $request, Meeting $meeting): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:300']]);

        $this->ledger->notify(
            $meeting->owner,
            'meeting',
            (string) setting('meetings.screen.request_access_msg', 'طلب وصول لمرفق اجتماع'),
            strtr((string) setting('meetings.screen.request_access_msg_2', ':a1 طلب الوصول لمرفقات: :a2'), [':a1' => (string) ($request->user()->name), ':a2' => (string) ($meeting->title)]),
            route('volunteer.meetings.show', $meeting),
        );

        return back()->with('status', (string) setting('meetings.screen.request_access_msg_3', 'اتبعت طلبك لصاحب الاجتماع — هيوصلك ردّ في الإشعارات.'));
    }

    // ------------------------------------------------------------------ داخليّ

    /** @return Collection<int,MeetingAttendance> مفهرسة بمعرّف الاجتماع */
    private function myAttendances(int $userId, array $meetingIds): Collection
    {
        if ($meetingIds === []) {
            return collect();
        }

        return MeetingAttendance::query()
            ->where('user_id', $userId)
            ->whereIn('meeting_id', $meetingIds)
            ->get()
            ->keyBy('meeting_id');
    }

    private function myVotes(int $userId, Collection $posts): Collection
    {
        $ids = $posts->pluck('id')->merge($posts->flatMap->replies->pluck('id'))->all();

        if ($ids === []) {
            return collect();
        }

        return PostVote::query()
            ->where('user_id', $userId)
            ->where('votable_type', (new MeetingPost)->getMorphClass())
            ->whereIn('votable_id', $ids)
            ->get()
            ->keyBy('votable_id');
    }

    private function userEntities($user): Collection
    {
        return Entity::query()
            ->whereIn('id', $this->scope->entityIdsWithAncestors($user))
            ->orderBy('name_ar')
            ->get();
    }
}
